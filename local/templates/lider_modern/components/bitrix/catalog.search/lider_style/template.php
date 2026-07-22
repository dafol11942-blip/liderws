<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) {
    echo '<p style="text-align:center;padding:40px;color:#666;">Ничего не найдено. Попробуйте изменить запрос.</p>';
    return;
}
?>

<div class="products-grid">
    <?php foreach ($arResult['ITEMS'] as $item):
        $img = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
        if (!empty($item['PREVIEW_PICTURE']['SRC'])) {
            $img = $item['PREVIEW_PICTURE']['SRC'];
        } elseif (!empty($item['DETAIL_PICTURE']['SRC'])) {
            $img = $item['DETAIL_PICTURE']['SRC'];
        }

        $price = $item['ITEM_PRICES'][0]['PRICE'] ?? 0;
        $oldPrice = $item['ITEM_PRICES'][0]['BASE_PRICE'] ?? 0;
        $article = $item['PROPERTIES']['CML2_ARTICLE']['VALUE'] ?? '';
        $detailUrl = $item['DETAIL_PAGE_URL'] ?? '/catalog/';
    ?>
    <div class="product-card">
        <div class="product-card__img">
            <a href="<?= $detailUrl ?>">
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['NAME']) ?>" loading="lazy">
            </a>
        </div>
        <div class="product-card__body">
            <div class="product-card__name">
                <a href="<?= $detailUrl ?>"><?= $item['NAME'] ?></a>
            </div>
            <?php if ($article): ?>
                <div class="product-card__article">Арт: <?= $article ?></div>
            <?php endif; ?>
            <div class="product-card__footer">
                <div>
                    <?php if ($oldPrice > $price && $oldPrice > 0): ?>
                        <div class="product-card__old-price"><?= number_format($oldPrice, 0, ',', ' ') ?> ₽</div>
                    <?php endif; ?>
                    <div class="product-card__price">
                        <?= number_format($price, 0, ',', ' ') ?> <span class="currency">₽</span>
                    </div>
                </div>
                <?php if ($item['CAN_BUY']): ?>
                <div style="display:flex;align-items:center;gap:6px;">
                    <div class="qty-box">
                        <button type="button" onclick="qtyDown(this)">−</button>
                        <input type="number" value="1" min="1" max="99" class="qty-input" style="width:40px;">
                        <button type="button" onclick="qtyUp(this)">+</button>
                    </div>
                    <a href="/cart/?action=ADD2BASKET&id=<?= $item['ID'] ?>"
                       class="btn btn--primary btn--sm add-to-cart-link"
                       data-id="<?= $item['ID'] ?>"
                       onclick="return addToCart(this, event);">
                        В корзину
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($arResult['NAV_STRING'])): ?>
    <div class="pagination"><?= $arResult['NAV_STRING'] ?></div>
<?php endif; ?>

<script>
function qtyDown(btn) {
    var input = btn.parentElement.querySelector('.qty-input');
    var val = parseInt(input.value) || 1;
    if (val > 1) input.value = val - 1;
}
function qtyUp(btn) {
    var input = btn.parentElement.querySelector('.qty-input');
    var val = parseInt(input.value) || 0;
    if (val < 99) input.value = val + 1;
}
function addToCart(link, event) {
    event.preventDefault();
    var id = link.getAttribute('data-id');
    var qtyInput = link.parentElement.querySelector('.qty-input');
    var qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
    link.textContent = '...';
    link.style.opacity = '0.6';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/ajax/add_to_basket.php?id=' + id + '&quantity=' + qty, true);
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.status === 'ok') {
                link.textContent = '✓ В корзине';
                link.style.background = '#4DCD71';
                link.style.opacity = '1';
                link.style.pointerEvents = 'none';
            } else {
                link.textContent = 'В корзину';
                link.style.opacity = '1';
            }
        } catch (e) {
            window.location.href = '/cart/?action=ADD2BASKET&id=' + id;
        }
    };
    xhr.onerror = function() {
        window.location.href = '/cart/?action=ADD2BASKET&id=' + id;
    };
    xhr.send();
    return false;
}
</script>