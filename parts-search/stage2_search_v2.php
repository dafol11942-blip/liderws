<?php
/**
 * stage2_search_v2.php (v3.1 — Этап 17.3)
 * Поток: b_cross_index → b_supplier_stock (мгновенно)
 * Холодный: UMAPI → FullSearchLauncher(один артикул) → saveResults → SQL
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

const UMAPI_BASE = 'https://api.umapi.ru/v2/cross/parts/Analogs/pro';
const UMAPI_KEY  = '52606cd0-b1fd-4a5e-a8e3-ad9fbef16435';

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = []; $analogGroups = []; $allBrands = [];
$totalGroups = 0; $totalWarehouses = 0; $searchNumber = $searchNumberRaw;
$analogToken = '';

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

$db     = \Bitrix\Main\Application::getConnection();
$helper = $db->getSqlHelper();

// ─── 1. Нормализация ───────────────────────────────────────
$normTargetArt  = BrandNormalizer::normalizeArticle($searchNumberRaw);
$displayArticle = $searchNumberRaw;
$displayBrand   = $canonBrand;
$exactKey       = $normTargetBrand . '|' . $normTargetArt;

// ─── 2. b_cross_index ──────────────────────────────────────
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

// ─── 4. Собираем article_norm для b_supplier_stock ──────────
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

// ─── 5. b_supplier_stock → SearchResultItem[] ───────────────
$allResults = [];
$supplierNames = [
    'moskvorechie' => 'Москворечье', 'rossko' => 'Росско', 'berg' => 'Берг',
    'autoeuro' => 'Автоевро', 'partkom' => 'ПартКом', 'ixora' => 'Иксора',
    'tatparts' => 'ТатПартс', 'autoruss' => 'Авторусь', 'autopiter' => 'Автопитер',
    'shatem' => 'Шатем',
];

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
        $item = new SearchResultItem();
        $item->source       = $row['supplier_code'];
        $item->article      = $row['article'];
        $item->brand        = $row['brand'];
        $item->name         = $row['name'] ?: ($crossMap[$row['article_normalized']]['title'] ?? '');
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
    }
}

// ─── 6. Холодный артикул: если нет в b_supplier_stock → API ─
if (empty($allResults)) {
    // Запускаем FullSearchLauncher только для ИСКОМОГО артикула
    $launcher = new FullSearchLauncher(getSupplierFactory());
    $apiResults = $launcher->launch(
        $displayBrand, $displayArticle,
        [$exactKey => ['brands' => [$selectedBrand], 'articles' => [$displayArticle], 'article_nr' => $displayArticle, 'description' => '', 'sources' => []]],
        $exactKey,
        [$exactKey => ['brands' => [$selectedBrand], 'articles' => [$displayArticle], 'article_nr' => $displayArticle, 'description' => '', 'sources' => []]],
        30.0
    );

    if (!empty($apiResults)) {
        // Сохраняем в кэш для будущих поисков
        try {
            $searcher = new InstantSearcher();
            $searcher->saveResults($apiResults);
        } catch (\Throwable $e) {}

        $allResults = $apiResults;
    }
}

// ─── 7. Агрегация + ResultBuilder ───────────────────────────
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