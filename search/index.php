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

<div class="container srch-box">

<?php if (!$q): ?>
<div class="hero">
    <h1><svg class="icon"><use href="#icon-search"></use></svg> Поиск автозапчастей</h1>
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
        <button type="submit" class="topbar-btn"><svg class="icon"><use href="#icon-search"></use></svg></button>
    </form>
    <span class="topbar-info">Поиск: <strong><?=esc($q)?></strong></span>
</div>
<?php
// Собственный склад — рендерим сразу серверно (как parts-search/), без AJAX-заглушки
// с мёртвой ссылкой "Показать →". LOGIC=>OR должен быть ВЛОЖЕННЫМ подмассивом —
// слитый на один уровень с IBLOCK_ID/ACTIVE превращает фильтр в "IBLOCK_ID=42 ИЛИ ACTIVE=Y ИЛИ ...".
$localOrBlock = ['LOGIC' => 'OR',
    ['%NAME' => $q], ['PROPERTY_CML2_ARTICLE' => $q],
    ['%PROPERTY_CML2_ARTICLE' => $q], ['%DETAIL_TEXT' => $q],
    ['PROPERTY_CML2_MANUFACTURER' => $q], ['%PROPERTY_CML2_MANUFACTURER' => $q],
];
$localCountRes = CIBlockElement::GetList([], [
    'IBLOCK_ID' => 42,
    'ACTIVE'    => 'Y',
    'CATALOG_AVAILABLE' => 'Y', // держим в паре с HIDE_NOT_AVAILABLE=>Y у catalog.section ниже, иначе счётчик считает и то, что компонент скроет
    $localOrBlock,
], false, false, ['ID']);
$localCount = $localCountRes->SelectedRowsCount();
?>
<?php if ($localCount > 0): ?>
<h2 class="sec-h sec-h--local"><svg class="icon"><use href="#icon-check-circle"></use></svg> На нашем складе <span class="topbar-info">(<?=$localCount?>)</span></h2>
<?php
global $arrFilter;
$arrFilter = [$localOrBlock];
$APPLICATION->IncludeComponent("bitrix:catalog.section", "lider_style", [
    "IBLOCK_TYPE"          => "1c_catalog",
    "IBLOCK_ID"            => 42,
    "INCLUDE_SUBSECTIONS"  => "Y",
    "SHOW_ALL_WO_SECTION"  => "Y",
    "ELEMENT_SORT_FIELD"   => "sort",
    "ELEMENT_SORT_ORDER"   => "asc",
    "FILTER_NAME"          => "arrFilter",
    "PRICE_CODE"           => ["Ручная розничная цена"],
    "PROPERTY_CODE"        => ["CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"],
    "PAGE_ELEMENT_COUNT"   => "12",
    "HIDE_NOT_AVAILABLE"   => "Y",
    "BASKET_URL"           => "/personal/cart/",
    "CACHE_TYPE"           => "A",
    "CACHE_TIME"           => "300",
    "SET_TITLE"            => "N",
], false);
?>
<?php endif; ?>
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
    hide('brandStep');hide('emptyMsg');
    show('loader');qs('#loaderText').textContent='Ищем бренды у поставщиков...';
    try{
        var r=await fetch(API+'?action=brands&article='+encodeURIComponent(article));
        var d=await r.json();
        hide('loader');
        if(d.error){showError(d.error);return}
        if(!d.brands||!d.brands.length){showError('По артикулу «'+esc(article)+'» ничего не найдено');return}
        var exact=d.brands.filter(function(b){return b.type==='exact'});
        var analogs=d.brands.filter(function(b){return b.type==='analog'});
        var h='';
        if(exact.length){
            h+='<h2 class="sec-h sec-h--brand"><svg class="icon"><use href="#icon-compare"></use></svg> Выберите бренд для «'+esc(article)+'»</h2>';
            h+='<p class="sec-p">Под этим артикулом у разных производителей могут быть разные детали.</p>';
            h+='<div class="bt"><div class="bt-head"><span class="bt-c bt-c--brand">Производитель</span><span class="bt-c bt-c--art">Артикул</span><span class="bt-c bt-c--desc">Описание</span><span class="bt-c bt-c--act"></span></div>';
            exact.forEach(function(b){
                h+='<div class="bt-row"><span class="bt-c bt-c--brand"><strong>'+esc(b.brand)+'</strong></span><span class="bt-c bt-c--art"><code>'+esc(b.article)+'</code></span><span class="bt-c bt-c--desc">'+esc(b.description||'—')+'</span><span class="bt-c bt-c--act"><a href="/search/?q='+encodeURIComponent(article)+'&brand='+encodeURIComponent(b.brand)+'&number='+encodeURIComponent(b.article)+'" class="btn-sel">Выбрать →</a></span></div>';
            });
            h+='</div>';
        }
        if(analogs.length){
            h+='<details class="dt"><summary class="dt-sum"><svg class="icon"><use href="#icon-list"></use></svg> Аналоги и кросс-номера ('+analogs.length+')</summary><div class="bt" style="margin-top:12px">';
            analogs.forEach(function(b){
                h+='<div class="bt-row"><span class="bt-c bt-c--brand">'+esc(b.brand)+'</span><span class="bt-c bt-c--art"><code>'+esc(b.article)+'</code></span><span class="bt-c bt-c--desc">'+esc(b.description||'—')+'</span><span class="bt-c bt-c--act"><a href="/search/?q='+encodeURIComponent(article)+'&brand='+encodeURIComponent(b.brand)+'&number='+encodeURIComponent(b.article)+'" class="btn-sel btn-sel--sm">Выбрать →</a></span></div>';
            });
            h+='</div></details>';
        }
        show('brandStep');qs('#brandStep').innerHTML=h;
    }catch(e){hide('loader');showError('Ошибка: '+e.message)}
}
function showError(msg){hide('brandStep');show('emptyMsg');qs('#emptyMsg').innerHTML='<div class="hero-icon"><svg class="icon"><use href="#icon-alert"></use></svg></div><p>'+esc(msg)+'</p><form class="hero-frm" method="get"><input type="text" name="q" class="hero-inp" placeholder="Попробуйте другой артикул" autofocus><button class="hero-btn">Найти</button></form>'}

document.addEventListener('DOMContentLoaded',function(){loadBrands(Q)});
})();
</script>

<?php else: ?>
<div class="topbar">
    <form class="topbar-frm" method="get">
        <input type="text" name="q" class="topbar-inp" value="<?=esc($q)?>">
        <button type="submit" class="topbar-btn"><svg class="icon"><use href="#icon-search"></use></svg></button>
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

function pollProgress(taskId, onTick) {
    var stopped = false;
    var timer = setInterval(async function(){
        if (stopped) return;
        try {
            var r = await fetch(API + '?action=progress&task=' + encodeURIComponent(taskId));
            var d = await r.json();
            if (stopped) return; // ответ пришёл ПОСЛЕ stop() — отбрасываем, иначе затрёт уже отрисованные результаты
            onTick(d.percent || 0, d.message || '');
            // Авто-остановку по percent>=100 намеренно не делаем: файл прогресса переиспользуется
            // между Phase 1 и crossload, и в момент старта докрутки там ещё лежит старое "100% Готово"
            // от Phase 1 — остановка по этому значению обрывала бы поллинг докрутки до её начала.
        } catch(e) { /* следующий тик попробует снова */ }
    }, 700);
    return function stop(){ stopped = true; clearInterval(timer); };
}

async function loadResults(){
    var taskId = 'srch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    showProgress(0, 'Запуск поиска...');
    var stopP1 = pollProgress(taskId, function(pct, msg){ showProgress(pct, msg); });

    // ═══ PHASE 1 ═══
    var d1;
    try {
        var r1 = await fetch(API + '?action=search&article=' + encodeURIComponent(Q)
            + '&brand=' + encodeURIComponent(B)
            + '&number=' + encodeURIComponent(N)
            + '&task=' + encodeURIComponent(taskId));
        d1 = await r1.json();
    } catch(e) {
        stopP1();
        showError('Ошибка соединения: ' + e.message);
        return;
    }
    stopP1();

    if (d1.error) { showError(d1.error); return; }

    renderResults(d1);

    // ═══ PHASE 2: добор в фоне ═══
    if (d1.phase === 1 && d1.cross_count > 0 && d1.crossPairs) {
        var resultEl = qs('#resultContent');
        var loadDiv = document.createElement('div');
        loadDiv.className = 'cross-loading';
        loadDiv.innerHTML = '<div class="loader-inline"><span class="spinner-inline"></span> <span class="cross-loading-text">Подбираем цены для ' + d1.cross_count + ' аналогов у всех поставщиков...</span></div>';
        resultEl.insertBefore(loadDiv, resultEl.firstChild);
        var textEl = loadDiv.querySelector('.cross-loading-text');

        var stopP2 = pollProgress(taskId, function(pct, msg){
            if (textEl) textEl.textContent = (msg || 'Докручиваем аналоги') + ' (' + pct + '%)';
        });

        try {
            var r2 = await fetch(API + '?action=crossload&task=' + encodeURIComponent(taskId)
                + '&brand=' + encodeURIComponent(B) + '&number=' + encodeURIComponent(N)
                + '&crossPairs=' + encodeURIComponent(JSON.stringify(d1.crossPairs)));
            if (!r2.ok) {
                console.error('crossload HTTP error', r2.status, await r2.text());
                showToast('Не удалось доподбрать часть предложений у поставщиков', 'warn');
            } else {
                var d2 = await r2.json();
                var hasOffers = d2.analog_offers && Object.keys(d2.analog_offers).length > 0;
                var hasNew = d2.new_analogs && Object.keys(d2.new_analogs).length > 0;
                var hasExact = d2.exact_offers && d2.exact_offers.length > 0;
                if (hasOffers || hasNew || hasExact) {
                    var addedCount = mergeAnalogOffers(d1, d2.analog_offers || {}, d2.new_analogs || {});
                    var addedExact = mergeExactOffers(d1, d2.exact_offers || []);
                    renderResults(d1);
                    var parts = [];
                    if (addedCount.groups > 0) parts.push(addedCount.groups + ' новых аналогов');
                    if (addedCount.offers > 0) parts.push(addedCount.offers + ' предл. от ' + addedCount.suppliers + ' поставщиков');
                    if (addedExact.offers > 0) parts.push(addedExact.offers + ' предл. искомого номера');
                    if (parts.length) showToast('Добавлено: ' + parts.join(', '), 'ok');
                } else {
                    console.warn('crossload вернул пусто', d2);
                }
            }
        } catch(e) {
            console.error('crossload failed:', e);
            showToast('Не удалось доподбрать часть предложений у поставщиков', 'warn');
        }
        stopP2();
        var liveDiv = qs('.cross-loading');
        if (liveDiv) liveDiv.remove();
    }
}

function mergeAnalogOffers(d1, analogOffers, newAnalogs) {
    if (!d1.analogs) d1.analogs = [];
    var keyToIdx = {};
    d1.analogs.forEach(function(a, i) {
        var key = a.key || (a.brand + '|' + a.article).toLowerCase().replace(/[^a-z0-9|]/g, '');
        keyToIdx[key] = i;
    });

    // Новые карточки аналогов, найденные при докрутке (докрутка умеет не только
    // добирать склады к уже известным аналогам, но и открывать новые — см. discovery в ajax.php)
    var addedGroups = 0;
    for (var nk in (newAnalogs || {})) {
        if (!newAnalogs.hasOwnProperty(nk) || keyToIdx[nk] !== undefined) continue;
        var info = newAnalogs[nk];
        d1.analogs.push({
            key: nk, brand: info.brand, article: info.article, description: info.description || '',
            suppliers: [], best_price: 0, best_delivery: null, total_qty: 0, has_instock: false
        });
        keyToIdx[nk] = d1.analogs.length - 1;
        addedGroups++;
    }

    var addedOffers = 0;
    var addedSuppliers = {};
    for (var gk in analogOffers) {
        if (!analogOffers.hasOwnProperty(gk)) continue;
        var idx = keyToIdx[gk];
        if (idx === undefined) continue;
        var existing = d1.analogs[idx];
        var seen = {};
        existing.suppliers.forEach(function(s) { seen[s.supplier + '|' + s.price] = true; });
        analogOffers[gk].forEach(function(o) {
            if (!seen[o.supplier + '|' + o.price]) {
                existing.suppliers.push(o);
                seen[o.supplier + '|' + o.price] = true;
                addedOffers++;
                addedSuppliers[o.supplier] = true;
            }
        });
        existing.suppliers.sort(function(x, y) {
            if (x.price != y.price) return x.price - y.price;
            return (x.delivery_days || 0) - (y.delivery_days || 0);
        });
        var prices = existing.suppliers.map(function(s){return s.price;}).filter(function(p){return p>0;});
        var days   = existing.suppliers.map(function(s){return s.delivery_days;}).filter(function(d){return d>=0;});
        var qtys   = existing.suppliers.map(function(s){return s.quantity;});
        existing.best_price    = prices.length ? Math.min.apply(null, prices) : 0;
        existing.best_delivery = days.length ? Math.min.apply(null, days) : null;
        existing.total_qty     = qtys.reduce(function(sum, q){return sum+q;}, 0);
        existing.has_instock   = qtys.some(function(q){return q>0;});
    }
    d1.analogs.sort(function(a, b) {
        if (a.has_instock !== b.has_instock) return b.has_instock - a.has_instock;
        var da = a.best_delivery != null ? a.best_delivery : 999;
        var db = b.best_delivery != null ? b.best_delivery : 999;
        if (da !== db) return da - db;
        return a.best_price - b.best_price;
    });
    return {offers: addedOffers, suppliers: Object.keys(addedSuppliers).length, groups: addedGroups};
}

// Доп. предложения ИСКОМОГО номера, найденные при докрутке (напр. поставщик
// отдал их под вариантом бренда вроде MANN вместо MANN-FILTER — see ajax.php discovery)
function mergeExactOffers(d1, exactOffers) {
    if (!exactOffers || !exactOffers.length) return {offers: 0};
    if (!d1.exact) d1.exact = {brand: B, article: N, suppliers: []};
    var seen = {};
    d1.exact.suppliers.forEach(function(s) { seen[s.supplier + '|' + s.price] = true; });
    var added = 0;
    exactOffers.forEach(function(o) {
        var key = o.supplier + '|' + o.price;
        if (!seen[key]) { d1.exact.suppliers.push(o); seen[key] = true; added++; }
    });
    d1.exact.suppliers.sort(function(x, y) {
        if (x.price != y.price) return x.price - y.price;
        return (x.delivery_days || 0) - (y.delivery_days || 0);
    });
    return {offers: added};
}

function showToast(msg, kind) {
    var t = document.createElement('div');
    t.className = 'toast toast--' + (kind || 'ok');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function(){ t.classList.add('toast--show'); });
    setTimeout(function(){
        t.classList.remove('toast--show');
        setTimeout(function(){ t.remove(); }, 300);
    }, 4500);
}

var lastData = null;
var filterState = { brands: null, maxDelivery: null, minQty: null }; // null = не ограничено

function normBrandKey(s){ return (s||'').toLowerCase().trim(); }

function getAllBrands(d){
    var map = {};
    if (d.exact) map[normBrandKey(B)] = B;
    (d.analogs||[]).forEach(function(a){ map[normBrandKey(a.brand)] = a.brand; });
    return map; // key -> отображаемое имя
}
function brandAllowed(brandDisplay){
    if (!filterState.brands) return true;
    return filterState.brands.has(normBrandKey(brandDisplay));
}
function passesRowFilter(s){
    if (filterState.maxDelivery != null && !(s.delivery_days >= 0 && s.delivery_days <= filterState.maxDelivery)) return false;
    if (filterState.minQty != null && !(s.quantity >= filterState.minQty)) return false;
    return true;
}

window.toggleFilterBrand = function(key){
    if (!filterState.brands) filterState.brands = new Set();
    if (filterState.brands.has(key)) filterState.brands.delete(key); else filterState.brands.add(key);
    if (filterState.brands.size === 0) filterState.brands = null; // ничего не выбрано — показываем всё
    if (lastData) renderResults(lastData);
};
window.filterBrandOptions = function(inputEl){
    var q = inputEl.value.trim().toLowerCase();
    var panel = inputEl.closest('.filter-dd-panel');
    panel.querySelectorAll('.filter-opt[data-label]').forEach(function(row){
        var label = row.getAttribute('data-label');
        row.style.display = (!q || label.indexOf(q) !== -1) ? '' : 'none';
    });
};
window.setFilterDelivery = function(val){
    filterState.maxDelivery = val;
    if (lastData) renderResults(lastData);
};
window.setFilterQty = function(val){
    filterState.minQty = val;
    if (lastData) renderResults(lastData);
};
window.resetFilters = function(){
    filterState = { brands: null, maxDelivery: null, minQty: null };
    if (lastData) renderResults(lastData);
};

var DELIVERY_OPTS = [[null,'Любой'],[0,'Сегодня'],[2,'До 2 дней'],[5,'До 5 дней'],[10,'До 10 дней']];
var QTY_OPTS = [[null,'Любое'],[1,'В наличии'],[10,'От 10 шт.'],[50,'От 50 шт.']];

function renderFilterBar(d){
    var brandsMap = getAllBrands(d);
    var brandKeys = Object.keys(brandsMap).sort();
    if (!brandKeys.length) return '';

    var isActive = !!filterState.brands || filterState.maxDelivery != null || filterState.minQty != null;

    var h = '<div class="filter-bar">';

    if (brandKeys.length > 1) {
        h += '<details class="filter-dd"><summary>Бренд<span class="filter-dd-arrow">▾</span></summary><div class="filter-dd-panel filter-dd-panel--wide">';
        h += '<input type="text" class="filter-search" placeholder="Введите бренд" oninput="filterBrandOptions(this)">';
        brandKeys.forEach(function(key){
            var checked = !!filterState.brands && filterState.brands.has(key);
            h += '<label class="filter-opt" data-label="' + esc(key) + '"><input type="checkbox" onchange="toggleFilterBrand(\'' + key + '\')"' + (checked?' checked':'') + '>' + esc(brandsMap[key]) + '</label>';
        });
        h += '</div></details>';
    }

    h += '<details class="filter-dd"><summary>Срок доставки<span class="filter-dd-arrow">▾</span></summary><div class="filter-dd-panel">';
    DELIVERY_OPTS.forEach(function(opt){
        var val = opt[0], label = opt[1];
        var checked = (filterState.maxDelivery === val);
        h += '<label class="filter-opt"><input type="radio" name="flt-deliv" onchange="setFilterDelivery(' + (val===null?'null':val) + ')"' + (checked?' checked':'') + '>' + label + '</label>';
    });
    h += '</div></details>';

    h += '<details class="filter-dd"><summary>Доступное количество<span class="filter-dd-arrow">▾</span></summary><div class="filter-dd-panel">';
    QTY_OPTS.forEach(function(opt){
        var val = opt[0], label = opt[1];
        var checked = (filterState.minQty === val);
        h += '<label class="filter-opt"><input type="radio" name="flt-qty" onchange="setFilterQty(' + (val===null?'null':val) + ')"' + (checked?' checked':'') + '>' + label + '</label>';
    });
    h += '</div></details>';

    if (isActive) h += '<button type="button" class="filter-reset" onclick="resetFilters()">Сбросить</button>';
    h += '</div>';
    return h;
}

function renderResults(d){
    lastData = d;
    var exact=d.exact||null,analogsAll=d.analogs||[];

    var exactVisible = (exact && exact.suppliers && brandAllowed(B)) ? exact.suppliers.filter(passesRowFilter) : [];
    var analogsVisible = analogsAll.map(function(a){
        if (!brandAllowed(a.brand)) return null;
        var visible = a.suppliers.filter(passesRowFilter);
        if (!visible.length) return null;
        var prices = visible.map(function(s){return s.price;}).filter(function(p){return p>0;});
        var days   = visible.map(function(s){return s.delivery_days;}).filter(function(dd){return dd>=0;});
        var qtys   = visible.map(function(s){return s.quantity;});
        return {
            brand: a.brand, article: a.article, description: a.description,
            suppliers: visible,
            best_price:    prices.length ? Math.min.apply(null, prices) : 0,
            best_delivery: days.length ? Math.min.apply(null, days) : null,
            total_qty:     qtys.reduce(function(s,q){return s+q;}, 0),
            has_instock:   qtys.some(function(q){return q>0;})
        };
    }).filter(function(a){ return a !== null; });

    var allOffers=[];
    exactVisible.forEach(function(s){s._type='exact';s._brand=exact.brand;s._article=exact.article;s._description=s.description||'';allOffers.push(s)});
    analogsVisible.forEach(function(a){a.suppliers.forEach(function(s){s._type='analog';s._brand=a.brand;s._article=a.article;s._description=a.description||'';allOffers.push(s)});});

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
    if(exact&&exact.suppliers){
        var filterOn = !!filterState.brands || filterState.maxDelivery != null || filterState.minQty != null;
        var subtxt = filterOn
            ? 'Показано '+exactVisible.length+' из '+exact.suppliers.length+' предл. искомого + '+analogsVisible.length+' из '+analogsAll.length+' аналогов (фильтр применён)'
            : 'Найдено '+exact.suppliers.length+' предл. искомого + '+analogsAll.length+' аналогов';
        h+='<p class="phead-sub">'+subtxt+'</p>';
    }
    h+='</div>';

    h+=renderFilterBar(d);

    if(bestPriceExact||bestPriceAnalog||bestDelivery){
        h+='<div class="hl-cards">';
        if(bestPriceExact)h+=hlCard(bestPriceExact,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Искомый номер');
        if(bestPriceAnalog)h+=hlCard(bestPriceAnalog,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Аналог');
        if(bestDelivery)h+=hlCard(bestDelivery,'НАИМЕНЬШИЙ СРОК','hl-card--fast','hl-badge--delivery',bestDelivery._type==='exact'?'Искомый номер':'Аналог');
        h+='</div>';
    }

    h+='<div class="full-tbl">';

    if(exactVisible.length){
        h+='<div class="ft-sec ft-sec--exact"><div class="ft-sec-head"><span class="ft-sec-title"><svg class="icon"><use href="#icon-check-circle"></use></svg> Искомый номер</span><span class="ft-sec-sub">'+esc(B)+' / '+esc(N)+' — '+exactVisible.length+' складов</span></div>';
        h+=supplierTable(exactVisible,'exact');
        h+='</div>';
    }

    if(analogsVisible.length){
        h+='<div class="ft-sec ft-sec--analog"><div class="ft-sec-head"><span class="ft-sec-title"><svg class="icon"><use href="#icon-refresh"></use></svg> Аналоги ('+analogsVisible.length+')</span></div>';
        analogsVisible.forEach(function(a){
            h+='<div class="ft-group"><div class="ft-ghead"><div class="ft-ginfo"><strong class="ft-gbrand">'+esc(a.brand)+'</strong><code class="ft-gart">'+esc(a.article)+'</code><span class="ft-gdesc">'+esc(a.description||'')+'</span></div><div class="ft-gmeta"><span class="ft-gbest">Лучшая: <b>'+fmt(a.best_price)+' р.</b> / '+(a.best_delivery!==null?a.best_delivery+' дн.':'—')+'</span><span class="badge '+(a.has_instock?'badge--green':'badge--yellow')+'">'+a.total_qty+' шт.</span></div></div>';
            h+=supplierTable(a.suppliers,'analog');
            h+='</div>';
        });
        h+='</div>';
    }

    if(!exactVisible.length&&!analogsVisible.length){
        var filterOn2 = !!filterState.brands || filterState.maxDelivery != null || filterState.minQty != null;
        if (filterOn2 && (exact||analogsAll.length)) {
            h+='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-search"></use></svg></div><p>Под текущий фильтр ничего не подходит</p><button type="button" class="btn-sel" onclick="resetFilters()">Сбросить фильтр</button></div>';
        } else {
            h='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-alert"></use></svg></div><p>По запросу «'+esc(B)+' '+esc(N)+'» ничего не найдено</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
        }
    }

    h+='</div>';
    qs('#resultContent').innerHTML=h;

    document.querySelectorAll('.ft-showmore').forEach(function(btn){
        btn.addEventListener('click',function(){
            var group=btn.closest('.ft-sec, .ft-group');
            var hidden=group.querySelectorAll('.ft-more');
            var expanded=btn.getAttribute('data-expanded')==='1';
            hidden.forEach(function(r){r.style.display=expanded?'none':''});
            btn.setAttribute('data-expanded',expanded?'0':'1');
            btn.textContent=expanded?('Показать еще '+btn.dataset.count+' товаров'):'Свернуть';
        });
    });
}

function hlCard(o,title,cardCls,badgeCls,type){
    return '<div class="hl-card '+cardCls+'"><div class="hl-badge '+badgeCls+'">'+title+'</div><div class="hl-type">'+type+'</div><div class="hl-name">'+esc(o._brand)+' / '+esc(o._article)+'</div><div class="hl-price">'+fmt(o.price)+' р.</div><div class="hl-meta">'+o.quantity+' шт. &middot; '+dRange(o.delivery_days)+'</div><div class="hl-src"><span class="src-tag src-tag--'+o.supplier+'">'+o.supplier+'</span></div></div>';
}

function supplierTable(suppliers,type){
    var limit=type==='exact'?5:2;
    var h='<table class="ft-tbl"><colgroup><col class="ft-col--det"><col class="ft-col--skl"><col class="ft-col--qty"><col class="ft-col--del"><col class="ft-col--prc"></colgroup><thead><tr><th class="ft-th--det">Деталь</th><th class="ft-th--skl">Склад</th><th class="ft-th--num">Кол.</th><th class="ft-th--num">Доставка</th><th class="ft-th--num">Цена</th></tr></thead><tbody>';
    suppliers.forEach(function(s,i){
        var cls=i>=limit?' class="ft-more" style="display:none"':'';
        var det=s._description||s.description||'—';
        h+='<tr'+cls+'><td class="ft-td--det" data-label="Деталь">'+esc(det)+'</td><td class="ft-td--skl" data-label="Склад"><span class="ft-skl-name">'+esc(s.warehouse||'—')+'</span><span class="src-tag src-tag--'+s.supplier+'">'+s.supplier+'</span></td><td class="ft-td--num" data-label="Кол.">'+s.quantity+' шт.</td><td class="ft-td--num" data-label="Доставка">'+dRange(s.delivery_days)+'</td><td class="ft-td--prc" data-label="Цена"><strong>'+fmt(s.price)+' р.</strong></td></tr>';
    });
    h+='</tbody></table>';
    if(suppliers.length>limit)h+='<button class="ft-showmore" data-count="'+(suppliers.length-limit)+'">Показать еще '+(suppliers.length-limit)+' товаров</button>';
    return h;
}

function showError(msg){
    qs('#resultContent').innerHTML='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-alert"></use></svg></div><p>'+esc(msg)+'</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
}

document.addEventListener('DOMContentLoaded',function(){loadResults()});
})();
</script>
<?php endif; ?>

</body></html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>