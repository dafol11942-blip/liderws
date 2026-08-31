<?php
// search/ajax.php v29 — кэш b_search_offer_cache для crossload (все поставщики на аналог + скорость без лимита пар)
ini_set('display_errors', 0);
set_time_limit(120);

// ═══ PROGRESS — лёгкий путь БЕЗ Bitrix bootstrap ═══
// Опрашивается каждые 700мс с фронта; полный bitrix prolog_before.php здесь не нужен
// и раньше создавал конкуренцию за PHP-FPM воркеры с самим поиском/crossload.
if (($_GET['action'] ?? '') === 'progress') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    $taskId = trim($_GET['task'] ?? '');
    $f = sys_get_temp_dir() . '/srch_' . preg_replace('/[^a-f0-9]/', '', $taskId) . '.json';
    if (!$taskId) { echo json_encode(['percent' => 0, 'message' => 'Нет задачи', 'done' => false]); exit; }
    if (file_exists($f)) { readfile($f); } else { echo json_encode(['percent' => 0, 'message' => 'Ожидание...', 'done' => false]); }
    exit;
}

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
use Bitrix\Main\Application;

// Кэш b_search_offer_cache используется ТОЛЬКО как «негативный» список: кого из
// поставщиков недавно уже спрашивали по этой паре и у него ничего не нашлось —
// таких не спрашиваем повторно. Цена/остаток НИКОГДА не берутся из кэша —
// это интернет-магазин, на каждый поиск нужны живые данные. Своя таблица —
// НЕ b_supplier_stock, там у старых систем (parts-search/, analog_search.php v5)
// разные и несовместимые ожидания по колонкам (last_updated vs source_updated).
const SEARCH_CACHE_SKIP_TTL_HOURS = 1;
// Верхняя граница числа кросс-пар за один поиск (защита от аномально длинных списков кроссов).
// Раньше здесь был жёсткий cross_count=30, из-за которого часть аналогов не докручивалась вовсе —
// теперь ограничение снято до разумного потолка, а не влияет на скорость благодаря кэшу ниже.
const MAX_ANALOG_PAIRS = 80;
// Эти поставщики таймаутили при живом кросс-поиске (with_crosses=true) прямо в Phase1,
// поэтому в Phase1 их спрашивают только на точное совпадение. Их СОБСТВЕННЫЙ список кроссов
// узнаём отдельно в crossload (фон, не блокирует первый экран) — см. ниже "discovery".
const NO_CROSS_DISCOVERY_SUPPLIERS = ['autoeuro', 'ixora', 'tatparts', 'autopiter'];

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

// ═══ КЭШ ОТВЕТОВ ПОСТАВЩИКОВ (b_search_offer_cache) ═══
// Ключ — (supplier_code, brand_norm, article_norm). Хранит либо реальные офферы,
// либо «сентинел»-строку (quantity=-1) — значит поставщика уже спрашивали и у него пусто.
// Это позволяет НЕ спрашивать поставщика живьём на каждый повторный поиск той же пары.

function cacheDb() {
    return Application::getConnection();
}

/**
 * Возвращает только «негативный» список: кого не нужно спрашивать живьём,
 * потому что недавно (в пределах $ttlHours) у него уже точно ничего не было.
 * Никакие цены/остатки отсюда не берутся — только сигнал «пропустить запрос».
 *
 * @param array $pairs [['brand_norm'=>..,'article_norm'=>..], ...]
 * @return array<string,bool> "supplier|brand|article" => true
 */
function cacheSkipList(array $pairs, int $ttlHours): array {
    if (empty($pairs)) return [];
    $db = cacheDb();
    $helper = $db->getSqlHelper();
    $conds = [];
    $seen = [];
    foreach ($pairs as $p) {
        $k = $p['brand_norm'] . '|' . $p['article_norm'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $conds[] = "(brand_norm='" . $helper->forSql($p['brand_norm']) . "' AND article_norm='" . $helper->forSql($p['article_norm']) . "')";
    }
    if (empty($conds)) return [];

    $ttlHours = max(1, $ttlHours);
    $sql = "SELECT supplier_code, brand_norm, article_norm
            FROM b_search_offer_cache
            WHERE quantity = -1 AND updated_at > (NOW() - INTERVAL {$ttlHours} HOUR) AND (" . implode(' OR ', $conds) . ")";

    $skip = [];
    try {
        $rows = $db->query($sql)->fetchAll();
    } catch (\Throwable $e) {
        ajaxLog('CACHE skiplist error: ' . $e->getMessage());
        return [];
    }
    foreach ($rows as $row) {
        $skip[$row['supplier_code'] . '|' . $row['brand_norm'] . '|' . $row['article_norm']] = true;
    }
    return $skip;
}

/**
 * Сохраняет живой ответ поставщика по паре в кэш.
 * $offers может быть пустым массивом — тогда пишется сентинел (значит поставщика спросили, офферов нет).
 */
function cacheSave(string $supplierCode, string $brandNorm, string $articleNorm, array $offers): void {
    $db = cacheDb();
    $helper = $db->getSqlHelper();
    try {
        if (empty($offers)) {
            $sql = "INSERT INTO b_search_offer_cache
                    (supplier_code, brand_norm, article_norm, warehouse, name, description, price, quantity, delivery_days, updated_at)
                    VALUES ('" . $helper->forSql($supplierCode) . "','" . $helper->forSql($brandNorm) . "','" . $helper->forSql($articleNorm) . "','','','',0,-1,-1,NOW())
                    ON DUPLICATE KEY UPDATE quantity = -1, updated_at = NOW()";
            $db->query($sql);
            return;
        }
        $values = [];
        foreach ($offers as $o) {
            $values[] = "('" . $helper->forSql($supplierCode) . "','" . $helper->forSql($brandNorm) . "','" . $helper->forSql($articleNorm) . "','"
                . $helper->forSql(mb_substr((string)($o['warehouse'] ?? ''), 0, 190)) . "','"
                . $helper->forSql(mb_substr((string)($o['name'] ?? ''), 0, 255)) . "','"
                . $helper->forSql(mb_substr((string)($o['description'] ?? ''), 0, 500)) . "',"
                . (float)($o['price'] ?? 0) . "," . (int)($o['quantity'] ?? 0) . "," . (int)($o['delivery_days'] ?? -1) . ",NOW())";
        }
        $sql = "INSERT INTO b_search_offer_cache
                (supplier_code, brand_norm, article_norm, warehouse, name, description, price, quantity, delivery_days, updated_at)
                VALUES " . implode(',', $values) . "
                ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description),
                    quantity=VALUES(quantity), delivery_days=VALUES(delivery_days), updated_at=NOW()";
        $db->query($sql);
    } catch (\Throwable $e) {
        ajaxLog('CACHE save error: ' . $e->getMessage());
    }
}

// ═══ CROSSLOAD — Phase 2: прямой поиск brand+article из Phase 1 ═══
if ($action === 'crossload') {
    progWrite($taskId, 1, 'Начинаем докрутку аналогов...');
    $crossJson = trim($_REQUEST['crossPairs'] ?? '');
    if ($crossJson === '') { echo json_encode(['done' => true, 'analog_offers' => []]); exit; }
    $crossPairs = json_decode($crossJson, true);
    if (!is_array($crossPairs) || empty($crossPairs)) {
        echo json_encode(['done' => true, 'analog_offers' => []]);
        exit;
    }
    $crossPairs = array_slice($crossPairs, 0, MAX_ANALOG_PAIRS, true);

    ajaxLog("CROSSLOAD START task=$taskId pairs=" . count($crossPairs));

    $analogOffers   = [];
    $newAnalogsMeta = []; // gk => {brand,article,description} — новые карточки для фронтенда

    // ═══ DISCOVERY: у поставщиков, исключённых из Phase1-кросс-поиска (таймаутили с
    // with_crosses=true), спрашиваем живьём их СОБСТВЕННЫЙ список кроссов по исходному
    // запросу. Это единственный способ узнать про аналоги, которых 6 «быстрых»
    // поставщиков в своей базе кроссов не знают — раньше эти 4 поставщика ТОЛЬКО
    // добирали склады по уже найденным кем-то другим парам и сами не могли предложить
    // ничего нового, отсюда и заниженное общее число аналогов.
    $brandOrig  = trim($_REQUEST['brand'] ?? '');
    $numberOrig = trim($_REQUEST['number'] ?? '');
    if ($brandOrig !== '' && $numberOrig !== '') {
        $discReqs = [];
        foreach (NO_CROSS_DISCOVERY_SUPPLIERS as $code) {
            if (!isset($suppliers[$code])) continue;
            $req = $suppliers[$code]->buildSearchRequest($brandOrig, $numberOrig, true);
            if ($req) $discReqs[$code] = $req;
        }
        if (!empty($discReqs)) {
            $tDisc = microtime(true);
            $discResponses = curlExec($suppliers, $discReqs, 15.0);
            $newPairs = 0;
            foreach ($discResponses as $code => $body) {
                if (!$body) continue;
                try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
                catch (\Throwable $e) { continue; }
                foreach ($items as $it) {
                    if (count($crossPairs) >= MAX_ANALOG_PAIRS) break;
                    $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
                    $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
                    $gk = $ib . '|' . $ia;
                    if (isset($crossPairs[$gk])) continue; // уже знаем эту пару от других поставщиков
                    $crossPairs[$gk] = [
                        'brand_orig'   => (string)($it->brand ?? ''),
                        'article_orig' => (string)($it->article ?? ''),
                        'brand_norm'   => $ib,
                        'article_norm' => $ia,
                        '_from'        => [$code],
                    ];
                    $newAnalogsMeta[$gk] = [
                        'brand'       => (string)($it->brand ?? ''),
                        'article'     => (string)($it->article ?? ''),
                        'description' => (string)($it->description ?? $it->name ?? ''),
                    ];
                    $analogOffers[$gk][] = [
                        'supplier'      => $code,
                        'warehouse'     => (string)($it->warehouse ?? ''),
                        'name'          => (string)($it->name ?? ''),
                        'description'   => (string)($it->name ?? $it->description ?? ''),
                        'price'         => (float)($it->price ?? 0),
                        'quantity'      => (int)($it->quantity ?? 0),
                        'delivery_days' => (int)($it->deliveryDays ?? -1),
                    ];
                    $newPairs++;
                }
            }
            ajaxLog("CROSSLOAD discovery: " . count($discReqs) . " req, +{$newPairs} новых аналогов за " . round(microtime(true) - $tDisc, 2) . "s");
        }
    }

    // ═══ Кэш: кого из поставщиков НЕ спрашиваем живьём (недавно точно пусто) ═══
    // Цену/остаток отсюда не берём никогда — только пропуск заведомо пустых.
    $lookupPairs = [];
    foreach ($crossPairs as $pair) {
        $lookupPairs[] = ['brand_norm' => $pair['brand_norm'], 'article_norm' => $pair['article_norm']];
    }
    $skipList = cacheSkipList($lookupPairs, SEARCH_CACHE_SKIP_TTL_HOURS);
    ajaxLog("CROSSLOAD cache: " . count($skipList) . " supplier×pair пропущено (недавно подтверждённо пусто)");

    // Прямой поиск: brand_orig + article_orig у каждого поставщика — только для того,
    // чего нет ни в Phase1 (_from), ни в недавнем «пусто» из кэша. Цена/остаток — ВСЕГДА живые.
    $allReqs = [];
    $reqInfo = [];

    foreach ($crossPairs as $ck => $pair) {
        $skip = $pair['_from'] ?? [];
        foreach ($suppliers as $code => $c) {
            if (in_array($code, $skip, true)) continue;
            if (isset($skipList[$code . '|' . $ck])) continue;
            $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig'], false);
            if ($req) {
                $key = $code . '|' . $ck;
                $allReqs[$key] = $req;
                $reqInfo[$key] = [$code, $ck];
            }
        }
    }

    ajaxLog("CROSSLOAD search requests=" . count($allReqs) . " (после вычета Phase1 и заведомо пустых)");
    $perPairReqs = [];
    foreach ($allReqs as $k => $v) {
        $ck = explode('|', $k)[1] ?? '?';
        $perPairReqs[$ck] = ($perPairReqs[$ck] ?? 0) + 1;
    }
    ajaxLog("CROSSLOAD reqs_per_pair: " . count($perPairReqs) . " pairs, top5=" . json_encode(array_slice($perPairReqs, 0, 5, true)));
    $t0 = microtime(true);
    // Один параллельный пул вместо ручных «волн»: curl сам держит не больше MAX_PER_HOST
    // одновременных соединений на поставщика (CURLMOPT_MAX_HOST_CONNECTIONS) и тут же
    // подхватывает следующий запрос к тому же хосту, как только освобождается слот.
    // Волны заставляли ВСЕ хосты синхронно ждать самого медленного в каждой волне —
    // это и была основная причина «медленно при снятии лимитов».
    $MAX_PER_HOST = 6;
    progWrite($taskId, 10, "Докручиваем аналоги: опрашиваем " . count($allReqs) . " предложений...");
    $responses = curlExec($suppliers, $allReqs, 25.0, $MAX_PER_HOST);
    ajaxLog("CROSSLOAD done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));
    progWrite($taskId, 90, 'Обрабатываем ответы поставщиков...');
    $perPairResps = [];
    foreach ($responses as $k => $v) {
        if (!$v) continue;
        $ck = explode('|', $k)[1] ?? '?';
        $perPairResps[$ck] = ($perPairResps[$ck] ?? 0) + 1;
    }
    ajaxLog("CROSSLOAD resps_per_pair: " . count($perPairResps) . " pairs, top5=" . json_encode(array_slice($perPairResps, 0, 5, true)));

    // Парсим и группируем (в $analogOffers уже могут быть офферы первооткрывателей из discovery выше)
    $suppStats = [];
    $toCache = []; // "$code|$ck" => offers[] — что реально спросили живьём (даже пустой ответ)

    foreach ($responses as $respKey => $body) {
        $info = $reqInfo[$respKey] ?? null;
        if (!$info) continue;
        [$code, $ck] = $info;

        if (!isset($suppStats[$code])) $suppStats[$code] = [0, 0, 0];
        $suppStats[$code][0]++;

        if (!$body) continue; // нет ответа/таймаут — в кэш не пишем, попробуем в следующий поиск

        $pair = $crossPairs[$ck] ?? null;
        if (!$pair) continue;

        try {
            $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']);
        } catch (\Throwable $e) { continue; }

        $gk = $ck; // ck уже равен brand_norm|article_norm
        $toCache[$code . '|' . $ck] = []; // отмечаем: живой ответ получен, даже если ниже 0 совпадений
        $added = 0;
        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            if ($ia !== $pair['article_norm']) continue;
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
            if (!brandsMatch($ib, $pair['brand_norm'])) continue; // не пускаем чужой бренд в группу аналога

            $suppStats[$code][1]++;
            $added++;

            $offer = [
                'supplier'      => $code,
                'warehouse'     => (string)($it->warehouse ?? ''),
                'name'          => (string)($it->name ?? ''),
                'description'   => (string)($it->name ?? $it->description ?? ''),
                'price'         => (float)($it->price ?? 0),
                'quantity'      => (int)($it->quantity ?? 0),
                'delivery_days' => (int)($it->deliveryDays ?? -1),
            ];
            if (!isset($analogOffers[$gk])) $analogOffers[$gk] = [];
            $analogOffers[$gk][] = $offer;
            $toCache[$code . '|' . $ck][] = $offer;
        }
        $suppStats[$code][2] += $added;
    }

    // Сохраняем в кэш то, что реально спросили живьём сейчас — включая пустые (сентинел)
    foreach ($toCache as $cacheKey => $offers) {
        [$code, $ck] = explode('|', $cacheKey, 2);
        $pair = $crossPairs[$ck] ?? null;
        if (!$pair) continue;
        cacheSave($code, $pair['brand_norm'], $pair['article_norm'], $offers);
    }

    $statsLines = [];
    foreach ($suppStats as $code => $st) {
        $statsLines[] = "$code:{$st[0]}req/{$st[1]}pass/{$st[2]}add";
    }
    ajaxLog("CROSSLOAD STATS " . implode(' | ', $statsLines) . " | skipped=" . count($skipList));

    foreach ($analogOffers as $gk => &$offers) {
        usort($offers, function($a, $b) {
            if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
            return $a['delivery_days'] - $b['delivery_days'];
        });
    }

    progWrite($taskId, 100, 'Докрутка завершена');
    echo json_encode(['done' => true, 'analog_offers' => $analogOffers, 'new_analogs' => $newAnalogsMeta], JSON_UNESCAPED_UNICODE);
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
    foreach ($suppliers as $code => $c) {
        $reqExact = $c->buildSearchRequest($brandOrig, $numberOrig, false);
        if ($reqExact) $r1Reqs['exact|' . $code] = $reqExact;
        $withCrosses = !in_array($code, NO_CROSS_DISCOVERY_SUPPLIERS, true);
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
    $toCachePhase1 = []; // "$code|$gk" => offers[] — кросс-офферы, найденные живьём в Phase1, тоже кладём в кэш
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
                $offerRow = [
                    'supplier'      => $code,
                    'warehouse'     => (string)($it->warehouse ?? ''),
                    'name'          => (string)($it->name ?? ''),
                    'description'   => (string)($it->name ?? $it->description ?? ''),
                    'price'         => (float)($it->price ?? 0),
                    'quantity'      => (int)($it->quantity ?? 0),
                    'delivery_days' => (int)($it->deliveryDays ?? -1),
                ];
                $analogGroups[$gk]['offers'][] = $offerRow;
                $toCachePhase1[$code . '|' . $gk][] = $offerRow;
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

    // Кросс-офферы, живьём найденные в Phase1, тоже кладём в кэш — будущие поиски
    // с пересекающимся набором аналогов смогут не спрашивать этих поставщиков заново.
    foreach ($toCachePhase1 as $cacheKey => $offers) {
        [$code, $gk] = explode('|', $cacheKey, 2);
        // $gk уже равен "brand_norm|article_norm" (см. построение $gk выше в цикле 2)
        [$bn, $an] = array_pad(explode('|', $gk, 2), 2, '');
        if ($bn === '' || $an === '') continue;
        cacheSave($code, $bn, $an, $offers);
    }

    $seenExact = [];
    $uniqueExact = [];
    foreach ($exactOffers as $o) {
        $key = $o['supplier'] . '|' . $o['warehouse'] . '|' . $o['price'];
        if (!isset($seenExact[$key])) { $seenExact[$key] = true; $uniqueExact[] = $o; }
    }
    $exactOffers = $uniqueExact;

    $crossPairs = array_slice($crossPairs, 0, MAX_ANALOG_PAIRS, true);
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