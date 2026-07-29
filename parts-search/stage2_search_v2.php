<?php
/**
 * stage2_search_v2 — ГИБРИДНЫЙ ПОИСК
 * 1. Мгновенно: кэш b_supplier_stock
 * 2. P2-файл для инкрементальной дозагрузки кросс-номеров
 */
@ini_set('memory_limit', '512M');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\InstantSearcher;

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = [];
$analogGroups = [];
$allBrands = [];
$totalGroups = 0;
$totalWarehouses = 0;
$searchNumber = $searchNumberRaw;
$analogToken = '';
$p2Hash = '';
$skipLive = false;

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

// UMAPI: кроссы
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/UmapiClient.php';
$umapi = new \Lider\Search\UmapiClient('52606cd0-b1fd-4a5e-a8e3-ad9fbef16435');
$umapiAnalogs = $umapi->getAnalogs($searchNumberRaw, $selectedBrand);

// brandMap для совместимости
$cachedBrandMap = [];
foreach ($umapiAnalogs as $a) {
    $ab = trim((string)($a['brand'] ?? ''));
    $aa = trim((string)($a['article'] ?? ''));
    if ($ab === '' || $aa === '') continue;
    $k = BrandNormalizer::groupKey($ab, $aa);
    if (!isset($cachedBrandMap[$k])) {
        $cachedBrandMap[$k] = [
            'brands'      => ['umapi' => $ab],
            'articles'    => ['umapi' => $aa],
            'article_nr'  => $aa,
            'description' => $a['title'] ?? '',
            'sources'     => ['umapi'],
        ];
    }
}

$normQArt = BrandNormalizer::normalizeArticle($searchNumberRaw);
$targetKey = (is_string($brandKey ?? null) && $brandKey !== '') ? $brandKey : BrandNormalizer::groupKey($selectedBrand, $searchNumberRaw);
$targetEntry = $cachedBrandMap[$targetKey] ?? null;
if ($targetEntry === null) {
    foreach ($cachedBrandMap as $k => $info) {
        [$kb, $ka] = array_pad(explode('|', $k, 2), 2, '');
        if ($kb === $normTargetBrand && $ka === $normQArt) { $targetKey = $k; $targetEntry = $info; break; }
    }
}

$displayArticle = $searchNumberRaw;
$displayBrand = $canonBrand;
if ($targetEntry) {
    $arts = $targetEntry['articles'] ?? [];
    $displayArticle = BrandNormalizer::pickDisplayArticle($arts, $targetEntry['article_nr'] ?? $searchNumberRaw);
    $displayBrand = $canonBrand ?: BrandNormalizer::displayBrand((string)reset($targetEntry['brands']));
}

$normTargetArt = BrandNormalizer::normalizeArticle($displayArticle);
$normTargetBrand = BrandNormalizer::normalize($displayBrand);
$exactKey = $normTargetBrand . '|' . $normTargetArt;
$analogToken = md5($q . '|' . $displayBrand . '|' . $displayArticle . '|analog_v2');

// ==================== ГИБРИДНЫЙ ПОИСК ====================
$useHybrid = true;
$allResults = [];

if ($useHybrid && !isset($_GET['verified'])) {
    $instantStart = microtime(true);
    $cache = new InstantSearcher();
    $cachedItems = $cache->search($normTargetArt, $normTargetBrand);
    $instantMs = round((microtime(true) - $instantStart) * 1000, 1);
    $instantCacheMs = $instantMs;

    if (!empty($cachedItems)) {
        $aggregator = new OfferAggregator(200, 1000);
        $builder = new ResultBuilder(300, 200, 1000);
        $cachedGroups = $aggregator->aggregate($cachedItems);
        $instantResult = $builder->build(
            $cachedGroups, $exactKey, $normTargetBrand, $normTargetArt,
            $displayBrand, $displayArticle, [], [], 'default', 'default'
        );

        $exactGroups = $instantResult['exactGroups'] ?? [];
        $analogGroups = $instantResult['analogGroups'] ?? [];
        $allBrands = $instantResult['allBrands'] ?? [];
        $totalGroups = $instantResult['totalGroups'] ?? 0;
        $totalWarehouses = $instantResult['totalWarehouses'] ?? 0;
        $searchNumber = $displayArticle;
        $allResults = $cachedItems;
        $skipLive = true;
    }
}

// LIVE-поиск (если кэш пустой)
if (!$skipLive) {
    $launcher = new FullSearchLauncher(getSupplierFactory());
    $allResults = $launcher->launchPhase1($displayBrand, $displayArticle, 30.0);

    if (!empty($allResults)) {
        try {
            $cache = new InstantSearcher();
            $cache->saveResults($allResults);
        } catch (\Throwable $ex) {}
    }

    $aggregator = new OfferAggregator(200, 1000);
    $offerGroups = $aggregator->aggregate($allResults);
    $builder = new ResultBuilder(300, 200, 1000);
    $result = $builder->build(
        $offerGroups, $exactKey, $normTargetBrand, $normTargetArt,
        $displayBrand, $displayArticle, [],
        [
            'price_min' => (int)($filterPriceMin ?? 0),
            'price_max' => (int)($filterPriceMax ?? 0),
            'brand' => (string)($filterBrand ?? ''),
        ],
        (string)($sortExact ?? 'default'), (string)($sortAnalog ?? 'default')
    );

    $exactGroups = $result['exactGroups'] ?? [];
    $analogGroups = $result['analogGroups'] ?? [];
    $allBrands = $result['allBrands'] ?? [];
    $totalGroups = $result['totalGroups'] ?? 0;
    $totalWarehouses = $result['totalWarehouses'] ?? 0;
    $searchNumber = $displayArticle;
}

// === P2-файл для инкрементальной дозагрузки ===
if (!empty($umapiAnalogs) && !isset($_GET['verified'])) {
    $p2Hash = md5($normTargetArt . '|' . $normTargetBrand . '|' . microtime(true));
    $p2Dir = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/p2';
    if (!is_dir($p2Dir)) mkdir($p2Dir, 0755, true);
    $p2File = $p2Dir . '/' . $p2Hash . '.json';

    // Дедупликация umapiAnalogs
    $uniqueAnalogs = [];
    foreach ($umapiAnalogs as $a) {
        $ab = trim((string)($a['brand'] ?? ''));
        $aa = trim((string)($a['article'] ?? ''));
        if ($ab === '' || $aa === '') continue;
        $k = BrandNormalizer::normalize($ab) . '|' . BrandNormalizer::normalizeArticle($aa);
        if (!isset($uniqueAnalogs[$k])) {
            $uniqueAnalogs[$k] = ['brand' => $ab, 'article' => $aa, 'title' => $a['title'] ?? ''];
        }
    }

    $p1Serialized = [];
    foreach ($allResults as $item) {
        $p1Serialized[] = [
            'source' => $item->source, 'article' => $item->article, 'brand' => $item->brand,
            'name' => $item->name, 'price' => $item->price, 'quantity' => $item->quantity,
            'warehouse' => $item->warehouse, 'stockId' => $item->stockId,
            'supplierName' => $item->supplierName, 'isSched' => $item->isSched,
            'deliveryDays' => $item->deliveryDays, 'deliveryPeriod' => $item->deliveryPeriod ?? 0,
            'multiplicity' => $item->multiplicity ?? 1, 'unit' => $item->unit ?? 'шт.',
        ];
    }

    file_put_contents($p2File, json_encode([
        'hash'            => $p2Hash,
        'umapiAnalogs'    => array_values($uniqueAnalogs),
        'brand'           => $displayBrand,
        'article'         => $displayArticle,
        'exactKey'        => $exactKey,
        'normTargetBrand' => $normTargetBrand,
        'normTargetArt'   => $normTargetArt,
        'p1_count'        => count($p1Serialized),
        'p1_results'      => $p1Serialized,
        'created'         => time(),
        'p2_results'      => [],
        'running'         => false,
        'done'            => false,
        'p2_count'        => 0,
    ], JSON_UNESCAPED_UNICODE));
}