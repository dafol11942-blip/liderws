<?php
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/init_pricing.php");
use Lider\Search\BrandNormalizer;

$APPLICATION->SetTitle("Поиск");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$q = trim($_REQUEST['q'] ?? '');
$selectedBrand = trim($_REQUEST['brand'] ?? '');
$selectedNumber = trim($_REQUEST['number'] ?? '');
$brandKey = $_REQUEST['brand_key'] ?? '';
$iblockId = 42;
$filterPriceMin = (int)($_REQUEST['price_min'] ?? 0);
$filterPriceMax = (int)($_REQUEST['price_max'] ?? 0);
$filterBrand = trim($_REQUEST['filter_brand'] ?? '');

function formatQty($qty, $exact = false) { if (isManager()) return $qty . " шт."; if ($qty > 4) return 'Достаточно'; return $qty . ' шт.'; }

function calcDelivery(\Lider\Search\SearchResultItem $item): array {
    $isSched = $item->isSched; $hours = $item->deliveryPeriod ?? 0; $days = $item->deliveryDays ?? 0;
    if ($isSched) { $d = $days > 0 ? $days : max(1, (int)ceil($hours / 24)); return ['days' => $d, 'is_approx' => true]; }
    if ($item->source === 'moskvorechie') {
        $flags = (array)($item->raw['flags'] ?? []); $stockId = (string)($item->raw['stock_id'] ?? ''); $now = time();
        if ($hours === 0 && in_array('pickup', $flags)) {
            $hms = (int)date('Hi');
            if ($hms < 1102) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 12:00'),'date_to'=>strtotime('today 14:00'),'deadline'=>strtotime('today 11:02')];
            elseif ($hms < 1402) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 15:00'),'date_to'=>strtotime('today 17:00'),'deadline'=>strtotime('today 14:02')];
            else return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 14:02')];
        }
        if ($stockId === '10') return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 07:02')];
        if ($hours > 0) { $deliveryTs = $now + $hours * 3600; $deliveryDay = strtotime(date('Y-m-d',$deliveryTs)); $h = (int)date('H',$deliveryTs); $waveHour = $h < 9 ? 7 : ($h < 12 ? 11 : 14); $waveTs = $deliveryDay + $waveHour * 3600; $resultDays = max(0,(int)ceil(($waveTs - strtotime("today",$now)) / 86400)); return ['days'=>$resultDays,'is_approx'=>false,'date_from'=>$waveTs,'date_to'=>$waveTs+3*3600,'deadline'=>$waveTs+120]; }
    }
    if ($item->deliveryDays !== null) { $result = ['days'=>$item->deliveryDays,'is_approx'=>false]; $raw = $item->raw; if(!empty($raw['deliveryDateFrom']))$result['date_from']=strtotime($raw['deliveryDateFrom']); if(!empty($raw['deliveryDateTo']))$result['date_to']=strtotime($raw['deliveryDateTo']); if(!empty($raw['deliveryCheckout']))$result['deadline']=strtotime($raw['deliveryCheckout']); return $result; }
    if ($hours > 0) return ['days'=>(int)ceil($hours/24),'is_approx'=>false];
    return ['days'=>0,'is_approx'=>false];
}

function maskWarehouse(\Lider\Search\SearchResultItem $item): string {
    $realName = $item->warehouse ?: '—'; if ($realName === '' || $realName === '—') return '—';
    if (isManager()) return $item->supplierName . ': ' . $realName;
    $connector = getSupplierFactory()->get($item->source);
    if ($connector && method_exists($connector, 'maskWarehouseName')) return $connector->maskWarehouseName($realName);
    return $realName;
}

function formatDelivery(array $delivery): string {
    $days = $delivery['days']; $approx = $delivery['is_approx'];
    if (!empty($delivery['date_from'])) { $from=$delivery['date_from']; $to=$delivery['date_to']??null; $deadline=$delivery['deadline']??null; $today=date('Y-m-d'); $fromDate=date('Y-m-d',$from); $dayLabel=$fromDate===$today?'<span class="sl-text-green">Сегодня</span>':($fromDate===date('Y-m-d',strtotime('+1 day'))?'<span class="sl-text-amber">Завтра</span>':date('d.m',$from)); $timeStr=date('H:i',$from); if($to)$timeStr.='–'.date('H:i',$to); $html=$dayLabel.' <span class="sl-delivery-time">'.$timeStr.'</span>'; if($deadline&&$deadline>time())$html.=' <span class="sl-deadline">заказ до '.date('H:i',$deadline).'</span>'; return $html; }
    if ($days===0) return '<span class="sl-text-green">Сегодня</span>';
    if ($days===1&&!$approx) return '<span class="sl-text-amber">Завтра</span>';
    $date=date('d.m',strtotime("+{$days} days")); $label=($approx?'≈ ':'').$date;
    return $approx?'<span class="sl-text-muted">'.$label.'</span>':$label;
}
?>
<div class="container">
<div class="catalog-toolbar">
<span class="catalog-toolbar__count">
<?php if ($q): ?>Поиск: <strong><?=htmlspecialchars($q)?></strong><?php if($selectedBrand):?> → Бренд: <strong><?=htmlspecialchars($selectedBrand)?></strong><?php endif;?>
<?php else: ?>Поиск запчастей<?php endif; ?>
</span>
<?php if(isManager()):?><span class="manager-badge">🔧 Менеджер (закупочные цены)</span><?php endif;?>
</div>

<?php if ($q):
if (empty($selectedBrand)):
    // === ВЫБОР БРЕНДА ===
    $normQ = BrandNormalizer::normalizeArticle($q);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/UmapiClient.php';
    $umapi = new \Lider\Search\UmapiClient('52606cd0-b1fd-4a5e-a8e3-ad9fbef16435');
    $umapiBrands = $umapi->refineBrandData($q);

    $brandMap = [];
    foreach ($umapiBrands as $br) {
        $brBrand = trim((string)($br['brand'] ?? ''));
        $brArt   = trim((string)($br['article'] ?? ''));
        if ($brBrand === '' || $brArt === '') continue;
        $key = \Lider\Search\BrandNormalizer::groupKey($brBrand, $brArt);
        $brandMap[$key] = [
            'brands'      => ['umapi' => $brBrand],
            'articles'    => ['umapi' => $brArt],
            'article_nr'  => $brArt,
            'description' => $br['title'] ?? '',
            'sources'     => ['umapi'],
        ];
    }

    $bmc = new \Lider\Search\SearchCacheManager('/search/supplier', 900);
    $bmc->set('brandmap_' . md5(mb_strtolower($q)), $brandMap, 900);

    $exactBrands = []; $analogBrands = [];
    foreach ($brandMap as $key => $info) {
        $isExact = (\Lider\Search\BrandNormalizer::normalizeArticle($info["article_nr"]) === $normQ);
        $displayBrand = \Lider\Search\BrandNormalizer::displayBrand((string)(reset($info["brands"]) ?: ''));
        $displayArticle = \Lider\Search\BrandNormalizer::pickDisplayArticle($info["articles"] ?? [], $info["article_nr"] ?? '');
        $entry = ["brand"=>$displayBrand,"article"=>$displayArticle,"article_nr"=>$displayArticle,"description"=>$info["description"],"sources"=>$info["sources"],"key"=>$key];
        if ($isExact) $exactBrands[] = $entry; else $analogBrands[] = $entry;
    }
    $sortByOffers = fn($a,$b) => count($b['sources']??[]) <=> count($a['sources']??[]) ?: strcmp(mb_strtolower($a['brand']??''), mb_strtolower($b['brand']??''));
    usort($exactBrands, $sortByOffers); usort($analogBrands, $sortByOffers);

    global $arrFilter;
    $arrFilter = [['LOGIC'=>'OR',['%NAME'=>$q],['PROPERTY_CML2_ARTICLE'=>$q],['%PROPERTY_CML2_ARTICLE'=>$q],['%DETAIL_TEXT'=>$q],['PROPERTY_CML2_MANUFACTURER'=>$q],['%PROPERTY_CML2_MANUFACTURER'=>$q]]];
    $localCache = new \Lider\Search\SearchCacheManager('/search/local',600);
    $lck = 'local_'.md5(mb_strtolower($q)); $ccl = $localCache->get($lck);
    if ($ccl !== null && isset($ccl['count'])) { $localCount = (int)$ccl['count']; }
    else { $localRes = CIBlockElement::GetList([], array_merge(['IBLOCK_ID'=>$iblockId,'ACTIVE'=>'Y'], $arrFilter[0]), false, false, ['ID']); $localCount = $localRes->SelectedRowsCount(); $localCache->set($lck, ['count'=>$localCount], 600); }
?>
<?php if ($localCount > 0): ?><h2 class="search-section-title">🔵 На нашем складе</h2>
<?php global $arrFilter; $APPLICATION->IncludeComponent("bitrix:catalog.section","lider_style",["IBLOCK_TYPE"=>"1c_catalog","IBLOCK_ID"=>$iblockId,"INCLUDE_SUBSECTIONS"=>"Y","SHOW_ALL_WO_SECTION"=>"Y","ELEMENT_SORT_FIELD"=>"sort","ELEMENT_SORT_ORDER"=>"asc","FILTER_NAME"=>"arrFilter","PRICE_CODE"=>["Ручная розничная цена"],"PROPERTY_CODE"=>["CML2_ARTICLE","CML2_MANUFACTURER","IN_STOCK"],"PAGE_ELEMENT_COUNT"=>"12","HIDE_NOT_AVAILABLE"=>"Y","BASKET_URL"=>"/personal/cart/","CACHE_TYPE"=>"A","CACHE_TIME"=>"300","SET_TITLE"=>"N"],false); ?>
<?php endif; ?>

<?php if (!empty($exactBrands)): ?>
<h2 class="search-section-title">🟠 Выберите бренд для артикула «<?=htmlspecialchars($q)?>»</h2>
<p class="search-hint">Под этим артикулом у разных производителей могут быть <strong>разные детали</strong>. Выберите нужный бренд для просмотра цен.</p>
<div class="brand-table">
<div class="brand-table__header">
<div class="brand-table__cell brand-table__cell--brand">Производитель</div>
<div class="brand-table__cell brand-table__cell--article">Артикул</div>
<div class="brand-table__cell brand-table__cell--desc">Описание детали</div>
<div class="brand-table__cell brand-table__cell--action"></div>
</div>
<?php foreach ($exactBrands as $br): ?>
<div class="brand-table__row">
<div class="brand-table__cell brand-table__cell--brand"><strong><?=htmlspecialchars($br['brand'])?></strong></div>
<div class="brand-table__cell brand-table__cell--article"><code><?=htmlspecialchars($br['article'])?></code></div>
<div class="brand-table__cell brand-table__cell--desc"><?=htmlspecialchars($br['description']?:'—')?></div>
<div class="brand-table__cell brand-table__cell--action"><a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn btn--brand-select">Выбрать →</a></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($analogBrands)): ?>
<details class="analogs-details"><summary class="analogs-summary">📋 Аналоги и кросс-номера (<?=count($analogBrands)?>)</summary>
<div class="brand-table brand-table--analogs">
<?php foreach ($analogBrands as $br): ?>
<div class="brand-table__row">
<div class="brand-table__cell brand-table__cell--brand"><?=htmlspecialchars($br['brand'])?></div>
<div class="brand-table__cell brand-table__cell--article"><code><?=htmlspecialchars($br['article'])?></code></div>
<div class="brand-table__cell brand-table__cell--desc"><?=htmlspecialchars($br['description']?:'—')?></div>
<div class="brand-table__cell brand-table__cell--action"><a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn btn--brand-select btn--brand-select-sm">Выбрать →</a></div>
</div>
<?php endforeach; ?>
</div></details>
<?php endif; ?>

<?php if (empty($exactBrands) && empty($analogBrands) && $localCount === 0): ?>
<div style="text-align:center;padding:60px 20px;color:var(--gray);"><div style="font-size:48px;margin-bottom:12px;">🔍</div><p>По артикулу «<?=htmlspecialchars($q)?>» ничего не найдено</p></div>
<?php endif; ?>

<?php
else:
    // === СТРАНИЦА РЕЗУЛЬТАТОВ ===
    require __DIR__ . "/stage2_search_v2.php";
//    if (!empty($verifyTaskHash)) include __DIR__ . "/_hybrid_notice.php";
?>
<div class="brand-back"><a href="?q=<?=urlencode($q)?>" class="back-link">← Назад к выбору бренда</a></div>
<h2 class="search-section-title"><?=htmlspecialchars($selectedBrand)?><span class="search-section-badge"><?=htmlspecialchars($searchNumber)?></span></h2>

<?php if ($totalGroups > 0):

// Блоки лучших предложений
$bestPrice = null; $bestPriceGroup = null;
foreach (array_merge($exactGroups, $analogGroups) as $g) {
    foreach ($g['warehouses'] as $w) {
        if (!$w['is_sched'] && ($bestPrice === null || $w['price'] < $bestPrice)) {
            $bestPrice = $w['price']; $bestPriceGroup = ['group' => $g, 'wh' => $w];
        }
    }
}
$bestDelivery = null; $bestDeliveryGroup = null;
foreach (array_merge($exactGroups, $analogGroups) as $g) {
    foreach ($g['warehouses'] as $w) {
        $d = $w['delivery']['days'] ?? 999;
        if (!$w['is_sched'] && ($bestDelivery === null || $d < $bestDelivery)) {
            $bestDelivery = $d; $bestDeliveryGroup = ['group' => $g, 'wh' => $w];
        }
    }
}
?>
<?php if ($bestPriceGroup || $bestDeliveryGroup): ?>
<div class="best-offers">
    <?php if ($bestPriceGroup): ?>
    <div class="best-offer-card">
        <div class="best-offer-badge best-offer-badge--price">🏷 САМАЯ НИЗКАЯ ЦЕНА</div>
        <div class="best-offer-body">
            <div class="best-offer-brand"><?=htmlspecialchars($bestPriceGroup['group']['brand'])?> / <?=htmlspecialchars($bestPriceGroup['group']['article'])?></div>
            <div class="best-offer-desc"><?=htmlspecialchars($bestPriceGroup['group']['description'])?></div>
            <div class="best-offer-price"><?=number_format($bestPriceGroup['wh']['price'], 2, ',', ' ')?> ₽</div>
            <div class="best-offer-meta">
                <span><?=formatQty($bestPriceGroup['wh']['qty'])?></span>
                <span><?=formatDelivery($bestPriceGroup['wh']['delivery'])?></span>
                <?php if (!$bestPriceGroup['wh']['returnable']): ?><span class="best-offer-noreturn">[Возвраты не принимаются]</span><?php endif; ?>
            </div>
            <button class="btn btn--order-supplier" onclick="orderFromSupplier(this,'<?=htmlspecialchars($bestPriceGroup['group']['article'])?>','<?=htmlspecialchars($bestPriceGroup['group']['brand'])?>')">В корзину</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($bestDeliveryGroup && $bestDeliveryGroup !== $bestPriceGroup): ?>
    <div class="best-offer-card">
        <div class="best-offer-badge best-offer-badge--delivery">🚚 НАИМЕНЬШИЙ СРОК ДОСТАВКИ</div>
        <div class="best-offer-body">
            <div class="best-offer-brand"><?=htmlspecialchars($bestDeliveryGroup['group']['brand'])?> / <?=htmlspecialchars($bestDeliveryGroup['group']['article'])?></div>
            <div class="best-offer-desc"><?=htmlspecialchars($bestDeliveryGroup['group']['description'])?></div>
            <div class="best-offer-price"><?=number_format($bestDeliveryGroup['wh']['price'], 2, ',', ' ')?> ₽</div>
            <div class="best-offer-meta">
                <span><?=formatQty($bestDeliveryGroup['wh']['qty'])?></span>
                <span><?=formatDelivery($bestDeliveryGroup['wh']['delivery'])?></span>
            </div>
            <button class="btn btn--order-supplier" onclick="orderFromSupplier(this,'<?=htmlspecialchars($bestDeliveryGroup['group']['article'])?>','<?=htmlspecialchars($bestDeliveryGroup['group']['brand'])?>')">В корзину</button>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<p class="search-hint">Найдено <strong><?=$totalGroups?></strong> позиций (<span class="wh-count"><?=$totalWarehouses?></span> предложений)<span class="search-legend"><span class="legend-item"><span class="legend-icon legend-icon--return">↻</span> возвратный</span><span class="legend-item"><span class="legend-icon legend-icon--noreturn">✕</span> невозвратный</span></span></p>

<?php
// Искомый номер
if (!empty($exactGroups)):
    $renderWhRows = function($group) {
        ob_start();
        foreach ($group['warehouses'] as $wh):
            $stockDisplay = $wh['stock'] ?? '—';
            $retIcon = $wh['returnable'] ? '<span class="ret-icon ret-icon--yes" title="Возвратный">↻</span>' : '<span class="ret-icon ret-icon--no" title="Невозвратный">✕</span>';
            $wq = (int)$wh['qty']; $wql = formatQty($wq); $wdl = formatDelivery($wh['delivery']);
            $sourceTag = isManager() ? '<span class="source-tag source-tag--'.htmlspecialchars($wh['source']).'">'.htmlspecialchars($wh['supplier']).'</span>' : '';
            $priceShow = isManager() ? $wh['price_base'] : $wh['price'];
?>
<div class="sl-warehouse-row <?=$wh['is_sched']?'sl-wh--order':'sl-wh--instock'?>">
<div class="sl-cell sl-cell--expand"><?=$retIcon?></div>
<div class="sl-cell sl-cell--brand"><?=$sourceTag?></div>
<div class="sl-cell sl-cell--desc"><span class="sl-wh-stock">📍 <?=htmlspecialchars($stockDisplay)?></span><?php if(!$wh['returnable']):?> <span class="sl-wh-noreturn">[Возвраты не принимаются]</span><?php endif;?></div>
<div class="sl-cell sl-cell--weight"><?=htmlspecialchars($wh['weight'] ?? '—')?></div>
<div class="sl-cell sl-cell--stock"><?=$wh['is_sched']?'<span class="sl-badge sl-badge--yellow">'.$wql.'</span>':'<span class="sl-badge sl-badge--green">'.$wql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$wdl?></div>
<div class="sl-cell sl-cell--price"><strong><?=number_format($priceShow, 2, ',', ' ')?> ₽</strong></div>
<div class="sl-cell sl-cell--order"><button class="btn btn--order-supplier btn--order-supplier-sm" onclick="event.stopPropagation();orderFromSupplier(this,'<?=htmlspecialchars($group['article'])?>','<?=htmlspecialchars($group['brand'])?>')">🛒</button></div>
</div>
<?php endforeach;
        return ob_get_clean();
    };
?>
<div class="result-block result-block--exact">
<div class="result-block__header"><span class="result-block__badge badge--exact">📍 Искомый номер</span><span class="result-block__count"><?=count($exactGroups)?> поз.</span></div>
<div class="supplier-list">
<div class="supplier-list__header">
<div class="sl-cell sl-cell--expand"></div>
<div class="sl-cell sl-cell--brand">Бренд</div>
<div class="sl-cell sl-cell--desc">Описание / Склад</div>
<div class="sl-cell sl-cell--article">Артикул</div>
<div class="sl-cell sl-cell--weight">Вес</div>
<div class="sl-cell sl-cell--stock">Кол.</div>
<div class="sl-cell sl-cell--delivery">Доставка</div>
<div class="sl-cell sl-cell--price">Цена</div>
<div class="sl-cell sl-cell--order"></div>
</div>
<?php foreach ($exactGroups as $group):
    $inStock = $group['has_instock']; $rc = $inStock ? 'sl-row--instock' : 'sl-row--order';
    $pl = $group['min_price'] == $group['max_price'] ? number_format($group['min_price'], 2, ',', ' ') : 'от ' . number_format($group['min_price'], 2, ',', ' ');
    $dq = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
    $ql = formatQty($dq); $dl = formatDelivery($group['min_delivery']);
?>
<div class="supplier-list__group">
<div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)">
<div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div>
<div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div>
<div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div>
<div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div>
<div class="sl-cell sl-cell--weight"><?=htmlspecialchars($group['weight'] ?? '—')?></div>
<div class="sl-cell sl-cell--stock"><?=$inStock?'<span class="sl-badge sl-badge--green">'.$ql.'</span>':'<span class="sl-badge sl-badge--yellow">'.$ql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$dl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div>
<div class="sl-cell sl-cell--order"></div>
</div>
<div class="sl-warehouses" style="display:none;"><?=$renderWhRows($group)?></div>
</div>
<?php endforeach; ?>
</div></div>
<?php endif; ?>

<?php
// Аналоги — сгруппированы по брендам
if (!empty($analogGroups)):
    $analogByBrand = [];
    foreach ($analogGroups as $g) {
        $bn = mb_strtolower($g['brand']);
        if (!isset($analogByBrand[$bn])) $analogByBrand[$bn] = ['brand' => $g['brand'], 'groups' => [], 'totalWarehouses' => 0];
        $analogByBrand[$bn]['groups'][] = $g;
        $analogByBrand[$bn]['totalWarehouses'] += count($g['warehouses']);
    }
?>
<div class="result-block result-block--analog">
<div class="result-block__header"><span class="result-block__badge badge--analog">🔄 Аналоги</span><span class="result-block__count"><?=count($analogGroups)?> поз.</span></div>

<?php foreach ($analogByBrand as $brandGroup): ?>
<div class="analog-brand-group">
<div class="analog-brand-header">
    <strong><?=htmlspecialchars($brandGroup['brand'])?></strong>
    <span class="analog-brand-count"><?=count($brandGroup['groups'])?> арт. · <?=$brandGroup['totalWarehouses']?> скл.</span>
</div>
<div class="supplier-list">
<div class="supplier-list__header">
<div class="sl-cell sl-cell--expand"></div>
<div class="sl-cell sl-cell--brand">Бренд</div>
<div class="sl-cell sl-cell--desc">Описание</div>
<div class="sl-cell sl-cell--article">Артикул</div>
<div class="sl-cell sl-cell--weight">Вес</div>
<div class="sl-cell sl-cell--stock">Наличие</div>
<div class="sl-cell sl-cell--delivery">Доставка</div>
<div class="sl-cell sl-cell--price">Цена</div>
<div class="sl-cell sl-cell--order"></div>
</div>
<?php
$shown = 0; $maxShow = 3;
foreach ($brandGroup['groups'] as $gi => $group):
    $hidden = $gi >= $maxShow ? ' style="display:none"' : '';
    $shown++;
    $inStock = $group['has_instock']; $rc = $inStock ? 'sl-row--instock' : 'sl-row--order';
    $pl = $group['min_price'] == $group['max_price'] ? number_format($group['min_price'], 2, ',', ' ') : 'от ' . number_format($group['min_price'], 2, ',', ' ');
    $dq = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
    $ql = formatQty($dq); $dl = formatDelivery($group['min_delivery']);
?>
<div class="supplier-list__group analog-item"<?=$hidden?> data-analog-key="<?=htmlspecialchars(mb_strtolower($group['brand'].'|'.$group['article']))?>">
<div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)">
<div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div>
<div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div>
<div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div>
<div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div>
<div class="sl-cell sl-cell--weight"><?=htmlspecialchars($group['weight'] ?? '—')?></div>
<div class="sl-cell sl-cell--stock"><?=$inStock?'<span class="sl-badge sl-badge--green">'.$ql.'</span>':'<span class="sl-badge sl-badge--yellow">'.$ql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$dl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div>
<div class="sl-cell sl-cell--order"></div>
</div>
<div class="sl-warehouses" style="display:none;"><?=$renderWhRows($group)?></div>
</div>
<?php endforeach; ?>
<?php if (count($brandGroup['groups']) > $maxShow): ?>
<div class="analog-show-more" onclick="showMoreAnalogs(this)">Показать еще <?=count($brandGroup['groups']) - $maxShow?> товаров</div>
<?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="result-block result-block--analog"><div class="result-block__header"><span class="result-block__badge badge--analog">🔄 Аналоги</span><span class="result-block__count">0 поз.</span></div><div class="supplier-list"></div></div>
<?php endif; ?>

<?php else: ?>
<div style="text-align:center;padding:40px;color:var(--gray);"><p>Нет доступных предложений</p><a href="?q=<?=urlencode($q)?>" class="back-link">← Назад</a></div>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
<div style="text-align:center;padding:80px 20px;color:var(--gray);"><div style="font-size:48px;margin-bottom:12px;">🔍</div><p>Введите артикул, название запчасти или VIN-номер</p></div>
<?php endif; ?>
</div>

<style>
.manager-badge{display:inline-block;background:#fef3c7;color:#92400e;font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;}
.sl-price-base{font-size:11px;color:#9ca3af;margin-top:2px;}
.ret-icon{display:inline-block;font-size:14px;font-weight:700;width:22px;height:22px;line-height:22px;text-align:center;border-radius:50%;cursor:help;}
.ret-icon--yes{background:#d1fae5;color:#065f46;}
.ret-icon--no{background:#fee2e2;color:#991b1b;}
.search-legend{display:inline-flex;gap:12px;margin-left:12px;font-size:12px;color:#6b7280;}
.legend-item{display:inline-flex;align-items:center;gap:4px;}
.legend-icon{display:inline-block;font-size:12px;font-weight:700;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;}
.legend-icon--return{background:#d1fae5;color:#065f46;}
.legend-icon--noreturn{background:#fee2e2;color:#991b1b;}
.result-block{margin:0 0 32px;}.result-block__header{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.result-block__badge{font-size:15px;font-weight:700;padding:6px 16px;border-radius:8px;}
.badge--exact{background:#d1fae5;color:#065f46;}.badge--analog{background:#e0e7ff;color:#3730a3;}
.result-block__count{font-size:13px;color:#6b7280;}
.result-block--exact .supplier-list{border-color:#059669;box-shadow:0 0 0 2px rgba(5,150,105,0.08);}
.result-block--analog .supplier-list{border-color:#c7d2fe;}
.brand-table{margin:20px 0 32px;border:1px solid #e8ecf0;border-radius:12px;overflow:hidden;background:#fff;}
.brand-table__header{display:flex;background:#f8f9fb;padding:12px 20px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #e8ecf0;}
.brand-table__row{display:flex;padding:14px 20px;border-bottom:1px solid #f3f4f6;align-items:center;transition:background 0.15s;}
.brand-table__row:last-child{border-bottom:none;}.brand-table__row:hover{background:#fafbfc;}
.brand-table__cell{padding:0 12px;}.brand-table__cell--brand{flex:0 0 150px;font-size:15px;color:#1a1a2e;}
.brand-table__cell--article{flex:0 0 150px;}.brand-table__cell--article code{font-family:'Fira Code',monospace;font-size:13px;color:#6b7280;background:#f3f4f6;padding:3px 8px;border-radius:4px;}
.brand-table__cell--desc{flex:1;font-size:14px;color:#374151;line-height:1.4;}
.brand-table__cell--action{flex:0 0 120px;text-align:right;}
.source-tag{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;white-space:nowrap;}
.source-tag--moskvorechie{background:#e0f2fe;color:#0369a1;}.source-tag--rossko{background:#fce7f3;color:#9d174d;}
.source-tag--shatem{background:#fef3c7;color:#92400e;}.source-tag--berg{background:#d1fae5;color:#065f46;}
.source-tag--autoeuro{background:#fce4ec;color:#c62828;}.source-tag--partkom{background:#e8f5e9;color:#2e7d32;}
.source-tag--ixora{background:#ede7f6;color:#5e35b1;}
.source-tag--tatparts{background:#e0f7fa;color:#006064;}
.source-tag--autoruss{background:#fff7ed;color:#c2410c;}
.source-tag--autopiter{background:#e8eaf6;color:#283593;}
.btn--brand-select{display:inline-block;padding:8px 20px;background:#0066ff;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;transition:background 0.2s;white-space:nowrap;}
.btn--brand-select:hover{background:#0052cc;color:#fff;text-decoration:none;}.btn--brand-select-sm{padding:6px 14px;font-size:12px;}
.analogs-details{margin:0 0 32px;}.analogs-summary{cursor:pointer;font-size:14px;font-weight:600;color:#0066ff;padding:10px 0;user-select:none;}
.analogs-summary:hover{color:#0052cc;}.brand-table--analogs{margin-top:8px;}.brand-table--analogs .brand-table__row{background:#fdfdff;}
.supplier-list{border:1px solid #e8ecf0;border-radius:12px;overflow:hidden;background:#fff;}
.supplier-list__header{display:flex;align-items:center;padding:12px 16px;background:#f8f9fb;border-bottom:2px solid #e8ecf0;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.3px;}
.supplier-list__group{border-bottom:1px solid #e8ecf0;}.supplier-list__group:last-child{border-bottom:none;}
.supplier-list__row{display:flex;align-items:center;padding:14px 16px;cursor:pointer;transition:background 0.15s;border-left:3px solid transparent;}
.supplier-list__row:hover{background:#fafbfc;}.sl-main-row{user-select:none;}
.sl-row--instock{border-left-color:#059669;}.sl-row--order{border-left-color:#f59e0b;}
.sl-row--instock.sl-main-row:hover{background:#f0fdf4;}.sl-row--order.sl-main-row:hover{background:#fffbeb;}
.sl-cell{padding:0 10px;}.sl-cell--expand{flex:0 0 30px;text-align:center;}.sl-cell--brand{flex:0 0 140px;font-size:14px;color:#1a1a2e;}
.sl-cell--desc{flex:2;min-width:0;}.sl-cell--article{flex:0 0 130px;}.sl-cell--stock{flex:0 0 110px;text-align:center;}
.sl-cell--delivery{flex:0 0 145px;text-align:center;font-size:13px;color:#374151;}.sl-cell--price{flex:0 0 140px;text-align:right;}
.sl-cell--weight{flex:0 0 60px;text-align:center;font-size:13px;color:#6b7280;}
.sl-cell--order{flex:0 0 50px;text-align:center;}.sl-expand-icon{display:inline-block;font-size:10px;color:#9ca3af;transition:transform 0.2s;width:16px;text-align:center;}
.sl-main-row.open .sl-expand-icon{transform:rotate(90deg);}
.sl-desc-text{font-size:14px;color:#1a1a2e;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.sl-cell--article code{font-family:'Fira Code',monospace;font-size:12px;color:#6b7280;background:#f3f4f6;padding:2px 6px;border-radius:3px;white-space:nowrap;}
.sl-warehouse-count{font-size:11px;color:#9ca3af;margin-top:2px;}
.sl-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
.sl-badge--green{background:#d1fae5;color:#065f46;}.sl-badge--yellow{background:#fef3c7;color:#92400e;}
.sl-text-green{color:#059669;font-size:13px;font-weight:600;}.sl-text-amber{color:#d97706;font-size:13px;font-weight:600;}
.sl-text-muted{color:#9ca3af;font-size:13px;}.sl-warehouses{background:#fafbfc;}
.sl-warehouse-row{display:flex;align-items:center;padding:10px 16px;border-top:1px solid #f3f4f6;font-size:13px;}
.sl-wh--instock{border-left:3px solid #d1fae5;}.sl-wh--order{border-left:3px solid #fef3c7;}
.sl-wh-stock{color:#6b7280;font-size:13px;}
.sl-wh-noreturn{color:#ef4444;font-size:10px;margin-left:6px;}
.btn--order-supplier{width:36px;height:36px;border:none;border-radius:8px;font-size:16px;cursor:pointer;background:#0066ff;color:#fff;transition:all 0.2s;display:flex;align-items:center;justify-content:center;}
.btn--order-supplier:hover{background:#0052cc;transform:scale(1.05);}.btn--order-supplier:disabled{background:#d1d5db;cursor:not-allowed;transform:none;}
.btn--order-supplier-sm{width:30px;height:30px;font-size:14px;}.brand-back{margin:16px 0;}
.back-link{color:#0066ff;text-decoration:none;font-size:14px;font-weight:500;}.back-link:hover{text-decoration:underline;}
.search-hint{color:#6b7280;font-size:14px;margin:0 0 16px;line-height:1.5;}.search-section-title{font-size:20px;font-weight:700;margin:24px 0 16px;color:#1a1a2e;}
.search-section-badge{display:inline-block;background:#e8ecf0;color:#6b7280;font-size:13px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:400;vertical-align:middle;}
.analog-loading-badge{text-align:center;padding:12px;background:#f0fdf4;color:#065f46;border-radius:8px;margin:8px 0;font-size:14px;}

/* Новые стили */
.best-offers{display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.best-offer-card{flex:1;min-width:280px;border:1px solid #e8ecf0;border-radius:12px;overflow:hidden;background:#fff;}
.best-offer-badge{font-size:12px;font-weight:700;padding:8px 16px;text-transform:uppercase;letter-spacing:0.5px;}
.best-offer-badge--price{background:#d1fae5;color:#065f46;}
.best-offer-badge--delivery{background:#dbeafe;color:#1e40af;}
.best-offer-body{padding:16px;}
.best-offer-brand{font-size:16px;font-weight:700;color:#1a1a2e;margin-bottom:4px;}
.best-offer-desc{font-size:13px;color:#6b7280;margin-bottom:8px;}
.best-offer-price{font-size:22px;font-weight:800;color:#059669;margin-bottom:8px;}
.best-offer-meta{display:flex;gap:12px;font-size:13px;color:#374151;margin-bottom:12px;flex-wrap:wrap;}
.best-offer-noreturn{color:#ef4444;font-size:11px;}
.analog-brand-group{margin-bottom:20px;}
.analog-brand-header{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#f0f4ff;border-radius:8px 8px 0 0;font-size:14px;color:#3730a3;}
.analog-brand-count{font-size:12px;color:#6b7280;}
.analog-show-more{cursor:pointer;text-align:center;padding:10px;color:#0066ff;font-size:13px;font-weight:500;border-top:1px dashed #e8ecf0;}
.analog-show-more:hover{background:#f8faff;}

@media(max-width:768px){.brand-table__header{display:none;}.brand-table__row{flex-wrap:wrap;gap:8px;padding:12px 14px;}.brand-table__cell--brand{flex:0 0 100%;}.brand-table__cell--desc{flex:0 0 100%;padding:0;}.brand-table__cell--article{flex:0 0 auto;padding:0;}.brand-table__cell--action{flex:0 0 auto;margin-left:auto;}.supplier-list__header{display:none;}.supplier-list__row,.sl-warehouse-row{flex-wrap:wrap;gap:6px;padding:12px 14px;}.sl-cell--expand{display:none;}.sl-cell--brand{flex:0 0 100%;order:0;}.sl-cell--desc{flex:0 0 100%;order:1;}.sl-cell--article{flex:0 0 auto;order:2;}.sl-cell--stock{flex:0 0 auto;order:3;}.sl-cell--delivery{flex:0 0 auto;order:4;}.sl-cell--price{flex:0 0 100%;order:5;text-align:left;}.sl-cell--order{flex:0 0 auto;order:6;margin-left:auto;}.search-legend{display:block;margin-left:0;margin-top:4px;}.best-offers{flex-direction:column;}}
.sl-delivery-time{font-size:12px;color:#6b7280;white-space:nowrap;}.sl-deadline{display:block;font-size:10px;color:#ef4444;white-space:nowrap;margin-top:1px;}
</style>

<script>
document.addEventListener('DOMContentLoaded',function(){
var sf=document.querySelector('.search-form');var si=sf?sf.querySelector('input[name="q"]'):null;var lr=document.getElementById('live-search-results');
if(!si)return;if(!lr){lr=document.createElement('div');lr.id='live-search-results';lr.className='live-search-dropdown';sf.appendChild(lr);}
var dt;si.addEventListener('input',function(){clearTimeout(dt);var q=this.value.trim();if(q.length<2){lr.innerHTML='';lr.style.display='none';return;}
dt=setTimeout(function(){lr.innerHTML='<div class="live-search-loading">Поиск...</div>';lr.style.display='block';
fetch('/local/ajax/supplier_search.php?q='+encodeURIComponent(q)).then(function(r){return r.json();}).then(function(data){
if(!data.success||!data.items||data.items.length===0){lr.innerHTML='<div class="live-search-empty">Ничего не найдено</div>';return;}
var h='';data.items.slice(0,8).forEach(function(item){h+='<a href="/parts-search/?q='+encodeURIComponent(data.query)+'" class="live-search-item"><span class="ls-name">'+escapeHtml(item.name)+'</span><span class="ls-article">'+escapeHtml(item.article)+'</span><span class="ls-price">'+numberFormat(item.price)+' ₽</span></a>';});
if(data.total>8)h+='<a href="/parts-search/?q='+encodeURIComponent(data.query)+'" class="live-search-all">Все результаты ('+data.total+') →</a>';lr.innerHTML=h;}).catch(function(){lr.innerHTML='<div class="live-search-error">Ошибка</div>';});},350);});
document.addEventListener('click',function(e){if(!sf.contains(e.target))lr.style.display='none';});si.addEventListener('focus',function(){if(lr.innerHTML.trim())lr.style.display='block';});
});

function toggleWarehouses(row){var g=row.closest('.supplier-list__group');var w=g.querySelector('.sl-warehouses');if(row.classList.contains('open')){row.classList.remove('open');w.style.display='none';}else{row.classList.add('open');w.style.display='block';}}
function showMoreAnalogs(btn){var group=btn.closest('.analog-brand-group');var hidden=group.querySelectorAll('.analog-item[style*="display:none"], .analog-item[style*="display: none"]');hidden.forEach(function(el){el.style.display='';});btn.style.display='none';}
function orderFromSupplier(btn,article,brand){if(btn.disabled)return;btn.disabled=true;var ot=btn.textContent;btn.textContent='✓';fetch('/local/ajax/order_from_supplier.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({article:article,brand:brand,supplier:'moskvorechie',quantity:1})}).then(function(r){return r.json();}).then(function(data){if(data.success){btn.style.background='#059669';btn.style.pointerEvents='none';}else{btn.textContent=ot;btn.disabled=false;}}).catch(function(){btn.textContent=ot;btn.disabled=false;});}
function escapeHtml(str){var d=document.createElement('div');d.textContent=str;return d.innerHTML;}
function numberFormat(num){return new Intl.NumberFormat('ru-RU').format(num);}

// === ИНКРЕМЕНТАЛЬНАЯ P2 (v3 — клиентский воркер) ===
(function(){
    var analogBlock = document.querySelector(".result-block--analog");
    if (!analogBlock) return;
    var analogContainer = analogBlock.querySelector(".supplier-list");
    if (!analogContainer) return;

    var p2Hash = "<?=$p2Hash?>";
    if (!p2Hash) return;

    var totalGroups = 0;
    var totalWarehouses = 0;
    var currentChunk = 0;
    var maxChunks = 60; // защита: максимум 60 чанков (600 кроссов)
    var seenKeys = {};
    
    // Собираем уже показанные бренд|артикул из серверного рендера
    document.querySelectorAll(".supplier-list__group[data-analog-key]").forEach(function(el){
        seenKeys[el.getAttribute("data-analog-key")] = true;
    });
    // Также собираем из result-block--analog без data- ключа
    analogContainer.querySelectorAll(".supplier-list__group").forEach(function(el) {
        var brand = (el.querySelector(".sl-cell--brand strong") || {}).textContent || "";
        var art = (el.querySelector(".sl-cell--article code") || {}).textContent || "";
        if (brand && art) seenKeys[(brand+"|"+art).toLowerCase()] = true;
    });

    var badge = document.createElement("div");
    badge.className = "analog-loading-badge";
    badge.id = "p2-badge";
    badge.textContent = "⏳ Догружаем поставщиков... (0 поз.)";
    analogBlock.insertBefore(badge, analogBlock.firstChild);

    function updateBadge(msg) {
        var b = document.getElementById("p2-badge");
        if (b) b.textContent = msg;
    }

    function addGroupsToDOM(html, totalG, totalW) {
        if (!html || html.trim() === "") return;
        var tmp = document.createElement("div");
        tmp.innerHTML = html;
        var groups = tmp.querySelectorAll(".supplier-list__group");
        var added = 0;
        groups.forEach(function(g) {
            var key = (g.getAttribute("data-analog-key") || "").toLowerCase();
            // Пропускаем дубли
            if (key && seenKeys[key]) return;
            if (key) seenKeys[key] = true;
            
            // Убираем header если уже есть
            var header = g.querySelector(".supplier-list__header");
            if (header) header.remove();
            
            analogContainer.appendChild(g);
            added++;
        });
        if (totalG) totalGroups = totalG;
        if (totalW) totalWarehouses = totalW;
        
        // Обновляем счётчики
        var countEl = analogBlock.querySelector(".result-block__count");
        if (countEl) countEl.textContent = (added > 0 ? "~" : "") + totalGroups + " поз.";
        var whCount = document.querySelector(".wh-count");
        if (whCount && totalWarehouses) whCount.textContent = totalWarehouses;
        var totalStrong = document.querySelector(".search-hint strong");
        if (totalStrong && totalGroups) totalStrong.textContent = totalGroups;
        
        updateBadge("⏳ Догружаем поставщиков... (~" + totalGroups + " поз.)");
    }

    function fetchChunk() {
        if (currentChunk < 0 || currentChunk > maxChunks) return;
        var url = "/local/ajax/analog_search.php?phase=p2_chunk&p2_hash=" + p2Hash
            + "&chunk=" + currentChunk
            + "&q=" + encodeURIComponent("<?=urlencode($q)?>")
            + "&brand=" + encodeURIComponent("<?=urlencode($selectedBrand)?>")
            + "&number=" + encodeURIComponent("<?=urlencode($searchNumber)?>");
        
        fetch(url).then(function(r){return r.json();}).then(function(data){
            if (!data.success) {
                if (data.error === 'no_file') { updateBadge("⚠️ Файл поиска не найден"); return; }
                // Повторяем через 3 секунды
                setTimeout(fetchChunk, 3000);
                return;
            }
            
            addGroupsToDOM(data.html, data.totalGroups, data.totalWarehouses);
            
            if (data.done) {
                updateBadge("✅ Загружены все поставщики (" + data.totalGroups + " поз., " + data.totalWarehouses + " складов)");
                var b = document.getElementById("p2-badge");
                if (b) { b.className = ""; b.style.cssText = "text-align:center;padding:12px;background:#d1fae5;color:#065f46;border-radius:8px;margin:8px 0;font-size:14px;"; }
                return;
            }
            
            currentChunk = data.nextChunk;
            // Следующий чанк сразу (без задержки — сервер уже ответил)
            fetchChunk();
        }).catch(function(e){
            updateBadge("⚠️ Ошибка загрузки, повтор...");
            setTimeout(fetchChunk, 3000);
        });
    }

    // Старт через 400мс (даём странице отрендериться)
    setTimeout(fetchChunk, 400);
})();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>