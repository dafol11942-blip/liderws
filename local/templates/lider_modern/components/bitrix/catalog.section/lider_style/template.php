<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) {
    echo '<p style="text-align:center;padding:40px;">Товары не найдены</p>';
    return;
}

CModule::IncludeModule('catalog');
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
        $article = $item['PROPERTIES']['CML2_ARTICLE']['VALUE'] ?? ($item['PROPERTIES']['ARTICLE']['VALUE'] ?? '');

        // Суммируем остатки по складам
		$inStock = true;  // фильтрация уже выполнена в result_modifier
    ?>
    <div class="product-card">
        <div class="product-card__img">
            <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['NAME']) ?>" loading="lazy">
            </a>
        </div>
        <div class="product-card__body">
            <div class="product-card__name">
                <a href="<?= $item['DETAIL_PAGE_URL'] ?>"><?= $item['NAME'] ?></a>
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
                <?php if ($inStock): ?>
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
                <?php else: ?>
                    <span class="product-card__no-stock">Нет в наличии</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
// Кастомная пагинация
$nav = $arResult['NAV_RESULT'];
if ($nav && $nav->NavPageCount > 1):
    $current = (int)$nav->NavPageNomer;
    $total   = (int)$nav->NavPageCount;
    $urlBase = $APPLICATION->GetCurPageParam('', ['PAGEN_1'], false);

    // Строим URL: добавляем PAGEN_1
    $makeUrl = function($page) use ($urlBase) {
        $sep = (strpos($urlBase, '?') === false) ? '?' : '&';
        return $page > 1 ? $urlBase . $sep . 'PAGEN_1=' . $page : $urlBase;
    };
?>
<div class="pagination">
    <?php if ($current > 1): ?>
        <a href="<?= $makeUrl($current - 1) ?>">←</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total; $i++):
        if ($i == $current): ?>
            <span class="active"><?= $i ?></span>
        <?php elseif ($i == 1 || $i == $total || ($i >= $current - 2 && $i <= $current + 2)): ?>
            <a href="<?= $makeUrl($i) ?>"><?= $i ?></a>
        <?php elseif ($i == $current - 3 || $i == $current + 3): ?>
            <span>...</span>
        <?php endif;
    endfor; ?>

    <?php if ($current < $total): ?>
        <a href="<?= $makeUrl($current + 1) ?>">→</a>
    <?php endif; ?>
</div>
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