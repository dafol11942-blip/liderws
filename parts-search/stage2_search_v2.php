<?php
/**
 * ГИБРИДНЫЙ stage2_search: МГНОВЕННАЯ отдача + фоновая верификация.
 * 
 * 1. Мгновенно (< 0.1 сек): поиск по b_supplier_stock
 * 2. Параллельно: FullSearchLauncher с сохранением в кэш
 * 3. Фронтенд дозагружает свежие данные через AJAX
 */
@ini_set('memory_limit', '512M');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;
use Lider\Search\InstantSearcher;

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = []; $analogGroups = []; $allBrands = [];
$totalGroups = 0; $totalWarehouses = 0; $searchNumber = $searchNumberRaw;
$analogToken = '';
$verifyTaskHash = ''; // Для фронтенда
$skipLive = false;    // Баг #10: инициализация до if ($useHybrid)

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

$isMgr = function_exists('isManager') ? (isManager() ? '1' : '0') : '0';

// Получаем brandMap (как раньше)
$bmCache = new SearchCacheManager('/search/supplier', 900);
$bmKey = 'brandmap_' . md5(mb_strtolower($q));
$cachedBrandMap = $bmCache->get($bmKey);
if (!is_array($cachedBrandMap) || empty($cachedBrandMap)) {
    $raw = []; $breqs = []; $bsups = [];
    foreach (getSupplierFactory()->allAvailable() as $s) {
        $r = $s->buildBrandsRequest($q);
        if ($r) { $breqs[$s->getCode()] = $r; $bsups[$s->getCode()] = $s; }
    }
    $e = new \Lider\Search\Common\MultiCurlExecutor();
    foreach ($e->executeAll($breqs, 6.0) as $code => $resp) {
        if (empty($resp['body'])) continue;
        try { foreach ($bsups[$code]->parseBrandsResponse($resp['body'], $q) as $br) { $br['source'] = $code; $raw[] = $br; } } catch (\Throwable $e) {}
    }
    $cachedBrandMap = [];
    foreach ($raw as $br) {
        $b = trim((string)($br['brand'] ?? '')); $a = trim((string)($br['article_nr'] ?? $br['article'] ?? ''));
        if ($b === '' || $a === '') continue;
        $k = BrandNormalizer::groupKey($b, $a);
        if (!isset($cachedBrandMap[$k])) $cachedBrandMap[$k] = ['brands'=>[], 'articles'=>[], 'article_nr'=>$a, 'description'=>(string)($br['description']??''), 'sources'=>[]];
        $src = $br['source']; $cachedBrandMap[$k]['brands'][$src] = $b; $cachedBrandMap[$k]['articles'][$src] = $a;
        if (!in_array($src, $cachedBrandMap[$k]['sources'], true)) $cachedBrandMap[$k]['sources'][] = $src;
        $cachedBrandMap[$k]['article_nr'] = BrandNormalizer::pickDisplayArticle($cachedBrandMap[$k]['articles'], $cachedBrandMap[$k]['article_nr']);
        $desc = (string)($br['description'] ?? ''); if (mb_strlen($desc) > mb_strlen($cachedBrandMap[$k]['description'])) $cachedBrandMap[$k]['description'] = $desc;
    }
    $bmCache->set($bmKey, $cachedBrandMap, 900);
}

$normQArt = BrandNormalizer::normalizeArticle($searchNumberRaw);
$targetKey = (is_string($brandKey ?? null) && $brandKey !== '') ? $brandKey : BrandNormalizer::groupKey($selectedBrand, $searchNumberRaw);
$targetEntry = $cachedBrandMap[$targetKey] ?? null;
if ($targetEntry === null) foreach ($cachedBrandMap as $k => $info) { [$kb, $ka] = array_pad(explode('|', $k, 2), 2, ''); if ($kb === $normTargetBrand && $ka === $normQArt) { $targetKey = $k; $targetEntry = $info; break; } }

$displayArticle = $searchNumberRaw; $displayBrand = $canonBrand;
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
$useHybrid = true; // Флаг: включить гибридный режим

if ($useHybrid) {
    // === ШАГ 1: МГНОВЕННЫЙ поиск по кэшу ===
    $instantStart = microtime(true);
    $cache = new InstantSearcher();
    file_put_contents(__DIR__ . '/../upload/logs/debug_cache.log', date('H:i:s') . " search(article='$normTargetArt', brand='$normTargetBrand')\n", FILE_APPEND);
$cachedItems = $cache->search($normTargetArt, $normTargetBrand);
file_put_contents(__DIR__ . '/../upload/logs/debug_cache.log', date('H:i:s') . " found=" . count($cachedItems) . "\n", FILE_APPEND);
    $instantMs = round((microtime(true) - $instantStart) * 1000, 1);
    $instantCacheMs = $instantMs; // alias for _hybrid_notice.php
    
    if (!empty($cachedItems)) {
        $aggregator = new OfferAggregator(50, 500);
        $builder = new ResultBuilder(300, 50, 500);
        $cachedGroups = $aggregator->aggregate($cachedItems);
        $instantResult = $builder->build(
            $cachedGroups, $exactKey, $normTargetBrand, $normTargetArt,
            $displayBrand, $displayArticle, $cachedBrandMap,
            [], 'default', 'default'
        );
        
        $exactGroups = $instantResult['exactGroups'] ?? [];
        $analogGroups = $instantResult['analogGroups'] ?? [];
        $allBrands = $instantResult['allBrands'] ?? [];
        $totalGroups = $instantResult['totalGroups'] ?? 0;
        $totalWarehouses = $instantResult['totalWarehouses'] ?? 0;
        $searchNumber = $displayArticle;
        
        // Генерируем task_hash только при первом показе (не при ?verified=1)
        if (!isset($_GET['verified'])) {
        $verifyTaskHash = md5($normTargetArt . '|' . $normTargetBrand . '|' . microtime(true));

        // Сохраняем задачу в БД (Баг #11: защита уже в родительском if)
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.$@wWd-", 'u3564357_liderws_db');
        $db->query("INSERT INTO b_search_verify_tasks (task_hash, article, brand, status)
                    VALUES ('{$verifyTaskHash}', '{$db->real_escape_string($displayArticle)}', '{$db->real_escape_string($displayBrand)}', 'pending')");
        $db->close();

        // Лог
        @file_put_contents(
            __DIR__ . '/../upload/logs/hybrid_' . date('Y-m-d') . '.log',
            '[' . date('H:i:s') . '] INSTANT article=' . $normTargetArt . ' brand=' . $normTargetBrand
            . ' items=' . count($cachedItems) . ' ms=' . $instantMs
            . ' task=' . $verifyTaskHash . "\n",
            FILE_APPEND
        );
    }   
     
        // НЕ возвращаемся — продолжаем и показываем кэш
        // но НЕ запускаем FullSearchLauncher ниже
        $skipLive = true;
    } else {
        $skipLive = false;
    }
}

// === ШАГ 2: LIVE-поиск (если кэш пустой) ===
if (!$skipLive) {
    $launcher   = new FullSearchLauncher(getSupplierFactory());
    $allResults = $launcher->launch($displayBrand, $displayArticle, $cachedBrandMap, $exactKey, $targetEntry, 10.0);
    
    // Сохраняем результаты в кэш (если есть что сохранять)
    if (!empty($allResults)) {
        try {
            $cache = new InstantSearcher();
            $saved = $cache->saveResults($allResults);
        } catch (\Throwable $ex) {
            // Тихо пропускаем ошибки кэша
        }
    }
    
    $aggregator  = new OfferAggregator(50, 500);
    $offerGroups = $aggregator->aggregate($allResults);
    $builder     = new ResultBuilder(300, 50, 500);
    $result      = $builder->build(
        $offerGroups, $exactKey, $normTargetBrand, $normTargetArt,
        $displayBrand, $displayArticle, $cachedBrandMap,
        [
            'price_min' => (int)($filterPriceMin ?? 0),
            'price_max' => (int)($filterPriceMax ?? 0),
            'brand' => (string)($filterBrand ?? ''),
        ],
        (string)$sortExact, (string)$sortAnalog
    );
    
    $exactGroups = $result['exactGroups'] ?? [];
    $analogGroups = $result['analogGroups'] ?? [];
    $allBrands = $result['allBrands'] ?? [];
    $totalGroups = $result['totalGroups'] ?? 0;
    $totalWarehouses = $result['totalWarehouses'] ?? 0;
    $searchNumber = $displayArticle;
}
