<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$item = $arResult;

$img = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
if (!empty($item['DETAIL_PICTURE']['SRC'])) {
    $img = $item['DETAIL_PICTURE']['SRC'];
} elseif (!empty($item['PREVIEW_PICTURE']['SRC'])) {
    $img = $item['PREVIEW_PICTURE']['SRC'];
}

$price    = $item['ITEM_PRICES'][0]['PRICE'] ?? 0;
$oldPrice = $item['ITEM_PRICES'][0]['BASE_PRICE'] ?? 0;
$article  = $item['PROPERTIES']['CML2_ARTICLE']['VALUE'] ?? '';
$brand    = $item['PROPERTIES']['CML2_MANUFACTURER']['VALUE'] ?? '';

// Суммируем остатки по складам
CModule::IncludeModule('catalog');
$totalAmount = 0;
$dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $item['ID']], false, false, ['AMOUNT']);
while ($arStore = $dbStore->Fetch()) {
    $totalAmount += (int)$arStore['AMOUNT'];
}
$inStock = $totalAmount > 0;
?>

<div class="product-detail">
    <div class="product-detail__gallery">
        <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['NAME']) ?>">
    </div>

    <div class="product-detail__info">
        <h1><?= $item['NAME'] ?></h1>

        <?php if ($article): ?>
            <div class="product-detail__article">Артикул: <?= $article ?></div>
        <?php endif; ?>

        <?php if ($brand): ?>
            <div class="product-detail__article">Производитель: <?= $brand ?></div>
        <?php endif; ?>

        <div class="product-detail__stock">
            <?php if ($inStock): ?>
                <span class="stock-badge stock-badge--yes"><svg class="icon"><use href="#icon-check-circle"></use></svg> В наличии (<?= $totalAmount ?> шт.)</span>
            <?php else: ?>
                <span class="stock-badge stock-badge--no"><svg class="icon"><use href="#icon-x-circle"></use></svg> Нет в наличии</span>
            <?php endif; ?>
        </div>

        <div class="product-detail__price">
            <?= number_format($price, 0, ',', ' ') ?> ₽
            <?php if ($oldPrice > $price && $oldPrice > 0): ?>
                <span class="product-detail__old-price">
                    <?= number_format($oldPrice, 0, ',', ' ') ?> ₽
                </span>
            <?php endif; ?>
        </div>

        <div class="product-detail__actions">
            <?php if ($inStock): ?>
                <div class="qty-box" style="height:44px;">
                    <button type="button" onclick="qtyDown(this)">−</button>
                    <input type="number" id="detail-qty" value="1" min="1" max="99" style="width:50px;font-size:16px;">
                    <button type="button" onclick="qtyUp(this)">+</button>
                </div>
                <button class="btn btn--primary btn--lg"
                        onclick="addToCartDetail(<?= $item['ID'] ?>)">
                    <svg class="icon"><use href="#icon-cart"></use></svg> В корзину
                </button>
            <?php else: ?>
                <button class="btn btn--outline btn--lg" disabled>Нет в наличии</button>
            <?php endif; ?>
        </div>

        <div class="product-detail__stores">
            <h3><svg class="icon"><use href="#icon-box"></use></svg> Наличие на складах</h3>
            <?php $APPLICATION->IncludeComponent(
                "bitrix:catalog.store.amount",
                "lider_style",
                array(
                    "ELEMENT_ID"      => $item['ID'],
                    "STORE_PATH"      => "/contacts/",
                    "CACHE_TYPE"      => "A",
                    "CACHE_TIME"      => "36000",
                    "SHOW_EMPTY_STORE" => "N",
                    "SHOW_GENERAL_STORE_INFORMATION" => "N",
                    "FIELDS"          => array("TITLE", "ADDRESS", "PHONE", "SCHEDULE"),
                    "USE_MIN_AMOUNT"  => "Y",
                    "MIN_AMOUNT"      => "1",
                    "STORES"          => array(),
                    "MAIN_TITLE"      => "",
                ),
                false
            ); ?>
        </div>


        <?php if (!empty($item['PROPERTIES'])): ?>
            <div class="product-detail__props">
                <h3><svg class="icon"><use href="#icon-list"></use></svg> Характеристики</h3>
                <?php foreach ($item['PROPERTIES'] as $prop): ?>
                    <?php if (!empty($prop['VALUE']) && !in_array($prop['CODE'], ['CML2_ARTICLE', 'CML2_MANUFACTURER', 'IN_STOCK', 'IN_STOCK_LIST'])): ?>
                        <div class="prop-row">
                            <span class="prop-row__name"><?= $prop['NAME'] ?>:</span>
                            <span class="prop-row__value">
                                <?= is_array($prop['VALUE']) ? implode(', ', $prop['VALUE']) : $prop['VALUE'] ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function qtyDown(btn) {
    var input = btn.parentElement.querySelector('input');
    var val = parseInt(input.value) || 1;
    if (val > 1) input.value = val - 1;
}
function qtyUp(btn) {
    var input = btn.parentElement.querySelector('input');
    var val = parseInt(input.value) || 0;
    if (val < 99) input.value = val + 1;
}
function addToCartDetail(id) {
    var qtyInput = document.getElementById('detail-qty');
    var qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
    var btn = document.querySelector('.product-detail__actions .btn--primary');
    if (btn) { btn.textContent = '...'; btn.style.opacity = '0.6'; }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/ajax/add_to_basket.php?id=' + id + '&quantity=' + qty, true);
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.status === 'ok') {
                if (btn) { btn.textContent = '✓ В корзине'; btn.style.background = '#4DCD71'; btn.style.opacity = '1'; btn.style.pointerEvents = 'none'; }
            } else {
                if (btn) { btn.innerHTML = '<svg class="icon"><use href="#icon-cart"></use></svg> В корзину'; btn.style.opacity = '1'; }
            }
        } catch(e) { window.location.href = '/cart/?action=ADD2BASKET&id=' + id; }
    };
    xhr.onerror = function() { window.location.href = '/cart/?action=ADD2BASKET&id=' + id; };
    xhr.send();
}
</script>