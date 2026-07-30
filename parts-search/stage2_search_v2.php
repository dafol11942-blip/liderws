<?php
/**
 * stage2_search_v2.php (v3.2 — Этап 17.3)
 * Поток:
 *   1. b_cross_index (мгновенно) — все кроссы
 *   2. b_supplier_stock (мгновенно) — кэшированные цены
 *   3. MultiCurlExecutor (2-4с) — топ-30 кроссов без кэша
 *   4. FullSearchLauncher (15-25с) — только если и артикула нет в кэше
 * Итог: горячий <200мс, средний 2-4с, холодный 15-25с
 */
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '90');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;
use Lider\Search\SearchResultItem;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\InstantSearcher;
use Lider\Search\Common\MultiCurlExecutor;

const UMAPI_BASE = 'https://api.umapi.ru/v2/cross/parts/Analogs/pro';
const UMAPI_KEY  = '52606cd0-b1fd-4a5e-a8e3-ad9fbef16435';
const TOP_CROSS  = 30;   // сколько кроссов обзванивать live
const MIN_WEIGHT = 0;    // минимальный вес кросса для live-обзвона

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = []; $analogGroups = []; $allBrands = [];
$totalGroups = 0; $totalWarehouses = 0; $searchNumber = $searchNumberRaw;
$analogToken = '';

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

$db     = \Bitrix\Main\Application::getConnection();
$helper = $db->getSqlHelper();

$supplierNames = [
    'moskvorechie' => 'Москворечье', 'rossko' => 'Росско', 'berg' => 'Берг',
    'autoeuro' => 'Автоевро', 'partkom' => 'ПартКом', 'ixora' => 'Иксора',
    'tatparts' => 'ТатПартс', 'autoruss' => 'Авторусь', 'autopiter' => 'Автопитер',
    'shatem' => 'Шатем',
];

// ─── 1. Нормализация ───────────────────────────────────────
$normTargetArt  = BrandNormalizer::normalizeArticle($searchNumberRaw);
$displayArticle = $searchNumberRaw;
$displayBrand   = $canonBrand;
$exactKey       = $normTargetBrand . '|' . $normTargetArt;

// ─── 2. b_cross_index (мгновенно) ───────────────────────────
$crossRows = $db->query(
    "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
     FROM b_cross_index
     WHERE article_orig_norm = '" . $helper->forSql($normTargetArt) . "'
       AND brand_orig_norm   = '" . $helper->forSql($normTargetBrand) . "'
     ORDER BY weight DESC"
)->fetchAll();

// ─── 3. Холодный старт: UMAPI на лету ──────────────────────
if (empty($crossRows)) {
    $url = UMAPI_BASE . '/' . urlencode($normTargetArt) . '/' . urlencode($normTargetBrand) . '/false';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'X-App-Key: ' . UMAPI_KEY],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data    = json_decode($resp, true);
    $analogs = $data['data'] ?? $data['analogs'] ?? $data ?? [];
    if (!empty($analogs) && is_array($analogs)) {
        $values = [];
        foreach ($analogs as $a) {
            $ca = BrandNormalizer::normalizeArticle($a['article'] ?? '');
            $cb = BrandNormalizer::normalize($a['brand'] ?? '');
            $w  = intval($a['weight'] ?? 0);
            $t  = mb_substr($a['title'] ?? '', 0, 500);
            if (empty($ca) || empty($cb)) continue;
            $values[] = sprintf("('%s','%s','%s','%s',%d,'%s',NOW())",
                $helper->forSql($normTargetArt), $helper->forSql($normTargetBrand),
                $helper->forSql($ca), $helper->forSql($cb), $w, $helper->forSql($t));
        }
        if (!empty($values)) {
            $db->query("INSERT IGNORE INTO b_cross_index 
                (article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm, weight, title_keywords, created_at)
                VALUES " . implode(',', $values));
        }
    }
    $crossRows = $db->query(
        "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
         FROM b_cross_index
         WHERE article_orig_norm = '" . $helper->forSql($normTargetArt) . "'
           AND brand_orig_norm   = '" . $helper->forSql($normTargetBrand) . "'
         ORDER BY weight DESC"
    )->fetchAll();
}

// ─── 4. Собираем article_norm → crossMap ────────────────────
$allArticleNorms = [$normTargetArt];
$crossMap = [];
foreach ($crossRows as $cr) {
    $an = $cr['article_cross_norm'];
    if (!isset($crossMap[$an])) {
        $crossMap[$an] = ['brand_norm' => $cr['brand_cross_norm'], 'title' => $cr['title_keywords']];
    }
    $allArticleNorms[] = $an;
}
$allArticleNorms = array_unique($allArticleNorms);

// ─── 5. b_supplier_stock (мгновенно) ────────────────────────
$allResults = [];
$cachedArticleNorms = []; // какие article_norm уже есть в кэше

if (!empty($allArticleNorms)) {
    $inClause = "'" . implode("','", array_map(fn($a) => $helper->forSql($a), $allArticleNorms)) . "'";
    $stockRows = $db->query(
        "SELECT supplier_code, article, brand, name, price, quantity,
                warehouse_name, warehouse_code, stock_id, delivery_days,
                is_sched, multiplicity, article_normalized, brand_normalized
         FROM b_supplier_stock
         WHERE article_normalized IN ($inClause) AND is_active = 1
         ORDER BY is_sched ASC, price ASC"
    )->fetchAll();

    foreach ($stockRows as $row) {
        $an = $row['article_normalized'];
        $item = new SearchResultItem();
        $item->source       = $row['supplier_code'];
        $item->article      = $row['article'];
        $item->brand        = $row['brand'];
        $item->name         = $row['name'] ?: ($crossMap[$an]['title'] ?? '');
        $item->price        = (float)$row['price'];
        $item->quantity     = (int)$row['quantity'];
        $item->warehouse    = $row['warehouse_name'];
        $item->stockId      = $row['stock_id'];
        $item->supplierName = $supplierNames[$row['supplier_code']] ?? $row['supplier_code'];
        $item->isSched      = (bool)$row['is_sched'];
        $item->deliveryDays = (int)$row['delivery_days'];
        $item->multiplicity = (int)$row['multiplicity'];
        $item->unit         = 'шт.';
        $item->returnable   = false;
        $item->raw          = [];
        $allResults[]       = $item;
        $cachedArticleNorms[$an] = true;
    }
}

// ─── 6. Холодный артикул: если ИСКОМОГО нет → FullSearchLauncher ─
$hasTargetInCache = isset($cachedArticleNorms[$normTargetArt]);
if (!$hasTargetInCache) {
    $launcher = new FullSearchLauncher(getSupplierFactory());
    $apiResults = $launcher->launch(
        $displayBrand, $displayArticle,
        [$exactKey => ['brands' => [$selectedBrand], 'articles' => [$displayArticle], 'article_nr' => $displayArticle, 'description' => '', 'sources' => []]],
        $exactKey,
        [$exactKey => ['brands' => [$selectedBrand], 'articles' => [$displayArticle], 'article_nr' => $displayArticle, 'description' => '', 'sources' => []]],
        30.0
    );
    if (!empty($apiResults)) {
        try {
            $searcher = new InstantSearcher();
            $searcher->saveResults($apiResults);
        } catch (\Throwable $e) {}
        $allResults = array_merge($allResults, $apiResults);
        $cachedArticleNorms[$normTargetArt] = true;
    }
}

// ─── 7. MultiCurlExecutor: догружаем топ-30 кроссов без кэша ─
$topCrosses = [];
foreach ($crossRows as $cr) {
    if (count($topCrosses) >= TOP_CROSS) break;
    $an = $cr['article_cross_norm'];
    $topCrosses[] = $cr;
}

if (!empty($topCrosses)) {
    $factory  = getSupplierFactory();
    $requests = [];
    $reqMeta  = []; // key → [supplier, crossArticle, crossBrand]

    foreach ($factory->allAvailable() as $supplier) {
        $code = $supplier->getCode();
        foreach ($topCrosses as $cross) {
            $req = $supplier->buildSearchRequest($cross['article_cross_norm'], $cross['brand_cross_norm']);
            if (!$req) continue;
            $key = $code . '|' . $cross['article_cross_norm'] . '|' . $cross['brand_cross_norm'];
            if ($req) file_put_contents(__DIR__ . '/../upload/logs/debug_step7.log', date('H:i:s') . " REQ: $key\n", FILE_APPEND);
            else file_put_contents(__DIR__ . '/../upload/logs/debug_step7.log', date('H:i:s') . " NULL: $key\n", FILE_APPEND);
            $requests[$key] = $req;
            $reqMeta[$key]  = [$supplier, $cross['article_cross_norm'], $cross['brand_cross_norm']];
        }
    }

    if (!empty($requests)) {
        file_put_contents(__DIR__ . '/../upload/logs/debug_step7.log', date('H:i:s') . " requestsTotal=" . count($requests) . "\n", FILE_APPEND);
        $executor  = new MultiCurlExecutor();
        $responses = $executor->executeAll($requests, 4.0);
        $respCount = 0; $bodyCount = 0; $parseCount = 0; $c25016found = 0;
        foreach ($responses as $k => $r) { $respCount++; if (!empty($r['body'])) $bodyCount++; if (stripos($k, 'c25016') !== false && !empty($r['body'])) $c25016found++; }
        file_put_contents(__DIR__ . '/../upload/logs/debug_step7.log', 
        date('H:i:s') . " executeAll: responses=$respCount withBody=$bodyCount c25016=$c25016found\n", FILE_APPEND);

        $apiResults = [];
        foreach ($responses as $key => $resp) {
            if (empty($resp['body'])) continue;
            [$supplier, $crossArt, $crossBrand] = $reqMeta[$key];
            try {
                $items = $supplier->parseSearchResponse($resp['body']);
                foreach ($items as $item) {
                    // Важно: не перетираем article/brand (поставщик мог вернуть другой)
                    $item->source       = $supplier->getCode();
                    $item->supplierName = $supplierNames[$supplier->getCode()] ?? $supplier->getCode();
                    $apiResults[]       = $item;
                    $parseCount++;
                    if (stripos($crossArt, 'c25016') !== false) $c25016found++;
                }
            } catch (\Throwable $e) {}
        }

        if (!empty($apiResults)) {
            file_put_contents(__DIR__ . '/../upload/logs/debug_step7.log',
            date('H:i:s') . " parsed=$parseCount apiResults=" . count($apiResults) . "\n", FILE_APPEND);
            try {
                $searcher = new InstantSearcher();
                $searcher->saveResults($apiResults);
            } catch (\Throwable $e) {}
            $allResults = array_merge($allResults, $apiResults);
        }
    }
}

// ─── 8. Агрегация + ResultBuilder ───────────────────────────
$aggregator   = new OfferAggregator(200, 1000);
$groupedItems = $aggregator->aggregate($allResults);

$builder = new ResultBuilder(800, 200, 1000);
$result  = $builder->build(
    $groupedItems, $exactKey, $normTargetBrand, $normTargetArt,
    $displayBrand, $displayArticle, [],
    [
        'price_min' => (int)($filterPriceMin ?? 0),
        'price_max' => (int)($filterPriceMax ?? 0),
        'brand'     => (string)($filterBrand ?? ''),
    ],
    (string)($sortExact ?? 'default'),
    (string)($sortAnalog ?? 'default')
);

$exactGroups     = $result['exactGroups'] ?? [];
$analogGroups    = $result['analogGroups'] ?? [];
$allBrands       = $result['allBrands'] ?? [];
$totalGroups     = $result['totalGroups'] ?? 0;
$totalWarehouses = $result['totalWarehouses'] ?? 0;
$searchNumber    = $displayArticle;

// Убираем искомый артикул из аналогов
unset($analogGroups[$exactKey]);
foreach ($analogGroups as $_key => $_g) {
    if (
        BrandNormalizer::normalize($_g['brand']) === $normTargetBrand
        && BrandNormalizer::normalizeArticle($_g['article']) === $normTargetArt
    ) {
        unset($analogGroups[$_key]);
    }
}

$analogToken = md5($q . '|' . $displayBrand . '|' . $displayArticle . '|analog_v3');