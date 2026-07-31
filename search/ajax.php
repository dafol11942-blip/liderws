<?php
// search/ajax.php — чистые эндпоинты (brands + search)
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/Common/MultiCurlExecutor.php';

use Lider\Search\BrandNormalizer;

$action  = $_GET['action'] ?? '';
$article = trim($_GET['article'] ?? '');

if (!$article) { echo json_encode(['error' => 'Укажите артикул']); exit; }

$normArt  = BrandNormalizer::normalizeArticle($article);
$factory  = getSupplierFactory();
$suppliers = $factory->allAvailable();

// ═══════════════════ ACTION: brands ═══════════════════
if ($action === 'brands') {

    // --- Свои остатки (1С) ---
    $arrFilter = [['LOGIC' => 'OR',
        ['%NAME' => $article], ['PROPERTY_CML2_ARTICLE' => $article],
        ['%PROPERTY_CML2_ARTICLE' => $article], ['%DETAIL_TEXT' => $article],
        ['PROPERTY_CML2_MANUFACTURER' => $article], ['%PROPERTY_CML2_MANUFACTURER' => $article],
    ]];
    $localRes = CIBlockElement::GetList([], array_merge(['IBLOCK_ID' => 42, 'ACTIVE' => 'Y'], $arrFilter[0]), false, false, ['ID']);
    $localCount = $localRes->SelectedRowsCount();

    // --- Бренды от поставщиков ---
    $mh = curl_multi_init();
    $handles = [];
    $brandReqs = [];

    foreach ($suppliers as $code => $connector) {
        $req = $connector->buildBrandsRequest($article);
        if (!$req) continue;
        $brandReqs[$code] = ['req' => $req, 'supplier' => $connector];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $req['url'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $req['headers'], CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_ENCODING => '',
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$code] = $ch;
    }

    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);

    $allRaw = [];
    foreach ($handles as $code => $ch) {
        $body = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($http === 200 && $body) {
            try {
                $items = $brandReqs[$code]['supplier']->parseBrandsResponse($body, $article);
                foreach ($items as $it) {
                    $it['source'] = $code;
                    $allRaw[] = $it;
                }
            } catch (\Throwable $e) {}
        }
    }
    curl_multi_close($mh);

    // --- Группировка ---
    $brandMap = [];
    foreach ($allRaw as $br) {
        $b = trim((string)($br['brand'] ?? ''));
        $a = trim((string)($br['article_nr'] ?? ($br['article'] ?? '')));
        if ($b === '' || $a === '') continue;
        $key = BrandNormalizer::groupKey($b, $a);
        if (!isset($brandMap[$key])) {
            $brandMap[$key] = ['brands' => [], 'articles' => [], 'description' => '', 'sources' => []];
        }
        $brandMap[$key]['brands'][$br['source']] = $b;
        $brandMap[$key]['articles'][$br['source']] = $a;
        if (!in_array($br['source'], $brandMap[$key]['sources'], true)) {
            $brandMap[$key]['sources'][] = $br['source'];
        }
        $desc = (string)($br['description'] ?? '');
        if (mb_strlen($desc) > mb_strlen($brandMap[$key]['description'])) {
            $brandMap[$key]['description'] = $desc;
        }
    }

    // --- Формируем ответ ---
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

    $brandOrig  = trim($_GET['brand'] ?? '');   // оригинальный — для API
    $numberOrig = trim($_GET['number'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }

    $normBrand  = BrandNormalizer::normalize($brandOrig);    // нормализованный — для сравнения
    $normNum    = BrandNormalizer::normalizeArticle($numberOrig);

    // --- 1. Точное совпадение у всех поставщиков (передаём ОРИГИНАЛЬНЫЙ бренд) ---
    $mh = curl_multi_init();
    $handles = [];
    $exactReqs = [];

    foreach ($suppliers as $code => $connector) {
        $req = $connector->buildSearchRequest($normNum, $brandOrig);
        if (!$req) continue;
        $exactReqs[$code] = ['req' => $req, 'supplier' => $connector];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $req['url'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $req['headers'], CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_ENCODING => '',
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        curl_multi_add_handle($mh, $ch);
        $handles[$code] = $ch;
    }

    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);

    $exactOffers = [];
    $crossPairs  = [];
    $seenCross   = [];

    foreach ($handles as $code => $ch) {
        $body = curl_multi_getcontent($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($http !== 200 || !$body) continue;

        try {
            $items = $exactReqs[$code]['supplier']->parseSearchResponse($body);
        } catch (\Throwable $e) { continue; }

        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it['article'] ?? ''));
            $ib = BrandNormalizer::normalize((string)($it['brand'] ?? ''));
            if (!$ia || !$ib) continue;

            // Точное совпадение (сравниваем по нормализованным)
            if ($ia === $normNum && $ib === $normBrand) {
                $exactOffers[] = normalizeOffer($it, $code);
            }

            // Кроссы (все кроме точного)
            $ck = $ib . '|' . $ia;
            if (($ia !== $normNum || $ib !== $normBrand) && !isset($seenCross[$ck])) {
                $seenCross[$ck] = true;
                $crossPairs[$ck] = [
                    'brand_orig'   => (string)($it['brand'] ?? ''),
                    'article_orig' => (string)($it['article'] ?? ''),
                    'brand_norm'   => $ib,
                    'article_norm' => $ia,
                ];
            }
        }
    }
    curl_multi_close($mh);

    // --- 2. Кроссы у всех поставщиков (передаём ОРИГИНАЛЬНЫЕ бренд+артикул) ---
    $analogGroups = [];

    if (!empty($crossPairs)) {
        $mh2 = curl_multi_init();
        $h2 = [];
        $crReqs = [];

        foreach ($crossPairs as $ck => $pair) {
            foreach ($suppliers as $code => $connector) {
                // Передаём оригинальные значения в API
                $req = $connector->buildSearchRequest($pair['article_orig'], $pair['brand_orig']);
                if (!$req) continue;
                $reqKey = $code . '|' . $ck;
                $crReqs[$reqKey] = ['req' => $req, 'supplier' => $connector, 'pair' => $pair];
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $req['url'], CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $req['headers'], CURLOPT_TIMEOUT => 6,
                    CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_ENCODING => '',
                ]);
                if (($req['method'] ?? 'GET') === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                }
                curl_multi_add_handle($mh2, $ch);
                $h2[$reqKey] = $ch;
            }
        }

        $running = null;
        do { curl_multi_exec($mh2, $running); curl_multi_select($mh2, 0.1); } while ($running > 0);

        foreach ($h2 as $reqKey => $ch) {
            $body = curl_multi_getcontent($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh2, $ch);
            curl_close($ch);
            if ($http !== 200 || !$body) continue;

            $pair = $crReqs[$reqKey]['pair'];
            $gk = $pair['brand_norm'] . '|' . $pair['article_norm'];

            try {
                $items = $crReqs[$reqKey]['supplier']->parseSearchResponse($body);
            } catch (\Throwable $e) { continue; }

            foreach ($items as $it) {
                $ia = BrandNormalizer::normalizeArticle((string)($it['article'] ?? ''));
                $ib = BrandNormalizer::normalize((string)($it['brand'] ?? ''));
                if ($ia !== $pair['article_norm'] || $ib !== $pair['brand_norm']) continue;

                if (!isset($analogGroups[$gk])) {
                    $analogGroups[$gk] = [
                        'brand_orig'   => $pair['brand_orig'],
                        'article_orig' => $pair['article_orig'],
                        'description'  => (string)($it['description'] ?? ''),
                        'offers'       => [],
                    ];
                }
                $desc = (string)($it['description'] ?? '');
                if (mb_strlen($desc) > mb_strlen($analogGroups[$gk]['description'])) {
                    $analogGroups[$gk]['description'] = $desc;
                }
                $analogGroups[$gk]['offers'][] = normalizeOffer($it, $code);
            }
        }
        curl_multi_close($mh2);
    }

    // --- 3. Формируем ответ ---
    $resp = [];

    if (!empty($exactOffers)) {
        usort($exactOffers, function($a, $b) {
            if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
            return $a['delivery_days'] - $b['delivery_days'];
        });
        $resp['exact'] = [
            'brand'     => $brandOrig,
            'article'   => $numberOrig,
            'suppliers' => $exactOffers,
        ];
    }

    $analogs = [];
    foreach ($analogGroups as $gk => $grp) {
        $offers = $grp['offers'];
        $prices = array_column($offers, 'price');
        $days   = array_column($offers, 'delivery_days');
        $qtys   = array_column($offers, 'quantity');
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

// ═══════════════════ Хелпер ═══════════════════
function normalizeOffer(array $item, string $supplierCode): array {
    return [
        'supplier'      => $supplierCode,
        'warehouse'     => (string)($item['warehouse'] ?? ''),
        'price'         => (float)($item['price'] ?? 0),
        'quantity'      => (int)($item['quantity'] ?? 0),
        'delivery_days' => (int)($item['delivery_days'] ?? -1),
    ];
}