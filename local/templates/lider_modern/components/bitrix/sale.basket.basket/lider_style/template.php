<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CModule::IncludeModule('sale');
CModule::IncludeModule('iblock');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

if (!defined('CART_TTL_SECONDS')) define('CART_TTL_SECONDS', 12 * 3600);

$isMgr = isManager();

// Получаем корзину
$items = [];
$bRes = CSaleBasket::GetList(
    ['NAME' => 'ASC'],
    ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID]
);

$totalSum = 0;
$totalQty = 0;
$cartMaxDeliveryDays = -1;
$cartMaxDeliveryText = '';
$hasSupplierItem = false;

while ($b = $bRes->Fetch()) {
    $b['PRICE_NUM'] = (float)$b['PRICE'];
    $b['QTY'] = (int)$b['QUANTITY'];
    $b['SUM_NUM'] = $b['PRICE_NUM'] * $b['QTY'];
    $b['PRICE_FMT'] = number_format($b['PRICE_NUM'], 0, ',', ' ') . ' ₽';
    $b['SUM_FMT'] = number_format($b['SUM_NUM'], 0, ',', ' ') . ' ₽';
    $b['URL'] = $b['DETAIL_PAGE_URL'] ?? '#';

    // Картинка из инфоблока
    $b['IMG'] = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
    if ($b['PRODUCT_ID'] > 0) {
        $el = CIBlockElement::GetByID($b['PRODUCT_ID'])->GetNextElement();
        if ($el) {
            $fields = $el->GetFields();
            $preview = $fields['PREVIEW_PICTURE'] ?? $fields['DETAIL_PICTURE'];
            if ($preview) {
                $imgPath = CFile::GetPath($preview);
                if ($imgPath) $b['IMG'] = $imgPath;
            }
        }
    }

    // Свойства позиции, положенные order_from_supplier.php / basket_recheck.php —
    // артикул/бренд/поставщик/склад/срок доставки/остаток/время подтверждения (TTL).
    $props = [];
    $propsRes = CSaleBasket::GetPropsList([], ['BASKET_ID' => $b['ID']]);
    while ($pr = $propsRes->Fetch()) {
        $props[$pr['CODE']] = $pr['VALUE'];
    }
    $b['PROPS'] = $props;

    $supplierCode = $props['SUPPLIER_NAME'] ?? '';
    $b['SUPPLIER_CODE'] = $supplierCode;
    $b['ARTICLE'] = $props['SUPPLIER_ARTICLE'] ?? '';
    $b['BRAND']   = $props['SUPPLIER_BRAND'] ?? '';
    if ($supplierCode !== '') $hasSupplierItem = true;

    // Товар со своего склада (не заказная позиция от поставщика) — своих
    // артикула/бренда в свойствах корзины нет, берём их прямо с элемента каталога.
    if (($b['ARTICLE'] === '' || $b['BRAND'] === '') && $b['PRODUCT_ID'] > 0) {
        $propRes = CIBlockElement::GetProperty(42, $b['PRODUCT_ID'], [], ['CODE' => ['CML2_ARTICLE', 'CML2_MANUFACTURER']]);
        while ($propRow = $propRes->Fetch()) {
            if ($propRow['CODE'] === 'CML2_ARTICLE' && $b['ARTICLE'] === '') {
                $b['ARTICLE'] = (string)($propRow['VALUE'] ?? '');
            } elseif ($propRow['CODE'] === 'CML2_MANUFACTURER' && $b['BRAND'] === '') {
                // Список (тип L) — читаемое значение в VALUE_ENUM, VALUE у него ID enum'а.
                $b['BRAND'] = (string)($propRow['VALUE_ENUM'] ?? $propRow['VALUE'] ?? '');
            }
        }
    }

    $articleBrandParts = [];
    if ($b['BRAND'] !== '')   $articleBrandParts[] = 'Бренд: <b>' . htmlspecialchars($b['BRAND']) . '</b>';
    if ($b['ARTICLE'] !== '') $articleBrandParts[] = 'Артикул: <b>' . htmlspecialchars($b['ARTICLE']) . '</b>';
    $b['ARTICLE_BRAND_HTML'] = implode(' &middot; ', $articleBrandParts);

    $supplierLabel = $supplierCode;
    if ($supplierCode !== '' && function_exists('getSupplierFactory')) {
        $conn = getSupplierFactory()->get($supplierCode);
        if ($conn) $supplierLabel = $conn->getName();
    }
    $b['SUPPLIER_LABEL'] = $supplierLabel;

    $deliveryLabel = $props['SUPPLIER_DELIVERY_LABEL'] ?? '';
    $deliveryTime  = $props['SUPPLIER_DELIVERY_TIME'] ?? '';
    $deliveryDays  = isset($props['SUPPLIER_DELIVERY_DAYS']) ? (int)$props['SUPPLIER_DELIVERY_DAYS'] : null;
    if ($deliveryLabel !== '') {
        $b['DELIVERY_TEXT'] = $deliveryLabel . ($deliveryTime !== '' ? ' ' . $deliveryTime : '');
    } elseif ($deliveryDays !== null && $deliveryDays >= 0) {
        $b['DELIVERY_TEXT'] = $deliveryDays . ' дн.';
    } else {
        $b['DELIVERY_TEXT'] = '';
    }
    // Срок доставки заказа = максимальный (самый долгий) срок среди позиций —
    // раньше всех остальных курьер приехать не может.
    if ($deliveryDays !== null && $deliveryDays >= 0 && $deliveryDays > $cartMaxDeliveryDays) {
        $cartMaxDeliveryDays = $deliveryDays;
        $cartMaxDeliveryText = $b['DELIVERY_TEXT'];
    }

    $addedAt = isset($props['SUPPLIER_ADDED_AT']) ? (int)$props['SUPPLIER_ADDED_AT'] : 0;
    $b['ADDED_AT'] = $addedAt;
    // Позиции без ADDED_AT (положены до введения TTL) не считаем устаревшими,
    // чтобы не ломать уже лежащее в корзинах.
    $b['IS_STALE'] = $addedAt > 0 && (time() - $addedAt) > CART_TTL_SECONDS;

    $totalSum += $b['SUM_NUM'];
    $totalQty += $b['QTY'];
    $items[] = $b;
}

$totalFmt = number_format($totalSum, 0, ',', ' ') . ' ₽';
// Страница корзины и так уже посчитала реальное количество товаров —
// заодно подравниваем кэш счётчика в шапке (header.php), если он разошёлся
// с БД (несколько вкладок/устройств, изменения в админке и т.п.).
$_SESSION['CART_QTY'] = $totalQty;
// Корзина целиком из товаров нашего склада (нет ни одной заказной позиции
// от поставщика) — забрать можно сегодня же, без ожидания поставки.
if (!empty($items) && !$hasSupplierItem) {
    $cartDeliveryFmt = 'Доступен к самовывозу сегодня';
} elseif ($cartMaxDeliveryText !== '') {
    $cartDeliveryFmt = $cartMaxDeliveryText;
} else {
    $cartDeliveryFmt = 'Рассчитывается при оформлении';
}
?>

<?php if (empty($items)): ?>
<div class="cart-page">
    <h1 class="cart-page__title">Корзина</h1>
    <div class="cart-empty">
        <div class="cart-empty__icon"><svg class="icon"><use href="#icon-cart"></use></svg></div>
        <h2>Ваша корзина пуста</h2>
        <p>Перейдите в каталог, чтобы найти нужные запчасти</p>
        <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
    </div>
</div>
<?php else: ?>
<div class="cart-page">
    <div class="cart-page__head">
        <h1 class="cart-page__title">Корзина</h1>
        <button type="button" id="cart-clear-btn" class="cart-clear-btn">Очистить корзину</button>
    </div>
    <div class="cart-layout">
        <div class="cart-items">
            <?php foreach ($items as $item):
                $searchUrl = ($item['ARTICLE'] !== '')
                    ? '/search/?q=' . urlencode($item['ARTICLE']) . '&brand=' . urlencode($item['BRAND']) . '&number=' . urlencode($item['ARTICLE'])
                    : '/search/';
            ?>
            <div class="cart-item<?= $item['IS_STALE'] ? ' cart-item--stale' : '' ?>" id="basket-row-<?= $item['ID'] ?>"
                 data-added-at="<?= (int)$item['ADDED_AT'] ?>" data-search-url="<?= htmlspecialchars($searchUrl) ?>">
                <div class="cart-item__img">
                    <a href="<?= $item['URL'] ?>">
                        <img src="<?= $item['IMG'] ?>" alt="<?= htmlspecialchars($item['NAME']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="cart-item__info">
                    <a href="<?= $item['URL'] ?>" class="cart-item__name"><?= htmlspecialchars($item['NAME']) ?></a>
                    <?php if ($item['ARTICLE_BRAND_HTML'] !== ''): ?>
                    <div class="cart-item__article"><?= $item['ARTICLE_BRAND_HTML'] ?></div>
                    <?php endif; ?>
                    <div class="cart-item__price-unit"><?= $item['PRICE_FMT'] ?> / шт.</div>
                    <?php if ($item['SUPPLIER_CODE'] !== ''): ?>
                    <div class="cart-item__meta">
                        <?php if ($isMgr): ?>
                            Поставщик: <?= htmlspecialchars($item['SUPPLIER_LABEL']) ?><?php if (!empty($item['PROPS']['SUPPLIER_WAREHOUSE'])): ?> · Склад: <?= htmlspecialchars($item['PROPS']['SUPPLIER_WAREHOUSE']) ?><?php endif; ?><?php if ($item['DELIVERY_TEXT'] !== ''): ?> · Доставка: <?= htmlspecialchars($item['DELIVERY_TEXT']) ?><?php endif; ?>
                        <?php elseif ($item['DELIVERY_TEXT'] !== ''): ?>
                            Доставка: <?= htmlspecialchars($item['DELIVERY_TEXT']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="cart-item__stale-banner">
                        Информация могла устареть
                        <button type="button" class="cart-item__recheck-btn" data-recheck-id="<?= $item['ID'] ?>">Обновить</button>
                    </div>
                    <div class="cart-item__recheck-result" id="recheck-<?= $item['ID'] ?>"></div>
                </div>
                <div class="cart-item__qty">
                    <button type="button" class="cart-qty-btn" onclick="basketChange(<?= $item['ID'] ?>, -1)">−</button>
                    <input type="number" class="cart-qty-input" id="qty-<?= $item['ID'] ?>"
                           value="<?= $item['QTY'] ?>" min="1" max="999"
                           onchange="basketSet(<?= $item['ID'] ?>, this.value)">
                    <button type="button" class="cart-qty-btn" onclick="basketChange(<?= $item['ID'] ?>, 1)">+</button>
                </div>
                <div class="cart-item__price">
                    <div class="cart-item__sum" id="sum-<?= $item['ID'] ?>"><?= $item['SUM_FMT'] ?></div>
                </div>
                <button class="cart-item__remove" onclick="basketDelete(<?= $item['ID'] ?>)" title="Удалить"><svg class="icon"><use href="#icon-x"></use></svg></button>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-sidebar">
            <div class="cart-summary">
                <h3 class="cart-summary__title">Ваш заказ</h3>
                <div class="cart-summary__rows">
                    <div class="cart-summary__row">
                        <span>Товары (<span id="cart-count"><?= $totalQty ?></span> шт.)</span>
                        <span id="cart-subtotal"><?= $totalFmt ?></span>
                    </div>
                    <div class="cart-summary__row">
                        <span>Доставка</span>
                        <span><?= htmlspecialchars($cartDeliveryFmt) ?></span>
                    </div>
                </div>
                <div class="cart-summary__total">
                    <span>Итого</span>
                    <span id="cart-total"><?= $totalFmt ?></span>
                </div>
                <a href="/order/" id="checkout-link" class="btn btn--primary btn--lg btn--block">Перейти к оформлению</a>
                <a href="/catalog/" class="btn btn--outline btn--block" style="margin-top:10px;">Продолжить покупки</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.cart-page { max-width: 1240px; margin: 0 auto; padding: 30px 20px; font-family: var(--font); }
.cart-page__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
.cart-page__title { font-size: 28px; font-weight: 800; color: var(--black); }
.cart-clear-btn {
    background: transparent; border: 1px solid var(--border); color: var(--gray);
    border-radius: var(--radius); padding: 10px 16px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all var(--transition);
}
.cart-clear-btn:hover { border-color: var(--red); color: var(--red); background: #fdecec; }
.cart-clear-btn:disabled { opacity: 0.6; cursor: default; }
.cart-empty { text-align: center; padding: 80px 20px; }
.cart-empty__icon { font-size: 60px; margin-bottom: 14px; opacity: 0.6; }
.cart-empty h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; color: var(--black); }
.cart-empty p { color: var(--gray); margin-bottom: 20px; font-size: 14px; }

.cart-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .cart-layout { grid-template-columns: 1fr; } }

.cart-items { display: flex; flex-direction: column; gap: 10px; }

.cart-item {
    display: flex; align-items: center; gap: 16px;
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px 24px;
    box-shadow: var(--shadow-sm); transition: box-shadow var(--transition);
}
.cart-item:hover { box-shadow: var(--shadow); }

.cart-item__img {
    width: 80px; height: 80px; flex-shrink: 0; border-radius: var(--radius);
    overflow: hidden; background: #fafafa; border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
}
.cart-item__img img { max-width: 100%; max-height: 100%; object-fit: contain; }

.cart-item__info { flex: 1; min-width: 0; }
.cart-item__name { font-size: 14px; font-weight: 700; color: var(--black); text-decoration: none; display: block; line-height: 1.4; }
.cart-item__name:hover { color: var(--blue); }
.cart-item__article { font-size: 12px; color: var(--gray); margin-top: 4px; }
.cart-item__price-unit { font-size: 12px; color: var(--gray-light); margin-top: 4px; }
.cart-item__meta { font-size: 12px; color: var(--gray); margin-top: 4px; }

.cart-item__stale-banner { display: none; align-items: center; gap: 10px; margin-top: 8px; font-size: 12px; color: #a15c00; }
.cart-item--stale .cart-item__stale-banner { display: flex; }
.cart-item--stale .cart-item__img,
.cart-item--stale .cart-item__name,
.cart-item--stale .cart-item__article,
.cart-item--stale .cart-item__price-unit,
.cart-item--stale .cart-item__meta,
.cart-item--stale .cart-item__price { opacity: 0.45; }
.cart-item--stale .cart-qty-btn,
.cart-item--stale .cart-qty-input { pointer-events: none; opacity: 0.45; }
.cart-item__recheck-btn {
    border: 1px solid #a15c00; background: #fff8ec; color: #a15c00; border-radius: 6px;
    padding: 4px 10px; font-size: 12px; font-weight: 700; cursor: pointer;
}
.cart-item__recheck-btn:hover { background: #ffefd1; }
.cart-item__recheck-btn:disabled { opacity: 0.6; cursor: default; }

.cart-item__recheck-result { font-size: 13px; margin-top: 10px; }
.cart-item__recheck-result:empty { display: none; }
.cart-item__recheck-result .rr-box { border-radius: var(--radius); padding: 12px 14px; }
.cart-item__recheck-result .rr-box--ok { background: #eafaf0; color: #1a7a3e; }
.cart-item__recheck-result .rr-box--warn { background: #fff5e6; color: #8a5300; }
.cart-item__recheck-result .rr-box--err { background: #fdecec; color: #a12626; }
.cart-item__recheck-result .rr-diff { margin: 6px 0; }
.cart-item__recheck-result .rr-actions { display: flex; gap: 8px; margin-top: 10px; }
.cart-item__recheck-result .rr-btn {
    border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid transparent;
}
.cart-item__recheck-result .rr-btn--accept { background: var(--blue); color: #fff; }
.cart-item__recheck-result .rr-btn--remove { background: transparent; border-color: currentColor; }
.cart-item__recheck-result .rr-btn--search { background: var(--blue); color: #fff; text-decoration: none; display: inline-block; }

.cart-item__qty { display: flex; align-items: center; flex-shrink: 0; }
.cart-qty-btn {
    width: 34px; height: 34px; border: 1px solid var(--border); background: var(--bg);
    font-size: 16px; font-weight: 700; cursor: pointer; transition: background var(--transition);
    display: flex; align-items: center; justify-content: center; user-select: none; color: var(--black);
}
.cart-qty-btn:first-child { border-radius: var(--radius) 0 0 var(--radius); }
.cart-qty-btn:last-child { border-radius: 0 var(--radius) var(--radius) 0; }
.cart-qty-btn:hover { background: #ddd; }
.cart-qty-input {
    width: 46px; height: 34px; border: 1px solid var(--border); border-left: none; border-right: none;
    text-align: center; font-size: 14px; font-weight: 700; padding: 6px; font-family: var(--font);
    -moz-appearance: textfield; box-shadow: var(--shadow-sm);
}
.cart-qty-input::-webkit-outer-spin-button,
.cart-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

.cart-item__price { text-align: right; flex-shrink: 0; min-width: 110px; }
.cart-item__sum { font-size: 17px; font-weight: 800; color: var(--black); }

.cart-item__remove {
    background: none; border: none; font-size: 18px; color: var(--gray-light);
    cursor: pointer; padding: 8px; transition: color var(--transition); flex-shrink: 0;
    line-height: 1;
}
.cart-item__remove:hover { color: var(--red); }

.cart-sidebar { position: sticky; top: 20px; }
.cart-summary {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);
}
.cart-summary__title { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: var(--black); }
.cart-summary__rows { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
.cart-summary__row { display: flex; justify-content: space-between; font-size: 14px; color: var(--gray); }
.cart-summary__total {
    display: flex; justify-content: space-between; font-size: 18px; font-weight: 800;
    padding-top: 16px; border-top: 2px solid var(--border); margin-bottom: 20px; color: var(--black);
}

.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; border-radius: var(--radius); cursor: pointer; text-decoration: none; border: 1px solid transparent; transition: all var(--transition); font-family: var(--font); line-height: 1.2; font-size: 14px; }
.btn--primary { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 1px 3px rgba(102,139,234,0.3); padding: 14px 24px; }
.btn--primary:hover { background: var(--blue-dark); border-color: var(--blue-dark); color: #fff; }
.btn--outline { background: transparent; color: var(--blue); border: 2px solid var(--border); box-shadow: var(--shadow-sm); padding: 12px 24px; }
.btn--outline:hover { border-color: var(--blue); color: var(--blue-dark); }
.btn--lg { padding: 14px 32px; font-size: 16px; }
.btn--block { display: flex; width: 100%; }
</style>

<script>
function basketChange(id, delta) {
    var input = document.getElementById('qty-' + id);
    var val = parseInt(input.value) || 1;
    val += delta;
    if (val < 1) val = 1;
    if (val > 999) val = 999;
    input.value = val;
    basketUpdate(id, val);
}

function basketSet(id, val) {
    val = parseInt(val) || 1;
    if (val < 1) val = 1;
    if (val > 999) val = 999;
    document.getElementById('qty-' + id).value = val;
    basketUpdate(id, val);
}

function basketUpdate(id, qty) {
    var input = document.getElementById('qty-' + id);
    if (input) input.disabled = true;

    fetch('/ajax/basket.php?action=update&id=' + id + '&quantity=' + qty)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (input) input.disabled = false;
            if (d.status === 'ok') {
                var sumEl = document.getElementById('sum-' + id);
                if (sumEl && d.itemSum) sumEl.textContent = d.itemSum;
                var totalEl = document.getElementById('cart-total');
                if (totalEl && d.totalSum) totalEl.textContent = d.totalSum;
                var subtotalEl = document.getElementById('cart-subtotal');
                if (subtotalEl && d.totalSum) subtotalEl.textContent = d.totalSum;
                var countEl = document.getElementById('cart-count');
                if (countEl && d.totalQty !== undefined) countEl.textContent = d.totalQty;
                if (window.updateCartBadge && d.totalQty !== undefined) window.updateCartBadge(d.totalQty);
            }
        })
        .catch(function() {
            if (input) input.disabled = false;
        });
}

function basketDelete(id) {
    if (!confirm('Удалить товар из корзины?')) return;
    var row = document.getElementById('basket-row-' + id);
    if (row) row.style.opacity = '0.4';

    fetch('/ajax/basket.php?action=delete&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'ok') location.reload();
        })
        .catch(function() { location.reload(); });
}

var clearBtn = document.getElementById('cart-clear-btn');
if (clearBtn) {
    clearBtn.addEventListener('click', function() {
        if (!confirm('Очистить корзину полностью?')) return;
        clearBtn.disabled = true;
        fetch('/ajax/basket.php?action=clear')
            .then(function(r) { return r.json(); })
            .then(function() { location.reload(); })
            .catch(function() { location.reload(); });
    });
}

// ===== TTL корзины (12ч) и ревалидация через API поставщика =====
var CART_TTL_MS = <?= CART_TTL_SECONDS ?> * 1000;

function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

function refreshStaleClasses() {
    var now = Date.now();
    document.querySelectorAll('.cart-item[data-added-at]').forEach(function(row) {
        var addedAt = parseInt(row.getAttribute('data-added-at'), 10);
        if (!addedAt) return; // позиции без TTL (добавлены до введения фичи) не помечаем
        var stale = (now - addedAt * 1000) > CART_TTL_MS;
        row.classList.toggle('cart-item--stale', stale);
    });
}
refreshStaleClasses();
setInterval(refreshStaleClasses, 60000);

function fmtDelivery(label, time) {
    if (!label) return '—';
    return label + (time ? ' ' + time : '');
}

document.addEventListener('click', function(e) {
    var btn = e.target.closest && e.target.closest('.cart-item__recheck-btn');
    if (btn) {
        recheckItem(btn.getAttribute('data-recheck-id'), 'check', btn);
        return;
    }

    var acceptBtn = e.target.closest && e.target.closest('.rr-btn--accept');
    if (acceptBtn) {
        recheckItem(acceptBtn.getAttribute('data-id'), 'apply', acceptBtn);
        return;
    }

    var removeBtn = e.target.closest && e.target.closest('.rr-btn--remove');
    if (removeBtn) {
        basketDelete(removeBtn.getAttribute('data-id'));
        return;
    }
});

function recheckItem(id, mode, triggerBtn) {
    if (triggerBtn) triggerBtn.disabled = true;
    var resultEl = document.getElementById('recheck-' + id);

    fetch('/local/ajax/basket_recheck.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id) + '&mode=' + encodeURIComponent(mode)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (triggerBtn) triggerBtn.disabled = false;
        var row = document.getElementById('basket-row-' + id);

        if (d.status === 'unchanged') {
            if (row) {
                row.setAttribute('data-added-at', d.added_at);
                row.classList.remove('cart-item--stale');
            }
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--ok">Актуально, изменений нет.</div>';
            setTimeout(function() { if (resultEl) resultEl.innerHTML = ''; }, 4000);
            return;
        }

        if (d.status === 'changed') {
            var prev = d.previous, cur = d.current;
            var lines = '';
            if (Math.abs((cur.price||0) - (prev.price||0)) > 0.01) {
                lines += '<div class="rr-diff">Цена: было ' + Math.round(prev.price) + ' ₽ → стало ' + Math.round(cur.price) + ' ₽</div>';
            }
            if (cur.delivery_days !== prev.delivery_days) {
                lines += '<div class="rr-diff">Доставка: было ' + esc(fmtDelivery(prev.delivery_label, prev.delivery_time)) + ' → стало ' + esc(fmtDelivery(cur.delivery_label, cur.delivery_time)) + '</div>';
            }
            if (cur.qty_avail < d.qty_requested) {
                lines += '<div class="rr-diff">В наличии у поставщика: ' + cur.qty_avail + ' шт. (в корзине ' + d.qty_requested + ' шт.)</div>';
            }
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--warn">Условия изменились.' + lines
                + '<div class="rr-actions">'
                + '<button type="button" class="rr-btn rr-btn--accept" data-id="' + id + '">Принять новые условия</button>'
                + '<button type="button" class="rr-btn rr-btn--remove" data-id="' + id + '">Удалить из корзины</button>'
                + '</div></div>';
            return;
        }

        if (d.status === 'not_found') {
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">Товара нет в наличии у поставщика.'
                + '<div class="rr-actions">'
                + '<a href="' + esc(d.search_url) + '" class="rr-btn rr-btn--search">Повторить поиск</a>'
                + '<button type="button" class="rr-btn rr-btn--remove" data-id="' + id + '">Удалить из корзины</button>'
                + '</div></div>';
            return;
        }

        if (d.status === 'ok') return; // apply прошёл успешно — страница перезагрузится ниже

        if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">' + esc(d.message || 'Не удалось обновить данные') + '</div>';
    }).then(function() {
        if (mode === 'apply') {
            // Успешное принятие новых условий — проще перерисовать всю корзину,
            // т.к. меняются цена/сумма/итоги/срок и, возможно, количество.
            location.reload();
        }
    }).catch(function() {
        if (triggerBtn) triggerBtn.disabled = false;
        if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">Ошибка соединения, попробуйте ещё раз</div>';
    });
}

var checkoutLink = document.getElementById('checkout-link');
if (checkoutLink) {
    checkoutLink.addEventListener('click', function(e) {
        if (document.querySelector('.cart-item--stale')) {
            e.preventDefault();
            showToast('Обновите устаревшие позиции перед оформлением заказа');
        }
    });
}

function showToast(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#2b2b2b;color:#fff;padding:12px 20px;border-radius:8px;font-size:14px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.2)';
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 4000);
}
</script>
