<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CModule::IncludeModule('sale');
CModule::IncludeModule('iblock');

// Получаем корзину
$items = [];
$bRes = CSaleBasket::GetList(
    ['NAME' => 'ASC'],
    ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID]
);

$totalSum = 0;
$totalQty = 0;

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

    $totalSum += $b['SUM_NUM'];
    $totalQty += $b['QTY'];
    $items[] = $b;
}

$totalFmt = number_format($totalSum, 0, ',', ' ') . ' ₽';
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
    <h1 class="cart-page__title">Корзина</h1>
    <div class="cart-layout">
        <div class="cart-items">
            <?php foreach ($items as $item): ?>
            <div class="cart-item" id="basket-row-<?= $item['ID'] ?>">
                <div class="cart-item__img">
                    <a href="<?= $item['URL'] ?>">
                        <img src="<?= $item['IMG'] ?>" alt="<?= htmlspecialchars($item['NAME']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="cart-item__info">
                    <a href="<?= $item['URL'] ?>" class="cart-item__name"><?= htmlspecialchars($item['NAME']) ?></a>
                    <div class="cart-item__price-unit"><?= $item['PRICE_FMT'] ?> / шт.</div>
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
                        <span>Рассчитывается при оформлении</span>
                    </div>
                </div>
                <div class="cart-summary__total">
                    <span>Итого</span>
                    <span id="cart-total"><?= $totalFmt ?></span>
                </div>
                <a href="/order/" class="btn btn--primary btn--lg btn--block">Перейти к оформлению</a>
                <a href="/catalog/" class="btn btn--outline btn--block" style="margin-top:10px;">Продолжить покупки</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.cart-page { max-width: 1240px; margin: 0 auto; padding: 30px 20px; font-family: var(--font); }
.cart-page__title { font-size: 28px; font-weight: 800; margin-bottom: 30px; color: var(--black); }
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
.cart-item__price-unit { font-size: 12px; color: var(--gray-light); margin-top: 4px; }

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
</script>
