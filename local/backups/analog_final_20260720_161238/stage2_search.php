<?php
@ini_set('memory_limit', '512M');
/**
 * Этап 2 v21
 * 1) Exact без кроссов — все поставщики (быстро, надёжно)
 * 2) Кроссы только PartKom + Autoeuro (отдельные запросы, timeout 15s)
 * 3) Full-cache warm ≈ 0s
 * 4) Квота складов по поставщикам; витрина до 80 аналогов
 */
use Lider\Search\BrandNormalizer;
use Lider\Search\SearchResultItem;

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = [];
$analogGroups = [];
$allBrands = [];
$totalGroups = 0;
$totalWarehouses = 0;
$searchNumber = $searchNumberRaw;

if ($searchNumberRaw === '' || $normTargetBrand === '') {
    return;
}

$isMgr = function_exists('isManager') ? (isManager() ? '1' : '0') : '0';
$fullCache = new \Lider\Search\SearchCacheManager('/search/s2_full_v22', 300);
$fullKey = md5(mb_strtolower(implode('|', [
    $q, $selectedBrand, $selectedNumber, (string)($brandKey ?? ''),
    (string)$filterPriceMin, (string)$filterPriceMax, (string)$filterBrand,
    (string)$sortExact, (string)$sortAnalog, $isMgr,
])));
$fullHit = $fullCache->get($fullKey);
if (is_array($fullHit) && !empty($fullHit['ok'])) {
    $exactGroups     = $fullHit['exactGroups'] ?? [];
    $analogGroups    = $fullHit['analogGroups'] ?? [];
    $allBrands       = $fullHit['allBrands'] ?? [];
    $totalGroups     = (int)($fullHit['totalGroups'] ?? 0);
    $totalWarehouses = (int)($fullHit['totalWarehouses'] ?? 0);
    $searchNumber    = (string)($fullHit['searchNumber'] ?? $searchNumberRaw);
    $selectedBrand   = (string)($fullHit['selectedBrand'] ?? $selectedBrand);
    return;
}

$multiFetch = function (array $reqs, int $timeout = 6, float $select = 0.05): array {
    $out = [];
    if (!$reqs) {
        return $out;
    }
    $mh = curl_multi_init();
    $hs = [];
    foreach ($reqs as $k => $r) {
        if (!$r || empty($r['url'])) {
            continue;
        }
        $to = (int)($r['_timeout'] ?? $timeout);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $r['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $r['headers'] ?? [],
            CURLOPT_TIMEOUT        => $to,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ]);
        if (($r['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($r['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $r['body']);
            }
        }
        curl_multi_add_handle($mh, $ch);
        $hs[$k] = $ch;
    }
    $rn = null;
    do {
        curl_multi_exec($mh, $rn);
        curl_multi_select($mh, $select);
    } while ($rn > 0);
    foreach ($hs as $k => $ch) {
        $out[$k] = [
            'body' => curl_multi_getcontent($ch),
            'http' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
};

// BRANDMAP
$bmCache = new \Lider\Search\SearchCacheManager('/search/supplier', 900);
$bmKey = 'brandmap_' . md5(mb_strtolower($q));
$cachedBrandMap = $bmCache->get($bmKey);

if (!is_array($cachedBrandMap) || empty($cachedBrandMap)) {
    $raw = [];
    $breqs = [];
    $bsups = [];
    foreach (getSupplierFactory()->allAvailable() as $s) {
        $r = $s->buildBrandsRequest($q);
        if ($r) {
            $breqs[$s->getCode()] = $r;
            $bsups[$s->getCode()] = $s;
        }
    }
    foreach ($multiFetch($breqs, 6) as $code => $resp) {
        if (($resp['http'] ?? 0) !== 200 || empty($resp['body'])) {
            continue;
        }
        try {
            foreach ($bsups[$code]->parseBrandsResponse($resp['body'], $q) as $br) {
                $br['source'] = $code;
                $raw[] = $br;
            }
        } catch (\Throwable $e) {
        }
    }
    $cachedBrandMap = [];
    foreach ($raw as $br) {
        $b = trim((string)($br['brand'] ?? ''));
        $a = trim((string)($br['article_nr'] ?? $br['article'] ?? ''));
        if ($b === '' || $a === '') {
            continue;
        }
        $k = BrandNormalizer::groupKey($b, $a);
        if (!isset($cachedBrandMap[$k])) {
            $cachedBrandMap[$k] = [
                'brands' => [], 'articles' => [], 'article_nr' => $a,
                'description' => (string)($br['description'] ?? ''), 'sources' => [],
            ];
        }
        $src = $br['source'];
        $cachedBrandMap[$k]['brands'][$src] = $b;
        $cachedBrandMap[$k]['articles'][$src] = $a;
        if (!in_array($src, $cachedBrandMap[$k]['sources'], true)) {
            $cachedBrandMap[$k]['sources'][] = $src;
        }
        $cachedBrandMap[$k]['article_nr'] = BrandNormalizer::pickDisplayArticle(
            $cachedBrandMap[$k]['articles'],
            $cachedBrandMap[$k]['article_nr']
        );
        $desc = (string)($br['description'] ?? '');
        if (mb_strlen($desc) > mb_strlen($cachedBrandMap[$k]['description'])) {
            $cachedBrandMap[$k]['description'] = $desc;
        }
    }
    $bmCache->set($bmKey, $cachedBrandMap, 900);
}
foreach ($cachedBrandMap as &$infoRef) {
    if (!empty($infoRef['articles'])) {
        $infoRef['article_nr'] = BrandNormalizer::pickDisplayArticle(
            $infoRef['articles'],
            (string)($infoRef['article_nr'] ?? '')
        );
    }
}
unset($infoRef);

$normQArt = BrandNormalizer::normalizeArticle($searchNumberRaw);
$targetKey = (is_string($brandKey ?? null) && $brandKey !== '')
    ? $brandKey
    : BrandNormalizer::groupKey($selectedBrand, $searchNumberRaw);
$targetEntry = $cachedBrandMap[$targetKey] ?? null;
if ($targetEntry === null) {
    foreach ($cachedBrandMap as $k => $info) {
        [$kb, $ka] = array_pad(explode('|', $k, 2), 2, '');
        if ($kb === $normTargetBrand && $ka === $normQArt) {
            $targetKey = $k;
            $targetEntry = $info;
            break;
        }
    }
}
if ($targetEntry === null) {
    foreach ($cachedBrandMap as $k => $info) {
        if (BrandNormalizer::normalizeArticle($info['article_nr'] ?? '') !== $normQArt) {
            continue;
        }
        foreach ($info['brands'] as $b) {
            if (BrandNormalizer::normalize($b) === $normTargetBrand) {
                $targetKey = $k;
                $targetEntry = $info;
                break 2;
            }
        }
    }
}

if ($targetEntry) {
    $arts = $targetEntry['articles'] ?? [];
    $displayArticle = BrandNormalizer::pickDisplayArticle($arts, $targetEntry['article_nr'] ?? $searchNumberRaw);
    foreach ($arts as $aCand) {
        $aCand = trim((string)$aCand);
        if ($aCand !== ''
            && BrandNormalizer::normalizeArticle($aCand) === BrandNormalizer::normalizeArticle($displayArticle)
            && preg_match('/[\/\-]/', $aCand)
        ) {
            $displayArticle = $aCand;
            break;
        }
    }
    $displayBrand = $canonBrand ?: BrandNormalizer::displayBrand((string)reset($targetEntry['brands']));
} else {
    $displayArticle = $searchNumberRaw;
    $displayBrand = $canonBrand;
}

$normTargetArt   = BrandNormalizer::normalizeArticle($displayArticle);
$normTargetBrand = BrandNormalizer::normalize($displayBrand);
$canonBrand      = BrandNormalizer::displayBrand($displayBrand);
$exactKey        = $normTargetBrand . '|' . $normTargetArt;
$brandmapMeta    = $cachedBrandMap;

// ---- anti false-analogs ----
$detectFamily = static function (string $text): string {
    $t = mb_strtolower($text);
    $map = [
        'pad'   => ['колодк', 'тормозн', 'brake pad', 'disc pad', 'fdb', 'gdb'],
        'stab'  => ['стабил', 'stabil', 'sway bar', 'anti-roll', 'тяга стаб', 'стойка стаб', 'link stab'],
        'filter'=> ['фильтр', 'filter', 'oil filter', 'air filter', 'fuel filter'],
        'spring'=> ['пружин', 'spring'],
        'tie'   => ['наконечник', 'рулев', 'tie rod', 'рулевая тяга'],
        'pan'   => ['поддон', 'oil pan', 'sump'],
        'bearing'=> ['подшипник', 'bearing'],
    ];
    foreach ($map as $fam => $words) {
        foreach ($words as $w) {
            if ($w !== '' && mb_strpos($t, $w) !== false) {
                return $fam;
            }
        }
    }
    return '';
};
$targetFamily = '';
// family from brandmap description if any
if ($targetEntry) {
    $targetFamily = $detectFamily((string)($targetEntry['description'] ?? ''));
}
if ($targetFamily === '') {
    $targetFamily = $detectFamily($displayBrand . ' ' . $displayArticle);
}



$itemToArray = static function (SearchResultItem $it): array {
    return [
        'source' => $it->source, 'article' => $it->article, 'brand' => $it->brand, 'name' => $it->name,
        'price' => $it->price, 'quantity' => $it->quantity, 'multiplicity' => $it->multiplicity, 'unit' => $it->unit,
        'deliveryDays' => $it->deliveryDays, 'deliveryPeriod' => $it->deliveryPeriod,
        'warehouse' => $it->warehouse, 'stockId' => $it->stockId, 'supplierName' => $it->supplierName,
        'isSched' => $it->isSched, 'returnable' => $it->returnable,
        'raw' => (static function ($raw) { $raw = is_array($raw) ? $raw : []; $keep = ['deliveryDateFrom','deliveryDateTo','deliveryCheckout','flags','stock_id','returnperiod','returnconditions','returnconditionsid','group','days','datearrival']; $o = []; foreach ($keep as $k) { if (array_key_exists($k, $raw)) { $o[$k] = $raw[$k]; } } return $o; })($it->raw),
    ];
};
$itemFromArray = static function (array $a): SearchResultItem {
    $it = new SearchResultItem();
    $it->source = (string)($a['source'] ?? '');
    $it->article = (string)($a['article'] ?? '');
    $it->brand = (string)($a['brand'] ?? '');
    $it->name = (string)($a['name'] ?? '');
    $it->price = (float)($a['price'] ?? 0);
    $it->quantity = (int)($a['quantity'] ?? 0);
    $it->multiplicity = max(1, (int)($a['multiplicity'] ?? 1));
    $it->unit = (string)($a['unit'] ?? 'шт.');
    $it->deliveryDays = array_key_exists('deliveryDays', $a) ? (is_null($a['deliveryDays']) ? null : (int)$a['deliveryDays']) : null;
    $it->deliveryPeriod = array_key_exists('deliveryPeriod', $a) ? (is_null($a['deliveryPeriod']) ? null : (int)$a['deliveryPeriod']) : null;
    $it->warehouse = $a['warehouse'] ?? null;
    $it->stockId = $a['stockId'] ?? null;
    $it->supplierName = $a['supplierName'] ?? null;
    $it->isSched = !empty($a['isSched']);
    $it->returnable = array_key_exists('returnable', $a) ? (bool)$a['returnable'] : true;
    $it->raw = is_array($a['raw'] ?? null) ? $a['raw'] : [];
    return $it;
};

$itemCache = new \Lider\Search\SearchCacheManager('/search/s2_items_v22', 600);
$allResults = [];
$batchReq = [];
$batchMeta = [];
$batchSeen = [];

$buildReq = function ($sup, string $brand, string $article, bool $withCrosses) {
    try {
        $rm = new \ReflectionMethod($sup, 'buildSearchRequest');
        if ($rm->getNumberOfParameters() >= 3) {
            return $sup->buildSearchRequest($brand, $article, $withCrosses);
        }
    } catch (\Throwable $e) {
    }
    return $sup->buildSearchRequest($brand, $article);
};

$addToBatch = function ($sup, string $brand, string $article, bool $withCrosses = false, int $timeout = 6) use (
    &$batchReq, &$batchMeta, &$allResults, &$batchSeen, $itemCache, $itemFromArray, $buildReq
) {
    $article = trim($article);
    if ($article === '') {
        return;
    }
    $brand = trim($brand);
    $code = $sup->getCode();
    $dedupe = $code . "\0" . mb_strtolower($brand) . "\0" . mb_strtolower($article) . "\0" . ($withCrosses ? '1' : '0');
    if (isset($batchSeen[$dedupe])) {
        return;
    }
    $batchSeen[$dedupe] = true;

    $ck = md5($code . '|' . BrandNormalizer::normalize($brand) . '|' . BrandNormalizer::normalizeArticle($article)
        . '|' . mb_strtolower($brand) . '|' . mb_strtolower($article) . '|x' . ($withCrosses ? '1' : '0'));

    $cached = $itemCache->get($ck);
    if (is_array($cached) && !empty($cached['ok']) && isset($cached['rows']) && is_array($cached['rows'])) {
        foreach ($cached['rows'] as $row) {
            if (is_array($row)) {
                $allResults[] = $itemFromArray($row);
            }
        }
        return;
    }

    $req = $buildReq($sup, $brand, $article, $withCrosses);
    if ($req) {
        $req['_timeout'] = $timeout;
        $batchReq[] = $req;
        $batchMeta[] = [
            'sup' => $sup,
            'brand' => $brand,
            'article' => $article,
            'ck' => $ck,
            'withCrosses' => $withCrosses,
        ];
    }
};

// ---- 1) EXACT without crosses (all suppliers) ----
foreach (getSupplierFactory()->allAvailable() as $sup) {
    $code = $sup->getCode();
    $queries = [];
    if ($targetEntry && !empty($targetEntry['brands'][$code]) && !empty($targetEntry['articles'][$code])) {
        $queries[] = [(string)$targetEntry['brands'][$code], (string)$targetEntry['articles'][$code]];
    }
    $queries[] = [$canonBrand, $displayArticle];
    $seenQ = [];
    foreach ($queries as $qa) {
        $k = mb_strtolower($qa[0] . '|' . $qa[1]);
        if (isset($seenQ[$k])) {
            continue;
        }
        $seenQ[$k] = true;
        $addToBatch($sup, $qa[0], $qa[1], false, 6);
        if (count($seenQ) >= 2) {
            break;
        }
    }
}

// ---- 2) CROSSES only PartKom + Autoeuro + Ixora (long timeout) ----
foreach (['partkom', 'autoeuro', 'ixora'] as $code) {
    $sup = getSupplierFactory()->get($code);
    if (!$sup) {
        continue;
    }
    $b = $canonBrand;
    $a = $displayArticle;
    if ($targetEntry && !empty($targetEntry['brands'][$code]) && !empty($targetEntry['articles'][$code])) {
        $b = (string)$targetEntry['brands'][$code];
        $a = (string)$targetEntry['articles'][$code];
    }
    $addToBatch($sup, $b, $a, true, 8);
}

// ---- 3) Доп. brandmap (no crosses), без артикулов-омонимов ----
$extra = [];
foreach ($cachedBrandMap as $mk => $info) {
    if ($mk === $targetKey || $mk === $exactKey) {
        continue;
    }
    [$kb, $ka] = array_pad(explode('|', $mk, 2), 2, '');
    if ($kb === '' || $ka === '') {
        continue;
    }
    if ($kb === $normTargetBrand && $ka === $normTargetArt) {
        continue;
    }
    // тот же номер, другой бренд — НЕ аналог
    if ($ka === $normTargetArt && $kb !== $normTargetBrand) {
        continue;
    }
    $desc = (string)($info['description'] ?? '');
    $fam = $detectFamily($desc . ' ' . $kb . ' ' . $ka);
    if ($targetFamily !== '' && $fam !== '' && $fam !== $targetFamily) {
        continue;
    }
    $isLynx = str_starts_with($mk, 'lynx') || $kb === 'lynxauto' || $kb === 'lynx';
    if ($isLynx || (count($info['sources'] ?? []) >= 2 && $ka !== $normTargetArt)) {
        $extra[$mk] = $info;
    }
}
uasort($extra, fn($a, $b) => count($b['sources'] ?? []) <=> count($a['sources'] ?? []));
$extra = array_slice($extra, 0, 8, true);
foreach ($extra as $mk => $info) {
    $fbBrand = BrandNormalizer::displayBrand((string)(reset($info['brands']) ?: ''));
    $fbArt = BrandNormalizer::pickDisplayArticle($info['articles'] ?? [], $info['article_nr'] ?? '');
    if ($fbBrand === '' || $fbArt === '') {
        continue;
    }
    foreach (getSupplierFactory()->allAvailable() as $sup) {
        $code = $sup->getCode();
        if (!empty($info['brands'][$code]) && !empty($info['articles'][$code])) {
            $addToBatch($sup, (string)$info['brands'][$code], (string)$info['articles'][$code], false, 4);
        } else {
            $addToBatch($sup, $fbBrand, $fbArt, false, 4);
        }
    }
}

// EXECUTE
$chunksR = array_chunk($batchReq, 25, true);
$chunksM = array_chunk($batchMeta, 25, true);
foreach ($chunksR as $ci => $chunk) {
    $mh = curl_multi_init();
    $hs = [];
    foreach ($chunk as $i => $r) {
        $to = (int)($r['_timeout'] ?? 6);
        unset($r['_timeout']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $r['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $r['headers'] ?? [],
            CURLOPT_TIMEOUT        => $to,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
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
        $m = $chunksM[$ci][$i];
        $rows = [];
        if ($http === 200 && $body) {
            try {
                $items = $m['sup']->parseSearchResponse($body, $m['brand'], $m['article']);
                // universal cross hard cap (OOM guard)
                if (!empty($m['withCrosses']) && is_array($items)) {
                    $codeSup = (string)($m['sup']->getCode() ?? '');
                    if ($codeSup === 'ixora' || $codeSup === 'partkom') {
                        // ixora_group_cap / partkom: max N groups, M rows each
                        $maxGroups = ($codeSup === 'ixora') ? 25 : 40;
                        $maxPerGroup = 4;
                        $byG = [];
                        $trimmed = [];
                        foreach ($items as $it) {
                            $gk = BrandNormalizer::groupKey($it->brand, $it->article);
                            if (!isset($byG[$gk])) {
                                if (count($byG) >= $maxGroups) {
                                    continue;
                                }
                                $byG[$gk] = 0;
                            }
                            if ($byG[$gk] >= $maxPerGroup) continue;
                            $byG[$gk]++;
                            $trimmed[] = $it;
                        }
                        $items = $trimmed;
                    } elseif (count($items) > 100) {
                        $items = array_slice($items, 0, 100);
                    }
                } elseif (is_array($items) && count($items) > 180) {
                    $items = array_slice($items, 0, 180);
                }
                unset($body);
                // Ixora cross может вернуть сотни позиций — режем до попадания в память/кеш
                if (!empty($m['withCrosses']) && (($m['sup']->getCode() ?? '') === 'ixora') && count($items) > 120) {
                    $items = array_slice($items, 0, 120);
                }
                // для огромных cross-ответов: ограничим уникальные группы
                $groupCount = [];
                foreach ($items as $it) {
                    if (trim($it->brand) === '' || trim($it->article) === '') {
                        continue;
                    }
                    // family_guard_item
                    if (!empty($m['withCrosses']) && isset($detectFamily, $targetFamily) && $targetFamily !== '') {
                        $fam = $detectFamily((string)$it->name . ' ' . $it->brand . ' ' . $it->article);
                        if ($fam !== '' && $fam !== $targetFamily) {
                            continue;
                        }
                        // same article other brand
                        if (BrandNormalizer::normalizeArticle($it->article) === $normTargetArt
                            && BrandNormalizer::normalize($it->brand) !== $normTargetBrand) {
                            continue;
                        }
                    }
                    if ($it->price <= 0 && $it->quantity <= 0) {
                        continue;
                    }
                    $gk = BrandNormalizer::groupKey($it->brand, $it->article);
                    $groupCount[$gk] = ($groupCount[$gk] ?? 0) + 1;
                    // не больше 8 складов на одну группу из одного ответа
                    if ($groupCount[$gk] > 8) {
                        continue;
                    }
                    $rows[] = $itemToArray($it);
                    $allResults[] = $it;
                }
            } catch (\Throwable $e) {
            }
        }
        $itemCache->set($m['ck'], ['ok' => 1, 'rows' => $rows], 600);
    }
    curl_multi_close($mh);
}


// =============================================================================

// =============================================================================
// ANALOG FILL v2 FAST
// -----------------------------------------------------------------------------
// Цель: полнота складов по аналогам БЕЗ минутных ожиданий.
// Правила скорости:
//  - мало кандидатов (топ-8)
//  - 1 написание brand/article на поставщика
//  - НЕ спрашиваем поставщика, если у него УЖЕ есть offers по этому gk
//  - общий hard-cap на число HTTP fill-запросов
//  - короткий timeout, крупный multi batch
//  - не трогаем $batchReq/$batchSeen/$addToBatch
// =============================================================================

$__af_t0 = microtime(true);

if (!isset($detectFamily) || !is_callable($detectFamily)) {
    $detectFamily = static function (string $text): string {
        $t = mb_strtolower($text);
        $map = [
            'pad' => ['колодк', 'brake pad', 'disc pad'],
            'stab' => ['стабил', 'stabilizer', 'sway bar', 'тяга стаб', 'стойка стаб', 'anti-roll'],
            'filter' => ['фильтр', 'filter'],
            'spring' => ['пружин', 'spring'],
            'tie' => ['наконечник', 'tie rod', 'рулев'],
            'pan' => ['поддон', 'oil pan'],
            'bearing' => ['подшипник', 'bearing'],
            'shock' => ['амортиз', 'shock absorber', 'strut'],
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
    };
}

if (!isset($targetFamily) || $targetFamily === '') {
    $targetFamily = '';
    if (!empty($targetEntry['description'])) {
        $targetFamily = $detectFamily((string)$targetEntry['description']);
    }
    if ($targetFamily === '') {
        $targetFamily = $detectFamily((string)$displayBrand . ' ' . (string)$displayArticle);
    }
    if ($targetFamily === '') {
        foreach ($allResults as $_it) {
            if (!($_it instanceof SearchResultItem)) continue;
            if (BrandNormalizer::groupKey($_it->brand, $_it->article) === $exactKey) {
                $targetFamily = $detectFamily((string)$_it->name);
                if ($targetFamily !== '') break;
            }
        }
    }
}

// --- already present offers by groupKey + supplier (чтобы не дублировать запросы)
$haveByGkSup = []; // gk => [code => count]
$analogCandidates = []; // gk => data

foreach ($allResults as $it) {
    if (!($it instanceof SearchResultItem)) {
        continue;
    }
    $gk = BrandNormalizer::groupKey($it->brand, $it->article);
    if ($gk === '') continue;
    $code = (string)$it->source;
    if (!isset($haveByGkSup[$gk])) $haveByGkSup[$gk] = [];
    $haveByGkSup[$gk][$code] = ($haveByGkSup[$gk][$code] ?? 0) + 1;

    if ($gk === $exactKey) {
        continue;
    }
    // омонимы
    if (BrandNormalizer::normalizeArticle($it->article) === $normTargetArt
        && BrandNormalizer::normalize($it->brand) !== $normTargetBrand) {
        continue;
    }
    if ($targetFamily !== '') {
        $fam = $detectFamily((string)$it->name . ' ' . $it->brand . ' ' . $it->article);
        if ($fam !== '' && $fam !== $targetFamily) {
            $padLike = ['pad', 'brake'];
            if (!(in_array($targetFamily, $padLike, true) && in_array($fam, $padLike, true))) {
                continue;
            }
        }
    }
    if (!isset($analogCandidates[$gk])) {
        $analogCandidates[$gk] = [
            'brand' => BrandNormalizer::displayBrand($it->brand) ?: (string)$it->brand,
            'article' => (string)$it->article,
            'name' => (string)$it->name,
            'score' => 0,
            'sources' => [],
        ];
    }
    $analogCandidates[$gk]['score'] += 3;
    $analogCandidates[$gk]['sources'][$code] = true;
    if (mb_strlen((string)$it->name) > mb_strlen((string)$analogCandidates[$gk]['name'])) {
        $analogCandidates[$gk]['name'] = (string)$it->name;
    }
}

// brandmap candidates (дешёвые, без HTTP)
if (!empty($cachedBrandMap) && is_array($cachedBrandMap)) {
    foreach ($cachedBrandMap as $mk => $info) {
        if ($mk === $exactKey || $mk === ($targetKey ?? '')) continue;
        [$kb, $ka] = array_pad(explode('|', (string)$mk, 2), 2, '');
        if ($kb === '' || $ka === '') continue;
        if ($ka === $normTargetArt && $kb !== $normTargetBrand) continue;
        $bDisp = BrandNormalizer::displayBrand((string)(reset($info['brands']) ?: $kb));
        $aDisp = BrandNormalizer::pickDisplayArticle($info['articles'] ?? [], $info['article_nr'] ?? $ka);
        $desc = (string)($info['description'] ?? '');
        if ($targetFamily !== '') {
            $fam = $detectFamily($desc . ' ' . $bDisp . ' ' . $aDisp);
            if ($fam !== '' && $fam !== $targetFamily) {
                $padLike = ['pad', 'brake'];
                if (!(in_array($targetFamily, $padLike, true) && in_array($fam, $padLike, true))) {
                    continue;
                }
            }
        }
        $gk = BrandNormalizer::groupKey($bDisp, $aDisp);
        if ($gk === '' || $gk === $exactKey) continue;
        $srcN = count($info['sources'] ?? []);
        $score = 1 + min(5, $srcN);
        if (str_starts_with((string)$mk, 'lynx') || $kb === 'lynxauto' || $kb === 'lynx') {
            $score += 4;
        }
        if (!isset($analogCandidates[$gk])) {
            $analogCandidates[$gk] = [
                'brand' => $bDisp,
                'article' => $aDisp,
                'name' => $desc,
                'score' => 0,
                'sources' => [],
            ];
        }
        $analogCandidates[$gk]['score'] += $score;
        foreach (($info['sources'] ?? []) as $sCode) {
            $analogCandidates[$gk]['sources'][$sCode] = true;
        }
        if ($desc !== '' && mb_strlen($desc) > mb_strlen((string)$analogCandidates[$gk]['name'])) {
            $analogCandidates[$gk]['name'] = $desc;
        }
        // сохраним brandmap info link
        $analogCandidates[$gk]['bm'] = $info;
    }
}

uasort($analogCandidates, static function ($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});

// СКОРОСТЬ: топ-8 аналогов (было 18)
$analogCandidates = array_slice($analogCandidates, 0, 8, true);

$fillReq = [];
$fillMeta = [];
$fillSeen = [];
$maxFillHttp = 36; // hard cap HTTP (8 analogs * ~4 missing suppliers)
$fillHttp = 0;

$allSuppliers = getSupplierFactory()->allAvailable();

foreach ($analogCandidates as $gk => $cand) {
    if ($fillHttp >= $maxFillHttp) {
        break;
    }
    $baseBrand = trim((string)$cand['brand']);
    $baseArt = trim((string)$cand['article']);
    if ($baseBrand === '' || $baseArt === '') {
        continue;
    }
    $bm = $cand['bm'] ?? ($brandmapMeta[$gk] ?? null);

    foreach ($allSuppliers as $sup) {
        if ($fillHttp >= $maxFillHttp) {
            break 2;
        }
        $code = $sup->getCode();

        // УЖЕ есть offers этого поставщика по аналогу — skip
        if (!empty($haveByGkSup[$gk][$code])) {
            continue;
        }

        // одно лучшее написание
        $b = $baseBrand;
        $a = $baseArt;
        if (is_array($bm) && !empty($bm['brands'][$code]) && !empty($bm['articles'][$code])) {
            $b = trim((string)$bm['brands'][$code]);
            $a = trim((string)$bm['articles'][$code]);
        }
        if ($b === '' || $a === '') {
            continue;
        }
        // groupKey должен совпасть
        if (BrandNormalizer::groupKey($b, $a) !== $gk) {
            $b = $baseBrand;
            $a = $baseArt;
            if (BrandNormalizer::groupKey($b, $a) !== $gk) {
                continue;
            }
        }

        $dedupe = $code . "\0" . $gk;
        if (isset($fillSeen[$dedupe])) {
            continue;
        }
        $fillSeen[$dedupe] = true;

        $ck = md5('afill2|' . $code . '|' . $gk . '|' . mb_strtolower($b) . '|' . mb_strtolower($a));
        $cached = $itemCache->get($ck);
        if (is_array($cached) && !empty($cached['ok']) && isset($cached['rows']) && is_array($cached['rows'])) {
            // даже пустой ok-кеш уважаем коротко, но мы пустые не пишем
            if (!empty($cached['rows'])) {
                foreach ($cached['rows'] as $row) {
                    if (is_array($row)) {
                        $allResults[] = $itemFromArray($row);
                    }
                }
                $haveByGkSup[$gk][$code] = ($haveByGkSup[$gk][$code] ?? 0) + count($cached['rows']);
            }
            continue;
        }

        try {
            $req = $buildReq($sup, $b, $a, false);
        } catch (\Throwable $e) {
            $req = null;
        }
        if (!$req) {
            continue;
        }
        $req['_timeout'] = 4; // быстро
        $fillReq[] = $req;
        $fillMeta[] = [
            'sup' => $sup,
            'brand' => $b,
            'article' => $a,
            'ck' => $ck,
            'gk' => $gk,
            'code' => $code,
        ];
        $fillHttp++;
    }
}

if ($fillReq) {
    // один-два крупных multi-batch вместо множества мелких
    $chunksR = array_chunk($fillReq, 30, true);
    $chunksM = array_chunk($fillMeta, 30, true);
    foreach ($chunksR as $ci => $chunk) {
        // deadline: не больше ~8с на весь fill
        if ((microtime(true) - $__af_t0) > 8.0) {
            break;
        }
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
            $m = $chunksM[$ci][$i];
            $rows = [];
            if ($http === 200 && $body) {
                try {
                    $items = $m['sup']->parseSearchResponse($body, $m['brand'], $m['article']);
                    if (!is_array($items)) {
                        $items = [];
                    }
                    if (count($items) > 40) {
                        $items = array_slice($items, 0, 40);
                    }
                    unset($body);
                    $wantGk = $m['gk'];
                    $gc = 0;
                    foreach ($items as $it) {
                        if (trim($it->brand) === '' || trim($it->article) === '') continue;
                        if ($it->price <= 0 && $it->quantity <= 0) continue;
                        if (BrandNormalizer::groupKey($it->brand, $it->article) !== $wantGk) continue;
                        if ($targetFamily !== '') {
                            $fam = $detectFamily((string)$it->name . ' ' . $it->brand);
                            if ($fam !== '' && $fam !== $targetFamily) {
                                $padLike = ['pad', 'brake'];
                                if (!(in_array($targetFamily, $padLike, true) && in_array($fam, $padLike, true))) {
                                    continue;
                                }
                            }
                        }
                        $gc++;
                        if ($gc > 10) break;
                        $rows[] = $itemToArray($it);
                        $allResults[] = $it;
                    }
                } catch (\Throwable $e) {
                }
            }
            if ($rows) {
                $itemCache->set($m['ck'], ['ok' => 1, 'rows' => $rows], 600);
            }
        }
        curl_multi_close($mh);
    }
}
// /ANALOG FILL v2 FAST

// GROUPING
$MAX_WH_TOTAL = 36;
$MAX_WH_PER_SUPPLIER = 8;
$ANALOG_DISPLAY_CAP = 80;
$groupedItems = [];

foreach ($allResults as $item) {
    $key = BrandNormalizer::groupKey($item->brand, $item->article);
    if (!isset($groupedItems[$key])) {
        $groupedItems[$key] = [
            '_seen_wh' => [], '_articles_raw' => [], '_by_sup' => [],
            'brand' => BrandNormalizer::displayBrand($item->brand),
            'article' => $item->article,
            'description' => $item->name,
            'min_price' => PHP_FLOAT_MAX, 'max_price' => 0.0,
            'total_qty' => 0, 'in_stock_qty' => 0,
            'min_delivery' => ['days' => PHP_INT_MAX, 'is_approx' => false],
            'has_instock' => false, 'warehouses' => [],
        ];
    }
    $g = &$groupedItems[$key];
    $g['_articles_raw'][] = $item->article;
    $g['brand'] = BrandNormalizer::displayBrand($g['brand'] ?: $item->brand);
    if (mb_strlen((string)$item->name) > mb_strlen((string)$g['description'])) {
        $g['description'] = $item->name;
    }

    $priceBase = round((float)$item->price, 2);
    $priceDisplay = getDisplayPrice($priceBase);
    $delivery = calcDelivery($item);
    $stockName = $item->isSched ? 'Под заказ' : maskWarehouse($item);
    $src = (string)$item->source;

    if ($priceDisplay > 0 && $priceDisplay < $g['min_price']) {
        $g['min_price'] = $priceDisplay;
    }
    if ($priceDisplay > $g['max_price']) {
        $g['max_price'] = $priceDisplay;
    }
    $g['total_qty'] += (int)$item->quantity;
    if (!$item->isSched) {
        $g['in_stock_qty'] += (int)$item->quantity;
        $g['has_instock'] = true;
    }
    if (($delivery['days'] ?? PHP_INT_MAX) < ($g['min_delivery']['days'] ?? PHP_INT_MAX)) {
        $g['min_delivery'] = $delivery;
    }

    $whKey = $src . '|' . ($item->stockId ?: $item->warehouse) . '|' . $priceDisplay . '|' . ((int)$item->quantity);
    if (isset($g['_seen_wh'][$whKey])) {
        unset($g);
        continue;
    }
    if (!isset($g['_by_sup'][$src])) {
        $g['_by_sup'][$src] = [];
    }
    if (count($g['_by_sup'][$src]) >= 20) {
        unset($g);
        continue;
    }
    $g['_seen_wh'][$whKey] = true;
    $g['_by_sup'][$src][] = [
        'stock' => $stockName, 'price' => $priceDisplay, 'price_base' => $priceBase,
        'qty' => $item->quantity, 'multiplicity' => $item->multiplicity ?? 1, 'unit' => $item->unit ?? 'шт.',
        'delivery' => $delivery, 'is_sched' => $item->isSched, 'returnable' => $item->returnable,
        'source' => $src, 'supplier' => $item->supplierName,
    ];
    unset($g);
}

$whSort = function ($a, $b) {
    $sa = !empty($a['is_sched']) ? 1 : 0;
    $sb = !empty($b['is_sched']) ? 1 : 0;
    if ($sa !== $sb) {
        return $sa <=> $sb;
    }
    if ($a['price'] !== $b['price']) {
        return $a['price'] <=> $b['price'];
    }
    return ($a['delivery']['days'] ?? 999) <=> ($b['delivery']['days'] ?? 999);
};

foreach ($groupedItems as $key => &$g) {
    if ($key === $exactKey
        || (BrandNormalizer::normalize($g['brand']) === $normTargetBrand
            && BrandNormalizer::normalizeArticle($g['article']) === $normTargetArt)
    ) {
        $g['brand'] = $displayBrand;
        $g['article'] = $displayArticle;
    } elseif (isset($brandmapMeta[$key])) {
        $info = $brandmapMeta[$key];
        $g['brand'] = BrandNormalizer::displayBrand((string)reset($info['brands']));
        $g['article'] = BrandNormalizer::pickDisplayArticle($info['articles'] ?? [], $info['article_nr'] ?? $g['article']);
    } else {
        $g['article'] = BrandNormalizer::pickDisplayArticle($g['_articles_raw'] ?? [], $g['article']);
        $g['brand'] = BrandNormalizer::displayBrand($g['brand']);
    }

    $bySup = $g['_by_sup'] ?? [];
    foreach ($bySup as $src => &$list) {
        usort($list, $whSort);
        $list = array_slice($list, 0, $MAX_WH_PER_SUPPLIER);
    }
    unset($list);

    $merged = [];
    foreach (array_keys($bySup) as $src) {
        if (!empty($bySup[$src])) {
            $merged[] = array_shift($bySup[$src]);
        }
    }
    $rest = [];
    foreach ($bySup as $list) {
        foreach ($list as $w) {
            $rest[] = $w;
        }
    }
    usort($rest, $whSort);
    foreach ($rest as $w) {
        if (count($merged) >= $MAX_WH_TOTAL) {
            break;
        }
        $merged[] = $w;
    }
    $g['warehouses'] = $merged;

    $g['min_price'] = PHP_FLOAT_MAX;
    $g['max_price'] = 0.0;
    $g['total_qty'] = 0;
    $g['in_stock_qty'] = 0;
    $g['has_instock'] = false;
    $g['min_delivery'] = ['days' => PHP_INT_MAX, 'is_approx' => false];
    foreach ($g['warehouses'] as $w) {
        if ($w['price'] > 0 && $w['price'] < $g['min_price']) {
            $g['min_price'] = $w['price'];
        }
        if ($w['price'] > $g['max_price']) {
            $g['max_price'] = $w['price'];
        }
        $g['total_qty'] += (int)$w['qty'];
        if (empty($w['is_sched'])) {
            $g['in_stock_qty'] += (int)$w['qty'];
            $g['has_instock'] = true;
        }
        if (($w['delivery']['days'] ?? PHP_INT_MAX) < ($g['min_delivery']['days'] ?? PHP_INT_MAX)) {
            $g['min_delivery'] = $w['delivery'];
        }
    }
    if ($g['min_price'] === PHP_FLOAT_MAX) {
        $g['min_price'] = 0;
    }
    unset($g['_seen_wh'], $g['_articles_raw'], $g['_by_sup']);
}
unset($g);

foreach ($groupedItems as $key => $g) {
    if (empty($g['warehouses'])) {
        continue;
    }
    $gBrandNorm = BrandNormalizer::normalize($g['brand']);
    $gArtNorm = BrandNormalizer::normalizeArticle($g['article']);
    if (($gBrandNorm === $normTargetBrand && $gArtNorm === $normTargetArt) || $key === $exactKey) {
        $g['brand'] = $displayBrand;
        $g['article'] = $displayArticle;
        $exactGroups[$key] = $g;
        if ($targetFamily === '') {
            $targetFamily = $detectFamily((string)($g['description'] ?? '') . ' ' . $g['brand'] . ' ' . $g['article']);
        }
    } else {
        // homonym_analog_filter: same article + other brand => drop
        if ($gArtNorm === $normTargetArt && $gBrandNorm !== $normTargetBrand) {
            continue;
        }
        // family mismatch (pads vs stabilizer links etc.)
        $fam = $detectFamily((string)($g['description'] ?? '') . ' ' . $g['brand'] . ' ' . $g['article']);
        if ($targetFamily !== '' && $fam !== '' && $fam !== $targetFamily) {
            continue;
        }
        $analogGroups[$key] = $g;
    }
}

$applyFilter = function ($g) use ($filterPriceMin, $filterPriceMax, $filterBrand) {
    if ($filterPriceMin > 0 && $g['min_price'] < $filterPriceMin) {
        return false;
    }
    if ($filterPriceMax > 0 && $g['min_price'] > $filterPriceMax) {
        return false;
    }
    if ($filterBrand !== '' && mb_stripos($g['brand'], $filterBrand) === false) {
        return false;
    }
    return true;
};
$exactGroups = array_filter($exactGroups, $applyFilter);
$analogGroups = array_filter($analogGroups, $applyFilter);

uasort($analogGroups, function ($a, $b) {
    $sa = [];
    foreach ($a['warehouses'] as $w) {
        $sa[$w['source'] ?? ''] = true;
    }
    unset($sa['']);
    $sb = [];
    foreach ($b['warehouses'] as $w) {
        $sb[$w['source'] ?? ''] = true;
    }
    unset($sb['']);
    if ((!empty($a['has_instock']) ? 1 : 0) !== (!empty($b['has_instock']) ? 1 : 0)) {
        return (!empty($b['has_instock']) ? 1 : 0) <=> (!empty($a['has_instock']) ? 1 : 0);
    }
    if (count($sa) !== count($sb)) {
        return count($sb) <=> count($sa);
    }
    return ($a['min_price'] ?? 0) <=> ($b['min_price'] ?? 0);
});

// LYNX и другие «закреплённые» из brandmap не должны выпадать из cap
$pinnedAnalog = [];
$otherAnalog = [];
foreach ($analogGroups as $k => $g) {
    $isPin = str_starts_with($k, 'lynx')
        || BrandNormalizer::normalize($g['brand'] ?? '') === 'lynxauto';
    if ($isPin) {
        $pinnedAnalog[$k] = $g;
    } else {
        $otherAnalog[$k] = $g;
    }
}
if (count($pinnedAnalog) + count($otherAnalog) > $ANALOG_DISPLAY_CAP) {
    $room = max(0, $ANALOG_DISPLAY_CAP - count($pinnedAnalog));
    $otherAnalog = array_slice($otherAnalog, 0, $room, true);
}
$analogGroups = $pinnedAnalog + $otherAnalog;

foreach ($exactGroups as &$g) {
    sortWarehouses($g['warehouses'], $sortExact);
}
unset($g);
foreach ($analogGroups as &$g) {
    sortWarehouses($g['warehouses'], $sortAnalog);
}
unset($g);
sortGroups($exactGroups, $sortExact);
sortGroups($analogGroups, $sortAnalog);

$allBrands = [];
foreach (array_merge($exactGroups, $analogGroups) as $g) {
    $allBrands[$g['brand']] = true;
}
ksort($allBrands);

$totalGroups = count($exactGroups) + count($analogGroups);
$totalWarehouses = 0;
foreach (array_merge($exactGroups, $analogGroups) as $g) {
    $totalWarehouses += count($g['warehouses']);
}

$searchNumber = $displayArticle;
$selectedBrand = $displayBrand;

$fullCache->set($fullKey, [
    'ok' => 1,
    'exactGroups' => $exactGroups,
    'analogGroups' => $analogGroups,
    'allBrands' => $allBrands,
    'totalGroups' => $totalGroups,
    'totalWarehouses' => $totalWarehouses,
    'searchNumber' => $searchNumber,
    'selectedBrand' => $selectedBrand,
], 300);
