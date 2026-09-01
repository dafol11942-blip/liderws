<?php
/**
 * search/ajax.php v30 — оркестрация поиска у поставщиков, переписана с нуля.
 *
 * ЦЕЛЬ: по запросу brand+article отдать (1) ВСЕ предложения точного совпадения от
 * ВСЕХ поставщиков и (2) для КАЖДОГО найденного аналога — тоже предложения ВСЕХ
 * поставщиков, которые его продают. Цена/остаток всегда живые (магазин), никогда
 * не отдаются из кэша.
 *
 * Опросить сразу всех поставщиков по всем аналогам синхронно нельзя — при 10+
 * аналогах это сотни HTTP-запросов и десятки секунд. Поэтому 2 фазы:
 *
 *   PHASE 1 (action=search, синхронно, ~10-15с):
 *     - точный запрос ко ВСЕМ поставщикам;
 *     - у поставщиков, которые умеют отдавать кроссы одним ответом, — ещё и
 *       кросс-запрос (это и есть ОБНАРУЖЕНИЕ аналогов: их бренды+артикулы
 *       складываются в crossPairs). Отдаётся сразу — пользователь видит результат.
 *
 *   PHASE 2 (action=crossload, фон, вызывается фронтендом сразу после Phase 1):
 *     - DISCOVERY: поставщики, которых нет в Phase 1 (см. NO_CROSS_DISCOVERY_SUPPLIERS),
 *       спрашиваются на СВОЙ список кроссов — иначе аналоги, известные только им,
 *       не появятся вообще;
 *     - COVERAGE: для каждой найденной пары (включая только что открытые) —
 *       запрос ко ВСЕМ поставщикам, которые её ещё не подтвердили в Phase 1.
 *
 * Три особенности поставщиков, выясненные на живых логах — их нарушение молча
 * возвращает 0 результатов, поэтому держим их явно как константы, а не забываем:
 *   - NO_CROSS_DISCOVERY_SUPPLIERS: медленно/нестабильно отвечают на массовый
 *     кросс-запрос (with_crosses=true) внутри Phase 1 — там их не трогаем.
 *   - RATE_SENSITIVE_SUPPLIERS: у них анти-бот/rate-limit защита — залп из 6+
 *     одновременных запросов (Phase 2 всегда бьёт залпом) роняет ответы в 0,
 *     единичные запросы работают нормально. Идут отдельным пулом с limit=1.
 *   - brandsMatch(): бренд у аналога почти никогда не совпадает дословно с
 *     искомым (MANN vs MANN-FILTER) — точное сравнение строк здесь неверно.
 *
 * Кэш (b_search_offer_cache) — ТОЛЬКО короткий негативный список «этого у
 * поставщика точно нет» (см. cacheSkipList/cacheSave). Он не хранит цену для
 * показа пользователю и не может её вернуть — только экономит время на заведомо
 * пустых запросах при повторной докрутке.
 */

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

// ═══════════════════════════ КОНФИГ ═══════════════════════════

// Сколько часов доверяем "негативному" кэшу (b_search_offer_cache, quantity=-1).
const SEARCH_CACHE_SKIP_TTL_HOURS = 1;

// Верхняя граница числа кросс-пар (аналогов) за один поиск — защита от аномально
// длинных списков, не влияет на скорость (см. кэш + fast/slow пулы ниже).
const MAX_ANALOG_PAIRS = 80;

// Не умеют/нестабильно умеют отдавать кроссы ОДНИМ массовым запросом внутри
// Phase 1 (with_crosses=true) — там их спрашивают только на точное совпадение.
// Их собственный список кроссов узнаём отдельно в Phase 2 (блок DISCOVERY в crossload).
const NO_CROSS_DISCOVERY_SUPPLIERS = ['autoeuro', 'ixora', 'tatparts', 'autopiter'];

// Роняют ответ в 0 при параллельном залпе из нескольких запросов (анти-бот/rate-limit
// по IP на их стороне) — Phase 2 всегда бьёт залпом по всем парам сразу, поэтому эти
// поставщики идут отдельным пулом с не более чем 1 одновременным соединением.
const RATE_SENSITIVE_SUPPLIERS = ['moskvorechie', 'partkom', 'ixora', 'autoruss'];

$action  = $_GET['action'] ?? '';
$article = trim($_GET['article'] ?? '');
$taskId  = trim($_GET['task'] ?? '');

if (!$article && $action !== 'progress' && $action !== 'crossload') {
    echo json_encode(['error' => 'Укажите артикул']);
    exit;
}

$normArt   = $article ? BrandNormalizer::normalizeArticle($article) : '';
$factory   = getSupplierFactory();
$suppliers = $factory->allAvailable();

// ═══════════════════════════ ОБЩИЕ УТИЛИТЫ ═══════════════════════════

/** MANN vs MANN-FILTER и т.п. — у аналога бренд почти никогда не совпадает дословно. */
function brandsMatch(string $a, string $b): bool {
    if ($a === $b) return true;
    $lenA = mb_strlen($a);
    $lenB = mb_strlen($b);
    if ($lenA < 4 || $lenB < 4) return false;
    return mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false;
}

/** Нормализованный артикул+бренд ответа совпадают с искомой парой? */
function itemMatchesPair($it, string $normBrand, string $normArticle): bool {
    if (BrandNormalizer::normalizeArticle((string)($it->article ?? '')) !== $normArticle) return false;
    return brandsMatch(BrandNormalizer::normalize((string)($it->brand ?? '')), $normBrand);
}

/**
 * Строка предложения для фронтенда. $preferDescription — для точного совпадения
 * приоритет description (собственное поле поставщика для найденного товара),
 * для аналогов — приоритет name (там обычно понятнее написан сам товар).
 */
function offerRow(string $code, $it, bool $preferDescription = false): array {
    $name = (string)($it->name ?? '');
    $desc = (string)($it->description ?? '');
    return [
        'supplier'      => $code,
        'warehouse'     => (string)($it->warehouse ?? ''),
        'name'          => $name,
        'description'   => $preferDescription ? ($desc ?: $name) : ($name ?: $desc),
        'price'         => (float)($it->price ?? 0),
        'quantity'      => (int)($it->quantity ?? 0),
        'delivery_days' => (int)($it->deliveryDays ?? -1),
    ];
}

function sortOffers(array &$offers): void {
    usort($offers, function ($a, $b) {
        if ($a['price'] != $b['price']) return $a['price'] - $b['price'];
        return $a['delivery_days'] - $b['delivery_days'];
    });
}

/** Параллельный пул запросов. $maxPerHost=0 — без ограничения соединений на хост. */
function curlExec(array $requests, float $deadline = 15.0, int $maxPerHost = 0): array {
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

/**
 * Как curlExec(), но RATE_SENSITIVE_SUPPLIERS уходят отдельным пулом с
 * limit=1 соединение на хост — общий залп им нельзя (см. константу выше).
 * $codeOf($key) должна вернуть код поставщика по ключу запроса.
 */
function curlExecSplit(array $requests, callable $codeOf, float $fastDeadline = 25.0, float $slowDeadline = 20.0, int $fastPerHost = 6): array {
    $fast = [];
    $slow = [];
    foreach ($requests as $key => $req) {
        if (in_array($codeOf($key), RATE_SENSITIVE_SUPPLIERS, true)) { $slow[$key] = $req; }
        else { $fast[$key] = $req; }
    }
    $responses = curlExec($fast, $fastDeadline, $fastPerHost);
    if (!empty($slow)) {
        $responses += curlExec($slow, $slowDeadline, 1);
    }
    return $responses;
}

function progFile($taskId) {
    return sys_get_temp_dir() . '/srch_' . preg_replace('/[^a-f0-9]/', '', $taskId) . '.json';
}
function progWrite($taskId, $pct, $msg): void {
    @file_put_contents(progFile($taskId), json_encode(
        ['percent' => (int)$pct, 'message' => $msg, 'done' => false],
        JSON_UNESCAPED_UNICODE
    ));
}

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/search_ajax.log';
function ajaxLog($msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ═══════════════════════════ КЭШ (только негативный список) ═══════════════════════════
// b_search_offer_cache: (supplier_code, brand_norm, article_norm) → офферы ИЛИ
// сентинел (quantity=-1) "проверено, пусто". Цена/остаток из кэша НИКОГДА не
// показываются пользователю — только сигнал "не спрашивать живьём повторно".

function cacheDb() {
    return Application::getConnection();
}

/** @return array<string,bool> "supplier|brand_norm|article_norm" => true (пропустить живой запрос) */
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

/** $offers = [] → пишет сентинел (поставщика спросили живьём, у него пусто). */
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

// ═══════════════════════════ ACTION: crossload (Phase 2) ═══════════════════════════

if ($action === 'crossload') {
    progWrite($taskId, 1, 'Начинаем докрутку аналогов...');

    $crossJson = trim($_REQUEST['crossPairs'] ?? '');
    $crossPairs = $crossJson !== '' ? json_decode($crossJson, true) : null;
    if (!is_array($crossPairs)) $crossPairs = [];
    $crossPairs = array_slice($crossPairs, 0, MAX_ANALOG_PAIRS, true);

    $brandOrig  = trim($_REQUEST['brand'] ?? '');
    $numberOrig = trim($_REQUEST['number'] ?? '');
    $normBrand  = BrandNormalizer::normalize($brandOrig);
    $normNum    = BrandNormalizer::normalizeArticle($numberOrig);

    ajaxLog("CROSSLOAD START task=$taskId pairs=" . count($crossPairs));

    $analogOffers   = [];  // gk => [offer, ...]
    $newAnalogsMeta = [];  // gk => {brand, article, description} — новые карточки для фронтенда
    $exactOffersD   = [];  // доп. предложения ИСКОМОГО товара, найденные при discovery (не аналоги)

    // ── DISCOVERY: поставщики вне Phase1-кросс-поиска (NO_CROSS_DISCOVERY_SUPPLIERS)
    // спрашиваются на СВОЙ список кроссов по исходному запросу — иначе аналоги,
    // известные только им, вообще никогда не попадут в выдачу.
    if ($brandOrig !== '' && $numberOrig !== '') {
        $discReqs = [];
        foreach (NO_CROSS_DISCOVERY_SUPPLIERS as $code) {
            if (!isset($suppliers[$code])) continue;
            $req = $suppliers[$code]->buildSearchRequest($brandOrig, $numberOrig, true);
            if ($req) $discReqs[$code] = $req;
        }
        if (!empty($discReqs)) {
            $tDisc = microtime(true);
            $discResponses = curlExec($discReqs, 15.0);
            $newPairs = 0;
            foreach ($discResponses as $code => $body) {
                if (!$body) continue;
                try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
                catch (\Throwable $e) { continue; }
                foreach ($items as $it) {
                    if (count($crossPairs) >= MAX_ANALOG_PAIRS) break;
                    $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
                    $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));

                    // Свой список кроссов у поставщика обычно включает и сам искомый товар
                    // (просто с другим написанием бренда, напр. MANN вместо MANN-FILTER) —
                    // это доп. предложение искомого, а не отдельный "аналог самому себе".
                    if ($ia === $normNum && brandsMatch($ib, $normBrand)) {
                        $exactOffersD[] = offerRow($code, $it, true);
                        continue;
                    }

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
                    $analogOffers[$gk][] = offerRow($code, $it);
                    $newPairs++;
                }
            }
            ajaxLog("CROSSLOAD discovery: " . count($discReqs) . " req, +{$newPairs} новых аналогов за " . round(microtime(true) - $tDisc, 2) . "s");
        }
    }

    if (empty($crossPairs)) {
        progWrite($taskId, 100, 'Докрутка завершена');
        echo json_encode(['done' => true, 'analog_offers' => $analogOffers, 'new_analogs' => $newAnalogsMeta, 'exact_offers' => $exactOffersD], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Кэш: кого не спрашиваем живьём (недавно точно пусто). Цена/остаток отсюда
    // не берутся никогда — только пропуск заведомо пустых пар.
    $lookupPairs = array_map(
        fn($p) => ['brand_norm' => $p['brand_norm'], 'article_norm' => $p['article_norm']],
        $crossPairs
    );
    $skipList = cacheSkipList($lookupPairs, SEARCH_CACHE_SKIP_TTL_HOURS);
    ajaxLog("CROSSLOAD cache: " . count($skipList) . " supplier×pair пропущено (недавно подтверждённо пусто)");

    // ── COVERAGE: brand_orig+article_orig у КАЖДОГО поставщика, который ещё не
    // подтвердил эту пару (ни в Phase1 через _from, ни только что в discovery).
    $allReqs = [];
    $reqInfo = []; // key => [code, ck]
    foreach ($crossPairs as $ck => $pair) {
        $already = $pair['_from'] ?? [];
        foreach ($suppliers as $code => $c) {
            if (in_array($code, $already, true)) continue;
            if (isset($skipList[$code . '|' . $ck])) continue;
            $req = $c->buildSearchRequest($pair['brand_orig'], $pair['article_orig'], false);
            if (!$req) continue;
            $key = $code . '|' . $ck;
            $allReqs[$key] = $req;
            $reqInfo[$key] = [$code, $ck];
        }
    }
    ajaxLog("CROSSLOAD requests=" . count($allReqs) . " (после вычета Phase1 и заведомо пустых)");

    $t0 = microtime(true);
    progWrite($taskId, 10, "Докручиваем аналоги: опрашиваем " . count($allReqs) . " предложений...");
    // Один параллельный пул вместо ручных "волн" — curl сам держит лимит соединений
    // на хост и тут же подхватывает следующий запрос, не дожидаясь остальных хостов.
    // RATE_SENSITIVE_SUPPLIERS — отдельно, см. curlExecSplit().
    $responses = curlExecSplit($allReqs, fn($key) => $reqInfo[$key][0]);
    ajaxLog("CROSSLOAD done in " . round(microtime(true) - $t0, 2) . "s responses=" . count(array_filter($responses)));
    progWrite($taskId, 90, 'Обрабатываем ответы поставщиков...');

    // ── Разбор ответов ──
    $suppStats = []; // code => [req, pass, add]
    $toCache   = []; // "code|ck" => offers[] — что реально спросили живьём сейчас

    foreach ($responses as $respKey => $body) {
        [$code, $ck] = $reqInfo[$respKey] ?? [null, null];
        if ($code === null) continue;

        $suppStats[$code] ??= [0, 0, 0];
        $suppStats[$code][0]++;

        if (!$body) continue; // нет ответа/таймаут — в кэш не пишем, попробуем в следующий поиск

        $pair = $crossPairs[$ck] ?? null;
        if (!$pair) continue;

        try {
            $items = $suppliers[$code]->parseSearchResponse($body, $pair['brand_orig'], $pair['article_orig']);
        } catch (\Throwable $e) { continue; }

        $toCache[$code . '|' . $ck] = []; // живой ответ получен, даже если ниже 0 совпадений — это факт
        $added = 0;
        foreach ($items as $it) {
            if (!itemMatchesPair($it, $pair['brand_norm'], $pair['article_norm'])) continue;
            $offer = offerRow($code, $it);
            $analogOffers[$ck][] = $offer;
            $toCache[$code . '|' . $ck][] = $offer;
            $added++;
        }
        $suppStats[$code][1] += $added;
        $suppStats[$code][2] += $added;
    }

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

    foreach ($analogOffers as &$offers) {
        sortOffers($offers);
    }
    unset($offers);

    progWrite($taskId, 100, 'Докрутка завершена');
    echo json_encode(['done' => true, 'analog_offers' => $analogOffers, 'new_analogs' => $newAnalogsMeta, 'exact_offers' => $exactOffersD], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════ ACTION: brands ═══════════════════════════

if ($action === 'brands') {

    // ВАЖНО: LOGIC=>OR должен остаться ВЛОЖЕННЫМ подмассивом, а не слитым в один
    // уровень с IBLOCK_ID/ACTIVE через array_merge — иначе весь фильтр становится
    // "IBLOCK_ID=42 ИЛИ ACTIVE=Y ИЛИ ..." и возвращает почти весь каталог.
    $localFilter = [
        'IBLOCK_ID' => 42,
        'ACTIVE'    => 'Y',
        ['LOGIC' => 'OR',
            ['%NAME' => $article], ['PROPERTY_CML2_ARTICLE' => $article],
            ['%PROPERTY_CML2_ARTICLE' => $article], ['%DETAIL_TEXT' => $article],
            ['PROPERTY_CML2_MANUFACTURER' => $article], ['%PROPERTY_CML2_MANUFACTURER' => $article],
        ],
    ];
    $localRes   = CIBlockElement::GetList([], $localFilter, false, false, ['ID']);
    $localCount = $localRes->SelectedRowsCount();

    $brandReqs = [];
    foreach ($suppliers as $code => $c) {
        $req = $c->buildBrandsRequest($article);
        if ($req) $brandReqs[$code] = $req;
    }
    $responses = curlExec($brandReqs);

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
        $brandMap[$key]['brands'][$br['source']]   = $b;
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
    usort($brands, function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'exact' ? -1 : 1;
        return count($b['sources']) - count($a['sources']);
    });

    echo json_encode([
        'brands'      => $brands,
        'local_count' => $localCount,
        'article'     => $article,
    ], JSON_UNESCAPED_UNICODE);

// ═══════════════════════════ ACTION: search (Phase 1) ═══════════════════════════

} elseif ($action === 'search') {

    $brandOrig  = trim($_GET['brand'] ?? '');
    $numberOrig = trim($_GET['number'] ?? $_GET['article'] ?? '');
    if (!$brandOrig) { echo json_encode(['error' => 'Укажите бренд']); exit; }
    if (!$taskId) { $taskId = md5($article . $brandOrig . time() . rand()); }

    ajaxLog("PHASE1 START task=$taskId article=$article brand=$brandOrig");
    $tTotal = microtime(true);

    $normBrand = BrandNormalizer::normalize($brandOrig);
    $normNum   = BrandNormalizer::normalizeArticle($numberOrig);

    progWrite($taskId, 5, 'Запрашиваем поставщиков...');

    // "exact|" — без кроссов (точное совпадение), "cross|" — с кроссами (обнаружение
    // аналогов). Оба запроса нужны от каждого поставщика: exact| даёт максимум
    // предложений искомого товара, cross| — то же самое ПЛЮС список аналогов.
    $r1Reqs = [];
    foreach ($suppliers as $code => $c) {
        $reqExact = $c->buildSearchRequest($brandOrig, $numberOrig, false);
        if ($reqExact) $r1Reqs['exact|' . $code] = $reqExact;

        $withCrosses = !in_array($code, NO_CROSS_DISCOVERY_SUPPLIERS, true);
        $reqCross = $c->buildSearchRequest($brandOrig, $numberOrig, $withCrosses);
        if ($reqCross) $r1Reqs['cross|' . $code] = $reqCross;
    }

    $t0 = microtime(true);
    $responses = curlExec($r1Reqs, 15.0);
    ajaxLog("PHASE1 done in " . round(microtime(true) - $t0, 2) . "s requests=" . count($r1Reqs) . " responses=" . count(array_filter($responses)));
    progWrite($taskId, 75, 'Обрабатываем ответы поставщиков...');

    $exactOffers  = [];
    $analogGroups = []; // gk => {brand_orig, article_orig, description, offers[]}
    $crossPairs   = []; // gk => {brand_orig, article_orig, brand_norm, article_norm, _from[]}
    $seenCross    = [$normBrand . '|' . $normNum => true]; // сам искомый товар — не аналог себе

    // Цикл 1 — exact|: только точные совпадения, максимум предложений искомого.
    foreach ($responses as $respKey => $body) {
        if (!$body || strpos($respKey, 'exact|') !== 0) continue;
        $code = substr($respKey, 6);
        try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
        catch (\Throwable $e) { continue; }
        foreach ($items as $it) {
            if (!itemMatchesPair($it, $normBrand, $normNum)) continue;
            $exactOffers[] = offerRow($code, $it, true);
        }
    }

    // Цикл 2 — cross|: точные совпадения (доп. подстраховка) + обнаружение аналогов.
    $toCachePhase1 = []; // "code|gk" => offers[] — сразу кладём в кэш, экономит будущим поискам
    foreach ($responses as $respKey => $body) {
        if (!$body || strpos($respKey, 'cross|') !== 0) continue;
        $code = substr($respKey, 6);
        try { $items = $suppliers[$code]->parseSearchResponse($body, $brandOrig, $numberOrig); }
        catch (\Throwable $e) { continue; }

        foreach ($items as $it) {
            $ia = BrandNormalizer::normalizeArticle((string)($it->article ?? ''));
            $ib = BrandNormalizer::normalize((string)($it->brand ?? ''));
            $gk = $ib . '|' . $ia;

            if ($ia === $normNum && brandsMatch($ib, $normBrand)) {
                $exactOffers[] = offerRow($code, $it, true);
            } else {
                if (!isset($analogGroups[$gk])) {
                    $analogGroups[$gk] = [
                        'brand_orig'   => (string)($it->brand ?? ''),
                        'article_orig' => (string)($it->article ?? ''),
                        'description'  => '',
                        'offers'       => [],
                    ];
                }
                $desc = (string)($it->description ?? '');
                if (mb_strlen($desc) > mb_strlen($analogGroups[$gk]['description'])) {
                    $analogGroups[$gk]['description'] = $desc;
                }
                $offer = offerRow($code, $it);
                $analogGroups[$gk]['offers'][] = $offer;
                $toCachePhase1[$code . '|' . $gk][] = $offer;
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
            } elseif (isset($crossPairs[$gk]) && !in_array($code, $crossPairs[$gk]['_from'], true)) {
                $crossPairs[$gk]['_from'][] = $code;
            }
        }
    }

    // Кросс-офферы, найденные живьём в Phase1, тоже кладём в кэш (негативный список
    // не пострадает — сюда попадают только реальные положительные результаты).
    foreach ($toCachePhase1 as $cacheKey => $offers) {
        [$code, $gk] = explode('|', $cacheKey, 2);
        [$bn, $an] = array_pad(explode('|', $gk, 2), 2, '');
        if ($bn === '' || $an === '') continue;
        cacheSave($code, $bn, $an, $offers);
    }

    // Дедуп: один и тот же оффер иногда приходит и от exact|, и от cross| одного поставщика.
    $seenExact = [];
    $uniqueExact = [];
    foreach ($exactOffers as $o) {
        $key = $o['supplier'] . '|' . $o['warehouse'] . '|' . $o['price'];
        if (isset($seenExact[$key])) continue;
        $seenExact[$key] = true;
        $uniqueExact[] = $o;
    }
    $exactOffers = $uniqueExact;

    $crossPairs = array_slice($crossPairs, 0, MAX_ANALOG_PAIRS, true);
    $crossCount = count($crossPairs);

    progWrite($taskId, 100, 'Готово');
    ajaxLog("PHASE1 done task=$taskId crossPairs=$crossCount exact=" . count($exactOffers) . " analogs=" . count($analogGroups) . " time=" . round(microtime(true) - $tTotal, 2) . "s");

    $resp = [];
    if (!empty($exactOffers)) {
        sortOffers($exactOffers);
        $resp['exact'] = ['brand' => $brandOrig, 'article' => $numberOrig, 'suppliers' => $exactOffers];
    }

    $analogs = [];
    foreach ($analogGroups as $gk => $grp) {
        $offers = $grp['offers'];
        sortOffers($offers);
        $prices = array_column($offers, 'price');
        $days   = array_column($offers, 'delivery_days');
        $qtys   = array_column($offers, 'quantity');
        $activePrices = array_filter($prices, fn($p) => $p > 0);
        $activeDays   = array_filter($days, fn($d) => $d >= 0);
        $analogs[] = [
            'key'           => $gk,
            'brand'         => $grp['brand_orig'],
            'article'       => $grp['article_orig'],
            'description'   => $grp['description'],
            'best_price'    => $activePrices ? min($activePrices) : 0,
            'best_delivery' => $activeDays ? min($activeDays) : null,
            'total_qty'     => array_sum($qtys),
            'has_instock'   => count(array_filter($qtys, fn($q) => $q > 0)) > 0,
            'suppliers'     => $offers,
        ];
    }
    usort($analogs, function ($a, $b) {
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

    ajaxLog("PHASE1 RESPOND task=$taskId time=" . round(microtime(true) - $tTotal, 2) . "s");
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} else {
    echo json_encode(['error' => 'Неизвестный action']);
}
