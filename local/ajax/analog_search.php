<?php
/**
 * analog_search.php (v5 — гибрид: b_cross_index + API-коннекторы)
 * Поиск: b_cross_index (мгновенно) → b_supplier_stock (мгновенно) → API (живые цены)
 * Все 10 поставщиков. delivery_days, returnable, stock_id — из API.
 */
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '90');
@ini_set('display_errors', 0);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';

$base = '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib';
require_once $base . '/Search/BrandNormalizer.php';
require_once $base . '/Search/SearchResultItem.php';
require_once $base . '/Search/SearchCacheManager.php';
require_once $base . '/Search/Common/MultiCurlExecutor.php';
require_once $base . '/Search/Stage2/OfferAggregator.php';
require_once $base . '/Search/Stage2/ResultBuilder.php';

use Lider\Search\BrandNormalizer;
use Lider\Search\Common\MultiCurlExecutor;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;

header('Content-Type: application/json; charset=utf-8');

const UMAPI_BASE = 'https://api.umapi.ru/v2/cross/parts/Analogs/pro';
const UMAPI_KEY  = '52606cd0-b1fd-4a5e-a8e3-ad9fbef16435';
const MAX_CROSS_API = 30;

// ─── Вспомогательные функции ─────────────────────────────────
function fmtDeliveryHtml(array $del): string {
    $days = $del['days'] ?? 0;
    $approx = $del['is_approx'] ?? false;
    if (!empty($del['date_from'])) {
        $from = $del['date_from'];
        $to = $del['date_to'] ?? null;
        $today = date('Y-m-d');
        $fromDate = date('Y-m-d', $from);
        $dayLabel = $fromDate === $today
            ? '<span class="sl-text-green">Сегодня</span>'
            : ($fromDate === date('Y-m-d', strtotime('+1 day'))
                ? '<span class="sl-text-amber">Завтра</span>'
                : date('d.m', $from));
        $timeStr = date('H:i', $from);
        if ($to) $timeStr .= '–' . date('H:i', $to);
        return $dayLabel . ' <span class="sl-delivery-time">' . $timeStr . '</span>';
    }
    if ($days === 0) return '<span class="sl-text-green">Сегодня</span>';
    if ($days === 1 && !$approx) return '<span class="sl-text-amber">Завтра</span>';
    $date = date('d.m', strtotime("+{$days} days"));
    return $approx ? '<span class="sl-text-muted">≈ ' . $date . '</span>' : $date;
}

function fmtQty(int $qty): string {
    if (isManager()) return $qty . ' шт.';
    if ($qty > 4) return 'Достаточно';
    return $qty . ' шт.';
}

function calcDelivery(\Lider\Search\SearchResultItem $item): array {
    $flags = (array)($item->raw['flags'] ?? []);
    $stockId = (string)($item->raw['stock_id'] ?? '');
    $hours = $item->deliveryPeriod ?? 0;
    $now = time();

    if ($hours === 0 && in_array('pickup', $flags)) {
        $hms = (int)date('Hi');
        if ($hms < 1102) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 12:00'),'date_to'=>strtotime('today 14:00')];
        elseif ($hms < 1402) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 15:00'),'date_to'=>strtotime('today 17:00')];
        else return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00')];
    }
    if ($stockId === '10') return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00')];
    if ($hours > 0) {
        $ts = $now + $hours * 3600;
        $day = strtotime(date('Y-m-d', $ts));
        $h = (int)date('H', $ts);
        $wh = $h < 9 ? 7 : ($h < 12 ? 11 : 14);
        $wave = $day + $wh * 3600;
        return ['days'=>max(0,(int)ceil(($wave-strtotime('today',$now))/86400)),'is_approx'=>false,'date_from'=>$wave,'date_to'=>$wave+10800];
    }
    if ($item->deliveryDays !== null) return ['days' => $item->deliveryDays, 'is_approx' => false];
    if ($hours > 0) return ['days' => (int)ceil($hours / 24), 'is_approx' => false];
    return ['days' => 0, 'is_approx' => false];
}

function maskWarehouse(\Lider\Search\SearchResultItem $item, \Lider\Supplier\SupplierFactory $factory): string {
    $realName = $item->warehouse ?: '—';
    if ($realName === '' || $realName === '—') return '—';
    if (isManager()) return $item->supplierName . ': ' . $realName;
    $connector = $factory->get($item->source);
    if ($connector && method_exists($connector, 'maskWarehouseName')) return $connector->maskWarehouseName($realName);
    return $realName;
}

function umapiFetchAndSave(string $artNorm, string $brandNorm, $db, $helper): array {
    $url = UMAPI_BASE . '/' . urlencode($artNorm) . '/' . urlencode($brandNorm) . '/false';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'X-App-Key: ' . UMAPI_KEY],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) return [];

    $data    = json_decode($response, true);
    $analogs = $data['data'] ?? $data['analogs'] ?? $data ?? [];
    if (empty($analogs) || !is_array($analogs)) return [];

    $crosses = [];
    $values  = [];
    foreach ($analogs as $a) {
        $ca = BrandNormalizer::normalizeArticle($a['article'] ?? '');
        $cb = BrandNormalizer::normalize($a['brand'] ?? '');
        $w  = intval($a['weight'] ?? 0);
        $t  = mb_substr($a['title'] ?? '', 0, 500);
        if (empty($ca) || empty($cb)) continue;
        $crosses[] = ['article_norm' => $ca, 'brand_norm' => $cb, 'title' => $t, 'weight' => $w];
        $values[]  = sprintf("('%s','%s','%s','%s',%d,'%s',NOW())",
            $helper->forSql($artNorm), $helper->forSql($brandNorm),
            $helper->forSql($ca), $helper->forSql($cb), $w, $helper->forSql($t));
    }

    if (!empty($values)) {
        $db->query("INSERT IGNORE INTO b_cross_index 
            (article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm, weight, title_keywords, created_at)
            VALUES " . implode(',', $values));
    }

    return $crosses;
}

// ─── ОСНОВНОЙ ПОТОК ──────────────────────────────────────────
try {
    $q      = trim((string)($_REQUEST['q'] ?? ''));
    $brand  = trim((string)($_REQUEST['brand'] ?? ''));
    $number = trim((string)($_REQUEST['number'] ?? ''));
    $token  = trim((string)($_REQUEST['token'] ?? ''));
    $filterBrand   = trim((string)($_REQUEST['filter_brand'] ?? ''));
    $filterPriceMin = (int)($_REQUEST['price_min'] ?? 0);
    $filterPriceMax = (int)($_REQUEST['price_max'] ?? 0);

    $expectedToken = md5($q . '|' . $brand . '|' . $number . '|analog_v5');
    if ($token !== $expectedToken || $q === '' || $brand === '' || $number === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid params']);
        exit;
    }

    $cache = new SearchCacheManager('/search/ajax_analog', 120);
    $cacheKey = md5(implode('|', [$q, $brand, $number, $filterPriceMin, $filterPriceMax, $filterBrand, isManager() ? 'mgr' : 'usr', 'v5']));
    $cached = $cache->get($cacheKey);
    if (is_array($cached) && !empty($cached['ok'])) {
        echo json_encode($cached);
        exit;
    }

    $normBrand   = BrandNormalizer::normalize($brand);
    $normArticle = BrandNormalizer::normalizeArticle($number);
    $displayBrand   = BrandNormalizer::displayBrand($brand);
    $displayArticle = $number;
    $exactKey = $normBrand . '|' . $normArticle;

    $db     = \Bitrix\Main\Application::getConnection();
    $helper = $db->getSqlHelper();
    $factory = getSupplierFactory();

    // Шаг 2: b_cross_index
    $crossRows = $db->query(
        "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
         FROM b_cross_index
         WHERE article_orig_norm = '" . $helper->forSql($normArticle) . "'
           AND brand_orig_norm   = '" . $helper->forSql($normBrand) . "'
         ORDER BY weight DESC"
    )->fetchAll();

    $source = 'b_cross_index';

    // Шаг 3: холодный UMAPI
    if (empty($crossRows)) {
        umapiFetchAndSave($normArticle, $normBrand, $db, $helper);
        $crossRows = $db->query(
            "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
             FROM b_cross_index
             WHERE article_orig_norm = '" . $helper->forSql($normArticle) . "'
               AND brand_orig_norm   = '" . $helper->forSql($normBrand) . "'
             ORDER BY weight DESC"
        )->fetchAll();
        $source = 'umapi_live';
    }

    // Шаг 4: собираем кросс-пары
    $allPairs = [['article_norm' => $normArticle, 'brand_norm' => $normBrand, 'title' => '']];
    $crossMap = [];
    foreach ($crossRows as $cr) {
        $an = $cr['article_cross_norm'];
        $bn = $cr['brand_cross_norm'];
        $key = $bn . '|' . $an;
        if (!isset($crossMap[$key])) {
            $crossMap[$key] = ['article_norm' => $an, 'brand_norm' => $bn, 'title' => $cr['title_keywords']];
            $allPairs[] = ['article_norm' => $an, 'brand_norm' => $bn, 'title' => $cr['title_keywords']];
        }
    }

    if (empty($allPairs)) {
        echo json_encode(['success' => false, 'error' => 'No data', 'ok' => 1]);
        exit;
    }

    // Шаг 5: b_supplier_stock
    $allArticleNorms = array_unique(array_column($allPairs, 'article_norm'));
    $inClause = "'" . implode("','", array_map(function($a) use ($helper) {
        return $helper->forSql($a);
    }, $allArticleNorms)) . "'";

    $stockRows = $db->query(
        "SELECT supplier_code, article, brand, name, price, quantity,
                warehouse_name, warehouse_code, stock_id, delivery_days,
                is_sched, multiplicity, article_normalized, brand_normalized,
                source_type, source_updated
         FROM b_supplier_stock
         WHERE article_normalized IN ($inClause)
           AND is_active = 1
         ORDER BY is_sched ASC, price ASC"
    )->fetchAll();

    $cachedPairs = [];
    $allResults = [];
    foreach ($stockRows as $row) {
        $key = $row['brand_normalized'] . '|' . $row['article_normalized'];
        $cachedPairs[$key] = true;

        $item = new \Lider\Search\SearchResultItem();
        $item->source       = $row['supplier_code'];
        $item->article      = $row['article'];
        $item->brand        = $row['brand'];
        $item->name         = $row['name'] ?: ($crossMap[$key]['title'] ?? '');
        $item->price        = (float)$row['price'];
        $item->quantity     = (int)$row['quantity'];
        $item->warehouse    = $row['warehouse_name'];
        $item->stockId      = $row['stock_id'];
        $item->supplierName = (string)($factory->get($row['supplier_code'])?->getName() ?? $row['supplier_code']);
        $item->isSched      = (bool)$row['is_sched'];
        $item->deliveryDays = (int)$row['delivery_days'];
        $item->multiplicity = (int)$row['multiplicity'];
        $item->unit         = 'шт.';
        $item->returnable   = false;
        $item->raw          = ['flags' => [], 'stock_id' => $row['stock_id']];
        $allResults[]       = $item;
    }

    // Шаг 6: API для недостающих
    $missingPairs = [];
    foreach ($allPairs as $pair) {
        $key = $pair['brand_norm'] . '|' . $pair['article_norm'];
        if (!isset($cachedPairs[$key])) {
            $missingPairs[] = $pair;
        }
    }

    $missingPairs = array_slice($missingPairs, 0, MAX_CROSS_API);

    if (!empty($missingPairs)) {
        $source .= ' + api';
        $mcurl = new MultiCurlExecutor(10, 8);

        foreach ($missingPairs as $pair) {
            foreach ($factory->all() as $supplier) {
                if (!$supplier->isAvailable()) continue;
                $req = $supplier->buildSearchRequest($pair['brand_norm'], $pair['article_norm']);
                if (!$req) continue;
                $key = $supplier->getCode() . '|' . $pair['brand_norm'] . '|' . $pair['article_norm'];
                $mcurl->addRequest($key, $req);
            }
        }

        $responses = $mcurl->executeAll();

        foreach ($missingPairs as $pair) {
            foreach ($factory->all() as $supplier) {
                if (!$supplier->isAvailable()) continue;
                $key = $supplier->getCode() . '|' . $pair['brand_norm'] . '|' . $pair['article_norm'];
                $resp = $responses[$key] ?? null;
                if (!$resp) continue;

                $items = $supplier->parseSearchResponse($resp, $pair['brand_norm'], $pair['article_norm']);
                foreach ($items as $item) {
                    $item->name = $pair['title'] ?: $item->name;
                    $allResults[] = $item;
                }
            }
        }
    }

    // Шаг 7: агрегация
    $aggregator   = new OfferAggregator(200, 1000);
    $groupedItems = $aggregator->aggregate($allResults);

    $builder = new ResultBuilder(800, 200, 1000);
    $result  = $builder->build(
        $groupedItems, $exactKey, $normBrand, $normArticle,
        $displayBrand, $displayArticle, [],
        ['price_min' => $filterPriceMin, 'price_max' => $filterPriceMax, 'brand' => $filterBrand],
        'default', 'default'
    );

    $analogGroups = $result['analogGroups'];

    unset($analogGroups[$exactKey]);
    foreach ($analogGroups as $_key => $_g) {
        if (
            BrandNormalizer::normalize($_g['brand']) === $normBrand
            && BrandNormalizer::normalizeArticle($_g['article']) === $normArticle
        ) {
            unset($analogGroups[$_key]);
        }
    }

    // Шаг 8: HTML
    ob_start();
    $ri = 0;
    if (!empty($analogGroups)):
    ?>
    <div class="supplier-list__header">
    <div class="sl-cell sl-cell--expand"></div>
    <div class="sl-cell sl-cell--brand">Бренд</div>
    <div class="sl-cell sl-cell--desc">Описание</div>
    <div class="sl-cell sl-cell--article">Артикул</div>
    <div class="sl-cell sl-cell--mult">Кратность</div>
    <div class="sl-cell sl-cell--stock">Наличие</div>
    <div class="sl-cell sl-cell--delivery">Доставка</div>
    <div class="sl-cell sl-cell--price">Цена</div>
    <div class="sl-cell sl-cell--order"></div>
    </div>
    <?php
    endif;
    foreach ($analogGroups as $group):
        $ri++;
        $inStock = $group['has_instock'];
        $rc = $inStock ? 'sl-row--instock' : 'sl-row--order';
        $pl = $group['min_price'] == $group['max_price']
            ? number_format($group['min_price'], 2, ',', ' ')
            : 'от ' . number_format($group['min_price'], 2, ',', ' ');
        $dq = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
        $ql = fmtQty($dq);
        $dl = fmtDeliveryHtml($group['min_delivery']);
    ?>
<div class="supplier-list__group">
<div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)" data-group="lazy-<?=$ri?>">
<div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div>
<div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div>
<div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div>
<div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div>
<div class="sl-cell sl-cell--stock"><?=$inStock?'<span class="sl-badge sl-badge--green">'.$ql.'</span>':'<span class="sl-badge sl-badge--yellow">'.$ql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$dl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div>
<div class="sl-cell sl-cell--order"></div>
</div>
<div class="sl-warehouses" id="wh-group-lazy-<?=$ri?>" style="display:none;">
<?php foreach ($group['warehouses'] as $wh):
    $priceBase = round((float)($wh['price_base'] ?? $wh['price']), 2);
    $priceDisplay = getDisplayPrice($priceBase);
    $stockDisplay = $wh['stock'] ?? '—';
    $retIcon = ($wh['returnable'] ?? false)
        ? '<span class="ret-icon ret-icon--yes" title="Возвратный">↻</span>'
        : '<span class="ret-icon ret-icon--no" title="Невозвратный">✕</span>';
    $wq = (int)$wh['qty'];
    $wql = fmtQty($wq);
    $wdl = fmtDeliveryHtml($wh['delivery']);
    $sourceTag = isManager() ? '<span class="source-tag">' . htmlspecialchars($wh['supplier'] ?? '') . '</span>' : '';
    $priceShow = isManager() ? $priceBase : $priceDisplay;
    $priceHtml = number_format($priceShow, 2, ',', ' ') . ' ₽';
    $priceBaseHtml = (isManager() && $priceDisplay !== $priceBase) ? '<div class="sl-price-base">Розница: ' . number_format($priceDisplay, 2, ',', ' ') . ' ₽</div>' : '';
?>
<div class="sl-warehouse-row <?=$wh['is_sched']?'sl-wh--order':'sl-wh--instock'?>">
<div class="sl-cell sl-cell--expand"><?=$retIcon?></div>
<div class="sl-cell sl-cell--brand"><?=$sourceTag?></div>
<div class="sl-cell sl-cell--desc"><span class="sl-wh-stock">📍 <?=htmlspecialchars($stockDisplay)?></span></div>
<div class="sl-cell sl-cell--mult"><?php if(($wh['multiplicity']??1)>1):?><span class="sl-mult-badge">×<?=$wh['multiplicity']?> <?=htmlspecialchars($wh['unit']??'шт.')?></span><?php else:?><span class="sl-mult-text"><?=htmlspecialchars($wh['unit']??'шт.')?></span><?php endif;?></div>
<div class="sl-cell sl-cell--stock"><?=$wh['is_sched']?'<span class="sl-badge sl-badge--yellow">'.$wql.'</span>':'<span class="sl-badge sl-badge--green">'.$wql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$wdl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$priceHtml?></strong><?=$priceBaseHtml?></div>
<div class="sl-cell sl-cell--order"><button class="btn btn--order-supplier btn--order-supplier-sm" onclick="event.stopPropagation();orderFromSupplier(this,'<?=htmlspecialchars($group['article'])?>','<?=htmlspecialchars($group['brand'])?>')">🛒</button></div>
</div>
<?php endforeach; ?>
</div></div>
<?php endforeach;
    $html = ob_get_clean();

    $response = [
        'success'         => true,
        'html'            => $html,
        'totalGroups'     => count($analogGroups),
        'totalWarehouses' => $result['totalWarehouses'] ?? 0,
        'ok'              => 1,
        'source'          => $source,
    ];

    $cache->set($cacheKey, $response, 120);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()]);
}