<?php
/**
 * stage2_search_v2.php (v3 — Этап 17.3)
 * Единый поток: b_cross_index → b_supplier_stock → HTML
 * Всё мгновенно (<200мс). Без API-запросов к поставщикам.
 */
@ini_set('memory_limit', '256M');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;
use Lider\Search\SearchResultItem;

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($resp)) {
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
    }
    // Перечитываем
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

// ─── 5. Запрос в b_supplier_stock ───────────────────────────
$allResults = [];
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

    $supplierNames = [
        'moskvorechie' => 'Москворечье', 'rossko' => 'Росско', 'berg' => 'Берг',
        'autoeuro' => 'Автоевро', 'partkom' => 'ПартКом', 'ixora' => 'Иксора',
        'tatparts' => 'ТатПартс', 'autoruss' => 'Авторусь', 'autopiter' => 'Автопитер',
        'shatem' => 'Шатем',
    ];

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

// ─── 6. Агрегация + ResultBuilder ───────────────────────────
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

$exactGroups    = $result['exactGroups'] ?? [];
$analogGroups   = $result['analogGroups'] ?? [];
$allBrands      = $result['allBrands'] ?? [];
$totalGroups    = $result['totalGroups'] ?? 0;
$totalWarehouses = $result['totalWarehouses'] ?? 0;
$searchNumber   = $displayArticle;

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

// ─── 7. Токен для JS lazy-loader (догрузка свежих данных) ──
$analogToken = md5($q . '|' . $displayBrand . '|' . $displayArticle . '|analog_v3');