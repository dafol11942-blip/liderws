<?php
namespace Lider\Search;

/**
 * Добор складов ПОСЛЕ группировки.
 * Работает с реальными карточками аналогов (brand/article как на витрине),
 * а не с промежуточными cross-результатами — поэтому offers не «теряются».
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
     * @param array<string,array> $groupedItems groupKey => group (with _by_sup)
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
        int $maxGroups = 12,
        int $maxHttp = 50,
        float $deadlineSec = 9.0
    ): array {
        $t0 = microtime(true);
        $log = [];

        $suppliers = [];
        if (is_object($supplierFactory) && method_exists($supplierFactory, 'allAvailable')) {
            $suppliers = $supplierFactory->allAvailable();
        }
        if (!$suppliers) {
            return ['groups' => $groupedItems, 'log' => 'no_suppliers'];
        }

        // кандидаты = все non-exact группы, у которых не хватает поставщиков
        $need = [];
        foreach ($groupedItems as $gk => $g) {
            if ($gk === $exactKey) {
                continue;
            }
            $brand = trim((string)($g['brand'] ?? ''));
            $article = trim((string)($g['article'] ?? ''));
            if ($brand === '' || $article === '') {
                continue;
            }
            // омонимы
            if (BrandNormalizer::normalizeArticle($article) === $normTargetArt
                && BrandNormalizer::normalize($brand) !== $normTargetBrand) {
                continue;
            }
            if ($targetFamily !== '') {
                $fam = AnalogOfferFiller::detectFamily(($g['description'] ?? '') . ' ' . $brand . ' ' . $article);
                if ($fam !== '' && $fam !== $targetFamily) {
                    $pad = ['pad', 'brake'];
                    if (!(in_array($targetFamily, $pad, true) && in_array($fam, $pad, true))) {
                        continue;
                    }
                }
            }
            $have = array_keys($g['_by_sup'] ?? []);
            $missing = [];
            foreach ($suppliers as $sup) {
                $code = $sup->getCode();
                if (!in_array($code, $have, true)) {
                    $missing[] = $sup;
                }
            }
            if (!$missing) {
                continue;
            }
            // ПРИОРИТЕТ: сначала группы с МАЛЫМ числом поставщиков (1 источник на витрине),
            // иначе http-бюджет съедают карточки, где уже 5/6 поставщиков.
            $haveN = count($have);
            $missN = count($missing);
            $score =
                ($haveN <= 1 ? 100000 : 0) +          // single-source — абсолютный приоритет
                ($haveN <= 2 ? 10000 : 0) +
                ($missN * 1000) +                     // больше missing — выше
                (int)min(500, (int)($g['total_qty'] ?? 0)) +
                (int)min(200, count($g['warehouses'] ?? []) * 5);
            // лёгкий boost популярным/известным бренд-ключам фильтров
            $blob = mb_strtolower($brand . ' ' . $article);
            if (str_contains($blob, 'miles') || str_contains($blob, 'lynx') || str_contains($blob, 'goodwill') || str_contains($blob, 'filtron') || str_contains($blob, 'mann')) {
                $score += 500;
            }
            $need[$gk] = [
                'brand' => $brand,
                'article' => $article,
                'missing' => $missing,
                'score' => $score,
                'have' => $have,
                'haveN' => $haveN,
                'missN' => $missN,
            ];
        }

        uasort($need, static function ($a, $b) {
            // score desc, then more missing, then fewer have
            if (($b['score'] ?? 0) !== ($a['score'] ?? 0)) {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            }
            if (($b['missN'] ?? 0) !== ($a['missN'] ?? 0)) {
                return ($b['missN'] ?? 0) <=> ($a['missN'] ?? 0);
            }
            return ($a['haveN'] ?? 0) <=> ($b['haveN'] ?? 0);
        });
        $need = array_slice($need, 0, $maxGroups, true);

        $reqs = [];
        $meta = [];
        $httpN = 0;
        foreach ($need as $gk => $info) {
            foreach ($info['missing'] as $sup) {
                if ($httpN >= $maxHttp || (microtime(true) - $t0) > $deadlineSec) {
                    break 2;
                }
                $b = $info['brand'];
                $a = $info['article'];
                $req = null;
                $tryPairs = [
                    [$b, $a],
                    [BrandNormalizer::displayBrand($b), $a],
                ];
                // MILES / Miles / lynxauto variants already covered by displayBrand; also spaced articles
                $a2 = preg_replace('/\s+/', '', $a) ?? $a;
                if ($a2 !== $a) {
                    $tryPairs[] = [$b, $a2];
                }
                foreach ($tryPairs as $pair) {
                    try {
                        $req = ($this->buildReq)($sup, $pair[0], $pair[1], false);
                    } catch (\Throwable $e) {
                        $req = null;
                    }
                    if ($req && !empty($req['url'])) {
                        $b = $pair[0];
                        $a = $pair[1];
                        break;
                    }
                }
                if (!$req || empty($req['url'])) {
                    continue;
                }
                $req['_timeout'] = 5;
                $reqs[] = $req;
                $meta[] = [
                    'sup' => $sup,
                    'code' => $sup->getCode(),
                    'brand' => $b,
                    'article' => $a,
                    'gk' => $gk,
                ];
                $httpN++;
            }
        }

        $sel = [];
        foreach ($need as $gk0 => $inf0) {
            $sel[] = $gk0 . '(have=' . ($inf0['haveN'] ?? '?') . ',miss=' . ($inf0['missN'] ?? '?') . ')';
        }
        $log[] = 'need_groups=' . count($need) . ' http=' . $httpN . ' sel=' . implode(',', array_slice($sel, 0, 20));

        if (!$reqs) {
            return ['groups' => $groupedItems, 'log' => implode(';', $log)];
        }

        foreach (array_chunk($reqs, 25, true) as $ci => $chunk) {
            if ((microtime(true) - $t0) > $deadlineSec) {
                $log[] = 'deadline';
                break;
            }
            $metaChunk = array_chunk($meta, 25, true)[$ci];
            $mh = curl_multi_init();
            $hs = [];
            foreach ($chunk as $i => $r) {
                $to = (int)($r['_timeout'] ?? 5);
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
                    $items = $m['sup']->parseSearchResponse($body, $m['brand'], $m['article']);
                } catch (\Throwable $e) {
                    continue;
                }
                if (!is_array($items)) {
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
                    // принимаем если groupKey совпал ИЛИ norm brand+article
                    $igk = BrandNormalizer::groupKey($it->brand, $it->article);
                    $same = ($igk === $gk)
                        || (
                            BrandNormalizer::normalize($it->brand) === BrandNormalizer::normalize($m['brand'])
                            && BrandNormalizer::normalizeArticle($it->article) === BrandNormalizer::normalizeArticle($m['article'])
                        );
                    if (!$same) {
                        continue;
                    }

                    // канон brand/article карточки
                    $it->brand = $m['brand'];
                    $it->article = $m['article'];

                    $row = $itemToWarehouseRow($it);
                    if (!$row) {
                        continue;
                    }
                    $src = (string)$it->source;
                    if (!isset($groupedItems[$gk]['_by_sup'][$src])) {
                        $groupedItems[$gk]['_by_sup'][$src] = [];
                    }
                    // дедуп wh
                    $whKey = $row['__whKey'] ?? ($src . '|' . $row['price'] . '|' . $row['qty'] . '|' . ($row['stock'] ?? ''));
                    if (!isset($groupedItems[$gk]['_seen_wh'])) {
                        $groupedItems[$gk]['_seen_wh'] = [];
                    }
                    if (isset($groupedItems[$gk]['_seen_wh'][$whKey])) {
                        continue;
                    }
                    $groupedItems[$gk]['_seen_wh'][$whKey] = true;
                    unset($row['__whKey']);
                    if (count($groupedItems[$gk]['_by_sup'][$src]) >= 12) {
                        continue;
                    }
                    $groupedItems[$gk]['_by_sup'][$src][] = $row;
                    $added++;
                }
                if ($added > 0) {
                    $log[] = $gk . '+' . $m['code'] . '=' . $added;
                }
            }
            curl_multi_close($mh);
        }

        return ['groups' => $groupedItems, 'log' => implode('; ', array_slice($log, 0, 40))];
    }
}
