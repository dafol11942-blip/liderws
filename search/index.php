<?php
// search/index.php — поиск liderws.ru (дизайн zap39)
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$q      = trim($_REQUEST['q'] ?? '');
$brand  = trim($_REQUEST['brand'] ?? '');
$number = trim($_REQUEST['number'] ?? '');

// Если есть brand+number — делаем серверный запрос и рендерим сразу
$results = null;
if ($q && $brand && $number) {
    $results = fetchResults($q, $brand, $number);
}

function fetchResults($article, $brand, $number) {
    $url = '/search/ajax.php?action=search&article=' . urlencode($article)
         . '&brand=' . urlencode($brand) . '&number=' . urlencode($number);
    
    // Внутренний вызов через file_get_contents с curl
    $ch = curl_init('http://127.0.0.1' . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Host: liderws.ru'],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true);
}

function fmt($n) { return number_format((float)$n, 2, ',', ' '); }
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dRange($s) {
    $d = $s['delivery_days'] ?? -1;
    if ($d < 0) return '—';
    return $d . ' дн.';
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $q ? esc($brand.' '.$number) : 'Поиск запчастей' ?> — liderws.ru</title>
<link rel="stylesheet" href="/search/style.css">
</head>
<body>

<div class="srch">

<?php if (!$q): ?>
<!-- ====== ПУСТОЙ ПОИСК ====== -->
<div class="hero">
    <h1>🔍 Поиск автозапчастей</h1>
    <p>Введите артикул запчасти</p>
    <form class="hero-frm" method="get">
        <input type="text" name="q" class="hero-inp" placeholder="Например: W7008" autofocus autocomplete="off">
        <button type="submit" class="hero-btn">Найти</button>
    </form>
</div>

<?php elseif ($q && !$brand): ?>
<!-- ====== ВЫБОР БРЕНДА (AJAX) ====== -->
<div class="topbar">
    <form class="topbar-frm" method="get">
        <input type="text" name="q" class="topbar-inp" value="<?=esc($q)?>">
        <button type="submit" class="topbar-btn">🔍</button>
    </form>
    <span class="topbar-info">Поиск: <strong><?=esc($q)?></strong></span>
</div>
<div id="localStock"></div>
<div id="loader" class="loader hidden"><div class="spinner"></div><div id="loaderText">Ищем бренды...</div></div>
<div id="brandStep"></div>
<div id="emptyMsg" class="hero hidden"></div>

<script>
(function(){
var API='/search/ajax.php',Q=<?=json_encode($q)?>;
function qs(s,el){return(el||document).querySelector(s)}
function hide(id){qs('#'+id).classList.add('hidden')}
function show(id){qs('#'+id).classList.remove('hidden')}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}

async function loadBrands(article){
    hide('localStock');hide('brandStep');hide('emptyMsg');
    show('loader');qs('#loaderText').textContent='Ищем бренды у поставщиков...';
    try{
        var r=await fetch(API+'?action=brands&article='+encodeURIComponent(article));
        var d=await r.json();
        hide('loader');
        if(d.error){showError(d.error);return}
        if(d.local_count>0){
            show('localStock');
            qs('#localStock').innerHTML='<div class="local-box"><h2 class="sec-h sec-h--local">🔵 На нашем складе</h2><p class="local-txt">Найдено <strong>'+d.local_count+'</strong> позиций. <a href="/search/?q='+encodeURIComponent(article)+'&show_local=1">Показать →</a></p></div>';
        }
        if(!d.brands||!d.brands.length){showError('По артикулу «'+esc(article)+'» ничего не найдено');return}
        var exact=d.brands.filter(function(b){return b.type==='exact'});
        var analogs=d.brands.filter(function(b){return b.type==='analog'});
        var h='';
        if(exact.length){
            h+='<h2 class="sec-h sec-h--brand">🟠 Выберите бренд для «'+esc(article)+'»</h2>';
            h+='<p class="sec-p">Под этим артикулом у разных производителей могут быть разные детали.</p>';
            h+='<div class="bt"><div class="bt-head"><span class="bt-c bt-c--brand">Производитель</span><span class="bt-c bt-c--art">Артикул</span><span class="bt-c bt-c--desc">Описание</span><span class="bt-c bt-c--src">Источники</span><span class="bt-c bt-c--act"></span></div>';
            exact.forEach(function(b){
                var srcs=(b.sources||[]).map(function(s){return '<span class="src-tag src-tag--'+s+'">'+s+'</span>'}).join(' ');
                h+='<div class="bt-row"><span class="bt-c bt-c--brand"><strong>'+esc(b.brand)+'</strong></span><span class="bt-c bt-c--art"><code>'+esc(b.article)+'</code></span><span class="bt-c bt-c--desc">'+esc(b.description||'—')+'</span><span class="bt-c bt-c--src">'+srcs+'</span><span class="bt-c bt-c--act"><a href="/search/?q='+encodeURIComponent(article)+'&brand='+encodeURIComponent(b.brand)+'&number='+encodeURIComponent(b.article)+'" class="btn-sel">Выбрать →</a></span></div>';
            });
            h+='</div>';
        }
        if(analogs.length){
            h+='<details class="dt"><summary class="dt-sum">📋 Аналоги и кросс-номера ('+analogs.length+')</summary><div class="bt" style="margin-top:12px">';
            analogs.forEach(function(b){
                var srcs=(b.sources||[]).map(function(s){return '<span class="src-tag src-tag--'+s+'">'+s+'</span>'}).join(' ');
                h+='<div class="bt-row"><span class="bt-c bt-c--brand">'+esc(b.brand)+'</span><span class="bt-c bt-c--art"><code>'+esc(b.article)+'</code></span><span class="bt-c bt-c--desc">'+esc(b.description||'—')+'</span><span class="bt-c bt-c--src">'+srcs+'</span><span class="bt-c bt-c--act"><a href="/search/?q='+encodeURIComponent(article)+'&brand='+encodeURIComponent(b.brand)+'&number='+encodeURIComponent(b.article)+'" class="btn-sel btn-sel--sm">Выбрать →</a></span></div>';
            });
            h+='</div></details>';
        }
        show('brandStep');qs('#brandStep').innerHTML=h;
    }catch(e){hide('loader');showError('Ошибка: '+e.message)}
}
function showError(msg){hide('localStock');hide('brandStep');show('emptyMsg');qs('#emptyMsg').innerHTML='<div class="hero-icon">⚠️</div><p>'+esc(msg)+'</p><form class="hero-frm" method="get"><input type="text" name="q" class="hero-inp" placeholder="Попробуйте другой артикул" autofocus><button class="hero-btn">Найти</button></form>'}

document.addEventListener('DOMContentLoaded',function(){loadBrands(Q)});
})();
</script>

<?php else: ?>
<!-- ====== СТРАНИЦА РЕЗУЛЬТАТОВ ====== -->
<?php
$exact  = $results['exact'] ?? null;
$analogs = $results['analogs'] ?? [];
$allSuppliers = [];

// Собираем все офферы в один плоский список для хайлайтов
$allOffers = [];
if ($exact && !empty($exact['suppliers'])) {
    foreach ($exact['suppliers'] as $s) {
        $s['_type'] = 'exact';
        $s['_brand'] = $exact['brand'];
        $s['_article'] = $exact['article'];
        $s['_description'] = '';
        $allOffers[] = $s;
    }
}
foreach ($analogs as $a) {
    foreach ($a['suppliers'] as $s) {
        $s['_type'] = 'analog';
        $s['_brand'] = $a['brand'];
        $s['_article'] = $a['article'];
        $s['_description'] = $a['description'] ?? '';
        $allOffers[] = $s;
    }
}

// Хайлайты
$bestPriceExact  = null;
$bestPriceAnalog = null;
$bestDelivery    = null;

foreach ($allOffers as $o) {
    if ($o['price'] > 0) {
        if ($o['_type'] === 'exact' && (!$bestPriceExact || $o['price'] < $bestPriceExact['price'])) {
            $bestPriceExact = $o;
        }
        if ($o['_type'] === 'analog' && (!$bestPriceAnalog || $o['price'] < $bestPriceAnalog['price'])) {
            $bestPriceAnalog = $o;
        }
    }
    if ($o['delivery_days'] >= 0 && (!$bestDelivery || $o['delivery_days'] < $bestDelivery['delivery_days'])) {
        $bestDelivery = $o;
    }
}
?>

<!-- Хлебные крошки -->
<div class="topbar">
    <form class="topbar-frm" method="get">
        <input type="text" name="q" class="topbar-inp" value="<?=esc($q)?>">
        <button type="submit" class="topbar-btn">🔍</button>
    </form>
    <a href="/search/?q=<?=urlencode($q)?>" class="back">← К выбору бренда</a>
</div>

<!-- Заголовок -->
<div class="phead">
    <h1 class="phead-title"><?=esc($number)?> <?=esc($brand)?></h1>
    <?php if ($exact && !empty($exact['suppliers'])): ?>
    <p class="phead-sub">Найдено <?=count($exact['suppliers'])?> предл. искомого + <?=count($analogs)?> аналогов</p>
    <?php endif; ?>
</div>

<!-- Карточки-хайлайты -->
<?php if ($bestPriceExact || $bestPriceAnalog || $bestDelivery): ?>
<div class="hl-cards">
    <?php if ($bestPriceExact): ?>
    <div class="hl-card hl-card--best">
        <div class="hl-badge hl-badge--price">САМАЯ НИЗКАЯ ЦЕНА</div>
        <div class="hl-type">Искомый номер</div>
        <div class="hl-name"><?=esc($bestPriceExact['_brand'])?> / <?=esc($bestPriceExact['_article'])?></div>
        <div class="hl-price"><?=fmt($bestPriceExact['price'])?> р.</div>
        <div class="hl-meta"><?=$bestPriceExact['quantity']?> шт. &middot; <?=dRange($bestPriceExact)?></div>
        <div class="hl-src"><span class="src-tag src-tag--<?=$bestPriceExact['supplier']?>"><?=$bestPriceExact['supplier']?></span></div>
    </div>
    <?php endif; ?>
    
    <?php if ($bestPriceAnalog): ?>
    <div class="hl-card hl-card--best">
        <div class="hl-badge hl-badge--price">САМАЯ НИЗКАЯ ЦЕНА</div>
        <div class="hl-type">Аналог</div>
        <div class="hl-name"><?=esc($bestPriceAnalog['_brand'])?> / <?=esc($bestPriceAnalog['_article'])?></div>
        <div class="hl-price"><?=fmt($bestPriceAnalog['price'])?> р.</div>
        <div class="hl-meta"><?=$bestPriceAnalog['quantity']?> шт. &middot; <?=dRange($bestPriceAnalog)?></div>
        <div class="hl-src"><span class="src-tag src-tag--<?=$bestPriceAnalog['supplier']?>"><?=$bestPriceAnalog['supplier']?></span></div>
    </div>
    <?php endif; ?>
    
    <?php if ($bestDelivery): ?>
    <div class="hl-card hl-card--fast">
        <div class="hl-badge hl-badge--delivery">НАИМЕНЬШИЙ СРОК</div>
        <div class="hl-type"><?=$bestDelivery['_type']==='exact'?'Искомый номер':'Аналог'?></div>
        <div class="hl-name"><?=esc($bestDelivery['_brand'])?> / <?=esc($bestDelivery['_article'])?></div>
        <div class="hl-price"><?=fmt($bestDelivery['price'])?> р.</div>
        <div class="hl-meta"><?=$bestDelivery['quantity']?> шт. &middot; <?=dRange($bestDelivery)?></div>
        <div class="hl-src"><span class="src-tag src-tag--<?=$bestDelivery['supplier']?>"><?=$bestDelivery['supplier']?></span></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ПОЛНАЯ ТАБЛИЦА -->
<div class="full-tbl">

<?php if ($exact && !empty($exact['suppliers'])): ?>
<!-- Искомый номер -->
<div class="ft-sec ft-sec--exact">
    <div class="ft-sec-head">
        <span class="ft-sec-title">Искомый номер</span>
        <span class="ft-sec-sub"><?=esc($brand)?> / <?=esc($number)?></span>
    </div>
    <table class="ft-tbl">
        <thead><tr>
            <th class="ft-th--det">Деталь</th>
            <th class="ft-th--skl">Склад</th>
            <th class="ft-th--num">Кол.</th>
            <th class="ft-th--num">Доставка</th>
            <th class="ft-th--num">Цена</th>
        </tr></thead>
        <tbody>
        <?php 
        $shown = 0;
        foreach ($exact['suppliers'] as $s): 
            $shown++;
            $rowStyle = $shown > 10 ? ' style="display:none" class="ft-more"' : '';
        ?>
        <tr<?=$rowStyle?>>
            <td class="ft-td--det">
                <div class="ft-det-name"><?=esc($s['_description'] ?: '')?></div>
                <div class="ft-det-brand"><?=esc($brand)?> <?=esc($number)?></div>
            </td>
            <td class="ft-td--skl">
                <span class="ft-skl-name"><?=esc($s['warehouse'])?></span>
                <span class="src-tag src-tag--<?=$s['supplier']?>"><?=$s['supplier']?></span>
            </td>
            <td class="ft-td--num"><?=$s['quantity']?> шт.</td>
            <td class="ft-td--num"><?=dRange($s)?></td>
            <td class="ft-td--prc"><strong><?=fmt($s['price'])?> р.</strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($shown > 10): ?>
    <button class="ft-showmore" onclick="showMore(this)">Показать еще <?=$shown-10?> товаров</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Аналоги -->
<?php if (!empty($analogs)): ?>
<div class="ft-sec ft-sec--analog">
    <div class="ft-sec-head">
        <span class="ft-sec-title">Аналоги</span>
        <span class="ft-sec-sub">Внимание! Информация по аналогам является справочной</span>
    </div>
    
    <?php foreach ($analogs as $a): ?>
    <div class="ft-group">
        <div class="ft-ghead">
            <div class="ft-ginfo">
                <strong class="ft-gbrand"><?=esc($a['brand'])?></strong>
                <code class="ft-gart"><?=esc($a['article'])?></code>
                <span class="ft-gdesc"><?=esc($a['description'] ?? '')?></span>
            </div>
            <div class="ft-gmeta">
                <span class="ft-gbest">Лучшая: <b><?=fmt($a['best_price'])?> р.</b> / <?=($a['best_delivery']??'—')?> дн.</span>
                <span class="badge <?=($a['has_instock']?'badge--green':'badge--yellow')?>"><?=$a['total_qty']?> шт.</span>
            </div>
        </div>
        <table class="ft-tbl">
            <thead><tr>
                <th class="ft-th--det">Деталь</th>
                <th class="ft-th--skl">Склад</th>
                <th class="ft-th--num">Кол.</th>
                <th class="ft-th--num">Доставка</th>
                <th class="ft-th--num">Цена</th>
            </tr></thead>
            <tbody>
            <?php 
            $as = 0;
            foreach ($a['suppliers'] as $s):
                $as++;
                $asStyle = $as > 10 ? ' style="display:none" class="ft-more"' : '';
            ?>
            <tr<?=$asStyle?>>
                <td class="ft-td--det">
                    <div class="ft-det-name"><?=esc($s['_description'] ?? '')?></div>
                </td>
                <td class="ft-td--skl">
                    <span class="ft-skl-name"><?=esc($s['warehouse'])?></span>
                    <span class="src-tag src-tag--<?=$s['supplier']?>"><?=$s['supplier']?></span>
                </td>
                <td class="ft-td--num"><?=$s['quantity']?> шт.</td>
                <td class="ft-td--num"><?=dRange($s)?></td>
                <td class="ft-td--prc"><strong><?=fmt($s['price'])?> р.</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($as > 10): ?>
        <button class="ft-showmore" onclick="showMore(this)">Показать еще <?=$as-10?> товаров</button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$exact && empty($analogs)): ?>
<div class="hero">
    <div class="hero-icon">⚠️</div>
    <p>По запросу «<?=esc($brand)?> <?=esc($number)?>» ничего не найдено</p>
    <a href="/search/?q=<?=urlencode($q)?>" class="hero-back">← К выбору бренда</a>
</div>
<?php endif; ?>

</div>

<script>
function showMore(btn) {
    var group = btn.parentElement;
    var rows = group.querySelectorAll('.ft-more');
    rows.forEach(function(r) { r.style.display = ''; });
    btn.style.display = 'none';
}
</script>

<?php endif; ?>

</div></body></html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>