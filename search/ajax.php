<?php
// search/ajax.php v19 — двухфазная загрузка: Phase 1 сразу + Phase 2 добор 30 кросс-пар
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

if (!$article && $action !== 'progress' && $action !== 'crossload') {
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
function progRead($taskId): ?array {
    $f = progFile($taskId);
    if (!file_exists($f)) return null;
    $raw = @file_get_contents($f);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    // Поднимаем поля result на верхний уровень
    if (isset($data['result']) && is_array($data['result'])) {
        unset($data['result']);
        $data = array_merge($data, $data['result']);
    }
    return $data;
}

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/search_ajax.log';
function ajaxLog($msg) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ═══ PROGRESS ═══
if ($action === 'progress') {
    if (!$taskId) { echo json_encode(['percent' => 0, 'message' => 'Нет задачи', 'done' => false]); exit; }
    $d = progRead($taskId);
    echo json_encode($d ?: ['percent' => 0, 'message' => 'Ожидание...', 'done' => false]);
    exit;
}

// ═══ CROSSLOAD — Phase 2: добор кросс-номеров ═══
if ($action === 'crossload') {
    if (!$taskId) { echo json_encode(['error' => 'Укажите task']); exit; }

    $state = progRead($taskId);
    if (!$state || empty($state['crossPairs'])) {
        echo json_encode(['done' => true, 'analog_offers' => []]);
        exit;
    }

    $crossPairs = $state['crossPairs'];
    ajaxLog("CROSSLOAD START task=$taskId pairs=" . count($crossPairs));

    $allReqs = [];
    foreach ($crossPairs as $ck => $pair) {
        $skip = $pair['_from'] ?? [];
        foreach ($suppliers as $code => $c) {
            if (in_array($code, $skip, true)) continue;
            $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig']);
            if ($req) $allReqs[$code . '|' . $ck] = $req;
        }
    }

    ajaxLog("CROSSLOAD requests=" . count($allReqs));
    $t0 = microtime(true);
    $responses = curlExec($suppliers, $allReqs, 12.0);
    ajaxLog("CROSSLOAD done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));

    $analogOffers = [];
    foreach ($responses as $respKey => $body) {
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

            if (!isset($analogOffers[$gk])) $analogOffers[$gk] = [];
            $analogOffers[$gk][] = [
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

    foreach ($analogOffers as $gk => &$offers) {
        usort($offers, function($a, $b) {
            if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
            return $a['delivery_days'] - $b['delivery_days'];
        });
    }

    echo json_encode(['done' => true, 'analog_offers' => $analogOffers], JSON_UNESCAPED_UNICODE);
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

// ═══ SEARCH — Phase 1: только Round 1 ═══
} elseif ($action === 'search') {

    $brandOrig  = trim($_GET['brand'] ?? '');
    $numberOrig = trim($_GET['number'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }

    if (!$taskId) {
        $taskId = md5($article . $brandOrig . time() . rand());
    }

    ajaxLog("PHASE1 START task=$taskId article=$article brand=$brandOrig");
    $tTotal = microtime(true);

    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    progWrite($taskId, 5, 'Запрашиваем точное совпадение...');

    // ═══ ROUND 1 ═══
    $exactReqs = [];
    foreach ($suppliers as $code => $c) {
        $req = $c->buildSearchRequest($brandOrig, $numberOrig, true);
        if ($req) $exactReqs[$code] = $req;
    }

    $t0 = microtime(true);
    $responses = curlExec($suppliers, $exactReqs, 12.0);
    ajaxLog("PHASE1 R1 done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));

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

    // 30 кросс-пар для Phase 2
    $crossPairs = array_slice($crossPairs, 0, 30, true);
    $crossCount = count($crossPairs);

    progWrite($taskId, 40, 'Загружаем аналоги...', false, ['crossPairs' => $crossPairs]);

    ajaxLog("PHASE1 done task=$taskId crossPairs=$crossCount exact=" . count($exactOffers) . " analogs=" . count($analogGroups) . " time=" . round(microtime(true) - $tTotal, 2) . "s");

    // ═══ BUILD PHASE 1 RESPONSE ═══
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

    $resp['analogs']     = $analogs;
    $resp['task_id']     = $taskId;
    $resp['phase']       = 1;
    $resp['cross_count'] = $crossCount;

    $totalTime = round(microtime(true) - $tTotal, 2);
    ajaxLog("PHASE1 RESPOND task=$taskId time={$totalTime}s");

    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}