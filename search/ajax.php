<?php
// search/ajax.php v16 — одиночный чанк Round 2 + полные таймауты 10с + 15 кросс-пар
ini_set('display_errors', 0);
set_time_limit(120);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
use Lider\Search\BrandNormalizer;

$action  = $_GET['action'] ?? '';
$article = trim($_GET['article'] ?? '');
$taskId  = trim($_GET['task'] ?? '');

if (!$article && $action !== 'progress') {
    echo json_encode(['error' => 'Укажите артикул']);
    exit;
}

$normArt  = $article ? BrandNormalizer::normalizeArticle($article) : '';
$factory  = getSupplierFactory();
$suppliers = $factory->allAvailable();

function curlExec(array $suppliers, array $requests, float $deadline = 15.0): array {
    if (empty($requests)) return [];
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $req['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
            CURLOPT_TIMEOUT        => 10,
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
    $start = microtime(true);
    do {
        curl_multi_exec($mh, $running);
        if ($running > 0) curl_multi_select($mh, 0.1);
        if (microtime(true) - $start > $deadline) break;
    } while ($running > 0);
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

function progFile($taskId) {
    return sys_get_temp_dir() . '/srch_' . preg_replace('/[^a-f0-9]/', '', $taskId) . '.json';
}
function progWrite($taskId, $pct, $msg, $done = false, $result = null) {
    $data = ['percent' => (int)$pct, 'message' => $msg, 'done' => $done];
    if ($result !== null) $data['result'] = $result;
    @file_put_contents(progFile($taskId), json_encode($data, JSON_UNESCAPED_UNICODE));
}

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/search_ajax.log';
function ajaxLog($msg) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ═══ PROGRESS ═══
if ($action === 'progress') {
    if (!$taskId) { echo json_encode(['percent' => 0, 'message' => 'Нет задачи', 'done' => false]); exit; }
    $f = progFile($taskId);
    if (file_exists($f)) { readfile($f); } else { echo json_encode(['percent' => 0, 'message' => 'Ожидание запуска...', 'done' => false]); }
    exit;
}

// ═══ BRANDS ═══
if ($action === 'brands') {

    $arrFilter = [['LOGIC' => 'OR',
        ['%NAME' => $article], ['PROPERTY_CML2_ARTICLE' => $article],
        ['%PROPERTY_CML2_ARTICLE' => $article], ['%DETAIL_TEXT' => $article],
        ['PROPERTY_CML2_MANUFACTURER' => $article], ['%PROPERTY_CML2_MANUFACTURER' => $article],
    ]];
    $localRes   = CIBlockElement::GetList([], array_merge(['IBLOCK_ID' => 42, 'ACTIVE' => 'Y'], $arrFilter[0]), false, false, ['ID']);
    $localCount = $localRes->SelectedRowsCount();

    $brandReqs = [];
    foreach ($suppliers as $code => $c) {
        $req = $c->buildBrandsRequest($article);
        if ($req) $brandReqs[$code] = $req;
    }
    $responses = curlExec($suppliers, $brandReqs);

    $allRaw = [];
    foreach ($responses as $code => $body) {
        if (!$body) continue;
        try {
            $items = $suppliers[$code]->parseBrandsResponse($body, $article);
            foreach ($items as $it) { $it['source'] = $code; $allRaw[] = $it; }
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
        $db = BrandNormalizer::displayBrand(reset($info['brands']));
        $da = BrandNormalizer::pickDisplayArticle($info['articles'], '');
        $isExact = (BrandNormalizer::normalizeArticle($da) === $normArt);
        $brands[] = [
            'brand'       => $db,
            'article'     => $da,
            'description' => $info['description'],
            'sources'     => array_values($info['sources']),
            'type'        => $isExact ? 'exact' : 'analog',
        ];
    }
    usort($brands, function($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'exact' ? -1 : 1;
        return count($b['sources']) - count($a['sources']);
    });

    echo json_encode([
        'brands'      => $brands,
        'local_count' => $localCount,
        'article'     => $article,
    ], JSON_UNESCAPED_UNICODE);

// ═══ SEARCH ═══
} elseif ($action === 'search') {

    $brandOrig  = trim($_GET['brand'] ?? '');
    $numberOrig = trim($_GET['number'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }

    if (!$taskId) {
        $taskId = md5($article . $brandOrig . time() . rand());
    }

    ajaxLog("SEARCH START task=$taskId article=$article brand=$brandOrig");
    $tTotal = microtime(true);

    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    progWrite($taskId, 5, 'Запрашиваем точное совпадение у ' . count($suppliers) . ' поставщиков...');

    // ═══ ROUND 1 ═══
    $exactReqs = [];
    foreach ($suppliers as $code => $c) {
        $req = $c->buildSearchRequest($brandOrig, $numberOrig, true);
        if ($req) $exactReqs[$code] = $req;
    }

    $t0 = microtime(true);
    $responses = curlExec($suppliers, $exactReqs, 15.0);
    ajaxLog("ROUND1 done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));

    progWrite($taskId, 20, 'Обрабатываем ответы...');

    $exactOffers  = [];
    $crossPairs   = [];
    $seenCross    = [];
    $analogGroups = [];
    $seenCross[$normBrand . '|' . $normNum] = true;

    foreach ($responses as $code => $body) {
        if (!$body) continue;
        try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
        catch (\Throwable $e) { continue; }
        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
            $gk = $ib . '|' . $ia;

            if ($ia === $normNum && $ib === $normBrand) {
                $exactOffers[] = [
                    'supplier'      => $code,
                    'warehouse'     => (string)($it->warehouse ?? ''),
                    'name'          => (string)($it->name ?? ''),
                    'description'   => (string)($it->description ?? $it->name ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            } else {
                if (!isset($analogGroups[$gk])) {
                    $analogGroups[$gk] = [
                        'brand_orig'   => (string)($it->brand ?? ''),
                        'article_orig' => (string)($it->article ?? ''),
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
                    'name'          => (string)($it->name ?? ''),
                    'description'   => (string)($it->name ?? $it->description ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            }

            if (!isset($seenCross[$gk])) {
                $seenCross[$gk] = true;
                $crossPairs[$gk] = [
                    'brand_orig'   => (string)($it->brand ?? ''),
                    'article_orig' => (string)($it->article ?? ''),
                    'brand_norm'   => $ib,
                    'article_norm' => $ia,
                    '_from'        => [$code],
                ];
            } else {
                if (isset($crossPairs[$gk]) && !in_array($code, $crossPairs[$gk]['_from'])) {
                    $crossPairs[$gk]['_from'][] = $code;
                }
            }
        }
    }

    $crossPairs = array_slice($crossPairs, 0, 15, true);
    $crossCount = count($crossPairs);
    ajaxLog("ROUND1 crossPairs=$crossCount exactOffers=" . count($exactOffers) . " analogGroups=" . count($analogGroups));

    // ═══ ROUND 2: ВСЕ кроссы ОДНИМ чанком ═══
    if (!empty($crossPairs)) {
        progWrite($taskId, 35, 'Запрашиваем остатки для ' . $crossCount . ' аналогов у ' . count($suppliers) . ' поставщиков...');

        $allCrossReqs = [];
        foreach ($crossPairs as $ck => $pair) {
            $skipSuppliers = $pair['_from'] ?? [];
            foreach ($suppliers as $code => $c) {
                if (in_array($code, $skipSuppliers, true)) continue;
                $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig']);
                if ($req) {
                    $allCrossReqs[$code . '|' . $ck] = $req;
                }
            }
        }

        ajaxLog("ROUND2 totalRequests=" . count($allCrossReqs) . " (single chunk)");
        $t2 = microtime(true);

        $allCrossResponses = curlExec($suppliers, $allCrossReqs, 15.0);

        $round2Time = round(microtime(true) - $t2, 2);
        ajaxLog("ROUND2 done in {$round2Time}s responses=" . count(array_filter($allCrossResponses)));

        progWrite($taskId, 70, 'Обрабатываем ' . count(array_filter($allCrossResponses)) . ' ответов по кроссам...');

        foreach ($allCrossResponses as $respKey => $body) {
            if (!$body) continue;
            $parts = explode('|', $respKey, 2);
            if (count($parts) < 2) continue;
            $code = $parts[0];
            $ck   = $parts[1];

            $pair = $crossPairs[$ck] ?? null;
            if (!$pair) continue;

            try {
                $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']);
            } catch (\Throwable $e) { continue; }

            $gk = $pair['brand_norm'] . '|' . $pair['article_norm'];

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
                    'name'          => (string)($it->name ?? ''),
                    'description'   => (string)($it->name ?? $it->description ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
            }
        }
    }

    progWrite($taskId, 80, 'Сортируем...');

    // ═══ BUILD RESPONSE ═══
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
        $days   = array_column($offers, 'delivery_days');
        $qtys   = array_column($offers, 'quantity');
        $activePrices = array_filter($prices, function($p) { return $p > 0; });
        $activeDays   = array_filter($days, function($d) { return $d >= 0; });
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
        $dA = $a['best_delivery'] ?? 999; $dB = $b['best_delivery'] ?? 999;
        if ($dA !== $dB) return $dA - $dB;
        return $a['best_price'] - $b['best_price'];
    });

    $resp['analogs'] = $analogs;
    $resp['task_id'] = $taskId;

    $totalTime = round(microtime(true) - $tTotal, 2);
    ajaxLog("SEARCH DONE task=$taskId exact=" . count($exactOffers) . " analogs=" . count($analogs) . " totalTime={$totalTime}s");

    progWrite($taskId, 100, 'Готово', true, $resp);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}