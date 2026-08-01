<?php
// search/index.php — поиск liderws.ru (AJAX, топ-5 в аналогах)
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$q      = trim($_REQUEST['q'] ?? '');
$brand  = trim($_REQUEST['brand'] ?? '');
$number = trim($_REQUEST['number'] ?? '');

function fmt($n) { return number_format((float)$n, 2, ',', ' '); }
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dRange($d) { return $d >= 0 ? $d . ' дн.' : '—'; }
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $q ? esc($brand.' '.$number ?: $q) : 'Поиск запчастей' ?> — liderws.ru</title>
<link rel="stylesheet" href="/search/style.css">
</head>
<body>

<div class="container">

<?php if (!$q): ?>
<div class="hero">
    <h1>🔍 Поиск автозапчастей</h1>
    <p>Введите артикул запчасти</p>
    <form class="hero-frm" method="get">
        <input type="text" name="q" class="hero-inp" placeholder="Например: W7008" autofocus autocomplete="off">
        <button type="submit" class="hero-btn">Найти</button>
    </form>
</div>

<?php elseif ($q && !$brand): ?>
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
<div class="topbar">
    <form class="topbar-frm" method="get">
        <input type="text" name="q" class="topbar-inp" value="<?=esc($q)?>">
        <button type="submit" class="topbar-btn">🔍</button>
    </form>
    <a href="/search/?q=<?=urlencode($q)?>" class="back">← К выбору бренда</a>
</div>

<div id="resultContent">
    <div class="loader"><div class="spinner"></div><div>Подбираем цены и аналоги...</div></div>
</div>

<script>
(function(){
var API='/search/ajax.php';
var Q=<?=json_encode($q)?>,B=<?=json_encode($brand)?>,N=<?=json_encode($number)?>;
function qs(s,el){return(el||document).querySelector(s)}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function fmt(n){return new Intl.NumberFormat('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}
function dRange(d){return d>=0?d+' дн.':'—'}

function showProgress(pct, msg) {
    qs('#resultContent').innerHTML =
        '<div class="loader">' +
        '<div class="spinner"></div>' +
        '<div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
        '<div class="progress-text">' + pct + '% — ' + esc(msg) + '</div>' +
        '</div>';
}

async function pollProgress(taskId, timeoutSec) {
    timeoutSec = timeoutSec || 120;
    var startTime = Date.now();

    while ((Date.now() - startTime) < timeoutSec * 1000) {
        try {
            var r = await fetch(API + '?action=progress&task=' + encodeURIComponent(taskId));
            var d = await r.json();
            if (d.percent !== undefined) {
                showProgress(d.percent, d.message || 'Обработка...');
                if (d.done && d.result) {
                    return d.result;
                }
            }
        } catch(e) {}
        await new Promise(function(resolve) { setTimeout(resolve, 500); });
    }
    throw new Error('Таймаут поиска (' + timeoutSec + 'с)');
}

async function loadResults(){
    showProgress(0, 'Запуск поиска...');

    try {
        var r = await fetch(API+'?action=search&article='+encodeURIComponent(Q)+'&brand='+encodeURIComponent(B)+'&number='+encodeURIComponent(N));
        var d = await r.json();

        if (d.error) { showError(d.error); return; }

        // Если есть task_id — поллим, иначе прямой результат
        if (d.task_id && !d.exact && !d.analogs) {
            var taskId = d.task_id, maxWait = 120, start = Date.now();
            while ((Date.now() - start) < maxWait * 1000) {
                await new Promise(function(r) { setTimeout(r, 500); });
                try {
                    var pr = await fetch(API+'?action=progress&task='+taskId);
                    var pd = await pr.json();
                    if (pd.percent !== undefined) showProgress(pd.percent, pd.message || '...');
                    if (pd.done && pd.result) { d = pd.result; break; }
                } catch(e) {}
            }
        }

        showProgress(100, 'Готово');
        setTimeout(function(){ renderResults(d); }, 300);
    } catch(e) {
        showError('Ошибка: ' + e.message);
    }
}

function renderResults(d){
    var exact=d.exact||null,analogs=d.analogs||[];
    var allOffers=[];
    if(exact&&exact.suppliers){exact.suppliers.forEach(function(s){s._type='exact';s._brand=exact.brand;s._article=exact.article;s._description=s.description||'';allOffers.push(s)});}
    analogs.forEach(function(a){a.suppliers.forEach(function(s){s._type='analog';s._brand=a.brand;s._article=a.article;s._description=a.description||'';allOffers.push(s)});});

    var bestPriceExact=null,bestPriceAnalog=null,bestDelivery=null;
    allOffers.forEach(function(o){
        if(o.price>0){
            if(o._type==='exact'&&(!bestPriceExact||o.price<bestPriceExact.price))bestPriceExact=o;
            if(o._type==='analog'&&(!bestPriceAnalog||o.price<bestPriceAnalog.price))bestPriceAnalog=o;
        }
        if(o.delivery_days>=0&&(!bestDelivery||o.delivery_days<bestDelivery.delivery_days))bestDelivery=o;
    });

    var h='';
    h+='<div class="phead"><h1 class="phead-title">'+esc(N)+' '+esc(B)+'</h1>';
    if(exact&&exact.suppliers)h+='<p class="phead-sub">Найдено '+exact.suppliers.length+' предл. искомого + '+analogs.length+' аналогов</p>';
    h+='</div>';

    if(bestPriceExact||bestPriceAnalog||bestDelivery){
        h+='<div class="hl-cards">';
        if(bestPriceExact)h+=hlCard(bestPriceExact,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Искомый номер');
        if(bestPriceAnalog)h+=hlCard(bestPriceAnalog,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Аналог');
        if(bestDelivery)h+=hlCard(bestDelivery,'НАИМЕНЬШИЙ СРОК','hl-card--fast','hl-badge--delivery',bestDelivery._type==='exact'?'Искомый номер':'Аналог');
        h+='</div>';
    }

    h+='<div class="full-tbl">';

    if(exact&&exact.suppliers&&exact.suppliers.length){
        h+='<div class="ft-sec ft-sec--exact"><div class="ft-sec-head"><span class="ft-sec-title">✅ Искомый номер</span><span class="ft-sec-sub">'+esc(B)+' / '+esc(N)+' — '+exact.suppliers.length+' складов</span></div>';
        h+=supplierTable(exact.suppliers,'exact');
        h+='</div>';
    }

    if(analogs.length){
        h+='<div class="ft-sec ft-sec--analog"><div class="ft-sec-head"><span class="ft-sec-title">🔄 Аналоги ('+analogs.length+')</span></div>';
        analogs.forEach(function(a){
            h+='<div class="ft-group"><div class="ft-ghead"><div class="ft-ginfo"><strong class="ft-gbrand">'+esc(a.brand)+'</strong><code class="ft-gart">'+esc(a.article)+'</code><span class="ft-gdesc">'+esc(a.description||'')+'</span></div><div class="ft-gmeta"><span class="ft-gbest">Лучшая: <b>'+fmt(a.best_price)+' р.</b> / '+(a.best_delivery!==null?a.best_delivery+' дн.':'—')+'</span><span class="badge '+(a.has_instock?'badge--green':'badge--yellow')+'">'+a.total_qty+' шт.</span></div></div>';
            h+=supplierTable(a.suppliers,'analog');
            h+='</div>';
        });
        h+='</div>';
    }

    if(!exact&&!analogs.length)h='<div class="hero" style="margin-top:16px"><div class="hero-icon">⚠️</div><p>По запросу «'+esc(B)+' '+esc(N)+'» ничего не найдено</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';

    h+='</div>';
    qs('#resultContent').innerHTML=h;

    document.querySelectorAll('.ft-showmore').forEach(function(btn){
        btn.addEventListener('click',function(){
            var group=btn.closest('.ft-sec, .ft-group');
            group.querySelectorAll('.ft-more').forEach(function(r){r.style.display=''});
            btn.style.display='none';
        });
    });
}

function hlCard(o,title,cardCls,badgeCls,type){
    return '<div class="hl-card '+cardCls+'"><div class="hl-badge '+badgeCls+'">'+title+'</div><div class="hl-type">'+type+'</div><div class="hl-name">'+esc(o._brand)+' / '+esc(o._article)+'</div><div class="hl-price">'+fmt(o.price)+' р.</div><div class="hl-meta">'+o.quantity+' шт. &middot; '+dRange(o.delivery_days)+'</div><div class="hl-src"><span class="src-tag src-tag--'+o.supplier+'">'+o.supplier+'</span></div></div>';
}

function supplierTable(suppliers,type){
    var limit=type==='exact'?15:5;
    var h='<table class="ft-tbl"><thead><tr><th class="ft-th--det">Деталь</th><th class="ft-th--skl">Склад</th><th class="ft-th--num">Кол.</th><th class="ft-th--num">Доставка</th><th class="ft-th--num">Цена</th></tr></thead><tbody>';
    suppliers.forEach(function(s,i){
        var cls=i>=limit?' class="ft-more" style="display:none"':'';
        var det=s._description||s.description||'—';
        h+='<tr'+cls+'><td class="ft-td--det" data-label="Деталь">'+esc(det)+'</td><td class="ft-td--skl" data-label="Склад"><span class="ft-skl-name">'+esc(s.warehouse||'—')+'</span><span class="src-tag src-tag--'+s.supplier+'">'+s.supplier+'</span></td><td class="ft-td--num" data-label="Кол.">'+s.quantity+' шт.</td><td class="ft-td--num" data-label="Доставка">'+dRange(s.delivery_days)+'</td><td class="ft-td--prc" data-label="Цена"><strong>'+fmt(s.price)+' р.</strong></td></tr>';
    });
    h+='</tbody></table>';
    if(suppliers.length>limit)h+='<button class="ft-showmore">Показать еще '+(suppliers.length-limit)+' товаров</button>';
    return h;
}

function showError(msg){
    qs('#resultContent').innerHTML='<div class="hero" style="margin-top:16px"><div class="hero-icon">⚠️</div><p>'+esc(msg)+'</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
}

document.addEventListener('DOMContentLoaded',function(){loadResults()});
})();
</script>
<?php endif; ?>

</body></html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>