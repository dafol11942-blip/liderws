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

    <!-- Верхняя полоса -->
    <div class="top-bar">
        <div class="container">
            <span class="top-bar__city"><svg class="icon"><use href="#icon-pin"></use></svg> Елабуга, пр-т Нефтяников, 4 &nbsp;|&nbsp; Пн-Вс: 9:00–20:00</span>
            <div class="top-bar__links">
                <a href="tel:+78000000000" class="top-bar__phone"><svg class="icon"><use href="#icon-phone"></use></svg> 8-800-000-00-00</a>
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

<!-- Кнопка Каталог + выпадающее меню -->
<div class="catalog-dropdown-wrapper">
    <a href="/catalog/" class="catalog-btn" id="catalogBtn">
        <span class="catalog-btn__burger"></span>
        Каталог
    </a>
    <div class="catalog-dropdown" id="catalogDropdown">
        <div class="catalog-dropdown__grid">
            <?php
            CModule::IncludeModule('iblock');
            $iblockId = 42;
            $topSections = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => 0, 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'SECTION_PAGE_URL']
            );
            while ($top = $topSections->GetNext()):
                // Получаем подразделы
                $subRes = CIBlockSection::GetList(
                    ['SORT' => 'ASC'],
                    ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $top['ID'], 'ACTIVE' => 'Y'],
                    false,
                    ['ID', 'NAME', 'CODE']
                );
                $subs = [];
                while ($sub = $subRes->GetNext()) {
                    $subs[] = $sub;
                }
                $topUrl = '/catalog/' . $top['CODE'] . '/';
            ?>
                <div class="catalog-dropdown__col">
                    <a href="<?= $topUrl ?>" class="catalog-dropdown__title">
                        <?= $top['NAME'] ?>
                    </a>
                    <?php if (!empty($subs)): ?>
                    <ul class="catalog-dropdown__list">
                        <?php foreach ($subs as $sub): ?>
                        <li>
                            <a href="/catalog/<?= $top['CODE'] ?>/<?= $sub['CODE'] ?>/">
                                <?= $sub['NAME'] ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li>
                            <a href="<?= $topUrl ?>" class="catalog-dropdown__all">
                                Все товары раздела →
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
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
                <a href="/personal/" class="header__icon" title="Личный кабинет"><svg class="icon"><use href="#icon-user"></use></svg></a>
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
                <a href="/autoservice/">Автосервис</a>
            </nav>
        </div>
    </div>

    <main class="main">
