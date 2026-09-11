<?php
// search/index.php — поиск liderws.ru (AJAX, топ-5 в аналогах)
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/init_pricing.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/lib/Search/BrandNormalizer.php");

use Lider\Search\BrandNormalizer;

$isManager = isManager();
$favSupplierKeys = $USER->IsAuthorized() ? getFavoritedSupplierKeys($USER->GetID()) : [];
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
// Делим найденное на своём складе на "искомый артикул" (точное совпадение по артикулу)
// и "аналоги" — по аналогии с делением exact/analogs у заказного товара (search/ajax.php).
$normQ = BrandNormalizer::normalizeArticle($q);
$localExactIds = [];
$localAnalogIds = [];
$localIdsRes = CIBlockElement::GetList([], [
    'IBLOCK_ID' => 42,
    'ACTIVE'    => 'Y',
    'CATALOG_AVAILABLE' => 'Y', // держим в паре с HIDE_NOT_AVAILABLE=>Y у catalog.section ниже, иначе счётчик считает и то, что компонент скроет
    $localOrBlock,
], false, false, ['ID', 'PROPERTY_CML2_ARTICLE']);
while ($row = $localIdsRes->Fetch()) {
    $isExact = $normQ !== '' && BrandNormalizer::normalizeArticle($row['PROPERTY_CML2_ARTICLE_VALUE'] ?? '') === $normQ;
    if ($isExact) { $localExactIds[] = $row['ID']; } else { $localAnalogIds[] = $row['ID']; }
}
$localCount = count($localExactIds) + count($localAnalogIds);
$localBrandPropCode = getBrandPropertyCode(42);
$localCardParams = [
    "IBLOCK_TYPE"          => "1c_catalog",
    "IBLOCK_ID"            => 42,
    "INCLUDE_SUBSECTIONS"  => "Y",
    "SHOW_ALL_WO_SECTION"  => "Y",
    "ELEMENT_SORT_FIELD"   => "sort",
    "ELEMENT_SORT_ORDER"   => "asc",
    "FILTER_NAME"          => "arrFilter",
    "PRICE_CODE"           => ["Ручная розничная цена"],
    "PROPERTY_CODE"        => array_values(array_filter(["CML2_ARTICLE", "CML2_MANUFACTURER", $localBrandPropCode, "IN_STOCK"])),
    "PAGE_ELEMENT_COUNT"   => "12",
    "HIDE_NOT_AVAILABLE"   => "Y",
    "BASKET_URL"           => "/personal/cart/",
    "CACHE_TYPE"           => "A",
    "CACHE_TIME"           => "300",
    "SET_TITLE"            => "N",
];
?>
<?php if ($localCount > 0): ?>
<h2 class="sec-h sec-h--local"><svg class="icon"><use href="#icon-check-circle"></use></svg> На нашем складе <span class="topbar-info">(<?=$localCount?>)</span></h2>

<?php if ($localExactIds): ?>
<div class="ft-sec ft-sec--exact">
    <div class="ft-sec-head">
        <span class="ft-sec-title"><svg class="icon"><use href="#icon-check-circle"></use></svg> Искомый артикул</span>
        <span class="ft-sec-sub"><?=esc($q)?> — <?=count($localExactIds)?> шт.</span>
    </div>
    <div class="ft-secbody">
    <?php
    global $arrFilter;
    $arrFilter = [['ID' => $localExactIds]];
    $APPLICATION->IncludeComponent("bitrix:catalog.section", "lider_style", $localCardParams, false);
    ?>
    </div>
</div>
<?php endif; ?>

<?php if ($localAnalogIds): ?>
<div class="ft-sec ft-sec--analog">
    <div class="ft-sec-head">
        <span class="ft-sec-title"><svg class="icon"><use href="#icon-refresh"></use></svg> Аналоги</span>
        <span class="ft-sec-sub"><?=count($localAnalogIds)?> шт.</span>
    </div>
    <div class="ft-secbody">
    <?php
    global $arrFilter;
    $arrFilter = [['ID' => $localAnalogIds]];
    $APPLICATION->IncludeComponent("bitrix:catalog.section", "lider_style", $localCardParams, false);
    ?>
    </div>
</div>
<?php endif; ?>

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
            h+='<h2 class="sec-h sec-h--brand"><svg class="icon"><use href="#icon-compare"></use></svg> Под заказ от поставщиков</h2>';
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
var IS_MANAGER=<?=json_encode($isManager)?>;
var FAV_SUPPLIER_KEYS=<?=json_encode($favSupplierKeys)?>;
function isFavSupplier(brand,article,supplier){ return FAV_SUPPLIER_KEYS.indexOf((brand||'')+'|'+(article||'')+'|'+(supplier||'')) !== -1; }
var TASK_ID=null;
var hideBasePrice=false;
var crossInfoData=null; // {success,title,img,criterias,oem,superseded} с /local/ajax/umapi_cross_info.php

// Липкая панель фильтров должна встать сразу под липкой шапкой сайта (.header,
// position:sticky из lider_modern), а не поверх неё. У шапки нет фиксированной
// высоты/CSS-переменной (зависит от брейкпоинта), поэтому меряем её в рантайме.
function syncHeaderHeight(){
    var hdr = document.querySelector('.header');
    if (hdr) document.documentElement.style.setProperty('--search-header-h', hdr.getBoundingClientRect().height + 'px');
}
syncHeaderHeight();
window.addEventListener('resize', syncHeaderHeight);
window.addEventListener('load', syncHeaderHeight);

function qs(s,el){return(el||document).querySelector(s)}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function fmt(n){return new Intl.NumberFormat('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}

// fetch() без таймаута может висеть бесконечно, если сервер не отвечает — а пока
// он висит, pollProgress() продолжает опрашивать action=progress каждые 1.5с без
// остановки (именно это переполнило лимит запросов/мин на стороне хостинга и
// привело к блокировке IP). Жёсткий предел заставляет запрос сдаться, если ответа
// нет слишком долго, вместо бесконечного ожидания.
function fetchWithTimeout(url, ms){
    var ctrl = new AbortController();
    var t = setTimeout(function(){ ctrl.abort(); }, ms);
    return fetch(url, {signal: ctrl.signal}).finally(function(){ clearTimeout(t); });
}
function dRange(s){
    if(s&&s.delivery_label){
        var cls='ft-deliv-label'+(s.delivery_today?' ft-deliv-label--today':'');
        var title=s.delivery_deadline?' title="Успеете, если закажете до '+esc(s.delivery_deadline)+'"':'';
        var text=s.delivery_label+(s.delivery_time?' '+s.delivery_time:'');
        return '<span class="'+cls+'"'+title+'>'+esc(text)+'</span>';
    }
    var d=s&&s.delivery_days;
    return d>=0?d+' дн.':'—';
}
// Предложение с минимальной ценой среди списка — чтобы в шапке группы аналога
// (ft-gbest, "Лучшая: ЦЕНА / СРОК") срок доставки был настоящим сроком именно
// ЭТОГО предложения ("Сегодня 13:15 - 16:00"), а не голым числом дней отдельно
// взятого (обычно другого) самого быстрого предложения.
function pickCheapestOffer(offers){
    var best=null;
    (offers||[]).forEach(function(s){
        if (s.client_price>0 && (!best || s.client_price<best.client_price)) best=s;
    });
    return best;
}

function showProgress(pct, msg) {
    qs('#resultContent').innerHTML =
        '<div class="loader">' +
        '<div class="spinner"></div>' +
        '<div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
        '<div class="progress-text">' + pct + '% — ' + esc(msg) + '</div>' +
        '</div>';
}

// Хостинг заблокировал IP посетителя за превышение частоты запросов именно к
// action=progress — при 700мс интервале это ~86 запросов/мин с ОДНОЙ вкладки,
// а если основной fetch (search/crossload) зависает (нет клиентского таймаута
// у fetch()), polling продолжается неограниченно долго, пока ответ не придёт.
// Увеличенный интервал + жёсткий потолок по времени страхуют от накрутки сотен
// запросов за одно зависшее ожидание.
function pollProgress(taskId, onTick, maxMs) {
    maxMs = maxMs || 90000;
    var stopped = false;
    var startedAt = Date.now();
    var timer = setInterval(async function(){
        if (stopped) return;
        if (Date.now() - startedAt > maxMs) { stopped = true; clearInterval(timer); return; }
        try {
            var r = await fetch(API + '?action=progress&task=' + encodeURIComponent(taskId));
            var d = await r.json();
            if (stopped) return; // ответ пришёл ПОСЛЕ stop() — отбрасываем, иначе затрёт уже отрисованные результаты
            onTick(d.percent || 0, d.message || '');
            // Авто-остановку по percent>=100 намеренно не делаем: файл прогресса переиспользуется
            // между Phase 1 и crossload, и в момент старта докрутки там ещё лежит старое "100% Готово"
            // от Phase 1 — остановка по этому значению обрывала бы поллинг докрутки до её начала.
        } catch(e) { /* следующий тик попробует снова */ }
    }, 1500);
    return function stop(){ stopped = true; clearInterval(timer); };
}

async function loadResults(){
    var taskId = 'srch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    showProgress(0, 'Запуск поиска...');
    var stopP1 = pollProgress(taskId, function(pct, msg){ showProgress(pct, msg); });

    // ═══ PHASE 1 ═══
    var d1;
    try {
        var r1 = await fetchWithTimeout(API + '?action=search&article=' + encodeURIComponent(Q)
            + '&brand=' + encodeURIComponent(B)
            + '&number=' + encodeURIComponent(N)
            + '&task=' + encodeURIComponent(taskId), 45000);
        d1 = await r1.json();
    } catch(e) {
        stopP1();
        showError(e.name === 'AbortError' ? 'Поиск занял слишком много времени, попробуйте ещё раз' : 'Ошибка соединения: ' + e.message);
        return;
    }
    stopP1();

    if (d1.error) { showError(d1.error); return; }

    TASK_ID = d1.task_id || null;
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
            var r2 = await fetchWithTimeout(API + '?action=crossload&task=' + encodeURIComponent(taskId)
                + '&brand=' + encodeURIComponent(B) + '&number=' + encodeURIComponent(N)
                + '&crossPairs=' + encodeURIComponent(JSON.stringify(d1.crossPairs)), 60000);
            if (!r2.ok) {
                console.error('crossload HTTP error', r2.status, await r2.text());
                showToast('Не удалось доподбрать часть предложений у поставщиков', 'warn');
            } else {
                var d2 = await r2.json();
                var hasOffers = d2.analog_offers && Object.keys(d2.analog_offers).length > 0;
                var hasNew = d2.new_analogs && Object.keys(d2.new_analogs).length > 0;
                if (hasOffers || hasNew) {
                    var addedCount = mergeAnalogOffers(d1, d2.analog_offers || {}, d2.new_analogs || {});
                    renderResults(d1);
                    var parts = [];
                    if (addedCount.groups > 0) parts.push(addedCount.groups + ' новых аналогов');
                    if (addedCount.offers > 0) parts.push(addedCount.offers + ' предл. от ' + addedCount.suppliers + ' поставщиков');
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
        // offer_token уникален на каждое предложение — используем его вместо supplier+price
        // (для не-менеджера supplier в ответе отсутствует).
        existing.suppliers.forEach(function(s) { seen[s.offer_token] = true; });
        analogOffers[gk].forEach(function(o) {
            if (!seen[o.offer_token]) {
                existing.suppliers.push(o);
                seen[o.offer_token] = true;
                addedOffers++;
                addedSuppliers[o.supplier || o.offer_token] = true;
            }
        });
        existing.suppliers.sort(function(x, y) {
            var dx = x.delivery_days >= 0 ? x.delivery_days : Infinity;
            var dy = y.delivery_days >= 0 ? y.delivery_days : Infinity;
            if (dx !== dy) return dx - dy;
            return x.client_price - y.client_price;
        });
        var prices = existing.suppliers.map(function(s){return s.client_price;}).filter(function(p){return p>0;});
        var days   = existing.suppliers.map(function(s){return s.delivery_days;}).filter(function(d){return d>=0;});
        var qtys   = existing.suppliers.map(function(s){return s.quantity;});
        existing.best_price    = prices.length ? Math.min.apply(null, prices) : 0;
        existing.best_delivery = days.length ? Math.min.apply(null, days) : null;
        existing.best_delivery_offer = pickCheapestOffer(existing.suppliers);
        existing.total_qty     = qtys.reduce(function(sum, q){return sum+q;}, 0);
        existing.total_qty_label = (IS_MANAGER || existing.total_qty <= 10) ? (existing.total_qty + ' шт.') : 'Много';
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

document.addEventListener('click', function(e) {
    var favBtn = e.target.closest && e.target.closest('.fav-btn');
    if (favBtn) {
        // TASK_ID живёт в замыкании этого IIFE, поэтому не наружный window.TASK_ID (которого
        // тут просто нет) — прокидываем его явно в data-атрибут перед вызовом общей toggleFavorite()
        // из favorites.js (см. footer.php), которая ждёт task именно там.
        favBtn.setAttribute('data-task', TASK_ID || '');
        if (window.toggleFavorite) window.toggleFavorite(favBtn);
        return;
    }

    var stepBtn = e.target.closest && e.target.closest('.actl-step');
    if (stepBtn) {
        var stepper = stepBtn.closest('.actl-stepper');
        var qtyInput = stepper ? stepper.querySelector('.actl-qty') : null;
        if (qtyInput) {
            // Шаг = минимальная партия (data-step, см. addToCartControl) — ниже
            // него уменьшить нельзя, увеличение идёт кратно ему (2 → 4 → 6…).
            var step = parseInt(qtyInput.getAttribute('data-step'), 10) || 1;
            var maxStep = parseInt(qtyInput.getAttribute('max'), 10) || 999999;
            var val = parseInt(qtyInput.value, 10) || step;
            val = stepBtn.classList.contains('actl-step--plus') ? Math.min(maxStep, val + step) : Math.max(step, val - step);
            qtyInput.value = val;
        }
        return;
    }

    var btn = e.target.closest && e.target.closest('.actl-btn');
    if (!btn) return;
    var wrap = btn.closest('.actl');
    var input = wrap ? wrap.querySelector('.actl-qty') : null;
    var step = parseInt(btn.getAttribute('data-step'), 10) || 1;
    var max = parseInt(btn.getAttribute('data-max'), 10) || 0;
    var qty = input ? (parseInt(input.value, 10) || step) : step;
    // На случай ручного ввода значения, не кратного минимальной партии —
    // округляем вниз до ближайшей допустимой партии перед сверкой с остатком.
    qty = Math.floor(qty / step) * step;
    if (qty < step) qty = step;

    if (qty > max) {
        var stepperEl = wrap ? wrap.querySelector('.actl-stepper') : null;
        if (input) input.value = max;
        if (stepperEl) {
            stepperEl.classList.remove('actl-stepper--err');
            void stepperEl.offsetWidth; // перезапуск CSS-анимации при повторной ошибке подряд
            stepperEl.classList.add('actl-stepper--err');
            setTimeout(function(){ stepperEl.classList.remove('actl-stepper--err'); }, 900);
        }
        var supplierLabel = btn.getAttribute('data-supplier');
        showToast((supplierLabel ? 'У поставщика «' + supplierLabel + '» ' : 'В наличии ') + 'только ' + max + ' шт. Количество скорректировано.', 'warn');
        return;
    }

    if (btn.disabled) return;

    var brand = btn.getAttribute('data-brand') || '';
    var article = btn.getAttribute('data-article') || '';
    var supplier = btn.getAttribute('data-supplier') || '';
    var token = btn.getAttribute('data-token') || '';

    btn.disabled = true;
    fetch('/local/ajax/order_from_supplier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            task: TASK_ID, offer_token: token,
            article: article, brand: brand, supplier: supplier,
            quantity: qty
        })
    }).then(function(r){ return r.json(); }).then(function(data){
        btn.disabled = false;
        if (data.success) {
            showToast('Добавлено в корзину: ' + brand + ' / ' + article + ' — ' + qty + ' шт.', 'ok');
            if (window.updateCartBadge && data.cart_qty !== undefined) window.updateCartBadge(data.cart_qty);
        } else {
            showToast(data.message || 'Не удалось добавить в корзину', 'warn');
        }
    }).catch(function(){
        btn.disabled = false;
        showToast('Ошибка соединения, попробуйте ещё раз', 'warn');
    });
});

// Ручной ввод количества (не только степпер +/-) — округляем до ближайшей
// допустимой партии сразу по выходу из поля, чтобы пользователь видел
// скорректированное значение, а не только терял его при добавлении в корзину.
document.addEventListener('change', function(e) {
    var input = e.target.closest && e.target.closest('.actl-qty');
    if (!input) return;
    var step = parseInt(input.getAttribute('data-step'), 10) || 1;
    var max = parseInt(input.getAttribute('max'), 10) || step;
    var val = Math.round((parseInt(input.value, 10) || step) / step) * step;
    input.value = Math.max(step, Math.min(max, val));
});

// Попап "Статистика поставки" (.rel-pop) — position:fixed, чтобы не обрезаться
// границами ячейки таблицы/карточки аналога (overflow:hidden). Координаты
// вычисляются от реального положения бейджа на экране непосредственно перед
// тем, как CSS (:hover/:focus) его покажет.
function positionRelPop(badge){
    var pop = badge.querySelector('.rel-pop');
    if (!pop) return;
    var r = badge.getBoundingClientRect();
    pop.style.left = Math.round(r.left + r.width / 2) + 'px';
    pop.style.top = Math.round(r.top - 8) + 'px';
    pop.style.transform = 'translate(-50%, -100%)';
}
document.addEventListener('mouseover', function(e) {
    var badge = e.target.closest && e.target.closest('.rel-badge');
    if (badge) positionRelPop(badge);
});
document.addEventListener('focusin', function(e) {
    var badge = e.target.closest && e.target.closest('.rel-badge');
    if (badge) positionRelPop(badge);
});

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
var filterState = { brands: null, suppliers: null, maxDelivery: null, minQty: null, excludeNonReturnable: false }; // null = не ограничено
function isFilterActive(){
    return !!filterState.brands || !!filterState.suppliers || filterState.maxDelivery != null || filterState.minQty != null || filterState.excludeNonReturnable;
}

// Пользовательская сортировка по цене внутри отдельной позиции (искомый номер
// или конкретный аналог). Ключ — 'exact' либо ключ группы аналога (a.key),
// значение — 1 (дешевле→дороже), -1 (дороже→дешевле) или undefined (сортировка
// по умолчанию: срок, потом цена — см. sortOffers() на бэкенде).
var priceSortState = {};
window.cyclePriceSort = function(key){
    var cur = priceSortState[key];
    priceSortState[key] = cur === undefined ? 1 : (cur === 1 ? -1 : undefined);
    if (lastData) renderResults(lastData);
};

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
// Список поставщиков доступен только менеджеру (у остальных s.supplier в ответе
// отсутствует — см. sanitizeOffer() на бэкенде), поэтому и фильтр по нему смысл
// имеет только для менеджера.
function getAllSuppliers(d){
    var map = {};
    if (!IS_MANAGER) return map;
    (d.exact && d.exact.suppliers || []).forEach(function(s){ if (s.supplier) map[s.supplier] = true; });
    (d.analogs||[]).forEach(function(a){ (a.suppliers||[]).forEach(function(s){ if (s.supplier) map[s.supplier] = true; }); });
    return map; // code -> true
}
function supplierAllowed(s){
    if (!filterState.suppliers) return true;
    return filterState.suppliers.has(s.supplier);
}
function passesRowFilter(s){
    if (!supplierAllowed(s)) return false;
    if (filterState.maxDelivery != null && !(s.delivery_days >= 0 && s.delivery_days <= filterState.maxDelivery)) return false;
    if (filterState.minQty != null && !(s.quantity >= filterState.minQty)) return false;
    if (filterState.excludeNonReturnable && s.returnable === false) return false;
    return true;
}
function hasAnyNonReturnable(d){
    var offers = (d.exact && d.exact.suppliers) || [];
    (d.analogs||[]).forEach(function(a){ offers = offers.concat(a.suppliers||[]); });
    return offers.some(function(s){ return s.returnable === false; });
}

window.toggleFilterBrand = function(key){
    if (!filterState.brands) filterState.brands = new Set();
    if (filterState.brands.has(key)) filterState.brands.delete(key); else filterState.brands.add(key);
    if (filterState.brands.size === 0) filterState.brands = null; // ничего не выбрано — показываем всё
    if (lastData) renderResults(lastData);
};
window.toggleFilterSupplier = function(code){
    if (!filterState.suppliers) filterState.suppliers = new Set();
    if (filterState.suppliers.has(code)) filterState.suppliers.delete(code); else filterState.suppliers.add(code);
    if (filterState.suppliers.size === 0) filterState.suppliers = null;
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
    filterState = { brands: null, suppliers: null, maxDelivery: null, minQty: null, excludeNonReturnable: false };
    if (lastData) renderResults(lastData);
};
window.toggleHideBasePrice = function(checked){
    hideBasePrice = checked;
    qs('#resultContent').classList.toggle('hide-base-price', hideBasePrice);
};
window.toggleExcludeNonReturnable = function(checked){
    filterState.excludeNonReturnable = checked;
    if (lastData) renderResults(lastData);
};

var DELIVERY_OPTS = [[null,'Любой'],[0,'Сегодня'],[2,'До 2 дней'],[5,'До 5 дней'],[10,'До 10 дней']];
var QTY_OPTS = [[null,'Любое'],[1,'В наличии'],[10,'От 10 шт.'],[50,'От 50 шт.']];

function renderFilterBar(d){
    var brandsMap = getAllBrands(d);
    var brandKeys = Object.keys(brandsMap).sort();
    var supplierKeys = Object.keys(getAllSuppliers(d)).sort();
    if (!brandKeys.length && !IS_MANAGER && !hasAnyNonReturnable(d)) return '';

    var isActive = isFilterActive();

    var h = '<div class="filter-bar">';

    if (IS_MANAGER) {
        h += '<label class="filter-opt filter-opt--toggle"><input type="checkbox"' + (hideBasePrice?' checked':'') + ' onchange="toggleHideBasePrice(this.checked)"> Скрыть закупочную цену</label>';
    }

    if (hasAnyNonReturnable(d)) {
        h += '<label class="filter-opt filter-opt--toggle"><input type="checkbox"' + (filterState.excludeNonReturnable?' checked':'') + ' onchange="toggleExcludeNonReturnable(this.checked)"> Исключить невозвратный товар</label>';
    }

    if (brandKeys.length > 1) {
        h += '<details class="filter-dd"><summary>Бренд<span class="filter-dd-arrow">▾</span></summary><div class="filter-dd-panel filter-dd-panel--wide">';
        h += '<input type="text" class="filter-search" placeholder="Введите бренд" oninput="filterBrandOptions(this)">';
        brandKeys.forEach(function(key){
            var checked = !!filterState.brands && filterState.brands.has(key);
            h += '<label class="filter-opt" data-label="' + esc(key) + '"><input type="checkbox" onchange="toggleFilterBrand(\'' + key + '\')"' + (checked?' checked':'') + '>' + esc(brandsMap[key]) + '</label>';
        });
        h += '</div></details>';
    }

    if (IS_MANAGER && supplierKeys.length > 1) {
        h += '<details class="filter-dd"><summary>Поставщик<span class="filter-dd-arrow">▾</span></summary><div class="filter-dd-panel">';
        supplierKeys.forEach(function(code){
            var checked = !!filterState.suppliers && filterState.suppliers.has(code);
            h += '<label class="filter-opt"><input type="checkbox" onchange="toggleFilterSupplier(\'' + code + '\')"' + (checked?' checked':'') + '><span class="src-tag src-tag--' + code + '">' + esc(code) + '</span></label>';
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

    h += '<button type="button" class="filter-reset' + (isActive ? '' : ' filter-reset--idle') + '"' + (isActive ? '' : ' disabled') + ' onclick="resetFilters()">Сбросить фильтры</button>';
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
        var prices = visible.map(function(s){return s.client_price;}).filter(function(p){return p>0;});
        var days   = visible.map(function(s){return s.delivery_days;}).filter(function(dd){return dd>=0;});
        var qtys   = visible.map(function(s){return s.quantity;});
        var totalQty = qtys.reduce(function(s,q){return s+q;}, 0);
        var cheapest = pickCheapestOffer(visible);
        return {
            key: a.key, brand: a.brand, article: a.article, description: a.description,
            suppliers: visible,
            best_price:    prices.length ? Math.min.apply(null, prices) : 0,
            best_delivery: days.length ? Math.min.apply(null, days) : null,
            best_delivery_offer: cheapest,
            total_qty:     totalQty,
            total_qty_label: (IS_MANAGER || totalQty <= 10) ? (totalQty + ' шт.') : 'Много',
            has_instock:   qtys.some(function(q){return q>0;})
        };
    }).filter(function(a){ return a !== null; });

    var allOffers=[];
    exactVisible.forEach(function(s){s._type='exact';s._brand=exact.brand;s._article=exact.article;s._description=s.description||'';allOffers.push(s)});
    analogsVisible.forEach(function(a){a.suppliers.forEach(function(s){s._type='analog';s._brand=a.brand;s._article=a.article;s._description=a.description||'';allOffers.push(s)});});

    var bestPriceExact=null,bestPriceAnalog=null,bestDelivery=null;
    allOffers.forEach(function(o){
        if(o.client_price>0){
            if(o._type==='exact'&&(!bestPriceExact||o.client_price<bestPriceExact.client_price))bestPriceExact=o;
            if(o._type==='analog'&&(!bestPriceAnalog||o.client_price<bestPriceAnalog.client_price))bestPriceAnalog=o;
        }
        if(o.delivery_days>=0&&(!bestDelivery||o.delivery_days<bestDelivery.delivery_days))bestDelivery=o;
    });

    var h='';
    h+='<div class="phead"><h1 class="phead-title">'+esc(N)+' '+esc(B)+'</h1>';
    if(exact&&exact.suppliers){
        var filterOn = isFilterActive();
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
        var exactHasMore=exactVisible.length>5;
        h+='<div class="ft-sec ft-sec--exact"><div class="ft-sec-head"'+(exactHasMore?' data-ft-toggle':'')+'><span class="ft-sec-title"><svg class="icon"><use href="#icon-check-circle"></use></svg> Искомый номер</span><span class="ft-sec-sub">'+esc(B)+' / '+esc(N)+' — '+exactVisible.length+' складов</span>'+(exactHasMore?'<button type="button" class="ft-gtoggle ft-sec-toggle" aria-expanded="false" title="Показать/свернуть все склады"><svg class="icon"><use href="#icon-chevron-down"></use></svg></button>':'')+'</div>';
        h+='<div class="ft-secbody">'+supplierTable(exactVisible,'exact',B,N,'exact')+'</div>';
        h+='</div>';
    }

    if(analogsVisible.length){
        h+='<div class="ft-sec ft-sec--analog"><div class="ft-sec-head"><span class="ft-sec-title"><svg class="icon"><use href="#icon-refresh"></use></svg> Аналоги ('+analogsVisible.length+')</span></div>';
        analogsVisible.forEach(function(a){
            var groupHasMore=a.suppliers.length>2;
            h+='<div class="ft-group"><div class="ft-ghead"'+(groupHasMore?' data-ft-toggle':'')+'><div class="ft-ginfo"><strong class="ft-gbrand">'+esc(a.brand)+'</strong><code class="ft-gart">'+esc(a.article)+'</code><span class="ft-gdesc">'+esc(a.description||'')+'</span></div><div class="ft-gmeta"><span class="ft-gbest">Лучшая: <b>'+fmt(a.best_price)+' р.</b> / '+(a.best_delivery_offer?dRange(a.best_delivery_offer):'—')+'</span><span class="badge '+(a.has_instock?'badge--green':'badge--yellow')+'">'+a.total_qty_label+'</span>'+(groupHasMore?'<button type="button" class="ft-gtoggle" aria-expanded="false" title="Показать/свернуть все склады"><svg class="icon"><use href="#icon-chevron-down"></use></svg></button>':'')+'</div></div>';
            h+='<div class="ft-gbody">'+supplierTable(a.suppliers,'analog',a.brand,a.article,a.key)+'</div>';
            h+='</div>';
        });
        h+='</div>';
    }

    if(!exactVisible.length&&!analogsVisible.length){
        var filterOn2 = isFilterActive();
        if (filterOn2 && (exact||analogsAll.length)) {
            h+='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-search"></use></svg></div><p>Под текущий фильтр ничего не подходит</p><button type="button" class="btn-sel" onclick="resetFilters()">Сбросить фильтр</button></div>';
        } else {
            h='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-alert"></use></svg></div><p>По запросу «'+esc(B)+' '+esc(N)+'» ничего не найдено</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
        }
    }

    h+='</div>';
    qs('#resultContent').innerHTML=h;
    tryRenderCrossInfoCard();

    // Раскрытие строк сверх лимита: и кнопка "Показать ещё" внизу таблицы, и стрелка
    // в шапке позиции переключают один и тот же .ft-all-shown у общего контейнера —
    // поэтому список можно свернуть прямо из шапки, даже если кнопка внизу уехала
    // далеко вниз из-за большого числа складов.
    function toggleMoreRows(container){
        if(!container)return;
        var expanded=container.classList.toggle('ft-all-shown');
        var toggleBtn=container.querySelector('.ft-gtoggle');
        if(toggleBtn)toggleBtn.setAttribute('aria-expanded',expanded?'true':'false');
        var moreBtn=container.querySelector('.ft-showmore');
        if(moreBtn)moreBtn.textContent=expanded?'Свернуть':('Показать еще '+moreBtn.dataset.count+' товаров');
    }

    document.querySelectorAll('.ft-showmore').forEach(function(btn){
        btn.addEventListener('click',function(){
            toggleMoreRows(btn.closest('.ft-sec, .ft-group'));
        });
    });

    document.querySelectorAll('.ft-ghead[data-ft-toggle], .ft-sec-head[data-ft-toggle]').forEach(function(head){
        head.addEventListener('click',function(){
            toggleMoreRows(head.closest('.ft-sec, .ft-group'));
        });
    });
}

// Карточка товара (фото/характеристики/OEM/замены) — грузится ПАРАЛЛЕЛЬНО с основным
// поиском предложений (см. вызов рядом с loadResults() в DOMContentLoaded), не блокирует
// и не задерживает страницу: если UMAPI медленная/недоступна, .phead остаётся как есть
// (заголовок + "Найдено..."), без ошибок у пользователя (см. план — история инцидента
// с UMAPI в STAGES.md).
function loadCrossInfo(){
    fetch('/local/ajax/umapi_cross_info.php?article=' + encodeURIComponent(N) + '&brand=' + encodeURIComponent(B))
        .then(function(r){ return r.json(); })
        .then(function(data){ crossInfoData = data; tryRenderCrossInfoCard(); })
        .catch(function(){});
}

// Вызывается и из loadCrossInfo(), и из renderResults() (после каждой перерисовки .phead,
// в т.ч. повторной после фазы 2) — гонка между "данные UMAPI пришли" и ".phead появился в
// DOM" решается тем, что оба места дергают одну и ту же функцию, кто последний — тот и
// отрисует; повторный вызов — no-op (проверка data-cross-rendered).
function tryRenderCrossInfoCard(){
    if(!crossInfoData || !crossInfoData.success) return;
    var phead = document.querySelector('.phead');
    if(!phead || phead.getAttribute('data-cross-rendered')) return;
    phead.setAttribute('data-cross-rendered','1');
    phead.insertAdjacentHTML('beforeend', renderCrossInfoCard(crossInfoData));
}

function renderCrossInfoCard(data){
    var h = '<div class="phead-body">';
    if(data.img){
        h += '<div class="phead-img"><img src="'+esc(data.img)+'" alt="'+esc(data.title||'')+'" loading="lazy"></div>';
    }
    h += '<div class="phead-info">';
    if(data.title) h += '<div class="phead-desc">'+esc(data.title)+'</div>';
    if(data.criterias && data.criterias.length){
        h += '<div class="phead-specs">';
        data.criterias.forEach(function(c){
            h += '<span class="phead-spec"><span class="phead-spec-label">'+esc(c.label)+':</span> '+esc(c.value)+'</span>';
        });
        h += '</div>';
    }
    if(data.oem && data.oem.length){
        h += '<div class="phead-oem"><span class="phead-oem-label">OEM:</span> '+data.oem.map(esc).join(', ')+'</div>';
    }
    if(data.superseded){
        if(data.superseded.new && data.superseded.new.length){
            h += '<div class="phead-superseded">Заменён на: '+data.superseded.new.map(esc).join(', ')+'</div>';
        }
        if(data.superseded.old && data.superseded.old.length){
            h += '<div class="phead-superseded">Заменяет: '+data.superseded.old.map(esc).join(', ')+'</div>';
        }
    }
    h += '</div></div>';
    return h;
}

function favToggleControl(brand,article,supplier,warehouse,token){
    var active=isFavSupplier(brand,article,supplier);
    return '<button type="button" class="fav-btn'+(active?' is-active':'')+'" data-fav-type="supplier" '
        +'data-brand="'+esc(brand)+'" data-article="'+esc(article)+'" data-supplier="'+esc(supplier||'')+'" '
        +'data-warehouse="'+esc(warehouse||'')+'" data-token="'+esc(token||'')+'" '
        +'title="В избранное" aria-pressed="'+(active?'true':'false')+'">'
        +'<svg class="icon'+(active?' icon--fill':'')+'"><use href="#icon-heart"></use></svg></button>';
}

function addToCartControl(brand,article,supplier,warehouse,token,qty,description,multiplicity){
    var step=Math.max(1,parseInt(multiplicity,10)||1);
    var avail=Math.max(0,parseInt(qty,10)||0);
    // Остаток должен помещать хотя бы одну полную партию, и максимум всегда
    // кратен шагу — иначе кнопка "+" могла бы довести значение до величины,
    // не являющейся допустимой партией (см. требование "2 → 4 → 6").
    var maxQty=Math.floor(avail/step)*step;
    if(maxQty<step)return '<span class="actl-oos">Нет в наличии</span>';
    return '<div class="actl">'
        +'<div class="actl-stepper">'
        +'<button type="button" class="actl-step actl-step--minus" aria-label="Уменьшить количество">−</button>'
        +'<input type="text" class="actl-qty" inputmode="numeric" min="'+step+'" max="'+maxQty+'" data-step="'+step+'" value="'+step+'">'
        +'<button type="button" class="actl-step actl-step--plus" aria-label="Увеличить количество">+</button>'
        +'</div>'
        +'<button type="button" class="actl-btn" title="В корзину" data-brand="'+esc(brand)+'" data-article="'+esc(article)+'" data-supplier="'+esc(supplier||'')+'" data-warehouse="'+esc(warehouse||'')+'" data-token="'+esc(token||'')+'" data-max="'+maxQty+'" data-step="'+step+'" data-desc="'+esc(description||'')+'"><svg class="icon"><use href="#icon-cart"></use></svg></button>'
        +'</div>';
}

function supplierBadge(s){
    return s.supplier ? '<span class="src-tag src-tag--'+s.supplier+'">'+s.supplier+'</span>' : '';
}

function returnIcon(s){
    if(s.returnable===false){
        return '<span class="ret-badge ret-badge--no" title="Товар не подлежит возврату"><svg class="icon"><use href="#icon-x-circle"></use></svg></span>';
    }
    return '<span class="ret-badge ret-badge--yes" title="Товар подлежит возврату"><svg class="icon"><use href="#icon-check-circle"></use></svg></span>';
}

// Вероятность поставки — не у всех поставщиков есть эта статистика (см.
// reliabilityPercent в коннекторах), тогда просто ничего не рисуем.
function reliabilityBadge(s){
    var pct=s.reliability_percent;
    if(pct===null||pct===undefined)return '';
    pct=Math.max(0,Math.min(100,Math.round(pct)));
    var refusal=(s.refusal_percent!==null&&s.refusal_percent!==undefined)?Math.max(0,Math.min(100,Math.round(s.refusal_percent))):(100-pct);
    var cls=pct>=90?'rel-badge--good':(pct>=70?'rel-badge--mid':'rel-badge--low');
    var deg=Math.round(pct*3.6);
    return '<span class="rel-badge '+cls+'" tabindex="0" title="Вероятность поставки: '+pct+'% (отказ '+refusal+'%)">'+pct+'%'
        +'<span class="rel-pop">'
        +'<span class="rel-pop-title">Статистика поставки</span>'
        +'<span class="rel-pop-body">'
        +'<span class="rel-donut" style="background:conic-gradient(var(--green) 0deg '+deg+'deg, var(--red) '+deg+'deg 360deg)"></span>'
        +'<span class="rel-pop-legend"><span class="rel-leg rel-leg--ok">Выдано: <b>'+pct+'%</b></span><span class="rel-leg rel-leg--no">Отказ: <b>'+refusal+'%</b></span></span>'
        +'</span></span></span>';
}

function priceBlock(s){
    if(!IS_MANAGER || s.base_price==null) return '<span class="price-main">'+fmt(s.client_price)+' р.</span>';
    return '<span class="price-main price-base">'+fmt(s.base_price)+' р.</span>'
        + '<span class="price-sub"><span class="price-sub-label">клиент: </span>'+fmt(s.client_price)+' р.</span>';
}

function hlCard(o,title,cardCls,badgeCls,type){
    var det=o._description||o.description||'';
    return '<div class="hl-card '+cardCls+'"><div class="hl-badge '+badgeCls+'">'+title+'</div><div class="hl-type">'+type+'</div><div class="hl-name">'+esc(o._brand)+' / '+esc(o._article)+'</div>'+(det?'<div class="hl-desc">'+esc(det)+'</div>':'')+'<div class="hl-price">'+priceBlock(o)+'</div><div class="hl-meta">'+o.quantity_label+' '+esc(o.unit||'шт.')+' &middot; '+dRange(o)+'</div><div class="hl-src">'+supplierBadge(o)+'</div><div class="hl-actl">'+reliabilityBadge(o)+returnIcon(o)+favToggleControl(o._brand,o._article,o.supplier,o.warehouse,o.offer_token)+addToCartControl(o._brand,o._article,o.supplier,o.warehouse,o.offer_token,o.quantity,det,o.multiplicity)+'</div></div>';
}

function supplierTable(suppliers,type,brand,article,sortKey){
    var limit=type==='exact'?5:2;
    var dir=priceSortState[sortKey];
    var list=dir?suppliers.slice().sort(function(a,b){return dir*(a.client_price-b.client_price);}):suppliers;
    var sortIc=dir===1?'▲':(dir===-1?'▼':'⇅');
    var sortCls='ft-th--sort'+(dir?' ft-th--sort-active':'');
    var priceTh=sortKey?('<th class="ft-th--num '+sortCls+'" onclick="cyclePriceSort(\''+sortKey+'\')" title="Сортировать по цене">Цена <span class="ft-sort-ic">'+sortIc+'</span></th>'):'<th class="ft-th--num">Цена</th>';
    var h='<table class="ft-tbl"><colgroup><col class="ft-col--det"><col class="ft-col--skl"><col class="ft-col--qty"><col class="ft-col--unit"><col class="ft-col--del"><col class="ft-col--prc"><col class="ft-col--act"></colgroup><thead><tr><th class="ft-th--det">Деталь</th><th class="ft-th--skl">Склад</th><th class="ft-th--num">Кол.</th><th class="ft-th--num">Ед.</th><th class="ft-th--num">Доставка</th>'+priceTh+'<th class="ft-th--act"></th></tr></thead><tbody>';
    list.forEach(function(s,i){
        var cls=i>=limit?' class="ft-more"':'';
        var det=s._description||s.description||'—';
        h+='<tr'+cls+'><td class="ft-td--det" data-label="Деталь">'+esc(det)+'</td><td class="ft-td--skl" data-label="Склад"><span class="ft-skl-name">'+esc(s.warehouse||'—')+'</span>'+supplierBadge(s)+'</td><td class="ft-td--num" data-label="Кол.">'+s.quantity_label+'</td><td class="ft-td--num" data-label="Ед.">'+esc(s.unit||'шт.')+'</td><td class="ft-td--num" data-label="Доставка">'+dRange(s)+'</td><td class="ft-td--prc" data-label="Цена">'+priceBlock(s)+'</td><td class="ft-td--act">'+reliabilityBadge(s)+returnIcon(s)+favToggleControl(brand,article,s.supplier,s.warehouse,s.offer_token)+addToCartControl(brand,article,s.supplier,s.warehouse,s.offer_token,s.quantity,det,s.multiplicity)+'</td></tr>';
    });
    h+='</tbody></table>';
    if(suppliers.length>limit)h+='<button class="ft-showmore" data-count="'+(suppliers.length-limit)+'">Показать еще '+(suppliers.length-limit)+' товаров</button>';
    return h;
}

function showError(msg){
    qs('#resultContent').innerHTML='<div class="hero" style="margin-top:16px"><div class="hero-icon"><svg class="icon"><use href="#icon-alert"></use></svg></div><p>'+esc(msg)+'</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
}

document.addEventListener('DOMContentLoaded',function(){loadResults();loadCrossInfo();});
})();
</script>
<?php endif; ?>

</body></html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>