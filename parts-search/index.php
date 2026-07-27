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
$sortExact = trim($_REQUEST['sort_exact'] ?? 'default');
$sortAnalog = trim($_REQUEST['sort_analog'] ?? 'default');

function normalizeArticle($s) { return mb_strtolower(preg_replace('/[\s\-\.\/]/', '', $s)); }
function normalizeBrand($s) { $s = BrandNormalizer::map($s); $firstWord = preg_replace("/^([^\s\-\._\/]+).*$/u", "$1", $s); return mb_strtolower($firstWord); }
function mapBrandName(string $brand): string {
    static $map = ['hi-q'=>'SANGSIN','hi q'=>'SANGSIN','hiq'=>'SANGSIN','sangsin'=>'SANGSIN','sang sin'=>'SANGSIN','mann'=>'MANN-FILTER','mann-filter'=>'MANN-FILTER','lynx'=>'LYNX','lynxauto'=>'LYNX','japanparts'=>'JAPANPARTS','japan parts'=>'JAPANPARTS','nipparts'=>'NIPPARTS','nip parts'=>'NIPPARTS','blue print'=>'BLUE PRINT','blueprint'=>'BLUE PRINT','febi'=>'FEBI','febi bilstein'=>'FEBI','magneti marelli'=>'MAGNETI MARELLI','magneti'=>'MAGNETI MARELLI','victor reinz'=>'VICTOR REINZ','victor'=>'VICTOR REINZ','jp group'=>'JP GROUP','borg & beck'=>'BORG & BECK','herth+buss'=>'HERTH+BUSS','quinton hazell'=>'QH','phc vale'=>'PHC VALE','hamburg technic'=>'HAMBURG TECHNIC','hans pries'=>'HANS PRIES','first line'=>'FIRST LINE','van wezel'=>'VAN WEZEL','s ashika'=>'ASHIKA','ruhr'=>'RUHR AUTO','triple q'=>'TRIPLE Q'];
    $lower = mb_strtolower(trim($brand));
    if (isset($map[$lower])) return $map[$lower];
    foreach (['hi-q','hi q','hiq','sangsin','sang sin'] as $v) if (mb_stripos($brand,$v)!==false) return 'SANGSIN';
    foreach (['mann-filter','mann'] as $v) if (mb_stripos($brand,$v)!==false) return 'MANN-FILTER';
    foreach (['lynxauto','lynx'] as $v) if (mb_stripos($brand,$v)!==false) return 'LYNX';
    return $brand;
}
function formatQty($qty, $exact = false) { if (isManager()) return $qty . " шт."; if ($qty > 4) return 'Достаточно'; return $qty . ' шт.'; }

function sortWarehouses(array &$warehouses, string $sortMode): void {
    switch ($sortMode) {
        case 'delivery_asc': usort($warehouses,fn($a,$b)=>($a['delivery']['days']??999)<=>($b['delivery']['days']??999)?:$a['price']<=>$b['price']); break;
        case 'price_asc': usort($warehouses,fn($a,$b)=>(!$a['is_sched']&&$b['is_sched'])?-1:(($a['is_sched']&&!$b['is_sched'])?1:$a['price']<=>$b['price'])); break;
        case 'price_desc': usort($warehouses,fn($a,$b)=>(!$a['is_sched']&&$b['is_sched'])?-1:(($a['is_sched']&&!$b['is_sched'])?1:$b['price']<=>$a['price'])); break;
        default: usort($warehouses,fn($a,$b)=>(!$a['is_sched']&&$b['is_sched'])?-1:(($a['is_sched']&&!$b['is_sched'])?1:(($a['delivery']['days']??999)<=>($b['delivery']['days']??999)?:$a['price']<=>$b['price']))); break;
    }
}

function sortGroups(array &$groups, string $sortMode): void {
    switch ($sortMode) {
        case 'delivery_asc': uasort($groups,fn($a,$b)=>(($a['min_delivery']['days']??999)<=>($b['min_delivery']['days']??999))?:$a['min_price']<=>$b['min_price']); break;
        case 'price_asc': uasort($groups,fn($a,$b)=>(!$a['has_instock']&&$b['has_instock'])?1:(($a['has_instock']&&!$b['has_instock'])?-1:$a['min_price']<=>$b['min_price'])); break;
        default: uasort($groups,fn($a,$b)=>(!$a['has_instock']&&$b['has_instock'])?1:(($a['has_instock']&&!$b['has_instock'])?-1:(($a['min_delivery']['days']??999)<=>($b['min_delivery']['days']??999)?:$a['min_price']<=>$b['min_price']))); break;
    }
}

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
    $normQ = BrandNormalizer::normalizeArticle($q);
    $brandCacheKey = 'brands_' . md5(mb_strtolower($q));
    $brandCache = new \Lider\Search\SearchCacheManager();
    $allBrandsRaw = $brandCache->get($brandCacheKey);
    if ($allBrandsRaw === null) {
        $allBrandsRaw = []; $brandRequests = [];
        foreach (getSupplierFactory()->allAvailable() as $supplier) { $req = $supplier->buildBrandsRequest($q); if ($req) $brandRequests[$supplier->getCode()] = ['req'=>$req,'supplier'=>$supplier]; }
        if (!empty($brandRequests)) {
            $mh=curl_multi_init(); $handles=[];
            foreach($brandRequests as $code=>$data){$ch=curl_init();$req=$data['req'];curl_setopt_array($ch,[CURLOPT_URL=>$req['url'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$req['headers'],CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_ENCODING=>'']);if($req['method']==='POST'){curl_setopt($ch,CURLOPT_POST,true);if($req['body'])curl_setopt($ch,CURLOPT_POSTFIELDS,$req['body']);}curl_multi_add_handle($mh,$ch);$handles[$code]=$ch;}
            $running=null;do{curl_multi_exec($mh,$running);curl_multi_select($mh,0.1);}while($running>0);
            foreach($handles as $code=>$ch){$responseBody=curl_multi_getcontent($ch);$httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_multi_remove_handle($mh,$ch);curl_close($ch);if($httpCode===200&&!empty($responseBody)){try{$brands=$brandRequests[$code]['supplier']->parseBrandsResponse($responseBody,$q);foreach($brands as $br){$br['source']=$brandRequests[$code]['supplier']->getCode();$br['supplier_name']=$brandRequests[$code]['supplier']->getName();$allBrandsRaw[]=$br;}}catch(\Throwable $e){}}}
            curl_multi_close($mh);
        }
        $brandCache->set($brandCacheKey, $allBrandsRaw, 900);
    }
    $brandMap = [];
    foreach($allBrandsRaw as $br){$brBrand=trim((string)($br["brand"]??""));$brArt=trim((string)($br["article_nr"]??($br["article"]??"")));if($brBrand===""||$brArt==="")continue;$br["brand"]=$brBrand;$br["article_nr"]=$brArt;$key=\Lider\Search\BrandNormalizer::groupKey($brBrand,$brArt);if(!isset($brandMap[$key])){$brandMap[$key]=["brands"=>[],"articles"=>[],"article_nr"=>$br["article_nr"],"description"=>$br["description"]?:'',"sources"=>[]];}$src=$br["source"];$brandMap[$key]["brands"][$src]=$br["brand"];$brandMap[$key]["articles"][$src]=$br["article_nr"];if(!in_array($src,$brandMap[$key]["sources"],true))$brandMap[$key]["sources"][]=$src;if(mb_strlen($br["description"]?:'')>mb_strlen($brandMap[$key]["description"]))$brandMap[$key]["description"]=$br["description"]?:'';$brandMap[$key]["article_nr"]=\Lider\Search\BrandNormalizer::pickDisplayArticle($brandMap[$key]["articles"],$brandMap[$key]["article_nr"]);}
    $bmc=new \Lider\Search\SearchCacheManager();$bmc->set('brandmap_'.md5(mb_strtolower($q)),$brandMap,900);
    $exactBrands=[];$analogBrands=[];
    foreach($brandMap as $key=>$info){$isExact=(\Lider\Search\BrandNormalizer::normalizeArticle($info["article_nr"])===$normQ);$displayBrand=\Lider\Search\BrandNormalizer::displayBrand((string)(reset($info["brands"])?:''));$displayArticle=\Lider\Search\BrandNormalizer::pickDisplayArticle($info["articles"]??[],$info["article_nr"]??'');$entry=["brand"=>$displayBrand,"article"=>$displayArticle,"article_nr"=>$displayArticle,"description"=>$info["description"],"sources"=>$info["sources"],"brands"=>$info["brands"],"articles"=>$info["articles"],"key"=>$key];if($isExact)$exactBrands[]=$entry;else $analogBrands[]=$entry;}
    $sortByOffers=fn($a,$b)=>count($b['sources']??[])<=>count($a['sources']??[])?:strcmp(mb_strtolower($a['brand']??''),mb_strtolower($b['brand']??''));
    usort($exactBrands,$sortByOffers);usort($analogBrands,$sortByOffers);
    global $arrFilter;$arrFilter=[['LOGIC'=>'OR',['%NAME'=>$q],['PROPERTY_CML2_ARTICLE'=>$q],['%PROPERTY_CML2_ARTICLE'=>$q],['%DETAIL_TEXT'=>$q],['PROPERTY_CML2_MANUFACTURER'=>$q],['%PROPERTY_CML2_MANUFACTURER'=>$q]]];
    $localCache=new \Lider\Search\SearchCacheManager('/search/local',600);$lck='local_'.md5(mb_strtolower($q));$ccl=$localCache->get($lck);
    if($ccl!==null&&isset($ccl['count'])){$localCount=(int)$ccl['count'];}else{$localRes=CIBlockElement::GetList([],array_merge(['IBLOCK_ID'=>$iblockId,'ACTIVE'=>'Y'],$arrFilter[0]),false,false,['ID']);$localCount=$localRes->SelectedRowsCount();$localCache->set($lck,['count'=>$localCount],600);}
?>
<?php if($localCount>0):?><h2 class="search-section-title">🔵 На нашем складе</h2>
<?php global $arrFilter;$APPLICATION->IncludeComponent("bitrix:catalog.section","lider_style",["IBLOCK_TYPE"=>"1c_catalog","IBLOCK_ID"=>$iblockId,"INCLUDE_SUBSECTIONS"=>"Y","SHOW_ALL_WO_SECTION"=>"Y","ELEMENT_SORT_FIELD"=>"sort","ELEMENT_SORT_ORDER"=>"asc","FILTER_NAME"=>"arrFilter","PRICE_CODE"=>["Ручная розничная цена"],"PROPERTY_CODE"=>["CML2_ARTICLE","CML2_MANUFACTURER","IN_STOCK"],"PAGE_ELEMENT_COUNT"=>"12","HIDE_NOT_AVAILABLE"=>"Y","BASKET_URL"=>"/personal/cart/","CACHE_TYPE"=>"A","CACHE_TIME"=>"300","SET_TITLE"=>"N"],false);?>
<?php endif;?>
<?php if(!empty($exactBrands)):?><h2 class="search-section-title">🟠 Выберите бренд для артикула «<?=htmlspecialchars($q)?>»</h2><p class="search-hint">Под этим артикулом у разных производителей могут быть <strong>разные детали</strong>. Выберите нужный бренд для просмотра цен.</p><div class="brand-table"><div class="brand-table__header"><div class="brand-table__cell brand-table__cell--brand">Производитель</div><div class="brand-table__cell brand-table__cell--article">Артикул</div><div class="brand-table__cell brand-table__cell--desc">Описание детали</div><?php if(isManager()):?><div class="brand-table__cell brand-table__cell--source">Источник</div><?php endif;?><div class="brand-table__cell brand-table__cell--action"></div></div><?php foreach($exactBrands as $br):?><div class="brand-table__row"><div class="brand-table__cell brand-table__cell--brand"><strong><?=htmlspecialchars($br['brand'])?></strong></div><div class="brand-table__cell brand-table__cell--article"><code><?=htmlspecialchars($br['article'])?></code></div><div class="brand-table__cell brand-table__cell--desc"><?=htmlspecialchars($br['description']?:'—')?></div><?php if(isManager()):?><div class="brand-table__cell brand-table__cell--source"><span class="source-tag source-tag--<?=htmlspecialchars($br['source'])?>"><?=htmlspecialchars($br['supplier_name'])?></span></div><?php endif;?><div class="brand-table__cell brand-table__cell--action"><a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article_fix'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn btn--brand-select">Выбрать →</a></div></div><?php endforeach;?></div><?php endif;?>
<?php if(!empty($analogBrands)):?><details class="analogs-details"><summary class="analogs-summary">📋 Аналоги и кросс-номера (<?=count($analogBrands)?>)</summary><div class="brand-table brand-table--analogs"><?php foreach($analogBrands as $br):?><div class="brand-table__row"><div class="brand-table__cell brand-table__cell--brand"><?=htmlspecialchars($br['brand'])?></div><div class="brand-table__cell brand-table__cell--article"><code><?=htmlspecialchars($br['article'])?></code></div><div class="brand-table__cell brand-table__cell--desc"><?=htmlspecialchars($br['description']?:'—')?></div><?php if(isManager()):?><div class="brand-table__cell brand-table__cell--source"><span class="source-tag source-tag--<?=htmlspecialchars($br['source'])?>"><?=htmlspecialchars($br['supplier_name'])?></span></div><?php endif;?><div class="brand-table__cell brand-table__cell--action"><a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article_fix'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn btn--brand-select btn--brand-select-sm">Выбрать →</a></div></div><?php endforeach;?></div></details><?php endif;?>
<?php if(empty($exactBrands)&&empty($analogBrands)&&$localCount===0):?><div style="text-align:center;padding:60px 20px;color:var(--gray);"><div style="font-size:48px;margin-bottom:12px;">🔍</div><p>По артикулу «<?=htmlspecialchars($q)?>» ничего не найдено</p></div><?php endif;?>
<?php
else:
    require __DIR__ . "/stage2_search_v2.php"; ?>
    <?php if (!empty($verifyTaskHash)) include __DIR__ . "/_hybrid_notice.php"; ?>
<div class="brand-back"><a href="?q=<?=urlencode($q)?>" class="back-link">← Назад к выбору бренда</a></div>
<h2 class="search-section-title"><?=htmlspecialchars($selectedBrand)?><span class="search-section-badge"><?=htmlspecialchars($searchNumber)?></span></h2>
<?php if ($totalGroups > 0): ?>
<div class="filter-bar"><div class="filter-bar__title">⚙️ Фильтры</div><div class="filter-bar__row"><div class="filter-field"><label class="filter-label">Цена от</label><input type="number" class="filter-input" id="price_min" value="<?=$filterPriceMin?:''?>" placeholder="0" min="0" step="100"></div><div class="filter-field"><label class="filter-label">Цена до</label><input type="number" class="filter-input" id="price_max" value="<?=$filterPriceMax?:''?>" placeholder="10000" min="0" step="100"></div><div class="filter-field filter-field--brand"><label class="filter-label">Бренд</label><select class="filter-input filter-select" id="filter_brand"><option value="">Все бренды</option><?php foreach($allBrands as $b=>$_):?><option value="<?=htmlspecialchars($b)?>" <?=$filterBrand===$b?'selected':''?>><?=htmlspecialchars($b)?></option><?php endforeach;?></select></div><div class="filter-field filter-field--actions"><button class="btn btn--filter-apply" onclick="applyFilters()">Применить</button><button class="btn btn--filter-reset" onclick="resetFilters()">Сбросить</button></div></div></div>
<p class="search-hint">Найдено <strong><?=$totalGroups?></strong> позиций (<span class="wh-count"><?=$totalWarehouses?></span> предложений от всех поставщиков)<span class="search-legend"><span class="legend-item"><span class="legend-icon legend-icon--return">↻</span> возвратный</span><span class="legend-item"><span class="legend-icon legend-icon--noreturn">✕</span> невозвратный</span></span></p>
<?php
$renderTable = function(array $groups, string $blockClass, string $blockTitle, string $badgeClass) { static $ri=0; if(empty($groups))return;?>
<div class="result-block <?=$blockClass?>"><div class="result-block__header"><span class="result-block__badge <?=$badgeClass?>"><?=$blockTitle?></span><span class="result-block__count"><?=count($groups)?> поз.</span></div><div class="supplier-list"><div class="supplier-list__header"><div class="sl-cell sl-cell--expand"></div><div class="sl-cell sl-cell--brand">Бренд</div><div class="sl-cell sl-cell--desc">Описание</div><div class="sl-cell sl-cell--article">Артикул</div><div class="sl-cell sl-cell--mult">Кратность</div><div class="sl-cell sl-cell--stock">Наличие</div><div class="sl-cell sl-cell--delivery">Доставка</div><div class="sl-cell sl-cell--price">Цена</div><div class="sl-cell sl-cell--order"></div></div><?php foreach($groups as $group):$ri++;$inStock=$group['has_instock'];$rc=$inStock?'sl-row--instock':'sl-row--order';$pl=$group['min_price']==$group['max_price']?number_format($group['min_price'],2,',',' '):'от '.number_format($group['min_price'],2,',',' ');$dl=formatDelivery($group['min_delivery']);$dq=$group['in_stock_qty']>0?$group['in_stock_qty']:$group['total_qty'];$ql=formatQty($dq);?>
<div class="supplier-list__group"><div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)" data-group="<?=$ri?>"><div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div><div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div><div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div><div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div><div class="sl-cell sl-cell--stock"><?php if($inStock):?><span class="sl-badge sl-badge--green"><?=$ql?></span><?php else:?><span class="sl-badge sl-badge--yellow"><?=$ql?></span><?php endif;?></div><div class="sl-cell sl-cell--delivery"><?=$dl?></div><div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div><div class="sl-cell sl-cell--order"></div></div><div class="sl-warehouses" id="wh-group-<?=$ri?>" style="display:none;"><?php foreach($group['warehouses'] as $wh):$retIcon=$wh['returnable']?'<span class="ret-icon ret-icon--yes" title="Возвратный товар">↻</span>':'<span class="ret-icon ret-icon--no" title="Невозвратный товар">✕</span>';$sourceTag=isManager()?'<span class="source-tag source-tag--'.htmlspecialchars($wh['source']).'">'.htmlspecialchars($wh['supplier']).'</span>':'';?><div class="sl-warehouse-row <?=$wh['is_sched']?'sl-wh--order':'sl-wh--instock'?>"><div class="sl-cell sl-cell--expand"><?=$retIcon?></div><div class="sl-cell sl-cell--brand"><?=$sourceTag?></div><div class="sl-cell sl-cell--desc"><span class="sl-wh-stock">📍 <?=htmlspecialchars($wh['stock'])?></span></div><div class="sl-cell sl-cell--mult"><?php if(($wh['multiplicity']??1)>1):?><span class="sl-mult-badge" title="Минимальная партия: <?=$wh['multiplicity']?> <?=htmlspecialchars($wh['unit']??'шт.')?>">×<?=$wh['multiplicity']?> <?=htmlspecialchars($wh['unit']??'шт.')?></span><?php else:?><span class="sl-mult-text"><?=htmlspecialchars($wh['unit']??'шт.')?></span><?php endif;?></div><div class="sl-cell sl-cell--stock"><?php if($wh['is_sched']):?><span class="sl-badge sl-badge--yellow"><?=formatQty($wh['qty'])?></span><?php else:?><span class="sl-badge sl-badge--green"><?=formatQty($wh['qty'])?></span><?php endif;?></div><div class="sl-cell sl-cell--delivery"><?=formatDelivery($wh['delivery'])?></div><div class="sl-cell sl-cell--price"><strong><?=number_format($wh['price'],2,',',' ')?> ₽</strong><?php if(isManager()&&$wh['price']!==$wh['price_base']):?><div class="sl-price-base">Закуп: <?=number_format($wh['price_base'],2,',',' ')?> ₽</div><?php endif;?></div><div class="sl-cell sl-cell--order"><button class="btn btn--order-supplier btn--order-supplier-sm" data-article="<?=htmlspecialchars($group['article'])?>" data-brand="<?=htmlspecialchars($group['brand'])?>" data-supplier="<?=htmlspecialchars($wh['source'])?>" data-delivery-days="<?=$wh['delivery']['days']?>" data-delivery-text="<?=strip_tags(formatDelivery($wh['delivery']))?>" onclick="event.stopPropagation();orderFromSupplier(this,'<?=htmlspecialchars($group['article'])?>','<?=htmlspecialchars($group['brand'])?>')">🛒</button></div></div><?php endforeach;?></div></div><?php endforeach;?></div></div><?php };
$renderTable($exactGroups, 'result-block--exact', '📍 ' . htmlspecialchars($selectedBrand) . ' / ' . htmlspecialchars($searchNumber), 'badge--exact');
$renderTable($analogGroups, 'result-block--analog', '🛒 Аналоги (' . htmlspecialchars($searchNumber) . ')', 'badge--analog');
if (empty($analogGroups)) { echo '<div class="result-block result-block--analog"><div class="result-block__header"><span class="result-block__badge badge--analog">📋 Аналоги (' . htmlspecialchars($searchNumber) . ')</span><span class="result-block__count">0 поз.</span></div><div class="supplier-list"></div></div>'; }
?>
<?php else: ?><div style="text-align:center;padding:40px;color:var(--gray);"><p>Нет доступных предложений</p><a href="?q=<?=urlencode($q)?>" class="back-link">← Назад</a></div><?php endif;?>
<?php endif;?>
<?php else: ?><div style="text-align:center;padding:80px 20px;color:var(--gray);"><div style="font-size:48px;margin-bottom:12px;">🔍</div><p>Введите артикул, название запчасти или VIN-номер</p></div><?php endif;?>
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
.brand-table__cell--desc{flex:1;font-size:14px;color:#374151;line-height:1.4;}.brand-table__cell--source{flex:0 0 130px;font-size:12px;}
.brand-table__cell--action{flex:0 0 120px;text-align:right;}
.source-tag{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;white-space:nowrap;}
.source-tag--moskvorechie{background:#e0f2fe;color:#0369a1;}.source-tag--rossko{background:#fce7f3;color:#9d174d;}
.source-tag--shatem{background:#fef3c7;color:#92400e;}.source-tag--berg{background:#d1fae5;color:#065f46;}
.source-tag--autoeuro{background:#fce4ec;color:#c62828;}.source-tag--partkom{background:#e8f5e9;color:#2e7d32;}
.source-tag--ixora{background:#ede7f6;color:#5e35b1;}
.source-tag--tatparts{background:#e0f7fa;color:#006064;}
.source-tag--autoruss{background:#fff7ed;color:#c2410c;}
.btn--brand-select{display:inline-block;padding:8px 20px;background:#0066ff;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;transition:background 0.2s;white-space:nowrap;}
.btn--brand-select:hover{background:#0052cc;color:#fff;text-decoration:none;}.btn--brand-select-sm{padding:6px 14px;font-size:12px;}
.analogs-details{margin:0 0 32px;}.analogs-summary{cursor:pointer;font-size:14px;font-weight:600;color:#0066ff;padding:10px 0;user-select:none;}
.analogs-summary:hover{color:#0052cc;}.brand-table--analogs{margin-top:8px;}.brand-table--analogs .brand-table__row{background:#fdfdff;}
.filter-bar{background:#f8f9fb;border:1px solid #e8ecf0;border-radius:12px;padding:16px 20px;margin:16px 0 20px;}
.filter-bar__title{font-size:14px;font-weight:700;color:#1a1a2e;margin-bottom:12px;}
.filter-bar__row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}.filter-field{display:flex;flex-direction:column;gap:4px;}
.filter-label{font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.3px;}
.filter-input{padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#1a1a2e;background:#fff;width:120px;transition:border-color 0.2s;}
.filter-input:focus{outline:none;border-color:#0066ff;box-shadow:0 0 0 3px rgba(0,102,255,0.1);}
.filter-select{width:180px;cursor:pointer;}.filter-field--actions{flex-direction:row;gap:8px;align-items:center;}
.btn--filter-apply{padding:8px 20px;background:#0066ff;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background 0.2s;}
.btn--filter-apply:hover{background:#0052cc;}.btn--filter-reset{padding:8px 16px;background:#fff;color:#6b7280;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s;}
.btn--filter-reset:hover{background:#f3f4f6;}
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
.sl-cell--order{flex:0 0 50px;text-align:center;}.sl-expand-icon{display:inline-block;font-size:10px;color:#9ca3af;transition:transform 0.2s;width:16px;text-align:center;}
.sl-main-row.open .sl-expand-icon{transform:rotate(90deg);}
.sl-desc-text{font-size:14px;color:#1a1a2e;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.sl-cell--article code{font-family:'Fira Code',monospace;font-size:12px;color:#6b7280;background:#f3f4f6;padding:2px 6px;border-radius:3px;white-space:nowrap;}
.sl-warehouse-count{font-size:11px;color:#9ca3af;margin-top:2px;}.sl-cell--mult{flex:0 0 80px;text-align:center;font-size:12px;}
.sl-mult-badge{display:inline-block;background:#fff3e0;color:#e65100;font-size:12px;font-weight:700;padding:3px 10px;border-radius:10px;white-space:nowrap;border:1px solid #ffe0b2;}
.sl-mult-text{color:#6b7280;font-size:12px;font-weight:500;}
.sl-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
.sl-badge--green{background:#d1fae5;color:#065f46;}.sl-badge--yellow{background:#fef3c7;color:#92400e;}
.sl-text-green{color:#059669;font-size:13px;font-weight:600;}.sl-text-amber{color:#d97706;font-size:13px;font-weight:600;}
.sl-text-muted{color:#9ca3af;font-size:13px;}.sl-warehouses{background:#fafbfc;}
.sl-warehouse-row{display:flex;align-items:center;padding:10px 16px;border-top:1px solid #f3f4f6;font-size:13px;}
.sl-wh--instock{border-left:3px solid #d1fae5;}.sl-wh--order{border-left:3px solid #fef3c7;}
.sl-wh-stock{color:#6b7280;font-size:13px;}
.btn--order-supplier{width:36px;height:36px;border:none;border-radius:8px;font-size:16px;cursor:pointer;background:#0066ff;color:#fff;transition:all 0.2s;display:flex;align-items:center;justify-content:center;}
.btn--order-supplier:hover{background:#0052cc;transform:scale(1.05);}.btn--order-supplier:disabled{background:#d1d5db;cursor:not-allowed;transform:none;}
.btn--order-supplier-sm{width:30px;height:30px;font-size:14px;}.brand-back{margin:16px 0;}
.back-link{color:#0066ff;text-decoration:none;font-size:14px;font-weight:500;}.back-link:hover{text-decoration:underline;}
.search-hint{color:#6b7280;font-size:14px;margin:0 0 16px;line-height:1.5;}.search-section-title{font-size:20px;font-weight:700;margin:24px 0 16px;color:#1a1a2e;}
.search-section-badge{display:inline-block;background:#e8ecf0;color:#6b7280;font-size:13px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:400;vertical-align:middle;}
.analog-loading-badge{text-align:center;padding:12px;background:#f0fdf4;color:#065f46;border-radius:8px;margin:8px 0;font-size:14px;}
@media(max-width:768px){.brand-table__header{display:none;}.brand-table__row{flex-wrap:wrap;gap:8px;padding:12px 14px;}.brand-table__cell--brand{flex:0 0 100%;}.brand-table__cell--desc{flex:0 0 100%;padding:0;}.brand-table__cell--article{flex:0 0 auto;padding:0;}.brand-table__cell--action{flex:0 0 auto;margin-left:auto;}.filter-bar__row{flex-direction:column;}.filter-input,.filter-select{width:100%;}.filter-field--actions{flex-direction:row;}.supplier-list__header{display:none;}.supplier-list__row,.sl-warehouse-row{flex-wrap:wrap;gap:6px;padding:12px 14px;}.sl-cell--expand{display:none;}.sl-cell--brand{flex:0 0 100%;order:0;}.sl-cell--desc{flex:0 0 100%;order:1;}.sl-cell--article{flex:0 0 auto;order:2;}.sl-cell--stock{flex:0 0 auto;order:3;}.sl-cell--delivery{flex:0 0 auto;order:4;}.sl-cell--price{flex:0 0 100%;order:5;text-align:left;}.sl-cell--order{flex:0 0 auto;order:6;margin-left:auto;}.search-legend{display:block;margin-left:0;margin-top:4px;}}
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
function applyFilters(){var p=new URLSearchParams(window.location.search);var mn=document.getElementById('price_min').value;var mx=document.getElementById('price_max').value;var fb=document.getElementById('filter_brand').value;if(mn)p.set('price_min',mn);else p.delete('price_min');if(mx)p.set('price_max',mx);else p.delete('price_max');if(fb)p.set('filter_brand',fb);else p.delete('filter_brand');window.location.search=p.toString();}
function resetFilters(){var p=new URLSearchParams(window.location.search);p.delete('price_min');p.delete('price_max');p.delete('filter_brand');window.location.search=p.toString();}
function orderFromSupplier(btn,article,brand){if(btn.disabled)return;btn.disabled=true;var ot=btn.textContent;btn.textContent='✓';var dd=parseInt(btn.getAttribute('data-delivery-days'))||0;var dt=btn.getAttribute('data-delivery-text')||'';var supplier=btn.getAttribute('data-supplier')||'moskvorechie';fetch('/local/ajax/order_from_supplier.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({article:article,brand:brand,supplier:supplier,quantity:1,delivery_days:dd,delivery_text:dt})}).then(function(r){return r.json();}).then(function(data){if(data.success){btn.style.background='#059669';btn.style.pointerEvents='none';}else{btn.textContent=ot;btn.disabled=false;}}).catch(function(){btn.textContent=ot;btn.disabled=false;});}
function escapeHtml(str){var d=document.createElement('div');d.textContent=str;return d.innerHTML;}
function numberFormat(num){return new Intl.NumberFormat('ru-RU').format(num);}

// === ЛЕНИВАЯ ЗАГРУЗКА АНАЛОГОВ ===
(function(){
    // Баг #9: гибридный поиск уже загрузил аналоги — старый AJAX не нужен
    // Этап 9: всегда разрешаем lazy-loader (догружает поставщиков, которых нет в кэше)
    var analogBlock = document.querySelector(".result-block--analog");
    if (!analogBlock) return;
    var analogContainer = analogBlock.querySelector(".supplier-list");
    if (!analogContainer) return;
    var analogToken = "<?=$analogToken?>";
    if (!analogToken) return;

    var loaded = false;
    var badge = document.createElement("div");
    badge.className = "analog-loading-badge";
    badge.textContent = "⏳ Догружаем предложения от всех поставщиков...";
    analogBlock.insertBefore(badge, analogBlock.firstChild);

    function loadAnalogs() {
        if (loaded) return;
        loaded = true;

        var url = "/local/ajax/analog_search.php?phase=fast&q=" + encodeURIComponent("<?=urlencode($q)?>")
            + "&brand=" + encodeURIComponent("<?=urlencode($selectedBrand)?>")
            + "&number=" + encodeURIComponent("<?=urlencode($searchNumber)?>")
            + "&token=" + analogToken
            + "&filter_brand=" + encodeURIComponent("<?=urlencode($filterBrand)?>")
            + "&price_min=<?=(int)$filterPriceMin?>"
            + "&price_max=<?=(int)$filterPriceMax?>";

        fetch(url).then(function(r){ return r.json(); }).then(function(data){
            badge.remove();
            if (!data.success || !data.html) return;

            // Заменяем содержимое таблицы
            analogContainer.innerHTML = data.html;

            // Если есть Phase 2 — запускаем polling
            if (data.p2_pending && data.p2_hash) {
                var p2Badge = document.createElement("div");
                p2Badge.className = "search-notice search-notice--progress";
                p2Badge.textContent = "⏳ Догружаем остальных поставщиков... (" + data.totalGroups + " поз.)";
                p2Badge.id = "p2-progress";
                analogBlock.insertBefore(p2Badge, analogBlock.firstChild);

                var p2PollCount = 0;
                var p2Timer = setInterval(function() {
                    p2PollCount++;
                    fetch("/local/ajax/analog_poll.php?hash=" + data.p2_hash)
                        .then(function(r) { return r.json(); })
                        .then(function(p2) {
                            if (p2.ready) {
                                clearInterval(p2Timer);
                                var pb = document.getElementById("p2-progress");
                                if (pb) pb.textContent = "⏳ Обновляем результаты...";
                                // Перезапрашиваем analog_search в режиме final
                                var finalUrl = "/local/ajax/analog_search.php?phase=final&p2_hash=" + data.p2_hash
                                    + "&q=" + encodeURIComponent("<?=urlencode($q)?>")
                                    + "&brand=" + encodeURIComponent("<?=urlencode($selectedBrand)?>")
                                    + "&number=" + encodeURIComponent("<?=urlencode($searchNumber)?>")
                                    + "&token=" + analogToken;
                                fetch(finalUrl).then(function(r){ return r.json(); }).then(function(d){
                                    if (pb) pb.remove();
                                    if (d.success && d.html) {
                                        analogContainer.innerHTML = d.html;
                                        var countEl = analogBlock.querySelector(".result-block__count");
                                        if (countEl && d.totalGroups) countEl.textContent = d.totalGroups + " поз.";
                                        var whCount = document.querySelector(".wh-count");
                                        if (whCount && d.totalWarehouses) whCount.textContent = d.totalWarehouses;
                                        var totalStrong = document.querySelector(".search-hint strong");
                                        if (totalStrong && d.totalGroups) totalStrong.textContent = d.totalGroups;
                                        var doneBanner = document.createElement("div");
                                        doneBanner.className = "search-notice search-notice--done";
                                        doneBanner.textContent = "✅ Загружены все поставщики (" + (d.totalGroups || "?") + " поз., " + (d.totalWarehouses || "?") + " складов)";
                                        analogBlock.insertBefore(doneBanner, analogBlock.firstChild);
                                    }
                                });
                            } else if (p2PollCount > 20) {
                                // Таймаут через 40 сек
                                clearInterval(p2Timer);
                                var pb = document.getElementById("p2-progress");
                                if (pb) pb.textContent = "⚠️ Не все поставщики загружены — попробуйте обновить страницу";
                            }
                        });
                }, 2000);
            }

            // Обновляем счётчики
            var countEl = analogBlock.querySelector(".result-block__count");
            if (countEl) countEl.textContent = data.totalGroups + " поз.";

            var whCount = document.querySelector(".wh-count");
            if (whCount) whCount.textContent = data.totalWarehouses;

            var totalStrong = document.querySelector(".search-hint strong");
            if (totalStrong) totalStrong.textContent = data.totalGroups;
        }).catch(function(){ badge.remove(); });
    }

    var timer = setTimeout(loadAnalogs, 800);
    window.addEventListener("scroll", function(){ clearTimeout(timer); loadAnalogs(); }, {once: true});
})();
</script>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>