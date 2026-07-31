<?php
// search/index.php — новый поиск liderws.ru
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
$q = trim($_REQUEST['q'] ?? '');
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $q ? 'Поиск: ' . htmlspecialchars($q) : 'Поиск запчастей' ?> — liderws.ru</title>
<link rel="stylesheet" href="/search/style.css">
</head>
<body>

<div class="srch" id="app">

    <!-- Пустой поиск -->
    <div id="emptyState" class="empty<?= $q ? '' : ' visible' ?>">
        <div class="empty-icon">🔍</div>
        <p>Введите артикул запчасти</p>
        <form class="srch-frm-hero" id="searchFormHero">
            <input type="text" name="q" class="srch-inp-hero" placeholder="Например: W7008" autofocus autocomplete="off">
            <button type="submit" class="srch-btn-hero">Найти</button>
        </form>
    </div>

    <!-- Свои остатки -->
    <div id="localStock" class="section hidden"></div>

    <!-- Загрузка -->
    <div id="loader" class="loader hidden"><div class="spinner"></div><div id="loaderText"></div></div>

    <!-- Выбор бренда -->
    <div id="brandStep" class="hidden"></div>

    <!-- Результаты -->
    <div id="resultStep" class="hidden"></div>

</div>

<script>
(function() {
    var API = '/search/ajax.php';
    var CURRENT_Q = '';
    var CURRENT_BRAND = '';
    var CURRENT_NUMBER = '';

    function qs(s, el) { return (el || document).querySelector(s); }
    function qsa(s, el) { return [].slice.call((el || document).querySelectorAll(s)); }
    function hide(id) { qs('#' + id).classList.add('hidden'); }
    function show(id) { qs('#' + id).classList.remove('hidden'); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function fmt(n) { return new Intl.NumberFormat('ru-RU', {minimumFractionDigits:2,maximumFractionDigits:2}).format(n); }

    function setLoader(text) {
        show('loader');
        qs('#loaderText').textContent = text;
    }

    // === Шаг 1: поиск брендов ===
    async function loadBrands(article) {
        CURRENT_Q = article;
        hide('localStock'); hide('brandStep'); hide('resultStep'); hide('emptyState');
        setLoader('Ищем бренды у поставщиков...');
        history.pushState(null, '', '/search/?q=' + encodeURIComponent(article));

        try {
            var r = await fetch(API + '?action=brands&article=' + encodeURIComponent(article));
            var d = await r.json();
            hide('loader');
            if (d.error) { showError(d.error); return; }

            // Свои остатки
            if (d.local_count > 0) {
                show('localStock');
                qs('#localStock').innerHTML =
                    '<div class="local-box">' +
                    '<h2 class="sec-title sec-title--local">🔵 На нашем складе</h2>' +
                    '<p class="local-hint">Найдено <strong>' + d.local_count + '</strong> позиций. ' +
                    '<a href="/search/?q=' + encodeURIComponent(article) + '&show_local=1">Показать →</a></p>' +
                    '</div>';
            }

            if (!d.brands || !d.brands.length) {
                showError('По артикулу «' + esc(article) + '» ничего не найдено');
                return;
            }

            var exact = d.brands.filter(function(b) { return b.type === 'exact'; });
            var analogs = d.brands.filter(function(b) { return b.type === 'analog'; });

            var html = '';
            if (exact.length) {
                html += '<h2 class="sec-title sec-title--brand">🟠 Выберите бренд для «' + esc(article) + '»</h2>';
                html += '<p class="sec-hint">Под этим артикулом у разных производителей могут быть разные детали.</p>';
                html += renderBrandTable(exact);
            }
            if (analogs.length) {
                html += '<details class="analog-toggle"><summary class="analog-summary">📋 Аналоги и кросс-номера (' + analogs.length + ')</summary>';
                html += renderBrandTable(analogs);
                html += '</details>';
            }

            show('brandStep');
            qs('#brandStep').innerHTML = html;

        } catch (e) {
            hide('loader');
            showError('Ошибка: ' + e.message);
        }
    }

    function renderBrandTable(brands) {
        var h = '<div class="bt">';
        h += '<div class="bt-head"><span class="bt-c bt-c--brand">Производитель</span><span class="bt-c bt-c--art">Артикул</span><span class="bt-c bt-c--desc">Описание</span><span class="bt-c bt-c--src">Источники</span><span class="bt-c bt-c--act"></span></div>';
        brands.forEach(function(b) {
            var srcs = (b.sources || []).map(function(s) {
                return '<span class="src-tag src-tag--' + s + '">' + s + '</span>';
            }).join(' ');
            h += '<div class="bt-row">' +
                '<span class="bt-c bt-c--brand"><strong>' + esc(b.brand) + '</strong></span>' +
                '<span class="bt-c bt-c--art"><code>' + esc(b.article) + '</code></span>' +
                '<span class="bt-c bt-c--desc">' + esc(b.description || '—') + '</span>' +
                '<span class="bt-c bt-c--src">' + srcs + '</span>' +
                '<span class="bt-c bt-c--act"><button class="btn-sel" data-brand="' + esc(b.brand) + '" data-number="' + esc(b.article) + '">Выбрать →</button></span>' +
            '</div>';
        });
        h += '</div>';
        return h;
    }

    // === Шаг 2: результаты ===
    async function loadResults(brand, number) {
        CURRENT_BRAND = brand;
        CURRENT_NUMBER = number;
        hide('localStock'); hide('brandStep'); hide('resultStep');
        setLoader('Подбираем цены и аналоги...');

        try {
            var r = await fetch(API + '?action=search&article=' + encodeURIComponent(CURRENT_Q) + '&brand=' + encodeURIComponent(brand) + '&number=' + encodeURIComponent(number));
            var d = await r.json();
            hide('loader');
            if (d.error) { showError(d.error); return; }

            var html = '';

            // === ХЛЕБНЫЕ КРОШКИ + ЗАГОЛОВОК ===
            html += '<div class="rs-top">' +
                '<a href="javascript:loadBrands(\'' + esc(CURRENT_Q) + '\')" class="rs-back">← К выбору бренда</a>' +
                '<h1 class="rs-title">' + esc(brand) + ' <span class="rs-art">' + esc(number) + '</span></h1>' +
            '</div>';

            // === ИСКОМЫЙ ===
            if (d.exact && d.exact.suppliers && d.exact.suppliers.length) {
                html += '<div class="rs-block rs-block--exact">' +
                    '<h2 class="rs-block-title rs-block-title--exact">✅ Искомый артикул</h2>' +
                    '<p class="rs-block-sub">' + esc(brand) + ' ' + esc(number) + ' — ' + d.exact.suppliers.length + ' предложений от поставщиков</p>' +
                    renderSupplierTable(d.exact.suppliers) +
                '</div>';
            }

            // === АНАЛОГИ ===
            if (d.analogs && d.analogs.length) {
                html += '<div class="rs-block rs-block--analog">' +
                    '<h2 class="rs-block-title rs-block-title--analog">🔄 Аналоги (' + d.analogs.length + ')</h2>';

                d.analogs.forEach(function(a) {
                    var bestP = a.best_price ? fmt(a.best_price) + ' ₽' : '—';
                    var bestD = a.best_delivery !== null ? a.best_delivery + ' дн.' : '—';
                    var inStk = a.has_instock;

                    html += '<div class="rs-analog-group">' +
                        '<div class="rs-analog-head">' +
                            '<div class="rs-analog-info">' +
                                '<strong class="rs-analog-brand">' + esc(a.brand) + '</strong>' +
                                '<code class="rs-analog-art">' + esc(a.article) + '</code>' +
                                '<span class="rs-analog-desc">' + esc(a.description || '') + '</span>' +
                            '</div>' +
                            '<div class="rs-analog-meta">' +
                                '<span class="rs-analog-best">Лучшая: <b>' + bestP + '</b> / ' + bestD + '</span>' +
                                '<span class="badge ' + (inStk ? 'badge--green' : 'badge--yellow') + '">' + a.total_qty + ' шт.</span>' +
                            '</div>' +
                        '</div>' +
                        renderSupplierTable(a.suppliers) +
                    '</div>';
                });

                html += '</div>';
            }

            if (!d.exact && (!d.analogs || !d.analogs.length)) {
                showError('Нет доступных предложений');
                return;
            }

            show('resultStep');
            qs('#resultStep').innerHTML = html;

        } catch (e) {
            hide('loader');
            showError('Ошибка: ' + e.message);
        }
    }

    function renderSupplierTable(suppliers) {
        if (!suppliers || !suppliers.length) return '<p class="rs-empty">Нет предложений</p>';
        var h = '<table class="sup-tbl"><thead><tr><th>Поставщик</th><th>Склад</th><th class="sup-tbl--num">Наличие</th><th class="sup-tbl--num">Доставка</th><th class="sup-tbl--num">Цена</th></tr></thead><tbody>';
        suppliers.forEach(function(s) {
            var dDays = s.delivery_days >= 0 ? s.delivery_days + ' дн.' : '—';
            var qtyCls = s.quantity > 0 ? 'sup-qty--ok' : 'sup-qty--zero';
            h += '<tr>' +
                '<td><span class="src-tag src-tag--' + s.supplier + '">' + s.supplier + '</span></td>' +
                '<td class="sup-wh">' + esc(s.warehouse || '—') + '</td>' +
                '<td class="sup-tbl--num"><span class="sup-qty ' + qtyCls + '">' + (s.quantity > 0 ? s.quantity + ' шт.' : 'под заказ') + '</span></td>' +
                '<td class="sup-tbl--num">' + dDays + '</td>' +
                '<td class="sup-tbl--num sup-price"><strong>' + fmt(s.price) + ' ₽</strong></td>' +
            '</tr>';
        });
        h += '</tbody></table>';
        return h;
    }

    function showError(msg) {
        hide('localStock'); hide('brandStep'); hide('resultStep');
        show('emptyState');
        qs('#emptyState').innerHTML =
            '<div class="empty-icon">⚠️</div><p>' + esc(msg) + '</p>' +
            '<form class="srch-frm-hero" id="searchFormHero"><input type="text" class="srch-inp-hero" placeholder="Попробуйте другой артикул" autofocus><button type="submit" class="srch-btn-hero">Найти</button></form>';
    }

    // Делегирование кликов
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-sel');
        if (btn) {
            var brand = btn.getAttribute('data-brand');
            var number = btn.getAttribute('data-number');
            if (brand && number) loadResults(brand, number);
        }
    });

    // Формы поиска
    function bindForm(formId) {
        var f = qs('#' + formId);
        if (!f) return;
        f.addEventListener('submit', function(e) {
            e.preventDefault();
            var inp = f.querySelector('input[name="q"]');
            var q = inp.value.trim();
            if (!q) return;
            loadBrands(q);
        });
    }
    bindForm('searchFormHero');

    // При загрузке
    <?php if ($q): ?>
    document.addEventListener('DOMContentLoaded', function() { loadBrands(<?= json_encode($q) ?>); });
    <?php endif; ?>

})();
</script>

</body>
</html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>