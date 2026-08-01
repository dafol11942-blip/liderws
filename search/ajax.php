<?php
// search/ajax.php v12 — офферы аналогов сразу из Round 1 + сессия разблокирована
ini_set('display_errors', 0);
set_time_limit(120);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// ⚡ Снять блокировку сессии — иначе progress-запросы висят
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

if (!$article && $action !== 'progress') {
    echo json_encode(['error' => 'Укажите артикул']);
    exit;
}

$normArt  = $article ? BrandNormalizer::normalizeArticle($article) : '';
$factory  = getSupplierFactory();
$suppliers = $factory->allAvailable();

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

function progFile($taskId) {
    return sys_get_temp_dir() . '/srch_' . preg_replace('/[^a-f0-9]/', '', $taskId) . '.json';
}
function progWrite($taskId, $pct, $msg, $done = false, $result = null) {
    $data = ['percent' => (int)$pct, 'message' => $msg, 'done' => $done];
    if ($result !== null) $data['result'] = $result;
    file_put_contents(progFile($taskId), json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ═══ PROGRESS ═══
if ($action === 'progress') {
    $task = trim($_GET['task'] ?? '');
    if (!$task) { echo json_encode(['percent' => 0, 'message' => 'Нет задачи', 'done' => false]); exit; }
    $f = progFile($task);
    if (file_exists($f)) { readfile($f); } else { echo json_encode(['percent' => 0, 'message' => 'Ожидание...', 'done' => false]); }
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

    $taskId = md5($article . $brandOrig . time() . rand());
    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    progWrite($taskId, 5, 'Запрашиваем точное совпадение у ' . count($suppliers) . ' поставщиков...');

    // ═══ ROUND 1: точное совпадение + сразу собираем офферы аналогов ═══
    $exactReqs = [];
    foreach ($suppliers as $code => $c) {
        $req = $c->buildSearchRequest($brandOrig, $numberOrig, true);
        if ($req) $exactReqs[$code] = $req;
    }
    $responses = curlExec($suppliers, $exactReqs);

    progWrite($taskId, 20, 'Обрабатываем ответы, собираем кросс-номера и их остатки...');

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
                // Точное совпадение
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
                // Аналог — сохраняем оффер СРАЗУ, не дожидаясь Round 2
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

            // Трекинг кросс-пар для Round 2 (кто уже отдал этот кросс)
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
                // Этот кросс уже был от другого поставщика — дополняем _from
                if (isset($crossPairs[$gk]) && !in_array($code, $crossPairs[$gk]['_from'])) {
                    $crossPairs[$gk]['_from'][] = $code;
                }
            }
        }
    }

    // Оставляем первые 15 кросс-пар для Round 2
    $crossPairs = array_slice($crossPairs, 0, 15, true);
    $crossCount = count($crossPairs);

    progWrite($taskId, 35, 'Найдено ' . $crossCount . ' кросс-номеров (часть остатков уже получена). Запрашиваем цены у остальных...');

    // ═══ ROUND 2: дозапрос кроссов только у поставщиков, которые ещё не отдали ═══
    if (!empty($crossPairs)) {
        $pairN = 0;
        foreach ($crossPairs as $ck => $pair) {
            $pairN++;
            $skipSuppliers = $pair['_from'] ?? [];

            $crReqs = [];
            foreach ($suppliers as $code => $c) {
                if (in_array($code, $skipSuppliers, true)) continue;
                $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig']);
                if ($req) $crReqs[$code] = $req;
            }

            // Все поставщики уже отдали этот кросс в Round 1 — пропускаем
            if (empty($crReqs)) {
                continue;
            }

            $pct = 50 + (int)(30 * $pairN / max($crossCount, 1));
            progWrite($taskId, $pct, 'Аналог ' . $pairN . '/' . $crossCount . ': ' . $pair['brand_orig'] . ' ' . $pair['article_orig'] . ' (' . count($crReqs) . ' поставщиков)');

            $crResponses = curlExec($suppliers, $crReqs);

            foreach ($crResponses as $reqKey => $body) {
                if (!$body) continue;
                $code = $reqKey;
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
                        'name'          => (string)($it->name ?? ''),
                        'description'   => (string)($it->name ?? $it->description ?? ''),
                        'price'         => (float)($it->price ?? 0),
                        'quantity'      => (int)($it->quantity ?? 0),
                        'delivery_days' => (int)($it->deliveryDays ?? -1),
                    ];
                }
            }
        }
    }

    progWrite($taskId, 80, 'Обработано ' . count($analogGroups) . ' аналогов. Сортируем...');

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
        $days = array_column($offers, 'delivery_days');
        $qtys = array_column($offers, 'quantity');
        $activePrices = array_filter($prices, function($p) { return $p > 0; });
        $activeDays = array_filter($days, function($d) { return $d >= 0; });
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
    progWrite($taskId, 100, 'Готово', true, $resp);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}