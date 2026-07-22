<?php
namespace Lider\Search;

/**
 * Системный добор складов по аналогам у ВСЕХ поставщиков.
 * Cross часто приносит аналог только от 1 источника — этот класс
 * делает exact-запросы остальным поставщикам по top-N аналогам.
 */
class AnalogOfferFiller
{
    /** @var callable */
    private $buildReq;
    /** @var callable */
    private $itemToArray;
    /** @var callable */
    private $itemFromArray;
    private SearchCacheManager $itemCache;

    public function __construct(
        callable $buildReq,
        callable $itemToArray,
        callable $itemFromArray,
        SearchCacheManager $itemCache
    ) {
        $this->buildReq = $buildReq;
        $this->itemToArray = $itemToArray;
        $this->itemFromArray = $itemFromArray;
        $this->itemCache = $itemCache;
    }

    public static function detectFamily(string $text): string
    {
        $t = mb_strtolower($text);
        $map = [
            'pad' => ['колодк', 'brake pad', 'disc pad'],
            'stab' => ['стабил', 'stabilizer', 'sway', 'тяга стаб', 'стойка стаб', 'anti-roll'],
            'filter' => ['фильтр', 'filter'],
            'spring' => ['пружин', 'spring'],
            'tie' => ['наконечник', 'tie rod', 'рулев'],
            'pan' => ['поддон', 'oil pan'],
            'bearing' => ['подшипник', 'bearing'],
            'shock' => ['амортиз', 'shock', 'strut'],
            'belt' => ['ремень', 'belt'],
            'pump' => ['насос', 'pump'],
            'sensor' => ['датчик', 'sensor'],
        ];
        foreach ($map as $fam => $words) {
            foreach ($words as $w) {
                if ($w !== '' && mb_strpos($t, $w) !== false) {
                    return $fam;
                }
            }
        }
        if (mb_strpos($t, 'тормоз') !== false) {
            return 'brake';
        }
        return '';
    }

    /**
     * @param SearchResultItem[] $allResults
     * @return SearchResultItem[]
     */
    public function fill(
        array $allResults,
        string $exactKey,
        string $normTargetBrand,
        string $normTargetArt,
        string $targetFamily,
        array $brandmapMeta,
        $supplierFactory,
        int $maxAnalogs = 10,
        int $maxHttp = 45,
        float $deadlineSec = 8.0
    ): array {
        $t0 = microtime(true);

        $have = [];  // gk => [code => true]
        $cands = []; // gk => data

        foreach ($allResults as $it) {
            if (!$it instanceof SearchResultItem) {
                continue;
            }
            $gk = BrandNormalizer::groupKey($it->brand, $it->article);
            if ($gk === '') {
                continue;
            }
            $code = (string)$it->source;
            $have[$gk][$code] = true;

            if ($gk === $exactKey) {
                continue;
            }
            if (!$this->isValidAnalog($it->brand, $it->article, (string)$it->name, $normTargetBrand, $normTargetArt, $targetFamily)) {
                continue;
            }
            if (!isset($cands[$gk])) {
                $cands[$gk] = [
                    'brand' => (string)$it->brand,
                    'article' => (string)$it->article,
                    'name' => (string)$it->name,
                    'score' => 0,
                ];
            }
            $cands[$gk]['score'] += 5;
            if (mb_strlen((string)$it->name) > mb_strlen($cands[$gk]['name'])) {
                $cands[$gk]['name'] = (string)$it->name;
            }
        }

        foreach ($brandmapMeta as $mk => $info) {
            if (!is_array($info) || (string)$mk === $exactKey) {
                continue;
            }
            $brands = $info['brands'] ?? [];
            $arts = $info['articles'] ?? [];
            $b = (string)(reset($brands) ?: '');
            $a = BrandNormalizer::pickDisplayArticle($arts, (string)($info['article_nr'] ?? ''));
            if ($b === '' || $a === '') {
                continue;
            }
            $gk = BrandNormalizer::groupKey($b, $a);
            if ($gk === '' || $gk === $exactKey) {
                continue;
            }
            $name = (string)($info['description'] ?? '');
            if (!$this->isValidAnalog($b, $a, $name, $normTargetBrand, $normTargetArt, $targetFamily)) {
                continue;
            }
            if (!isset($cands[$gk])) {
                $cands[$gk] = ['brand' => $b, 'article' => $a, 'name' => $name, 'score' => 0];
            }
            $cands[$gk]['score'] += 1 + min(5, count($info['sources'] ?? []));
            if (str_starts_with((string)$mk, 'lynx')) {
                $cands[$gk]['score'] += 3;
            }
        }

        uasort($cands, static fn($x, $y) => ($y['score'] <=> $x['score']));
        $cands = array_slice($cands, 0, max(1, $maxAnalogs), true);

        $suppliers = [];
        if (is_object($supplierFactory) && method_exists($supplierFactory, 'allAvailable')) {
            $suppliers = $supplierFactory->allAvailable();
        }
        if (!$suppliers || !$cands) {
            return $allResults;
        }

        $reqs = [];
        $meta = [];
        $seen = [];
        $httpN = 0;

        foreach ($cands as $gk => $cand) {
            if ($httpN >= $maxHttp || (microtime(true) - $t0) > $deadlineSec) {
                break;
            }
            foreach ($suppliers as $sup) {
                if ($httpN >= $maxHttp || (microtime(true) - $t0) > $deadlineSec) {
                    break 2;
                }
                if (!is_object($sup) || !method_exists($sup, 'getCode')) {
                    continue;
                }
                $code = (string)$sup->getCode();
                if (!empty($have[$gk][$code])) {
                    continue; // уже есть offers этого поставщика
                }

                [$b, $a] = $this->pickBrandArticle($gk, $cand, $code, $brandmapMeta);
                if ($b === '' || $a === '') {
                    continue;
                }

                $dedupe = $code . '|' . $gk . '|' . mb_strtolower($b) . '|' . mb_strtolower($a);
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;

                $ck = 'af|' . md5($dedupe);
                $cached = $this->itemCache->get($ck);
                if (is_array($cached) && !empty($cached['ok']) && !empty($cached['rows']) && is_array($cached['rows'])) {
                    foreach ($cached['rows'] as $row) {
                        if (is_array($row)) {
                            $allResults[] = ($this->itemFromArray)($row);
                        }
                    }
                    $have[$gk][$code] = true;
                    continue;
                }

                try {
                    $req = ($this->buildReq)($sup, $b, $a, false);
                } catch (\Throwable $e) {
                    $req = null;
                }
                if (!$req || empty($req['url'])) {
                    continue;
                }
                $req['_timeout'] = 5;
                $reqs[] = $req;
                $meta[] = [
                    'sup' => $sup,
                    'code' => $code,
                    'brand' => $b,
                    'article' => $a,
                    'gk' => $gk,
                    'ck' => $ck,
                    'canon_brand' => (string)$cand['brand'],
                    'canon_article' => (string)$cand['article'],
                ];
                $httpN++;
            }
        }

        if (!$reqs) {
            return $allResults;
        }

        $chunksR = array_chunk($reqs, 25, true);
        $chunksM = array_chunk($meta, 25, true);
        foreach ($chunksR as $ci => $chunk) {
            if ((microtime(true) - $t0) > $deadlineSec) {
                break;
            }
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
                $m = $chunksM[$ci][$i] ?? null;
                if (!$m) {
                    continue;
                }
                $rows = [];
                if ($http === 200 && $body) {
                    try {
                        $items = $m['sup']->parseSearchResponse($body, $m['brand'], $m['article']);
                        if (!is_array($items)) {
                            $items = [];
                        }
                        $n = 0;
                        foreach ($items as $it) {
                            if (!$it instanceof SearchResultItem) {
                                continue;
                            }
                            if (trim($it->brand) === '' || trim($it->article) === '') {
                                continue;
                            }
                            if ($it->price <= 0 && $it->quantity <= 0) {
                                continue;
                            }
                            if (!$this->sameProduct($it->brand, $it->article, $m['brand'], $m['article'], $m['gk'])) {
                                continue;
                            }
                            if ($targetFamily !== '' && !$this->familyOk((string)$it->name . ' ' . $it->brand, $targetFamily)) {
                                continue;
                            }
                            // канонизируем brand/article под кандидата, чтобы groupKey совпал с карточкой аналога
                            if (!empty($m['canon_brand'])) {
                                $it->brand = (string)$m['canon_brand'];
                            }
                            if (!empty($m['canon_article'])) {
                                $it->article = (string)$m['canon_article'];
                            }
                            $rows[] = ($this->itemToArray)($it);
                            $allResults[] = $it;
                            $n++;
                            if ($n >= 12) {
                                break;
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }
                if ($rows) {
                    $this->itemCache->set($m['ck'], ['ok' => 1, 'rows' => $rows], 600);
                    $have[$m['gk']][$m['code']] = true;
                }
            }
            curl_multi_close($mh);
        }

        // FILL_STATS
        $stats = [];
        foreach ($allResults as $it) {
            if (!$it instanceof SearchResultItem) continue;
            $gk = BrandNormalizer::groupKey($it->brand, $it->article);
            if ($gk === $exactKey) continue;
            $stats[$gk][$it->source] = ($stats[$gk][$it->source] ?? 0) + 1;
        }
        // top multi-source analogs
        $multi = [];
        foreach ($stats as $gk => $by) {
            if (count($by) >= 1) {
                $multi[$gk] = $by;
            }
        }
        uasort($multi, static fn($a, $b) => count($b) <=> count($a));
        $lines = [];
        $i = 0;
        foreach ($multi as $gk => $by) {
            $lines[] = $gk . ' => ' . json_encode($by, JSON_UNESCAPED_UNICODE);
            if (++$i >= 12) break;
        }
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        @file_put_contents(
            $root . '/upload/logs/analog_fill_' . date('Y-m-d') . '.log',
            date('H:i:s') . ' STATS exact=' . $exactKey . ' analogs=' . count($cands) . ' http=' . $httpN . ' total=' . count($allResults) . "
  " . implode("
  ", $lines) . "
",
            FILE_APPEND
        );

        return $allResults;
    }

    private function pickBrandArticle(string $gk, array $cand, string $code, array $brandmapMeta): array
    {
        if (isset($brandmapMeta[$gk]) && is_array($brandmapMeta[$gk])) {
            $info = $brandmapMeta[$gk];
            if (!empty($info['brands'][$code]) && !empty($info['articles'][$code])) {
                return [trim((string)$info['brands'][$code]), trim((string)$info['articles'][$code])];
            }
            foreach (($info['brands'] ?? []) as $src => $b) {
                $a = (string)($info['articles'][$src] ?? '');
                if ($b && $a && BrandNormalizer::groupKey((string)$b, $a) === $gk) {
                    return [trim((string)$b), trim($a)];
                }
            }
        }
        return [trim((string)($cand['brand'] ?? '')), trim((string)($cand['article'] ?? ''))];
    }

    private function sameProduct(string $b1, string $a1, string $b2, string $a2, string $wantGk): bool
    {
        if (BrandNormalizer::groupKey($b1, $a1) === $wantGk) {
            return true;
        }
        return BrandNormalizer::normalize($b1) === BrandNormalizer::normalize($b2)
            && BrandNormalizer::normalizeArticle($a1) === BrandNormalizer::normalizeArticle($a2);
    }

    private function isValidAnalog(
        string $brand,
        string $article,
        string $name,
        string $normTargetBrand,
        string $normTargetArt,
        string $targetFamily
    ): bool {
        // омоним артикула другого бренда — не аналог
        if ($normTargetArt !== ''
            && BrandNormalizer::normalizeArticle($article) === $normTargetArt
            && BrandNormalizer::normalize($brand) !== $normTargetBrand) {
            return false;
        }
        if ($targetFamily !== '' && !$this->familyOk($name . ' ' . $brand . ' ' . $article, $targetFamily)) {
            return false;
        }
        return true;
    }

    private function familyOk(string $text, string $targetFamily): bool
    {
        $fam = self::detectFamily($text);
        if ($fam === '' || $targetFamily === '' || $fam === $targetFamily) {
            return true;
        }
        $padLike = ['pad', 'brake'];
        return in_array($targetFamily, $padLike, true) && in_array($fam, $padLike, true);
    }
}
