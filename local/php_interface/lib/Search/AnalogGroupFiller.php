<?php
namespace Lider\Search;

/**
 * Системный добор складов для ВСЕХ карточек аналогов.
 *
 * Принцип:
 *  - берём каждую non-exact группу, где не хватает поставщиков;
 *  - очередь ROUND-ROBIN: раунд1 = +1 поставщик каждой группе, раунд2 = ещё +1, ...
 *  - так ни одна карточка не остаётся «в конце списка без бюджета»;
 *  - канон brand/article = как на витрине (склейка в ту же карточку).
 */
class AnalogGroupFiller
{
    /** @var callable */
    private $buildReq;

    public function __construct(callable $buildReq)
    {
        $this->buildReq = $buildReq;
    }

    /**
     * @param array<string,array> $groupedItems
     * @return array{groups: array, log: string}
     */
    public function fillMissingSuppliers(
        array $groupedItems,
        string $exactKey,
        string $normTargetBrand,
        string $normTargetArt,
        string $targetFamily,
        $supplierFactory,
        callable $itemToWarehouseRow,
        int $maxHttp = 120,
        float $deadlineSec = 14.0
    ): array {
        $t0 = microtime(true);
        $log = [];

        $suppliers = [];
        if (is_object($supplierFactory) && method_exists($supplierFactory, 'allAvailable')) {
            $suppliers = array_values($supplierFactory->allAvailable());
        }
        if (!$suppliers) {
            return ['groups' => $groupedItems, 'log' => 'no_suppliers'];
        }

        // стабильный порядок поставщиков для fair fill
        $pref = ['berg' => 0, 'partkom' => 1, 'ixora' => 2, 'autoeuro' => 3, 'rossko' => 4, 'moskvorechie' => 5];
        usort($suppliers, static function ($a, $b) use ($pref) {
            return ($pref[$a->getCode()] ?? 50) <=> ($pref[$b->getCode()] ?? 50);
        });

        // ---- все группы, которым чего-то не хватает ----
        $need = []; // list of meta, keep order by haveN asc then gk
        foreach ($groupedItems as $gk => $g) {
            if ($gk === $exactKey) {
                continue;
            }
            $brand = trim((string)($g['brand'] ?? ''));
            $article = trim((string)($g['article'] ?? ''));
            if ($brand === '' || $article === '') {
                continue;
            }

            // омоним артикула другого бренда
            if ($normTargetArt !== ''
                && BrandNormalizer::normalizeArticle($article) === $normTargetArt
                && BrandNormalizer::normalize($brand) !== $normTargetBrand) {
                continue;
            }

            // family filter (колодки vs тяги и т.п.)
            if ($targetFamily !== '') {
                $fam = AnalogOfferFiller::detectFamily((string)($g['description'] ?? '') . ' ' . $brand . ' ' . $article);
                if ($fam !== '' && $fam !== $targetFamily) {
                    $pad = ['pad', 'brake'];
                    if (!(in_array($targetFamily, $pad, true) && in_array($fam, $pad, true))) {
                        continue;
                    }
                }
            }

            $have = [];
            foreach (array_keys($g['_by_sup'] ?? []) as $c) {
                $have[(string)$c] = true;
            }
            foreach (($g['warehouses'] ?? []) as $w) {
                $c = (string)($w['source'] ?? '');
                if ($c !== '') {
                    $have[$c] = true;
                }
            }

            $missing = [];
            foreach ($suppliers as $sup) {
                $code = (string)$sup->getCode();
                if (!isset($have[$code])) {
                    $missing[] = $sup;
                }
            }
            if (!$missing) {
                continue;
            }

            $need[] = [
                'gk' => $gk,
                'brand' => $brand,
                'article' => $article,
                'missing' => $missing,
                'haveN' => count($have),
                'missN' => count($missing),
            ];
        }

        // fair order: сначала у кого меньше поставщиков (но ВСЕ участвуют в RR)
        usort($need, static function ($a, $b) {
            if ($a['haveN'] !== $b['haveN']) {
                return $a['haveN'] <=> $b['haveN'];
            }
            return $b['missN'] <=> $a['missN'];
        });

        $groupsTotal = count($need);
        if ($groupsTotal === 0) {
            return ['groups' => $groupedItems, 'log' => 'nothing_to_fill'];
        }

        // ---- ROUND-ROBIN очередь по ВСЕМ группам ----
        $maxMiss = 0;
        foreach ($need as $n) {
            $maxMiss = max($maxMiss, $n['missN']);
        }

        $queue = [];
        for ($round = 0; $round < $maxMiss; $round++) {
            foreach ($need as $n) {
                if (!isset($n['missing'][$round])) {
                    continue;
                }
                $queue[] = [
                    'gk' => $n['gk'],
                    'sup' => $n['missing'][$round],
                    'brand' => $n['brand'],
                    'article' => $n['article'],
                ];
            }
        }

        // Бюджет: не режем список групп, режем только глубину (сколько раундов влезет).
        // Гарантия: при maxHttp >= groupsTotal каждая группа получит >=1 запрос в 1-м раунде.
        if (count($queue) > $maxHttp) {
            $queue = array_slice($queue, 0, $maxHttp);
        }

        $coveredFirstRound = min($groupsTotal, count($queue));
        $log[] = 'analog_groups_need=' . $groupsTotal
            . ' queue=' . count($queue)
            . ' first_round_cover=' . $coveredFirstRound
            . ' suppliers=' . count($suppliers);

        // ---- build HTTP requests ----
        $reqs = [];
        $meta = [];
        foreach ($queue as $job) {
            if ((microtime(true) - $t0) > $deadlineSec) {
                $log[] = 'deadline_before_build';
                break;
            }
            $sup = $job['sup'];
            $cardBrand = $job['brand'];
            $cardArt = $job['article'];

            $req = null;
            $useB = $cardBrand;
            $useA = $cardArt;
            $tries = [
                [$cardBrand, $cardArt],
                [BrandNormalizer::displayBrand($cardBrand), $cardArt],
            ];
            $aNS = preg_replace('/\s+/', '', $cardArt) ?? $cardArt;
            if ($aNS !== $cardArt) {
                $tries[] = [$cardBrand, $aNS];
                $tries[] = [BrandNormalizer::displayBrand($cardBrand), $aNS];
            }
            foreach ($tries as $pair) {
                try {
                    $req = ($this->buildReq)($sup, $pair[0], $pair[1], false);
                } catch (\Throwable $e) {
                    $req = null;
                }
                if ($req && !empty($req['url'])) {
                    $useB = $pair[0];
                    $useA = $pair[1];
                    break;
                }
            }
            if (!$req || empty($req['url'])) {
                continue;
            }
            $req['_timeout'] = 4;
            $reqs[] = $req;
            $meta[] = [
                'sup' => $sup,
                'code' => (string)$sup->getCode(),
                'gk' => $job['gk'],
                'brand' => $cardBrand,
                'article' => $cardArt,
                'req_brand' => $useB,
                'req_article' => $useA,
            ];
        }

        $log[] = 'http_built=' . count($reqs);

        $addedTotal = 0;
        $filledGroups = []; // gk => [code => added]

        foreach (array_chunk($reqs, 35, true) as $ci => $chunk) {
            if ((microtime(true) - $t0) > $deadlineSec) {
                $log[] = 'deadline_exec';
                break;
            }
            $metaChunk = array_chunk($meta, 35, true)[$ci] ?? [];
            $mh = curl_multi_init();
            $hs = [];
            foreach ($chunk as $i => $r) {
                $to = (int)($r['_timeout'] ?? 4);
                unset($r['_timeout'], $r['_ixora_with_crosses']);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $r['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $r['headers'] ?? [],
                    CURLOPT_TIMEOUT => $to,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_ENCODING => '',
                ]);
                if (($r['method'] ?? 'GET') === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if (!empty($r['body'])) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $r['body']);
                    }
                }
                curl_multi_add_handle($mh, $ch);
                $hs[$i] = $ch;
            }
            $rn = null;
            do {
                curl_multi_exec($mh, $rn);
                curl_multi_select($mh, 0.05);
            } while ($rn > 0);

            foreach ($hs as $i => $ch) {
                $body = curl_multi_getcontent($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                $m = $metaChunk[$i] ?? null;
                if (!$m || $http !== 200 || !$body) {
                    continue;
                }
                $gk = $m['gk'];
                if (!isset($groupedItems[$gk])) {
                    continue;
                }
                try {
                    $items = $m['sup']->parseSearchResponse($body, $m['req_brand'], $m['req_article']);
                } catch (\Throwable $e) {
                    continue;
                }
                if (!is_array($items) || !$items) {
                    continue;
                }

                $added = 0;
                foreach ($items as $it) {
                    if (!$it instanceof SearchResultItem) {
                        continue;
                    }
                    if ($it->price <= 0 && $it->quantity <= 0) {
                        continue;
                    }
                    $same =
                        BrandNormalizer::groupKey($it->brand, $it->article) === $gk
                        || (
                            BrandNormalizer::normalize($it->brand) === BrandNormalizer::normalize($m['brand'])
                            && BrandNormalizer::normalizeArticle($it->article) === BrandNormalizer::normalizeArticle($m['article'])
                        )
                        || (
                            BrandNormalizer::normalize($it->brand) === BrandNormalizer::normalize($m['req_brand'])
                            && BrandNormalizer::normalizeArticle($it->article) === BrandNormalizer::normalizeArticle($m['req_article'])
                        );
                    if (!$same) {
                        continue;
                    }

                    // всегда в карточку витрины
                    $it->brand = $m['brand'];
                    $it->article = $m['article'];

                    $row = $itemToWarehouseRow($it);
                    if (!$row) {
                        continue;
                    }
                    $src = (string)$it->source;
                    if (!isset($groupedItems[$gk]['_by_sup']) || !is_array($groupedItems[$gk]['_by_sup'])) {
                        $groupedItems[$gk]['_by_sup'] = [];
                    }
                    if (!isset($groupedItems[$gk]['_by_sup'][$src])) {
                        $groupedItems[$gk]['_by_sup'][$src] = [];
                    }
                    if (!isset($groupedItems[$gk]['_seen_wh']) || !is_array($groupedItems[$gk]['_seen_wh'])) {
                        $groupedItems[$gk]['_seen_wh'] = [];
                    }
                    $whKey = $row['__whKey'] ?? ($src . '|' . ($row['price'] ?? '') . '|' . ($row['qty'] ?? '') . '|' . ($row['stock'] ?? ''));
                    if (isset($groupedItems[$gk]['_seen_wh'][$whKey])) {
                        continue;
                    }
                    if (count($groupedItems[$gk]['_by_sup'][$src]) >= 10) {
                        continue;
                    }
                    $groupedItems[$gk]['_seen_wh'][$whKey] = true;
                    unset($row['__whKey']);
                    $groupedItems[$gk]['_by_sup'][$src][] = $row;
                    $added++;
                    $addedTotal++;
                }
                if ($added > 0) {
                    $filledGroups[$gk][$m['code']] = ($filledGroups[$gk][$m['code']] ?? 0) + $added;
                }
            }
            curl_multi_close($mh);
        }

        // итоговая статистика покрытия
        $single = 0;
        $multi = 0;
        $full = 0;
        $supN = count($suppliers);
        foreach ($groupedItems as $gk => $g) {
            if ($gk === $exactKey) {
                continue;
            }
            $codes = [];
            foreach (array_keys($g['_by_sup'] ?? []) as $c) {
                $codes[(string)$c] = true;
            }
            foreach (($g['warehouses'] ?? []) as $w) {
                $c = (string)($w['source'] ?? '');
                if ($c !== '') {
                    $codes[$c] = true;
                }
            }
            $n = count($codes);
            if ($n <= 1) {
                $single++;
            } elseif ($n >= $supN) {
                $full++;
            } else {
                $multi++;
            }
        }

        $log[] = 'added_total=' . $addedTotal;
        $log[] = 'groups_got_new=' . count($filledGroups);
        $log[] = 'coverage_single=' . $single . ',multi=' . $multi . ',full=' . $full;
        $log[] = 'time=' . round(microtime(true) - $t0, 2) . 's';

        return ['groups' => $groupedItems, 'log' => implode('; ', $log)];
    }
}
