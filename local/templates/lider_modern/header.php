<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Счётчик корзины для шапки берём из сессии, а не запросом к b_sale_basket на
// каждой странице сайта — с непустой корзиной этот запрос выполнялся бы на
// КАЖДОМ хите для этого посетителя (см. предыдущий фикс с GetBasketUserID(false):
// он спасал только гостей с пустой корзиной). Все точки изменения корзины
// (order_from_supplier.php, ajax/add_to_basket.php, ajax/basket.php,
// basket_recheck.php) пишут актуальное значение в $_SESSION['CART_QTY'] сами.
// Здесь — только один ленивый пересчёт, если сессия ещё не проинициализирована.
if (!isset($_SESSION['CART_QTY'])) {
    $_SESSION['CART_QTY'] = 0;
    if (CModule::IncludeModule('sale')) {
        $fuserId = CSaleBasket::GetBasketUserID(false);
        if ($fuserId) {
            $cartRes = CSaleBasket::GetList(
                [],
                ['FUSER_ID' => $fuserId, 'ORDER_ID' => 'NULL', 'LID' => SITE_ID],
                false, false, ['QUANTITY']
            );
            while ($cartRow = $cartRes->Fetch()) {
                $_SESSION['CART_QTY'] += (int)$cartRow['QUANTITY'];
            }
        }
    }
}
$cartQty = (int)$_SESSION['CART_QTY'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
    <?php $APPLICATION->ShowHead(); ?>
    <?php $styleCssPath = $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . '/assets/css/style.css'; ?>
    <link rel="stylesheet" href="<?= SITE_TEMPLATE_PATH ?>/assets/css/style.css?v=<?= @filemtime($styleCssPath) ?: '1' ?>">
</head>
<body>
    <?php $APPLICATION->ShowPanel(); ?>
    <?php require __DIR__ . '/include/svg-sprite.php'; ?>
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/shop_locations.php'; ?>

    <!-- Верхняя полоса -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar__stores">
                <?php foreach (getShopLocations() as $shop): ?>
                <div class="top-bar__store">
                    <button type="button" class="top-bar__store-toggle">
                        <svg class="icon"><use href="#icon-pin"></use></svg>
                        <?= htmlspecialchars($shop['short']) ?>
                        <svg class="icon top-bar__store-caret"><use href="#icon-chevron-down"></use></svg>
                    </button>
                    <div class="top-bar__store-panel">
                        <div class="top-bar__store-address"><?= htmlspecialchars($shop['address']) ?></div>
                        <?php if (!empty($shop['hours'])): ?>
                        <div class="top-bar__store-hours"><svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?></div>
                        <?php endif; ?>
                        <?php foreach ($shop['phones'] as $phone): ?>
                        <a href="tel:<?= htmlspecialchars($phone['tel']) ?>" class="top-bar__store-phone">
                            <span><?= htmlspecialchars($phone['label']) ?></span>
                            <b><?= htmlspecialchars($phone['display']) ?></b>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="top-bar__links">
                <a href="/about/" class="top-bar__link">О компании</a>
                <a href="/contacts/" class="top-bar__link">Контакты</a>
            </div>
        </div>
    </div>

    <!-- Основная шапка -->
    <header class="header">
        <div class="container header__inner">
            <a href="/" class="logo">
                <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/logo.png" alt="Лидер — автотехцентр">
            </a>

<!-- Кнопка Каталог + выпадающее меню (флайаут: слева категории, справа плитки подразделов активной) -->
<?php
// Иконка для категории верхнего уровня — подбираем по ключевым словам в
// названии (данные разделов могут меняться в админке, поэтому не хардкодим
// по ID/коду), с разумным запасным вариантом.
function pickCatalogNavIcon(string $name): string {
    $name = mb_strtolower($name);
    if (mb_strpos($name, 'масл') !== false || mb_strpos($name, 'жидк') !== false) return 'icon-droplet';
    if (mb_strpos($name, 'шин') !== false || mb_strpos($name, 'диск') !== false) return 'icon-tire';
    if (mb_strpos($name, 'аккум') !== false || mb_strpos($name, 'батар') !== false) return 'icon-battery';
    if (mb_strpos($name, 'инструм') !== false || mb_strpos($name, 'оборудован') !== false) return 'icon-settings';
    return 'icon-car';
}
?>
<div class="catalog-dropdown-wrapper">
    <a href="/catalog/" class="catalog-btn" id="catalogBtn">
        <span class="catalog-btn__burger"></span>
        Каталог
    </a>
    <div class="catalog-dropdown" id="catalogDropdown">
        <?php
        CModule::IncludeModule('iblock');
        $iblockId = 42;
        $topSections = CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => 0, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'CODE', 'PICTURE']
        );
        $catalogNavSections = [];
        while ($top = $topSections->GetNext()) {
            $subRes = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $top['ID'], 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'PICTURE']
            );
            $subs = [];
            while ($sub = $subRes->GetNext()) {
                $subs[] = $sub;
            }
            $top['SUBS'] = $subs;
            $catalogNavSections[] = $top;
        }
        ?>
        <div class="catalog-dropdown__nav">
            <?php foreach ($catalogNavSections as $i => $top): ?>
                <a href="/catalog/<?= $top['CODE'] ?>/" class="catalog-dropdown__nav-item<?= $i === 0 ? ' active' : '' ?>" data-panel="catalogNavPanel<?= $top['ID'] ?>">
                    <?php if (!empty($top['PICTURE'])): ?>
                        <img src="<?= CFile::GetPath($top['PICTURE']) ?>" alt="">
                    <?php else: ?>
                        <svg class="icon"><use href="#<?= pickCatalogNavIcon($top['NAME']) ?>"></use></svg>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($top['NAME']) ?></span>
                    <span class="catalog-dropdown__nav-arrow">›</span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="catalog-dropdown__panels">
            <?php foreach ($catalogNavSections as $i => $top): ?>
                <div class="catalog-dropdown__panel<?= $i === 0 ? ' active' : '' ?>" id="catalogNavPanel<?= $top['ID'] ?>">
                    <?php if (!empty($top['SUBS'])): ?>
                    <div class="catalog-dropdown__tiles">
                        <?php foreach ($top['SUBS'] as $sub): ?>
                            <a href="/catalog/<?= $top['CODE'] ?>/<?= $sub['CODE'] ?>/" class="catalog-dropdown__tile">
                                <span class="catalog-dropdown__tile-icon">
                                    <?php if (!empty($sub['PICTURE'])): ?>
                                        <img src="<?= CFile::GetPath($sub['PICTURE']) ?>" alt="">
                                    <?php else: ?>
                                        <svg class="icon"><use href="#icon-box"></use></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="catalog-dropdown__tile-name"><?= htmlspecialchars($sub['NAME']) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <a href="/catalog/<?= $top['CODE'] ?>/" class="catalog-dropdown__tile catalog-dropdown__tile--all">
                            <span class="catalog-dropdown__tile-icon"><svg class="icon"><use href="#icon-list"></use></svg></span>
                            <span class="catalog-dropdown__tile-name">Все товары раздела</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
            
            <div class="header__search">
                <form class="search-form" action="/search/">
                    <input type="text" name="q" placeholder="Поиск по VIN, названию или артикулу...">
                    <button type="submit"><svg class="icon"><use href="#icon-search"></use></svg></button>
                </form>
            </div>

            <div class="header__actions">
                <a href="/personal/favorites/" class="header__icon" title="Избранное"><svg class="icon"><use href="#icon-heart"></use></svg></a>
                <a href="/personal/compare/" class="header__icon" title="Сравнение"><svg class="icon"><use href="#icon-compare"></use></svg></a>
                <a href="/cart/" class="header__icon" id="cartIcon" title="Корзина">
                    <svg class="icon"><use href="#icon-cart"></use></svg>
                    <span class="badge" id="cartBadge"<?= $cartQty > 0 ? '' : ' style="display:none;"' ?>><?= $cartQty ?></span>
                </a>
                <a href="<?= $USER->IsAuthorized() ? '/personal/' : '/auth/' ?>" class="header__icon" title="<?= $USER->IsAuthorized() ? 'Личный кабинет' : 'Войти' ?>"><svg class="icon"><use href="#icon-user"></use></svg></a>
            </div>
        </div>
    </header>

    <script>
    // Единая точка обновления счётчика корзины в шапке — вызывается со страниц
    // поиска/корзины после добавления/изменения/удаления товара, без перезагрузки.
    window.updateCartBadge = function (qty) {
        var badge = document.getElementById('cartBadge');
        var icon = document.getElementById('cartIcon');
        if (!badge) return;
        qty = Math.max(0, parseInt(qty, 10) || 0);
        badge.textContent = qty;
        badge.style.display = qty > 0 ? '' : 'none';
        if (icon) {
            icon.classList.remove('header__icon--bump');
            void icon.offsetWidth; // перезапуск CSS-анимации при повторном добавлении подряд
            icon.classList.add('header__icon--bump');
        }
    };
    </script>

    <!-- Навигация -->
    <div class="header-nav">
        <div class="container">
            <nav class="header-nav__menu">
<a href="/service-parts/" style="color:var(--blue);"><svg class="icon"><use href="#icon-wrench"></use></svg> Запчасти для ТО</a>
                <a href="/catalog/masla/">Масла</a>
                <a href="/catalog/filtry/">Фильтры</a>
                <a href="/catalog/tormoznye-kolodki/">Тормозные колодки</a>
                <a href="/catalog/grm/">ГРМ</a>
                <a href="/catalog/shiny/">Шины и диски</a>
                <div class="services-dropdown-wrapper">
                    <a href="/autoservice/">Автосервис</a>
                    <div class="services-dropdown">
                        <a href="/autoservice/diagnostika-i-remont-podveski/">Диагностика и ремонт подвески</a>
                        <a href="/autoservice/zamena-masla-v-dvigatele/">Замена масла в двигателе</a>
                        <a href="/autoservice/tekhnicheskoe-obsluzhivanie/">Техническое обслуживание и мелкий ремонт</a>
                        <a href="/autoservice/remont-tormoznoy-sistemy/">Ремонт тормозной системы</a>
                        <a href="/autoservice/zamena-tsepi-remnya-grm/">Замена цепи/ремня ГРМ</a>
                        <a href="/autoservice/zamena-masla-v-transmissii/">Замена масла в трансмиссии</a>
                        <a href="/autoservice/zamena-filtrov/">Замена фильтров</a>
                        <a href="/autoservice/" class="services-dropdown__all">Все услуги автосервиса →</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <main class="main">
