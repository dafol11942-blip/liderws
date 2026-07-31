<?php
// search/index.php — новый поиск liderws.ru (чистовой)
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Поиск запчастей");
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
    <!-- Шапка с поисковой строкой -->
    <div class="srch-bar">
        <form class="srch-frm" id="searchForm">
            <input type="text" name="q" class="srch-inp" id="qInput"
                   placeholder="Введите артикул, например: W7008"
                   value="<?= htmlspecialchars($q) ?>" autofocus autocomplete="off">
            <button type="submit" class="srch-btn" id="searchBtn">Найти</button>
        </form>
        <div class="srch-info" id="searchInfo"></div>
    </div>

    <!-- Свои остатки (1С) -->
    <div id="localStock" class="local-stock"></div>

    <!-- Загрузка -->
    <div id="loader" class="loader hidden">
        <div class="spinner"></div>
        <div id="loaderText"></div>
    </div>

    <!-- Выбор бренда -->
    <div id="brandStep" class="hidden"></div>

    <!-- Результаты -->
    <div id="resultStep" class="hidden"></div>

    <!-- Пусто -->
    <div id="emptyState" class="empty<?= $q ? '' : ' visible' ?>">
        <div class="empty-icon">🔍</div>
        <p>Введите артикул, название запчасти или VIN-номер</p>
    </div>
</div>

<script>
(function() {
    var API = '/search/ajax.php';

    // === Хелперы ===
    function qs(s, el) { return (el || document).querySelector(s); }
    function qsa(s, el) { return [].slice.call((el || document).querySelectorAll(s)); }
    function hide(id) { qs('#' + id).classList.add('hidden'); }
    function show(id, display) {
        var el = qs('#' + id);
        el.classList.remove('hidden');
        if (display) el.style.display = display;
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function fmt(n) { return new Intl.NumberFormat('ru-RU').format(n); }

    // === Шаг 1: поиск брендов ===
    async function loadBrands(article) {
        hide('localStock'); hide('brandStep'); hide('resultStep'); hide('emptyState');
        show('loader');
        qs('#loaderText').textContent = 'Ищем бренды у поставщиков...';
        qs('#searchBtn').disabled = true;
        qs('#searchInfo').innerHTML = 'Поиск: <strong>' + esc(article) + '</strong>';

        try {
            var r = await fetch(API + '?action=brands&article=' + encodeURIComponent(article));
            var d = await r.json();
            hide('loader');
            qs('#searchBtn').disabled = false;

            if (d.error) { showError(d.error); return; }

            // Свои остатки
            if (d.local_count > 0) {
                show('localStock');
                qs('#localStock').innerHTML =
                    '<h2 class="sec-title sec-title--local">🔵 На нашем складе</h2>' +
                    '<div class="local-hint">Найдено <strong>' + d.local_count + '</strong> позиций. ' +
                    '<a href="/search/?q=' + encodeURIComponent(article) + '&show_local=1">Показать →</a></div>';
            }

            // Бренды
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
                html += renderBrandTable(exact, article);
            }
            if (analogs.length) {
                html += '<details class="analog-toggle"><summary class="analog-summary">📋 Аналоги и кросс-номера (' + analogs.length + ')</summary>';
                html += renderBrandTable(analogs, article, true);
                html += '</details>';
            }

            show('brandStep');
            qs('#brandStep').innerHTML = html;

        } catch (e) {
            hide('loader');
            qs('#searchBtn').disabled = false;
            showError('Ошибка: ' + e.message);
        }
    }

    function renderBrandTable(brands, article, compact) {
        var h = '<div class="brand-tbl">';
        h += '<div class="bt-head"><div class="bt-c bt-c--brand">Бренд</div><div class="bt-c bt-c--art">Артикул</div><div class="bt-c bt-c--desc">Описание</div><div class="bt-c bt-c--src">Источники</div><div class="bt-c bt-c--act"></div></div>';
        brands.forEach(function(b) {
            var srcs = (b.sources || []).map(function(s) { return '<span class="src-tag src-tag--' + s + '">' + s + '</span>'; }).join('');
            h += '<div class="bt-row">' +
                '<div class="bt-c bt-c--brand"><strong>' + esc(b.brand) + '</strong></div>' +
                '<div class="bt-c bt-c--art"><code>' + esc(b.article) + '</code></div>' +
                '<div class="bt-c bt-c--desc">' + esc(b.description || '—') + '</div>' +
                '<div class="bt-c bt-c--src">' + srcs + '</div>' +
                '<div class="bt-c bt-c--act"><button class="btn-sel' + (compact ? ' btn-sel--sm' : '') + '" data-article="' + esc(article) + '" data-brand="' + esc(b.brand) + '" data-number="' + esc(b.article) + '">Выбрать →</button></div>' +
            '</div>';
        });
        h += '</div>';
        return h;
    }

    // === Шаг 2: результаты ===
    async function loadResults(article, brand, number) {
        hide('localStock'); hide('brandStep'); hide('resultStep');
        show('loader');
        qs('#loaderText').textContent = 'Подбираем аналоги и цены...';
        qs('#searchBtn').disabled = true;
        qs('#qInput').value = article + ' / ' + brand;
        qs('#searchInfo').innerHTML = '<strong>' + esc(brand) + '</strong> <code>' + esc(number) + '</code> ' +
            '<a href="javascript:void(0)" class="back-lnk" data-article="' + esc(article) + '">← Назад</a>';

        try {
            var r = await fetch(API + '?action=search&article=' + encodeURIComponent(article) + '&brand=' + encodeURIComponent(brand) + '&number=' + encodeURIComponent(number));
            var d = await r.json();
            hide('loader');
            qs('#searchBtn').disabled = false;

            if (d.error) { showError(d.error); return; }

            var html = '';

            // Точное совпадение
            if (d.exact) {
                html += renderResultBlock(d.exact, 'exact', '✅ Искомый: ' + esc(brand) + ' / ' + esc(number));
            }

            // Аналоги
            if (d.analogs && d.analogs.length) {
                html += renderResultBlock({ groups: d.analogs }, 'analog', '🔄 Аналоги (' + d.analogs.length + ' поз.)');
            }

            if (!html) {
                showError('Нет доступных предложений');
                return;
            }

            show('resultStep');
            qs('#resultStep').innerHTML = html;

            // Клик для раскрытия складов
            qsa('.res-main-row').forEach(function(row) {
                row.addEventListener('click', function() {
                    var wh = row.nextElementSibling;
                    var isOpen = row.classList.toggle('open');
                    wh.style.display = isOpen ? 'block' : 'none';
                });
            });

        } catch (e) {
            hide('loader');
            qs('#searchBtn').disabled = false;
            showError('Ошибка: ' + e.message);
        }
    }

    function renderResultBlock(data, cls, title) {
        if (data.groups) {
            // Аналоги
            var h = '<div class="res-block res-block--' + cls + '">' +
                '<div class="res-hdr"><span class="res-badge res-badge--' + cls + '">' + title + '</span></div>' +
                '<div class="res-tbl">' +
                '<div class="res-thead"><div class="res-c res-c--exp"></div><div class="res-c res-c--brand">Бренд</div><div class="res-c res-c--desc">Описание</div><div class="res-c res-c--art">Артикул</div><div class="res-c res-c--stk">Наличие</div><div class="res-c res-c--dlv">Доставка</div><div class="res-c res-c--prc">Цена</div></div>';
            data.groups.forEach(function(g) { h += renderGroup(g); });
            h += '</div></div>';
            return h;
        } else {
            // Точное совпадение (одна позиция, много складов)
            var h = '<div class="res-block res-block--exact">' +
                '<div class="res-hdr"><span class="res-badge res-badge--exact">' + title + '</span></div>' +
                '<div class="res-tbl">' +
                '<div class="res-thead"><div class="res-c res-c--exp"></div><div class="res-c res-c--src">Поставщик</div><div class="res-c res-c--wh">Склад</div><div class="res-c res-c--stk">Наличие</div><div class="res-c res-c--dlv">Доставка</div><div class="res-c res-c--prc">Цена</div></div>';
            (data.suppliers || []).forEach(function(s) {
                var dDays = s.delivery_days >= 0 ? s.delivery_days + ' дн.' : '—';
                h += '<div class="res-wrow">' +
                    '<div class="res-c res-c--exp"></div>' +
                    '<div class="res-c res-c--src"><span class="src-tag src-tag--' + s.supplier + '">' + s.supplier + '</span></div>' +
                    '<div class="res-c res-c--wh">' + esc(s.warehouse || '—') + '</div>' +
                    '<div class="res-c res-c--stk"><span class="badge ' + (s.quantity > 0 ? 'badge--green' : 'badge--yellow') + '">' + (s.quantity > 0 ? s.quantity + ' шт.' : 'под заказ') + '</span></div>' +
                    '<div class="res-c res-c--dlv">' + dDays + '</div>' +
                    '<div class="res-c res-c--prc"><strong>' + fmt(s.price) + ' ₽</strong></div>' +
                '</div>';
            });
            h += '</div></div>';
            return h;
        }
    }

    function renderGroup(g) {
        var dDays = g.best_delivery !== null ? g.best_delivery + ' дн.' : '—';
        var inStock = g.has_instock;
        var h = '<div class="res-group">' +
            '<div class="res-row res-main-row ' + (inStock ? 'res-row--instock' : 'res-row--order') + '">' +
                '<div class="res-c res-c--exp"><span class="res-exp">▶</span></div>' +
                '<div class="res-c res-c--brand"><strong>' + esc(g.brand) + '</strong></div>' +
                '<div class="res-c res-c--desc"><div class="res-desc">' + esc(g.description || '') + '</div></div>' +
                '<div class="res-c res-c--art"><code>' + esc(g.article) + '</code></div>' +
                '<div class="res-c res-c--stk"><span class="badge ' + (inStock ? 'badge--green' : 'badge--yellow') + '">' + g.total_qty + ' шт.</span></div>' +
                '<div class="res-c res-c--dlv">' + dDays + '</div>' +
                '<div class="res-c res-c--prc"><strong>' + fmt(g.best_price) + ' ₽</strong><div class="res-whc">' + (g.suppliers ? g.suppliers.length : 0) + ' складов</div></div>' +
            '</div>' +
            '<div class="res-warehouses" style="display:none">';
        (g.suppliers || []).forEach(function(s) {
            var sDays = s.delivery_days >= 0 ? s.delivery_days + ' дн.' : '—';
            h += '<div class="res-wrow ' + (s.quantity > 0 ? 'res-wrow--instock' : 'res-wrow--order') + '">' +
                '<div class="res-c res-c--exp"></div>' +
                '<div class="res-c res-c--src"><span class="src-tag src-tag--' + s.supplier + '">' + s.supplier + '</span></div>' +
                '<div class="res-c res-c--wh">' + esc(s.warehouse || '—') + '</div>' +
                '<div class="res-c res-c--stk"><span class="badge ' + (s.quantity > 0 ? 'badge--green' : 'badge--yellow') + '">' + (s.quantity > 0 ? s.quantity + ' шт.' : 'под заказ') + '</span></div>' +
                '<div class="res-c res-c--dlv">' + sDays + '</div>' +
                '<div class="res-c res-c--prc"><strong>' + fmt(s.price) + ' ₽</strong></div>' +
            '</div>';
        });
        h += '</div></div>';
        return h;
    }

    function showError(msg) {
        hide('localStock'); hide('brandStep'); hide('resultStep');
        show('emptyState');
        qs('#emptyState').innerHTML = '<div class="empty-icon">⚠️</div><p>' + esc(msg) + '</p>';
    }

    // === Делегирование событий (вместо onclick в атрибутах) ===
    document.addEventListener('click', function(e) {
        // Кнопка «Выбрать» в таблице брендов
        var btn = e.target.closest('.btn-sel');
        if (btn) {
            var article = btn.getAttribute('data-article');
            var brand = btn.getAttribute('data-brand');
            var number = btn.getAttribute('data-number');
            if (article && brand && number) {
                loadResults(article, brand, number);
            }
        }

        // Кнопка «Назад»
        var back = e.target.closest('.back-lnk');
        if (back) {
            var article = back.getAttribute('data-article');
            if (article) {
                loadBrands(article);
            }
        }
    });

    // Форма
    qs('#searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var q = qs('#qInput').value.trim();
        if (!q) return;
        history.pushState(null, '', '/search/?q=' + encodeURIComponent(q));
        loadBrands(q);
    });

    // При загрузке
    <?php if ($q): ?>
    document.addEventListener('DOMContentLoaded', function() { loadBrands(<?= json_encode($q) ?>); });
    <?php endif; ?>

})();
</script>

</body>
</html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>