<?php
// search/ajax.php — эндпоинты нового поиска v5 (FIX: brand/article order)
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use Lider\Search\BrandNormalizer;

$action  = $_GET['action'] ?? '';
$article = trim($_GET['article'] ?? '');
if (!$article) { echo json_encode(['error' => 'Укажите артикул']); exit; }

$normArt  = BrandNormalizer::normalizeArticle($article);
$factory  = getSupplierFactory();
$suppliers = $factory->allAvailable();

// ─── curl multi helper ───
function curlExec(array $suppliers, array $requests): array {
    if (empty($requests)) return [];
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $req['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }
    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);
    $results = [];
    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $results[$key] = ($http === 200 && $body) ? $body : null;
    }
    curl_multi_close($mh);
    return $results;
}

// ═══════════════════ ACTION: brands ═══════════════════
if ($action === 'brands') {

    $arrFilter = [['LOGIC' => 'OR',
        ['%NAME' => $article], ['PROPERTY_CML2_ARTICLE' => $article],
        ['%PROPERTY_CML2_ARTICLE' => $article], ['%DETAIL_TEXT' => $article],
        ['PROPERTY_CML2_MANUFACTURER' => $article], ['%PROPERTY_CML2_MANUFACTURER' => $article],
    ]];
    $localRes   = CIBlockElement::GetList([], array_merge(['IBLOCK_ID' => 42, 'ACTIVE' => 'Y'], $arrFilter[0]), false, false, ['ID']);
    $localCount = $localRes->SelectedRowsCount();

    $brandReqs = [];
    foreach ($suppliers as $code => $connector) {
        $req = $connector->buildBrandsRequest($article);
        if ($req) $brandReqs[$code] = $req;
    }
    $responses = curlExec($suppliers, $brandReqs);

    $allRaw = [];
    foreach ($responses as $code => $body) {
        if (!$body) continue;
        try {
            $items = $suppliers[$code]->parseBrandsResponse($body, $article);
            foreach ($items as $it) {
                $it['source'] = $code;
                $allRaw[] = $it;
            }
        } catch (\Throwable $e) {}
    }

    $brandMap = [];
    foreach ($allRaw as $br) {
        $b = trim((string)($br['brand'] ?? ''));
        $a = trim((string)($br['article_nr'] ?? ($br['article'] ?? '')));
        if ($b === '' || $a === '') continue;
        $key = BrandNormalizer::groupKey($b, $a);
        if (!isset($brandMap[$key])) {
            $brandMap[$key] = ['brands' => [], 'articles' => [], 'description' => '', 'sources' => []];
        }
        $brandMap[$key]['brands'][$br['source']]  = $b;
        $brandMap[$key]['articles'][$br['source']] = $a;
        if (!in_array($br['source'], $brandMap[$key]['sources'], true)) {
            $brandMap[$key]['sources'][] = $br['source'];
        }
        $desc = (string)($br['description'] ?? '');
        if (mb_strlen($desc) > mb_strlen($brandMap[$key]['description'])) {
            $brandMap[$key]['description'] = $desc;
        }
    }

    $brands = [];
    foreach ($brandMap as $key => $info) {
        $displayBrand   = BrandNormalizer::displayBrand(reset($info['brands']));
        $displayArticle = BrandNormalizer::pickDisplayArticle($info['articles'], '');
        $isExact        = (BrandNormalizer::normalizeArticle($displayArticle) === $normArt);
        $brands[] = [
            'brand'       => $displayBrand,
            'article'     => $displayArticle,
            'description' => $info['description'],
            'sources'     => array_values($info['sources']),
            'type'        => $isExact ? 'exact' : 'analog',
        ];
    }
    usort($brands, function($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'exact' ? -1 : 1;
        return count($b['sources']) - count($a['sources']);
    });

    echo json_encode(['brands' => $brands, 'local_count' => $localCount, 'article' => $article], JSON_UNESCAPED_UNICODE);

// ═══════════════════ ACTION: search ═══════════════════
} elseif ($action === 'search') {

    $brandOrig  = trim($_GET['brand'] ?? '');
    $numberOrig = trim($_GET['number'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }

    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    // ── 1. Точное совпадение ──
    // ВАЖНО: buildSearchRequest(БРЕНД, АРТИКУЛ) — бренд первый!
    $exactReqs = [];
    foreach ($suppliers as $code => $connector) {
        $req = $connector->buildSearchRequest($brandOrig, $normNum);
        if ($req) $exactReqs[$code] = $req;
    }
    $responses = curlExec($suppliers, $exactReqs);

    $exactOffers = [];
    $crossPairs  = [];
    $seenCross   = [];
    $seenCross[$normBrand . '|' . $normNum] = true;

    foreach ($responses as $code => $body) {
        if (!$body) continue;
        try {
            $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig);
        } catch (\Throwable $e) { continue; }

        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));

            if ($ia === $normNum && $ib === $normBrand) {
                $exactOffers[] = [
                    'supplier'      => $code,
                    'warehouse'     => (string)($it->warehouse ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            }

            $ck = $ib . '|' . $ia;
            if (!isset($seenCross[$ck])) {
                $seenCross[$ck] = true;
                $crossPairs[$ck] = [
                    'brand_orig'   => (string)($it->brand ?? ''),
                    'article_orig' => (string)($it->article ?? ''),
                    'brand_norm'   => $ib,
                    'article_norm' => $ia,
                ];
            }
        }
    }

    // ── 2. Кроссы у всех поставщиков ──
    $analogGroups = [];

    if (!empty($crossPairs)) {
        $crReqs = [];
        foreach ($crossPairs as $ck => $pair) {
            foreach ($suppliers as $code => $connector) {
                // ВАЖНО: buildSearchRequest(БРЕНД, АРТИКУЛ)
                $req = $connector->buildSearchRequest($pair['brand_orig'], $pair['article_orig']);
                if ($req) {
                    $crReqs[$code . '|' . $ck] = $req;
                }
            }
        }
        $crResponses = curlExec($suppliers, $crReqs);

        foreach ($crResponses as $reqKey => $body) {
            if (!$body) continue;
            $parts = explode('|', $reqKey, 2);
            $code  = $parts[0];
            $ck    = $parts[1] ?? '';
            $pair  = $crossPairs[$ck] ?? null;
            if (!$pair) continue;
            $gk = $pair['brand_norm'] . '|' . $pair['article_norm'];

            try {
                $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']);
            } catch (\Throwable $e) { continue; }

            foreach ($items as $it) {
                $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
                $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
                if ($ia !== $pair['article_norm'] || $ib !== $pair['brand_norm']) continue;

                if (!isset($analogGroups[$gk])) {
                    $analogGroups[$gk] = [
                        'brand_orig'   => $pair['brand_orig'],
                        'article_orig' => $pair['article_orig'],
                        'description'  => (string)($it->description ?? ''),
                        'offers'       => [],
                    ];
                }
                $desc = (string)($it->description ?? '');
                if (mb_strlen($desc) > mb_strlen($analogGroups[$gk]['description'])) {
                    $analogGroups[$gk]['description'] = $desc;
                }
                $analogGroups[$gk]['offers'][] = [
                    'supplier'      => $code,
                    'warehouse'     => (string)($it->warehouse ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            }
        }
    }

    // ── 3. Ответ ──
    $resp = [];

    if (!empty($exactOffers)) {
        usort($exactOffers, function($a, $b) {
            if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
            return $a['delivery_days'] - $b['delivery_days'];
        });
        $resp['exact'] = ['brand' => $brandOrig, 'article' => $numberOrig, 'suppliers' => $exactOffers];
    }

    $analogs = [];
    foreach ($analogGroups as $gk => $grp) {
        $offers       = $grp['offers'];
        $prices       = array_column($offers, 'price');
        $days         = array_column($offers, 'delivery_days');
        $qtys         = array_column($offers, 'quantity');
        $activePrices = array_filter($prices, function($p) { return $p > 0; });
        $activeDays   = array_filter($days, function($d) { return $d >= 0; });

        usort($offers, function($a, $b) {
            if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
            return $a['delivery_days'] - $b['delivery_days'];
        });

        $analogs[] = [
            'brand'         => $grp['brand_orig'],
            'article'       => $grp['article_orig'],
            'description'   => $grp['description'],
            'best_price'    => !empty($activePrices) ? min($activePrices) : 0,
            'best_delivery' => !empty($activeDays) ? min($activeDays) : null,
            'total_qty'     => array_sum($qtys),
            'has_instock'   => count(array_filter($qtys, function($q) { return $q > 0; })) > 0,
            'suppliers'     => $offers,
        ];
    }

    usort($analogs, function($a, $b) {
        if ($a['has_instock'] !== $b['has_instock']) return $b['has_instock'] - $a['has_instock'];
        $dA = $a['best_delivery'] ?? 999;
        $dB = $b['best_delivery'] ?? 999;
        if ($dA !== $dB) return $dA - $dB;
        return $a['best_price'] - $b['best_price'];
    });

    $resp['analogs'] = $analogs;
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}