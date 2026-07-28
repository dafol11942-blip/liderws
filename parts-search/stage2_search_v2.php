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

// UMAPI: кроссы + brandMap одним запросом вместо 8 API поставщиков
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/UmapiClient.php';
$umapi = new \Lider\Search\UmapiClient('52606cd0-b1fd-4a5e-a8e3-ad9fbef16435');

// brandMap для обратной совместимости с ResultBuilder
$cachedBrandMap = [];
$umapiAnalogs = $umapi->getAnalogs($searchNumberRaw, $selectedBrand);
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
        $aggregator = new OfferAggregator(200, 1000);
        $builder = new ResultBuilder(300, 200, 1000);
        $cachedGroups = $aggregator->aggregate($cachedItems);
        $instantResult = $builder->build(
            $cachedGroups, $exactKey, $normTargetBrand, $normTargetArt,
            $displayBrand, $displayArticle, [],
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
    // fastcgi_finish_request: отдаём страницу со спиннером, поиск в фоне
    if (function_exists('fastcgi_finish_request')) {
        // Рендерим спиннер вместо пустых блоков
        $exactGroups = ['__pending__' => true];
        $analogGroups = ['__pending__' => true];
        $allBrands = [];
        $totalGroups = 0;
        $totalWarehouses = 0;
        $searchNumber = $displayArticle;
        $verifyTaskHash = 'cold_' . md5($normTargetArt . '|' . $normTargetBrand . '|' . time());
        
        // Сохраняем задачу
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $db->query("INSERT INTO b_search_verify_tasks (task_hash, article, brand, status)
                    VALUES ('{$verifyTaskHash}', '{$db->real_escape_string($displayArticle)}', '{$db->real_escape_string($displayBrand)}', 'pending')");
        $db->close();
        
        // Отдаём страницу сейчас, поиск продолжится в фоне
        // (основной render ниже покажет спиннер)
    } else {
        // Без fastcgi — синхронно (старое поведение)
        $launcher   = new FullSearchLauncher(getSupplierFactory());
        $allResults = $launcher->launchPhase1($displayBrand, $displayArticle, 30.0);

        if (!empty($allResults)) {
            try {
                $cache = new InstantSearcher();
                $saved = $cache->saveResults($allResults);
            } catch (\Throwable $ex) {}
        }

        $aggregator  = new OfferAggregator(200, 1000);
        $offerGroups = $aggregator->aggregate($allResults);
        $builder     = new ResultBuilder(300, 200, 1000);
        $result      = $builder->build(
            $offerGroups, $exactKey, $normTargetBrand, $normTargetArt,
            $displayBrand, $displayArticle, [],
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
}
