<?php
// search/ajax.php v27 — фикс normNum + cross| only во втором цикле
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

function brandsMatch(string $a, string $b): bool {
    if ($a === $b) return true;
    $lenA = mb_strlen($a);
    $lenB = mb_strlen($b);
    if ($lenA < 4 || $lenB < 4) return false;
    return mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false;
}

function curlExec(array $suppliers, array $requests, float $deadline = 15.0, int $maxPerHost = 0): array {
    if (empty($requests)) return [];
    $mh = curl_multi_init();
    if ($maxPerHost > 0 && defined('CURLMOPT_MAX_HOST_CONNECTIONS')) {
        curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, $maxPerHost);
    }
    $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $req['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
            CURLOPT_TIMEOUT        => 12,
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
        $results[$key] = ($http === 200 && $body && strlen($body) > 10) ? $body : null;
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
    if (file_exists($f)) { readfile($f); } else { echo json_encode(['percent' => 0, 'message' => 'Ожидание...', 'done' => false]); }
    exit;
}

// ═══ CROSSLOAD — Phase 2: прямой поиск brand+article из Phase 1 ═══
if ($action === 'crossload') {
    $crossJson = trim($_REQUEST['crossPairs'] ?? '');
    if ($crossJson === '') { echo json_encode(['done' => true, 'analog_offers' => []]); exit; }
    $crossPairs = json_decode($crossJson, true);
    if (!is_array($crossPairs) || empty($crossPairs)) {
        echo json_encode(['done' => true, 'analog_offers' => []]);
        exit;
    }

    ajaxLog("CROSSLOAD START task=$taskId pairs=" . count($crossPairs));

    // Прямой поиск: brand_orig + article_orig у каждого поставщика
    $allReqs = [];
    $reqInfo = [];

    foreach ($crossPairs as $ck => $pair) {
        $skip = $pair['_from'] ?? [];
        foreach ($suppliers as $code => $c) {
            if (in_array($code, $skip, true)) continue;
            $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig'], false);
            if ($req) {
                $key = $code . '|' . $ck;
                $allReqs[$key] = $req;
                $reqInfo[$key] = [$code, $ck];
            }
        }
    }

    ajaxLog("CROSSLOAD search requests=" . count($allReqs));
    $perPairReqs = [];
    foreach ($allReqs as $k => $v) {
        $ck = explode('|', $k)[1] ?? '?';
        $perPairReqs[$ck] = ($perPairReqs[$ck] ?? 0) + 1;
    }
    ajaxLog("CROSSLOAD reqs_per_pair: " . count($perPairReqs) . " pairs, top5=" . json_encode(array_slice($perPairReqs, 0, 5, true)));
    $t0 = microtime(true);
    // Волновой чанкинг: группируем запросы по поставщику (хосту) и формируем волны так,
    // чтобы в каждой волне было не больше MAX_PER_HOST запросов к одному поставщику одновременно.
    // Это устраняет очередь на стороне libcurl/поставщика вместо угадывания размера чанка вслепую.
    $MAX_PER_HOST = 6;
    $bySupplier = [];
    foreach ($allReqs as $key => $req) {
        $code = $reqInfo[$key][0];
        $bySupplier[$code][] = $key;
    }
    $maxWaves = 0;
    foreach ($bySupplier as $code => $keys) {
        $maxWaves = max($maxWaves, (int)ceil(count($keys) / $MAX_PER_HOST));
    }
    progWrite($taskId, 10, "Докручиваем аналоги: 0/$maxWaves волн");
    $responses = [];
    for ($w = 0; $w < $maxWaves; $w++) {
        $wave = [];
        foreach ($bySupplier as $code => $keys) {
            $slice = array_slice($keys, $w * $MAX_PER_HOST, $MAX_PER_HOST);
            foreach ($slice as $key) { $wave[$key] = $allReqs[$key]; }
        }
        if (empty($wave)) continue;
        $tc = microtime(true);
        $partial = curlExec($suppliers, $wave, 15.0, $MAX_PER_HOST);
        $responses += $partial;
        ajaxLog("CROSSLOAD wave " . ($w + 1) . "/" . $maxWaves
            . " requests=" . count($wave)
            . " responses=" . count(array_filter($partial))
            . " time=" . round(microtime(true) - $tc, 2) . "s");
        $pct = 10 + (int)round(85 * ($w + 1) / $maxWaves);
        progWrite($taskId, $pct, "Докручиваем аналоги: " . ($w + 1) . "/$maxWaves волн");
    }
    ajaxLog("CROSSLOAD done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));
    progWrite($taskId, 100, 'Докрутка завершена');
    $perPairResps = [];
    foreach ($responses as $k => $v) {
        if (!$v) continue;
        $ck = explode('|', $k)[1] ?? '?';
        $perPairResps[$ck] = ($perPairResps[$ck] ?? 0) + 1;
    }
    ajaxLog("CROSSLOAD resps_per_pair: " . count($perPairResps) . " pairs, top5=" . json_encode(array_slice($perPairResps, 0, 5, true)));

    // Парсим и группируем
    $analogOffers = [];
    $suppStats = [];

    foreach ($responses as $respKey => $body) {
        $info = $reqInfo[$respKey] ?? null;
        if (!$info) continue;
        [$code, $ck] = $info;

        if (!isset($suppStats[$code])) $suppStats[$code] = [0, 0, 0];
        $suppStats[$code][0]++;

        if (!$body) continue;

        $pair = $crossPairs[$ck] ?? null;
        if (!$pair) continue;

        try {
            $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']);
        } catch (\Throwable $e) { continue; }

        $gk = $pair['brand_norm'] . '|' . $pair['article_norm'];
        $added = 0;
        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            if ($ia !== $pair['article_norm']) continue;

            $suppStats[$code][1]++;
            $added++;

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
        $suppStats[$code][2] += $added;
    }

    $statsLines = [];
    foreach ($suppStats as $code => $st) {
        $statsLines[] = "$code:{$st[0]}req/{$st[1]}pass/{$st[2]}add";
    }
    ajaxLog("CROSSLOAD STATS " . implode(' | ', $statsLines));

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

// ═══ SEARCH — Phase 1 ═══
} elseif ($action === 'search') {

    $brandOrig  = trim($_GET['brand'] ?? '');
    $numberOrig = trim($_GET['number'] ?? $_GET['article'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }

    if (!$taskId) {
        $taskId = md5($article . $brandOrig . time() . rand());
    }

    ajaxLog("PHASE1 START task=$taskId article=$article brand=$brandOrig");
    $tTotal = microtime(true);

    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    progWrite($taskId, 5, 'Запрашиваем поставщиков...');

    // exact| = запрос без кроссов, cross| = запрос с кроссами
    $r1Reqs = [];
    $noCrossSuppliers = ['autoeuro', 'ixora', 'tatparts', 'autopiter'];
    foreach ($suppliers as $code => $c) {
        $reqExact = $c->buildSearchRequest($brandOrig, $numberOrig, false);
        if ($reqExact) $r1Reqs['exact|' . $code] = $reqExact;
        $withCrosses = !in_array($code, $noCrossSuppliers, true);
        $reqCross = $c->buildSearchRequest($brandOrig, $numberOrig, $withCrosses);
        if ($reqCross) $r1Reqs['cross|' . $code] = $reqCross;
    }

    $t0 = microtime(true);
    $responses = curlExec($suppliers, $r1Reqs, 15.0);
    ajaxLog("PHASE1 R1 done in " . round(microtime(true) - $t0, 2) . "s requests=" . count($r1Reqs) . " responses=" . count(array_filter($responses)));
    progWrite($taskId, 75, 'Обрабатываем ответы поставщиков...');

    $exactOffers  = [];
    $crossPairs   = [];
    $seenCross    = [];
    $analogGroups = [];
    $seenCross[$normBrand . '|' . $normNum] = true;

    // ── Цикл 1: exact| ответы → только точные совпадения ──
    foreach ($responses as $respKey => $body) {
        if (!$body) continue;
        if (strpos($respKey, 'exact|') !== 0) continue;
        $code = substr($respKey, 6);

        try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
        catch (\Throwable $e) { continue; }
        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
            if ($ia !== $normNum) continue;
            if (!brandsMatch($ib, $normBrand)) continue;
            $exactOffers[] = [
                'supplier'      => $code,
                'warehouse'     => (string)($it->warehouse ?? ''),
                'name'          => (string)($it->name ?? ''),
                'description'   => (string)($it->description ?? $it->name ?? ''),
                'price'         => (float)($it->price ?? 0),
                'quantity'      => (int)($it->quantity ?? 0),
                'delivery_days' => (int)($it->deliveryDays ?? -1),
            ];
        }
    }

    // ── Цикл 2: cross| ответы → точные + аналоги ──
    foreach ($responses as $respKey => $body) {
        if (!$body) continue;
        if (strpos($respKey, 'cross|') !== 0) continue;
        $code = substr($respKey, 6);

        try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
        catch (\Throwable $e) { continue; }
        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
            $gk = $ib . '|' . $ia;

            if ($ia === $normNum && brandsMatch($ib, $normBrand)) {
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

    $seenExact = [];
    $uniqueExact = [];
    foreach ($exactOffers as $o) {
        $key = $o['supplier'] . '|' . $o['warehouse'] . '|' . $o['price'];
        if (!isset($seenExact[$key])) { $seenExact[$key] = true; $uniqueExact[] = $o; }
    }
    $exactOffers = $uniqueExact;

    $crossPairs = array_slice($crossPairs, 0, 30, true);
    $crossCount = count($crossPairs);

    progWrite($taskId, 100, 'Готово');

    ajaxLog("PHASE1 done task=$taskId crossPairs=$crossCount exact=" . count($exactOffers) . " analogs=" . count($analogGroups) . " time=" . round(microtime(true) - $tTotal, 2) . "s");

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
            'key'           => $gk,
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
    $resp['crossPairs']  = $crossPairs;

    $totalTime = round(microtime(true) - $tTotal, 2);
    ajaxLog("PHASE1 RESPOND task=$taskId time={$totalTime}s");

    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}