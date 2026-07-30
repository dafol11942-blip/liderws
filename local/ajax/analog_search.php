<?php
/**
 * analog_search.php (v3 — Этап 17.3)
 * Поток: нормализация → b_cross_index → b_supplier_stock → JSON
 * Холодный: b_cross_index пуст → UMAPI (1с) → INSERT → SQL
 */
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '30');
@ini_set('display_errors', 0);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';

$base = '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib';
require_once $base . '/Search/BrandNormalizer.php';
require_once $base . '/Search/SearchResultItem.php';
require_once $base . '/Search/SearchCacheManager.php';
require_once $base . '/Search/Stage2/OfferAggregator.php';
require_once $base . '/Search/Stage2/ResultBuilder.php';
require_once $base . '/Supplier/SupplierFactory.php';
require_once $base . '/Supplier/MoskvorechieConnector.php';
require_once $base . '/Supplier/RosskoConnector.php';
require_once $base . '/Supplier/PartKomConnector.php';
require_once $base . '/Supplier/AutoeuroConnector.php';
require_once $base . '/Supplier/BergConnector.php';
require_once $base . '/Supplier/IxoraConnector.php';
require_once $base . '/Supplier/ShateMConnector.php';
require_once $base . '/Supplier/TatpartsConnector.php';
require_once $base . '/Supplier/AutorussConnector.php';
require_once $base . '/Supplier/AutopiterConnector.php';

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;

header('Content-Type: application/json; charset=utf-8');

const UMAPI_BASE = 'https://api.umapi.ru/v2/cross/parts/Analogs/pro';
const UMAPI_KEY  = '52606cd0-b1fd-4a5e-a8e3-ad9fbef16435';

// ─── Вспомогательные функции ─────────────────────────────────

function getAjaxFactory(): \Lider\Supplier\SupplierFactory {
    $f = new \Lider\Supplier\SupplierFactory();
    $f->register(new \Lider\Supplier\MoskvorechieConnector(['API_KEY'=>'2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf']));
    $f->register(new \Lider\Supplier\RosskoConnector(['KEY1'=>'d6907f0f857524815255b74cda86fe9b','KEY2'=>'a514b4c11299686d7cfe8fd3563d1c58','DELIVERY_ID'=>'000000002','ADDRESS_ID'=>'71520']));
    $f->register(new \Lider\Supplier\BergConnector(['API_KEY'=>'9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36','ADDRESS_ID'=>31173]));
    $f->register(new \Lider\Supplier\AutoeuroConnector(['API_KEY'=>'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX','DELIVERY_KEY'=>'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA']));
    $f->register(new \Lider\Supplier\PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']));
    $f->register(new \Lider\Supplier\IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81611D','TIMEOUT'=>8]));
    $f->register(new \Lider\Supplier\TatpartsConnector());
    $f->register(new \Lider\Supplier\AutorussConnector(['LOGIN'=>'Lider-16@bk.ru','PASSWORD_MD5'=>'00fd3781d2cfdf0d971b57fa7397cfac']));
    $f->register(new \Lider\Supplier\AutopiterConnector(['USER_ID'=>'165286','PASSWORD'=>'LidGates16']));
    return $f;
}

function getSupplierName(string $code): string {
    return match($code) {
        'moskvorechie' => 'Москворечье', 'rossko' => 'Росско', 'berg' => 'Берг',
        'autoeuro' => 'Автоевро', 'partkom' => 'ПартКом', 'ixora' => 'Иксора',
        'tatparts' => 'ТатПартс', 'autoruss' => 'Авторусь', 'autopiter' => 'Автопитер',
        'shatem' => 'Шатем', default => $code,
    };
}

function fmtDeliveryHtml(array $del): string {
    $days = $del['days'] ?? 0;
    $approx = $del['is_approx'] ?? false;
    if (!empty($del['date_from'])) {
        $from = $del['date_from']; $to = $del['date_to'] ?? null; $deadline = $del['deadline'] ?? null;
        $today = date('Y-m-d'); $fromDate = date('Y-m-d', $from);
        $dayLabel = $fromDate === $today ? '<span class="sl-text-green">Сегодня</span>'
            : ($fromDate === date('Y-m-d', strtotime('+1 day')) ? '<span class="sl-text-amber">Завтра</span>' : date('d.m', $from));
        $timeStr = date('H:i', $from); if ($to) $timeStr .= '–' . date('H:i', $to);
        $html = $dayLabel . ' <span class="sl-delivery-time">' . $timeStr . '</span>';
        if ($deadline && $deadline > time()) $html .= ' <span class="sl-deadline">заказ до ' . date('H:i', $deadline) . '</span>';
        return $html;
    }
    if ($days === 0) return '<span class="sl-text-green">Сегодня</span>';
    if ($days === 1 && !$approx) return '<span class="sl-text-amber">Завтра</span>';
    $date = date('d.m', strtotime("+{$days} days"));
    $label = ($approx ? '≈ ' : '') . $date;
    return $approx ? '<span class="sl-text-muted">' . $label . '</span>' : $label;
}

function fmtQty(int $qty): string {
    if (isManager()) return $qty . ' шт.';
    if ($qty > 4) return 'Достаточно';
    return $qty . ' шт.';
}

function calcDelivery(\Lider\Search\SearchResultItem $item): array {
    $isSched = $item->isSched;
    $hours   = $item->deliveryPeriod ?? 0;
    $days    = $item->deliveryDays ?? 0;

    if ($isSched) {
        $d = $days > 0 ? $days : max(1, (int)ceil($hours / 24));
        return ['days' => $d, 'is_approx' => true];
    }

    if ($item->source === 'moskvorechie') {
        $flags   = (array)($item->raw['flags'] ?? []);
        $stockId = (string)($item->raw['stock_id'] ?? '');
        $now = time();
        if ($hours === 0 && in_array('pickup', $flags)) {
            $hms = (int)date('Hi');
            if ($hms < 1102) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 12:00'),'date_to'=>strtotime('today 14:00'),'deadline'=>strtotime('today 11:02')];
            elseif ($hms < 1402) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 15:00'),'date_to'=>strtotime('today 17:00'),'deadline'=>strtotime('today 14:02')];
            else return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 14:02')];
        }
        if ($stockId === '10') return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 07:02')];
        if ($hours > 0) {
            $ts = $now + $hours * 3600;
            $day = strtotime(date('Y-m-d', $ts));
            $h = (int)date('H', $ts);
            $wh = $h < 9 ? 7 : ($h < 12 ? 11 : 14);
            $wave = $day + $wh * 3600;
            return ['days'=>max(0,(int)ceil(($wave-strtotime('today',$now))/86400)),'is_approx'=>false,'date_from'=>$wave,'date_to'=>$wave+10800,'deadline'=>$wave+120];
        }
    }

    if ($item->source === 'berg') {
        if (!empty($item->raw['deliveryDateTo']) && !empty($item->raw['deliveryCheckout'])) {
            return ['days'=>$item->deliveryDays??0,'is_approx'=>false,'date_from'=>strtotime($item->raw['deliveryDateTo']),'date_to'=>strtotime($item->raw['deliveryDateTo'])+7200,'deadline'=>strtotime($item->raw['deliveryCheckout'])];
        }
    }

    if ($item->source === 'rossko') {
        $cutoff = $item->raw['deliveryCheckout'] ?? null;
        $from = $item->raw['deliveryDateFrom'] ?? null;
        $to = $item->raw['deliveryDateTo'] ?? null;
        if ($from && $to) {
            $r = ['days'=>$item->deliveryDays??0,'is_approx'=>false,'date_from'=>strtotime($from),'date_to'=>strtotime($to)];
            if ($cutoff) $r['deadline'] = strtotime($cutoff);
            return $r;
        }
    }

    if ($item->deliveryDays !== null) {
        $r = ['days' => $item->deliveryDays, 'is_approx' => false];
        if (!empty($item->raw['deliveryDateFrom'])) $r['date_from'] = strtotime($item->raw['deliveryDateFrom']);
        if (!empty($item->raw['deliveryDateTo']))   $r['date_to']   = strtotime($item->raw['deliveryDateTo']);
        if (!empty($item->raw['deliveryCheckout'])) $r['deadline']   = strtotime($item->raw['deliveryCheckout']);
        return $r;
    }
    if ($hours > 0) return ['days' => (int)ceil($hours / 24), 'is_approx' => false];
    return ['days' => 0, 'is_approx' => false];
}

function maskWarehouse(\Lider\Search\SearchResultItem $item): string {
    $realName = $item->warehouse ?: '—';
    if ($realName === '' || $realName === '—') return '—';
    if (isManager()) return $item->supplierName . ': ' . $realName;
    $factory = getAjaxFactory();
    $connector = $factory->get($item->source);
    if ($connector && method_exists($connector, 'maskWarehouseName')) {
        return $connector->maskWarehouseName($realName);
    }
    return $realName;
}

/**
 * Живой вызов UMAPI для холодного артикула + сохранение в b_cross_index
 * @return array кросс-пар [['article_norm','brand_norm','title'], ...]
 */
function umapiFetchAndSave(string $artNorm, string $brandNorm): array {
    $db     = \Bitrix\Main\Application::getConnection();
    $helper = $db->getSqlHelper();

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

    // Валидация
    $expectedToken = md5($q . '|' . $brand . '|' . $number . '|analog_v3');
    if ($token !== $expectedToken || $q === '' || $brand === '' || $number === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid params']);
        exit;
    }

    // Файловый кэш
    $cache = new SearchCacheManager('/search/ajax_analog', 300);
    $cacheKey = md5(implode('|', [$q, $brand, $number, $filterPriceMin, $filterPriceMax, $filterBrand, isManager() ? 'mgr' : 'usr', 'v3']));
    $cached = $cache->get($cacheKey);
    if (is_array($cached) && !empty($cached['ok'])) {
        echo json_encode($cached);
        exit;
    }

    // ─── 1. Нормализация ─────────────────────────────────────
    $normBrand   = BrandNormalizer::normalize($brand);
    $normArticle = BrandNormalizer::normalizeArticle($number);
    $displayBrand   = BrandNormalizer::displayBrand($brand);
    $displayArticle = $number;
    $exactKey = $normBrand . '|' . $normArticle;

    $db     = \Bitrix\Main\Application::getConnection();
    $helper = $db->getSqlHelper();

    // ─── 2. b_cross_index (мгновенно) ─────────────────────────
    $crossRows = $db->query(
        "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
         FROM b_cross_index
         WHERE article_orig_norm = '" . $helper->forSql($normArticle) . "'
           AND brand_orig_norm   = '" . $helper->forSql($normBrand) . "'
         ORDER BY weight DESC"
    )->fetchAll();

    // ─── 3. Холодный старт: UMAPI на лету ────────────────────
    if (empty($crossRows)) {
        $liveCrosses = umapiFetchAndSave($normArticle, $normBrand);
        // Перезапрашиваем b_cross_index
        $crossRows = $db->query(
            "SELECT article_cross_norm, brand_cross_norm, title_keywords, weight
             FROM b_cross_index
             WHERE article_orig_norm = '" . $helper->forSql($normArticle) . "'
               AND brand_orig_norm   = '" . $helper->forSql($normBrand) . "'
             ORDER BY weight DESC"
        )->fetchAll();
    }

    // ─── 4. Собираем все article_norm для запроса в b_supplier_stock ──
    $allArticleNorms = [$normArticle];
    $crossMap = []; // article_norm → [brand_norm, title]
    foreach ($crossRows as $cr) {
        $an = $cr['article_cross_norm'];
        $allArticleNorms[] = $an;
        if (!isset($crossMap[$an])) {
            $crossMap[$an] = ['brand_norm' => $cr['brand_cross_norm'], 'title' => $cr['title_keywords']];
        }
    }
    $allArticleNorms = array_unique($allArticleNorms);

    // ─── 5. Запрос в b_supplier_stock (мгновенно) ─────────────
    if (empty($allArticleNorms)) {
        echo json_encode(['success' => false, 'error' => 'No data', 'ok' => 1]);
        exit;
    }

    $inClause = "'" . implode("','", array_map(fn($a) => $helper->forSql($a), $allArticleNorms)) . "'";
    $stockRows = $db->query(
        "SELECT supplier_code, article, brand, name, price, quantity,
                warehouse_name, warehouse_code, stock_id, delivery_days,
                is_sched, multiplicity, article_normalized, brand_normalized,
                source_type, source_updated
         FROM b_supplier_stock
         WHERE article_normalized IN ($inClause) AND is_active = 1
         ORDER BY is_sched ASC, price ASC"
    )->fetchAll();

    // ─── 6. Строим SearchResultItem[] ─────────────────────────
    $allResults = [];
    foreach ($stockRows as $row) {
        $item = new \Lider\Search\SearchResultItem();
        $item->source       = $row['supplier_code'];
        $item->article      = $row['article'];
        $item->brand        = $row['brand'];
        $item->name         = $row['name'] ?: ($crossMap[$row['article_normalized']]['title'] ?? '');
        $item->price        = (float)$row['price'];
        $item->quantity     = (int)$row['quantity'];
        $item->warehouse    = $row['warehouse_name'];
        $item->stockId      = $row['stock_id'];
        $item->supplierName = getSupplierName($row['supplier_code']);
        $item->isSched      = (bool)$row['is_sched'];
        $item->deliveryDays = (int)$row['delivery_days'];
        $item->multiplicity = (int)$row['multiplicity'];
        $item->unit         = 'шт.';
        $item->returnable   = false;
        $item->raw          = [];
        $allResults[]       = $item;
    }

    // ─── 7. Агрегация и сборка ────────────────────────────────
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

    // Убираем дубликат искомого артикула из аналогов
    unset($analogGroups[$exactKey]);
    foreach ($analogGroups as $_key => $_g) {
        if (
            BrandNormalizer::normalize($_g['brand']) === $normBrand
            && BrandNormalizer::normalizeArticle($_g['article']) === $normArticle
        ) {
            unset($analogGroups[$_key]);
        }
    }

    $factoryForMask = getAjaxFactory();

    // ─── 8. HTML ──────────────────────────────────────────────
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
    if (!isManager()) {
        $connector = $factoryForMask->get($wh['source'] ?? '');
        if ($connector && $stockDisplay !== 'Под заказ' && $stockDisplay !== '—') {
            $stockDisplay = $connector->maskWarehouseName($stockDisplay);
        }
    } else {
        if ($stockDisplay !== 'Под заказ' && $stockDisplay !== '—') {
            $stockDisplay = ($wh['supplier'] ?? '') . ': ' . $stockDisplay;
        }
    }
    $retIcon = $wh['returnable']
        ? '<span class="ret-icon ret-icon--yes" title="Возвратный">↻</span>'
        : '<span class="ret-icon ret-icon--no" title="Невозвратный">✕</span>';
    $wq = (int)$wh['qty'];
    $wql = fmtQty($wq);
    $wdl = fmtDeliveryHtml($wh['delivery']);
    $sourceTag = isManager() ? '<span class="source-tag source-tag--' . htmlspecialchars($wh['source']) . '">' . htmlspecialchars($wh['supplier']) . '</span>' : '';
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

    // ─── 9. Ответ ─────────────────────────────────────────────
    $response = [
        'success'         => true,
        'html'            => $html,
        'totalGroups'     => count($analogGroups),
        'totalWarehouses' => $result['totalWarehouses'],
        'ok'              => 1,
        'source'          => 'b_cross_index',
    ];

    $cache->set($cacheKey, $response, 300);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()]);
}