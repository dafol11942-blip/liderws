<?php
// search/worker.php — фоновый поиск
if ($argc < 2) exit(1);

$params = json_decode($argv[1], true);
if (!$params || empty($params['brand']) || empty($params['article']) || empty($params['taskId'])) exit(1);

$brandOrig  = $params['brand'];
$numberOrig = $params['article'];
$taskId     = $params['taskId'];

$_SERVER['DOCUMENT_ROOT'] = ($params['docRoot'] ?? '/var/www/u3564357/data/www/liderws.ru');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
use Lider\Search\BrandNormalizer;

$factory  = getSupplierFactory();
$suppliers = $factory->allAvailable();

function curlExec(array $suppliers, array $requests): array {
    if (empty($requests)) return [];
    $mh = curl_multi_init(); $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $req['url'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $req['headers'] ?? [], CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        curl_multi_add_handle($mh, $ch); $handles[$key] = $ch;
    }
    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);
    $results = [];
    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
        $results[$key] = ($http === 200 && $body) ? $body : null;
    }
    curl_multi_close($mh);
    return $results;
}

function progFile($taskId) {
    return sys_get_temp_dir() . '/srch_' . preg_replace('/[^a-f0-9]/', '', $taskId) . '.json';
}
function progWrite($taskId, $pct, $msg, $done = false, $result = null) {
    file_put_contents(progFile($taskId), json_encode([
        'percent' => (int)$pct, 'message' => $msg, 'done' => $done,
    ] + ($result !== null ? ['result' => $result] : []), JSON_UNESCAPED_UNICODE));
}

$normBrand = BrandNormalizer::normalize($brandOrig);
$normNum   = BrandNormalizer::normalizeArticle($numberOrig);

progWrite($taskId, 5, 'Запрашиваем точное совпадение у ' . count($suppliers) . ' поставщиков...');

// 1. Exact
$exactReqs = [];
foreach ($suppliers as $code => $c) {
    $req = $c->buildSearchRequest($brandOrig, $numberOrig, true);
    if ($req) $exactReqs[$code] = $req;
}
$responses = curlExec($suppliers, $exactReqs);
progWrite($taskId, 20, 'Анализируем результаты точного поиска...');

$exactOffers = [];
$crossPairs  = [];
$seenCross   = [];
$seenCross[$normBrand . '|' . $normNum] = true;

foreach ($responses as $code => $body) {
    if (!$body) continue;
    try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
    catch (\Throwable $e) { continue; }
    foreach ($items as $it) {
        $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
        $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
        if ($ia === $normNum && $ib === $normBrand) {
            $exactOffers[] = [
                'supplier' => $code, 'warehouse' => (string)($it->warehouse ?? ''),
                'name' => (string)($it->name ?? ''),
                'description' => (string)($it->description ?? $it->name ?? ''),
                'price' => (float)($it->price ?? 0),
                'quantity' => (int)($it->quantity ?? 0),
                'delivery_days' => (int)($it->deliveryDays ?? -1),
            ];
        }
        $ck = $ib . '|' . $ia;
        if (!isset($seenCross[$ck])) {
            $seenCross[$ck] = true;
            $crossPairs[$ck] = [
                'brand_orig' => (string)($it->brand ?? ''),
                'article_orig' => (string)($it->article ?? ''),
                'brand_norm' => $ib, 'article_norm' => $ia,
            ];
        }
    }
}

$crossPairs = array_slice($crossPairs, 0, 15, true);
progWrite($taskId, 35, 'Найдено ' . count($crossPairs) . ' кросс-номеров. Запрашиваем цены...');

// 2. Crosses
$analogGroups = [];
if (!empty($crossPairs)) {
    $crossChunks = array_chunk($crossPairs, 15, true);
    $chunkTotal = count($crossChunks); $chunkN = 0;
    foreach ($crossChunks as $chunkPairs) {
        $chunkN++;
        $crReqs = [];
        foreach ($chunkPairs as $ck => $pair) {
            foreach ($suppliers as $code => $c) {
                $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig']);
                if ($req) $crReqs[$code . '|' . $ck] = $req;
            }
        }
        $pct = 50 + (int)(30 * $chunkN / max($chunkTotal, 1));
        progWrite($taskId, $pct, 'Аналоги ' . $chunkN . '/' . $chunkTotal . ' (' . count($crReqs) . ' запросов)...');
        $crResponses = curlExec($suppliers, $crReqs);
        foreach ($crResponses as $reqKey => $body) {
            if (!$body) continue;
            $parts = explode('|', $reqKey, 2); $code = $parts[0]; $ck = $parts[1] ?? '';
            $pair = $chunkPairs[$ck] ?? null;
            if (!$pair) continue;
            $gk = $pair['brand_norm'] . '|' . $pair['article_norm'];
            try { $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']); }
            catch (\Throwable $e) { continue; }
            foreach ($items as $it) {
                $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
                $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
                if ($ia !== $pair['article_norm'] || $ib !== $pair['brand_norm']) continue;
                if (!isset($analogGroups[$gk])) {
                    $analogGroups[$gk] = ['brand_orig' => $pair['brand_orig'], 'article_orig' => $pair['article_orig'],
                        'description' => (string)($it->description ?? ''), 'offers' => []];
                }
                $desc = (string)($it->description ?? '');
                if (mb_strlen($desc) > mb_strlen($analogGroups[$gk]['description'])) {
                    $analogGroups[$gk]['description'] = $desc;
                }
                $analogGroups[$gk]['offers'][] = [
                    'supplier' => $code, 'warehouse' => (string)($it->warehouse ?? ''),
                    'name' => (string)($it->name ?? ''),
                    'description' => (string)($it->name ?? $it->description ?? ''),
                    'price' => (float)($it->price ?? 0),
                    'quantity' => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            }
        }
    }
}
progWrite($taskId, 80, 'Обработано ' . count($analogGroups) . ' аналогов...');
progWrite($taskId, 90, 'Формируем результат...');

// 3. Build
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
    $offers = $grp['offers'];
    usort($offers, function($a, $b) {
        if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
        return $a['delivery_days'] - $b['delivery_days'];
    });
    $prices = array_column($offers, 'price');
    $days = array_column($offers, 'delivery_days');
    $qtys = array_column($offers, 'quantity');
    $activePrices = array_filter($prices, function($p) { return $p > 0; });
    $activeDays = array_filter($days, function($d) { return $d >= 0; });
    $analogs[] = [
        'brand' => $grp['brand_orig'], 'article' => $grp['article_orig'],
        'description' => $grp['description'],
        'best_price' => !empty($activePrices) ? min($activePrices) : 0,
        'best_delivery' => !empty($activeDays) ? min($activeDays) : null,
        'total_qty' => array_sum($qtys),
        'has_instock' => count(array_filter($qtys, function($q) { return $q > 0; })) > 0,
        'suppliers' => $offers,
    ];
}
usort($analogs, function($a, $b) {
    if ($a['has_instock'] !== $b['has_instock']) return $b['has_instock'] - $a['has_instock'];
    $dA = $a['best_delivery'] ?? 999; $dB = $b['best_delivery'] ?? 999;
    if ($dA !== $dB) return $dA - $dB;
    return $a['best_price'] - $b['best_price'];
});
$resp['analogs'] = $analogs;
$resp['task_id'] = $taskId;
progWrite($taskId, 100, 'Готово', true, $resp);