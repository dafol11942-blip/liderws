<?php
@ini_set('memory_limit', '512M');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = []; $analogGroups = []; $allBrands = [];
$totalGroups = 0; $totalWarehouses = 0; $searchNumber = $searchNumberRaw;
$analogToken = '';

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

$isMgr = function_exists('isManager') ? (isManager() ? '1' : '0') : '0';
$fullCache = new SearchCacheManager('/search/s2_fast_v1', 300);
$fullKey = md5(mb_strtolower(implode('|', [$q, $selectedBrand, $selectedNumber, (string)($brandKey ?? ''), (string)$filterPriceMin, (string)$filterPriceMax, (string)$filterBrand, (string)$sortExact, (string)$sortAnalog, $isMgr])));
$fullHit = $fullCache->get($fullKey);
if (is_array($fullHit) && !empty($fullHit['ok'])) {
    $exactGroups = $fullHit['exactGroups'] ?? []; $analogGroups = $fullHit['analogGroups'] ?? [];
    $allBrands = $fullHit['allBrands'] ?? []; $totalGroups = (int)($fullHit['totalGroups'] ?? 0);
    $totalWarehouses = (int)($fullHit['totalWarehouses'] ?? 0);
    $searchNumber = (string)($fullHit['searchNumber'] ?? $searchNumberRaw);
    $selectedBrand = (string)($fullHit['selectedBrand'] ?? $selectedBrand);
    $analogToken = (string)($fullHit['analogToken'] ?? '');
    return;
}

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

$launcher   = new FullSearchLauncher(getSupplierFactory());
$allResults = $launcher->launch($displayBrand, $displayArticle, $cachedBrandMap, $exactKey, $targetEntry, 10.0);

$aggregator   = new OfferAggregator(10, 80);
$groupedItems = $aggregator->aggregate($allResults);

$builder = new ResultBuilder(200, 10, 80);
$result  = $builder->build($groupedItems, $exactKey, $normTargetBrand, $normTargetArt, $displayBrand, $displayArticle, $cachedBrandMap, ['price_min'=>(int)$filterPriceMin,'price_max'=>(int)$filterPriceMax,'brand'=>trim((string)$filterBrand)], (string)$sortExact, (string)$sortAnalog);

$exactGroups = $result['exactGroups']; $analogGroups = $result['analogGroups'];
$allBrands = $result['allBrands']; $totalGroups = $result['totalGroups']; $totalWarehouses = $result['totalWarehouses'];
$searchNumber = $displayArticle; $selectedBrand = $displayBrand;

$fullCache->set($fullKey, ['ok'=>1,'exactGroups'=>$exactGroups,'analogGroups'=>$analogGroups,'allBrands'=>$allBrands,'totalGroups'=>$totalGroups,'totalWarehouses'=>$totalWarehouses,'searchNumber'=>$searchNumber,'selectedBrand'=>$selectedBrand,'analogToken'=>$analogToken], 300);
