<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/require_phone_auth.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/init_pricing.php");
CModule::IncludeModule('catalog');
CModule::IncludeModule('iblock');

$APPLICATION->SetPageProperty("title", "Избранное — личный кабинет ЛИДЕР");
$APPLICATION->SetTitle("Избранное");

global $USER;
$userId = (int)$USER->GetID();
$isMgr  = isManager();
$db     = \Bitrix\Main\Application::getConnection();

// ===== Товары своего склада (IBLOCK_ID 42) — цена/остаток всегда живые, без TTL =====
$catalogItems = [];
$catRows = $db->query("SELECT ID, PRODUCT_ID FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'catalog' ORDER BY CREATED_AT DESC")->fetchAll();
foreach ($catRows as $row) {
    $productId = (int)$row['PRODUCT_ID'];

    $res = CIBlockElement::GetList(
        [], ['IBLOCK_ID' => 42, 'ID' => $productId, 'ACTIVE' => 'Y'], false, false,
        ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
    );
    $fields = $res->GetNext();
    if (!$fields) continue; // товар удалён/снят с публикации — тихо пропускаем

    $img = SITE_TEMPLATE_PATH . '/assets/images/no-photo.png';
    $previewId = $fields['PREVIEW_PICTURE'] ?: $fields['DETAIL_PICTURE'];
    if ($previewId) {
        $imgPath = CFile::GetPath($previewId);
        if ($imgPath) $img = $imgPath;
    }

    $article = '';
    $artRes = CIBlockElement::GetProperty(42, $productId, [], ['CODE' => 'CML2_ARTICLE']);
    if ($artRow = $artRes->Fetch()) $article = (string)($artRow['VALUE'] ?? '');

    $brand = '';
    $brandRes = CIBlockElement::GetProperty(42, $productId, [], ['CODE' => 'CML2_MANUFACTURER']);
    if ($brandRow = $brandRes->Fetch()) $brand = (string)($brandRow['VALUE_ENUM'] ?? $brandRow['VALUE'] ?? '');

    $basePrice = 0;
    $dbPrice = CPrice::GetList([], ['PRODUCT_ID' => $productId]);
    if ($arPrice = $dbPrice->Fetch()) $basePrice = (float)$arPrice['PRICE'];
    $price = getDisplayPrice($basePrice);

    $totalAmount = 0;
    $dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $productId], false, false, ['AMOUNT']);
    while ($arStore = $dbStore->Fetch()) $totalAmount += (int)$arStore['AMOUNT'];

    $catalogItems[] = [
        'ID'       => $productId,
        'NAME'     => $fields['NAME'],
        'URL'      => $fields['DETAIL_PAGE_URL'] ?: '#',
        'IMG'      => $img,
        'ARTICLE'  => $article,
        'BRAND'    => $brand,
        'PRICE_FMT'=> number_format($price, 0, ',', ' ') . ' ₽',
        'IN_STOCK' => $totalAmount > 0,
    ];
}

// ===== Заказные позиции у поставщика — снимок + TTL 2ч (см. CART_TTL_SECONDS в init.php) =====
$supplierItems = [];
$supRows = $db->query("SELECT * FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'supplier' ORDER BY CREATED_AT DESC")->fetchAll();
$now = time();
foreach ($supRows as $row) {
    $supplierLabel = (string)$row['SUPPLIER'];
    if (function_exists('getSupplierFactory')) {
        $conn = getSupplierFactory()->get($row['SUPPLIER']);
        if ($conn) $supplierLabel = $conn->getName();
    }

    $deliveryLabel = (string)($row['SNAP_DELIVERY_LABEL'] ?? '');
    $deliveryTime  = (string)($row['SNAP_DELIVERY_TIME'] ?? '');
    $deliveryDays  = isset($row['SNAP_DELIVERY_DAYS']) ? (int)$row['SNAP_DELIVERY_DAYS'] : null;
    if ($deliveryLabel !== '') {
        $deliveryText = $deliveryLabel . ($deliveryTime !== '' ? ' ' . $deliveryTime : '');
    } elseif ($deliveryDays !== null && $deliveryDays >= 0) {
        $deliveryText = $deliveryDays . ' дн.';
    } else {
        $deliveryText = '';
    }

    $confirmedAt = (int)($row['CONFIRMED_AT'] ?? 0);

    $supplierItems[] = [
        'FAV_ID'         => (int)$row['ID'],
        'ARTICLE'        => (string)$row['ARTICLE'],
        'BRAND'          => (string)$row['BRAND'],
        'SUPPLIER'       => (string)$row['SUPPLIER'],
        'SUPPLIER_LABEL' => $supplierLabel,
        'NAME'           => (string)$row['SNAP_NAME'],
        'PRICE_FMT'      => number_format((float)$row['SNAP_PRICE_DISPLAY'], 0, ',', ' ') . ' ₽',
        'RETURNABLE'     => ($row['SNAP_RETURNABLE'] ?? 'Y') !== 'N',
        'DELIVERY_TEXT'  => $deliveryText,
        'CONFIRMED_AT'   => $confirmedAt,
        'IS_STALE'       => $confirmedAt > 0 && ($now - $confirmedAt) > CART_TTL_SECONDS,
    ];
}

$isEmpty = empty($catalogItems) && empty($supplierItems);
?>

<div class="lk-layout">
    <?php $lkNavActive = 'favorites'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
    <div class="lk-content">
        <h2><svg class="icon"><use href="#icon-star"></use></svg> Избранное</h2>

        <?php if ($isEmpty): ?>
        <div class="empty-state">
            <div class="empty-state__icon"><svg class="icon"><use href="#icon-star"></use></svg></div>
            <h3>Пока пусто</h3>
            <p>Добавляйте товары в избранное значком сердечка на карточке товара или в поиске</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        </div>
        <?php else: ?>

        <?php if (!empty($catalogItems)): ?>
        <h3 class="fav-section__title">Товары со склада</h3>
        <div class="products-grid">
            <?php foreach ($catalogItems as $item): ?>
            <div class="product-card">
                <div class="product-card__img">
                    <button type="button" class="fav-btn product-card__fav is-active"
                            data-fav-type="catalog" data-fav-id="<?= $item['ID'] ?>"
                            aria-pressed="true" title="Убрать из избранного" onclick="toggleFavorite(this)">
                        <svg class="icon icon--fill"><use href="#icon-heart"></use></svg>
                    </button>
                    <a href="<?= $item['URL'] ?>">
                        <img src="<?= $item['IMG'] ?>" alt="<?= htmlspecialchars($item['NAME']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="product-card__body">
                    <?php if ($item['BRAND'] !== ''): ?>
                        <div class="product-card__brand"><?= htmlspecialchars($item['BRAND']) ?></div>
                    <?php endif; ?>
                    <div class="product-card__name">
                        <a href="<?= $item['URL'] ?>"><?= htmlspecialchars($item['NAME']) ?></a>
                    </div>
                    <?php if ($item['ARTICLE'] !== ''): ?>
                        <div class="product-card__article">Арт: <?= htmlspecialchars($item['ARTICLE']) ?></div>
                    <?php endif; ?>
                    <div class="product-card__footer">
                        <div class="product-card__price"><?= $item['PRICE_FMT'] ?></div>
                        <?php if ($item['IN_STOCK']): ?>
                            <a href="/cart/?action=ADD2BASKET&id=<?= $item['ID'] ?>"
                               class="btn btn--primary btn--sm add-to-cart-link"
                               data-id="<?= $item['ID'] ?>"
                               onclick="return favAddCatalogToCart(this, event);">
                                В корзину
                            </a>
                        <?php else: ?>
                            <span class="product-card__no-stock">Нет в наличии</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($supplierItems)): ?>
        <h3 class="fav-section__title">Заказные позиции</h3>
        <div class="favorite-items">
            <?php foreach ($supplierItems as $item): ?>
            <div class="favorite-item<?= $item['IS_STALE'] ? ' favorite-item--stale' : '' ?>"
                 id="fav-row-<?= $item['FAV_ID'] ?>" data-confirmed-at="<?= (int)$item['CONFIRMED_AT'] ?>">
                <div class="favorite-item__info">
                    <button type="button" class="fav-btn" style="float:right;"
                            data-fav-type="supplier"
                            data-article="<?= htmlspecialchars($item['ARTICLE']) ?>"
                            data-brand="<?= htmlspecialchars($item['BRAND']) ?>"
                            data-supplier="<?= htmlspecialchars($item['SUPPLIER']) ?>"
                            aria-pressed="true" title="Убрать из избранного" onclick="toggleFavorite(this)">
                        <svg class="icon icon--fill"><use href="#icon-heart"></use></svg>
                    </button>
                    <div class="favorite-item__name"><?= htmlspecialchars($item['NAME']) ?></div>
                    <div class="favorite-item__article">
                        Бренд: <b><?= htmlspecialchars($item['BRAND']) ?></b> &middot;
                        Артикул: <b><?= htmlspecialchars($item['ARTICLE']) ?></b>
                    </div>
                    <?php if (!$item['RETURNABLE']): ?>
                    <div class="cart-item__no-return"><svg class="icon"><use href="#icon-x-circle"></use></svg> Товар не подлежит возврату</div>
                    <?php endif; ?>
                    <div class="favorite-item__price"><?= $item['PRICE_FMT'] ?></div>
                    <div class="cart-item__meta">
                        <?php if ($isMgr): ?>
                            Поставщик: <?= htmlspecialchars($item['SUPPLIER_LABEL']) ?><?php if ($item['DELIVERY_TEXT'] !== ''): ?> · Доставка: <?= htmlspecialchars($item['DELIVERY_TEXT']) ?><?php endif; ?>
                        <?php elseif ($item['DELIVERY_TEXT'] !== ''): ?>
                            Доставка: <?= htmlspecialchars($item['DELIVERY_TEXT']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item__stale-banner">
                        Информация могла устареть
                        <button type="button" class="cart-item__recheck-btn" data-recheck-id="<?= $item['FAV_ID'] ?>">Обновить</button>
                    </div>
                    <div class="cart-item__recheck-result" id="fav-recheck-<?= $item['FAV_ID'] ?>"></div>
                </div>
                <button type="button" class="btn btn--primary btn--sm"
                        onclick="favAddSupplierToCart(this, '<?= htmlspecialchars($item['ARTICLE'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['BRAND'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['SUPPLIER'], ENT_QUOTES) ?>')">
                    В корзину
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<style>
.fav-section__title { font-size: 15px; font-weight: 700; margin: 24px 0 14px; }
.fav-section__title:first-of-type { margin-top: 0; }

.favorite-items { display: flex; flex-direction: column; gap: 10px; }
.favorite-item {
    display: flex; align-items: center; gap: 16px;
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 20px 24px;
    box-shadow: var(--shadow-sm); transition: box-shadow var(--transition);
}
.favorite-item:hover { box-shadow: var(--shadow); }
.favorite-item__info { flex: 1; min-width: 0; }
.favorite-item__name { font-size: 14px; font-weight: 700; color: var(--black); line-height: 1.4; }
.favorite-item__article { font-size: 12px; color: var(--gray); margin-top: 4px; }
.favorite-item__price { font-size: 17px; font-weight: 800; color: var(--black); margin-top: 8px; }

.favorite-item--stale .favorite-item__name,
.favorite-item--stale .favorite-item__article,
.favorite-item--stale .favorite-item__price { opacity: 0.45; }

.cart-item__no-return { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: var(--red); margin-top: 6px; }
.cart-item__no-return .icon { width: 14px; height: 14px; }
.cart-item__meta { font-size: 12px; color: var(--gray); margin-top: 4px; }

.cart-item__stale-banner { display: none; align-items: center; gap: 10px; margin-top: 8px; font-size: 12px; color: #a15c00; }
.favorite-item--stale .cart-item__stale-banner { display: flex; }
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
.cart-item__recheck-result .rr-btn { border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid transparent; }
.cart-item__recheck-result .rr-btn--accept { background: var(--blue); color: #fff; }
.cart-item__recheck-result .rr-btn--remove { background: transparent; border-color: currentColor; }
.cart-item__recheck-result .rr-btn--search { background: var(--blue); color: #fff; text-decoration: none; display: inline-block; }
</style>

<script>
// На этой странице каждая карточка/строка — сама запись избранного, поэтому снятие с
// избранного (сердечко гаснет) должно убирать её целиком, а не просто гасить иконку
// (как на карточке каталога/в поиске, где карточка остаётся товаром сама по себе).
window.onFavoriteToggled = function (btn, active) {
    if (active) return;
    var row = btn.closest('.product-card') || btn.closest('.favorite-item');
    if (!row) return;
    row.style.transition = 'opacity .2s';
    row.style.opacity = '0';
    setTimeout(function () { row.remove(); }, 200);
};

// "В корзину" со своего склада — тот же путь, что и на карточке каталога (addToCart()
// в catalog.section/lider_style/template.php), локальная копия, т.к. эта страница не
// подключает тот компонент.
function favAddCatalogToCart(link, event) {
    event.preventDefault();
    var id = link.getAttribute('data-id');
    link.textContent = '...';
    link.style.opacity = '0.6';
    fetch('/ajax/add_to_basket.php?id=' + id + '&quantity=1')
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (resp.status === 'ok') {
                link.textContent = '✓ В корзине';
                link.style.background = '#4DCD71';
                link.style.opacity = '1';
                link.style.pointerEvents = 'none';
                if (window.updateCartBadge && resp.cart_qty !== undefined) window.updateCartBadge(resp.cart_qty);
            } else {
                link.textContent = 'В корзину';
                link.style.opacity = '1';
            }
        })
        .catch(function () { window.location.href = '/cart/?action=ADD2BASKET&id=' + id; });
    return false;
}

// "В корзину" для заказной позиции — без task/offer_token: order_from_supplier.php в этом
// случае сам идёт за свежими данными к поставщику (см. его же резолв-ветку), то есть в
// корзину всегда уходит актуальная цена/остаток, а не протухший снимок избранного.
function favAddSupplierToCart(btn, article, brand, supplier) {
    btn.disabled = true;
    var original = btn.textContent;
    btn.textContent = '...';
    fetch('/local/ajax/order_from_supplier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article: article, brand: brand, supplier: supplier, quantity: 1 })
    }).then(function (r) { return r.json(); }).then(function (data) {
        btn.disabled = false;
        if (data.success) {
            btn.textContent = '✓ В корзине';
            btn.style.background = '#4DCD71';
            if (window.updateCartBadge && data.cart_qty !== undefined) window.updateCartBadge(data.cart_qty);
        } else {
            btn.textContent = original;
            if (window.showToast) window.showToast(data.message || 'Не удалось добавить в корзину', 'warn');
        }
    }).catch(function () {
        btn.disabled = false;
        btn.textContent = original;
    });
}

// ===== TTL заказных позиций избранного (тот же CART_TTL_SECONDS, что и в корзине) и
// ревалидация через /local/ajax/favorites.php (action=recheck) — логика 1:1 с
// recheckItem() в sale.basket.basket/lider_style/template.php, но над избранным. =====
function favEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function favFmtDelivery(label, time) {
    if (!label) return '—';
    return label + (time ? ' ' + time : '');
}

document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.cart-item__recheck-btn');
    if (btn) { favRecheckItem(btn.getAttribute('data-recheck-id'), 'check', btn); return; }

    var acceptBtn = e.target.closest && e.target.closest('.rr-btn--accept');
    if (acceptBtn) { favRecheckItem(acceptBtn.getAttribute('data-id'), 'apply', acceptBtn); return; }
});

function favRecheckItem(id, mode, triggerBtn) {
    if (triggerBtn) triggerBtn.disabled = true;
    var resultEl = document.getElementById('fav-recheck-' + id);

    fetch('/local/ajax/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'recheck', id: id, mode: mode })
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (triggerBtn) triggerBtn.disabled = false;
        var row = document.getElementById('fav-row-' + id);

        if (d.status === 'unchanged') {
            if (row) { row.setAttribute('data-confirmed-at', d.confirmed_at); row.classList.remove('favorite-item--stale'); }
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--ok">Актуально, изменений нет.</div>';
            setTimeout(function () { if (resultEl) resultEl.innerHTML = ''; }, 4000);
            return;
        }

        if (d.status === 'changed') {
            var prev = d.previous, cur = d.current;
            var lines = '';
            if (Math.abs((cur.price || 0) - (prev.price || 0)) > 0.01) {
                lines += '<div class="rr-diff">Цена: было ' + Math.round(prev.price) + ' ₽ → стало ' + Math.round(cur.price) + ' ₽</div>';
            }
            if (cur.delivery_days !== prev.delivery_days) {
                lines += '<div class="rr-diff">Доставка: было ' + favEsc(favFmtDelivery(prev.delivery_label, prev.delivery_time)) + ' → стало ' + favEsc(favFmtDelivery(cur.delivery_label, cur.delivery_time)) + '</div>';
            }
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--warn">Условия изменились.' + lines
                + '<div class="rr-actions">'
                + '<button type="button" class="rr-btn rr-btn--accept" data-id="' + id + '">Принять новые условия</button>'
                + '</div></div>';
            return;
        }

        if (d.status === 'not_found') {
            if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">Товара нет в наличии у поставщика.'
                + '<div class="rr-actions"><a href="' + favEsc(d.search_url) + '" class="rr-btn rr-btn--search">Повторить поиск</a></div></div>';
            return;
        }

        if (d.status === 'ok') { location.reload(); return; } // apply прошёл успешно

        if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">' + favEsc(d.message || 'Не удалось обновить данные') + '</div>';
    }).catch(function () {
        if (triggerBtn) triggerBtn.disabled = false;
        if (resultEl) resultEl.innerHTML = '<div class="rr-box rr-box--err">Ошибка соединения, попробуйте ещё раз</div>';
    });
}
</script>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
