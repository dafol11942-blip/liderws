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
global $USER;
$favQty = $USER->IsAuthorized() ? getFavoritesCount($USER->GetID()) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
    <?php $APPLICATION->ShowHead(); ?>
    <?php // Основные наборы шрифта (кириллица + латиница) — сразу, без ожидания разбора CSS ?>
    <link rel="preload" href="<?= SITE_TEMPLATE_PATH ?>/assets/fonts/manrope-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= SITE_TEMPLATE_PATH ?>/assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <?php $styleCssPath = $_SERVER['DOCUMENT_ROOT'] . SITE_TEMPLATE_PATH . '/assets/css/style.css'; ?>
    <link rel="stylesheet" href="<?= SITE_TEMPLATE_PATH ?>/assets/css/style.css?v=<?= @filemtime($styleCssPath) ?: '1' ?>">
</head>
<body>
    <?php $APPLICATION->ShowPanel(); ?>
    <?php require __DIR__ . '/include/svg-sprite.php'; ?>
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/shop_locations.php'; ?>
    <?php
        $shopLocationsForHours = getShopLocations();
        $uniqueHours = array_unique(array_filter(array_column($shopLocationsForHours, 'hours')));
        $commonShopHours = count($uniqueHours) === 1 ? reset($uniqueHours) : null;
        // Статус "до закрытия" одинаков для всех магазинов, только пока у них
        // общий график (как сейчас) — считаем по первому, раз $commonShopHours
        // уже подтвердил, что расписание одно и то же.
        $commonShopStatus = ($commonShopHours && $shopLocationsForHours) ? getShopOpenStatus($shopLocationsForHours[0]) : ['isOpen' => null, 'text' => ''];
    ?>

    <!-- Плашка о бета-тестировании — временная, без возможности закрыть:
         статус сайта, а не разовое уведомление вроде cookie-consent. -->
    <div class="beta-banner">
        <div class="container beta-banner__inner">
            <svg class="icon"><use href="#icon-alert"></use></svg>
            <span>Сайт находится в стадии бета-тестирования: оформление заказа и авторизация временно недоступны.</span>
        </div>
    </div>

    <!-- Верхняя полоса -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar__left">
            <?php if ($commonShopHours): ?>
            <span class="top-bar__hours">
                <svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($commonShopHours) ?>
                <?php if ($commonShopStatus['text']): ?>
                <span class="top-bar__hours-status<?= $commonShopStatus['isOpen'] ? ' top-bar__hours-status--open' : '' ?>">· <?= htmlspecialchars($commonShopStatus['text']) ?></span>
                <?php endif; ?>
            </span>
            <?php endif; ?>
            <div class="top-bar__stores">
                <?php foreach ($shopLocationsForHours as $shop): ?>
                <div class="top-bar__store">
                    <a href="/shop/<?= htmlspecialchars($shop['id']) ?>/" class="top-bar__store-toggle">
                        <svg class="icon"><use href="#icon-pin"></use></svg>
                        <?= htmlspecialchars($shop['short']) ?>
                        <svg class="icon top-bar__store-caret"><use href="#icon-chevron-down"></use></svg>
                    </a>
                    <div class="top-bar__store-panel">
                        <div class="top-bar__store-address"><?= htmlspecialchars($shop['address']) ?></div>
                        <?php if (!empty($shop['hours'])): ?>
                        <?php $shopStatus = getShopOpenStatus($shop); ?>
                        <div class="top-bar__store-hours">
                            <svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?>
                            <?php if ($shopStatus['text']): ?>
                            <span class="top-bar__hours-status<?= $shopStatus['isOpen'] ? ' top-bar__hours-status--open' : '' ?>">· <?= htmlspecialchars($shopStatus['text']) ?></span>
                            <?php endif; ?>
                        </div>
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
        // Три группы номенклатуры — три отдельных инфоблока без общего
        // дерева разделов (см. CATALOG_BRANCHES в local/php_interface/init.php
        // и разбор в catalog/index.php) — поэтому здесь каждая ветка сама
        // становится одним пунктом верхнего меню, а её реальные подразделы
        // (если есть — как у "Масла", либо нет вовсе — как у ВАЗ/Иномарки)
        // идут в панель под ней.
        $catalogNavSections = [];
        foreach (CATALOG_BRANCHES as $branchSlug => $branchInfo) {
            $branchRoot = resolveEffectiveRoot($branchInfo['id']);
            $subRes = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $branchInfo['id'], 'SECTION_ID' => $branchRoot['rootId'], 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'PICTURE']
            );
            $subs = [];
            while ($sub = $subRes->GetNext()) {
                $subs[] = $sub;
            }
            $catalogNavSections[] = [
                'SLUG' => $branchSlug,
                'NAME' => $branchInfo['name'],
                'SUBS' => $subs,
            ];
        }
        ?>
        <div class="catalog-dropdown__nav">
            <?php foreach ($catalogNavSections as $i => $top): ?>
                <a href="/catalog/<?= $top['SLUG'] ?>/" class="catalog-dropdown__nav-item<?= $i === 0 ? ' active' : '' ?>" data-panel="catalogNavPanel<?= $top['SLUG'] ?>">
                    <svg class="icon"><use href="#<?= pickCatalogNavIcon($top['NAME']) ?>"></use></svg>
                    <span><?= htmlspecialchars($top['NAME']) ?></span>
                    <span class="catalog-dropdown__nav-arrow">›</span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="catalog-dropdown__panels">
            <?php foreach ($catalogNavSections as $i => $top): ?>
                <div class="catalog-dropdown__panel<?= $i === 0 ? ' active' : '' ?>" id="catalogNavPanel<?= $top['SLUG'] ?>">
                    <div class="catalog-dropdown__tiles">
                        <?php foreach ($top['SUBS'] as $sub): ?>
                            <a href="/catalog/<?= $top['SLUG'] ?>/<?= $sub['CODE'] ?>/" class="catalog-dropdown__tile">
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
                        <a href="/catalog/<?= $top['SLUG'] ?>/" class="catalog-dropdown__tile catalog-dropdown__tile--all">
                            <span class="catalog-dropdown__tile-icon"><svg class="icon"><use href="#icon-list"></use></svg></span>
                            <span class="catalog-dropdown__tile-name">Все товары раздела</span>
                        </a>
                    </div>
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

            <?php
            $userLink  = $USER->IsAuthorized() ? '/personal/' : '/auth/';
            $userLabel = $USER->IsAuthorized() ? 'Кабинет' : 'Войти';
            ?>
            <div class="header__actions">
                <a href="<?= $userLink ?>" class="header__icon" title="<?= $USER->IsAuthorized() ? 'Личный кабинет' : 'Войти' ?>">
                    <span class="header__icon-box"><svg class="icon"><use href="#icon-user"></use></svg></span>
                    <span class="header__icon-label"><?= $userLabel ?></span>
                </a>
                <a href="/personal/favorites/" class="header__icon" id="favIcon" title="Избранное">
                    <span class="header__icon-box">
                        <svg class="icon"><use href="#icon-heart"></use></svg>
                        <span class="badge" id="favBadge"<?= $favQty > 0 ? '' : ' style="display:none;"' ?>><?= $favQty ?></span>
                    </span>
                    <span class="header__icon-label">Избранное</span>
                </a>
                <a href="/cart/" class="header__icon" id="cartIcon" title="Корзина">
                    <span class="header__icon-box">
                        <svg class="icon"><use href="#icon-cart"></use></svg>
                        <span class="badge" id="cartBadge"<?= $cartQty > 0 ? '' : ' style="display:none;"' ?>><?= $cartQty ?></span>
                    </span>
                    <span class="header__icon-label">Корзина</span>
                </a>
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
        // Дубль счётчика в мобильной нижней панели (.tabbar в конце шапки)
        document.querySelectorAll('[data-badge="cart"]').forEach(function (b) {
            b.textContent = qty;
            b.style.display = qty > 0 ? '' : 'none';
        });
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
                <a href="/podbor-po-vin/" class="header-nav__accent"><svg class="icon"><use href="#icon-car"></use></svg> Подбор по VIN</a>
                <a href="/service-parts/" class="header-nav__accent"><svg class="icon"><use href="#icon-wrench"></use></svg> Запчасти для ТО</a>
                <div class="nav-dropdown-wrapper">
                    <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/">Масла</a>
                    <div class="nav-dropdown">
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/maslo_motornoe/">Масло моторное</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/maslo_transmissionnoe/">Масло трансмиссионное</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/tormoznaya_zhidkost/">Тормозная жидкость</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/okhlazhdayushchaya_zhidkost/">Охлаждающая жидкость</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/zhidkost_gur/">Жидкость ГУР</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/stekloomyvayushchaya_zhidkost/">Стеклоомывающая жидкость</a>
                        <a href="/catalog/masla_i_tekhnicheskie_zhidkosti/" class="nav-dropdown__all">Все масла и жидкости →</a>
                    </div>
                </div>
                <div class="nav-dropdown-wrapper">
                    <a href="/catalog/inomarki/filtry/">Фильтры</a>
                    <div class="nav-dropdown">
                        <a href="/catalog/inomarki/filtry/maslyanye_filtry/">Масляные фильтры</a>
                        <a href="/catalog/inomarki/filtry/vozdushnye_filtry/">Воздушные фильтры</a>
                        <a href="/catalog/inomarki/filtry/toplivnye_filtry/">Топливные фильтры</a>
                        <a href="/catalog/inomarki/filtry/salonnye_filtry/">Салонные фильтры</a>
                        <a href="/catalog/inomarki/filtry/akpp_filtry/">Фильтры АКПП</a>
                        <a href="/catalog/vaz/filtry_vaz/">Фильтры ВАЗ</a>
                        <a href="/catalog/inomarki/filtry/" class="nav-dropdown__all">Все фильтры →</a>
                    </div>
                </div>
                <div class="nav-dropdown-wrapper">
                    <a href="/catalog/inomarki/tormoznaya_sistema/">Тормозные колодки</a>
                    <div class="nav-dropdown">
                        <a href="/catalog/inomarki/tormoznaya_sistema/perednie_kolodki/">Передние колодки</a>
                        <a href="/catalog/inomarki/tormoznaya_sistema/zadnie_kolodki/">Задние колодки</a>
                        <a href="/catalog/inomarki/tormoznaya_sistema/kolodki_ruchnika/">Колодки ручника</a>
                        <a href="/catalog/inomarki/tormoznaya_sistema/diski_tormoznye/">Тормозные диски</a>
                        <a href="/catalog/vaz/tormoznaya_sistema_vaz/">Тормозная система ВАЗ</a>
                        <a href="/catalog/inomarki/tormoznaya_sistema/" class="nav-dropdown__all">Вся тормозная система →</a>
                    </div>
                </div>
                <a href="/catalog/vaz/elektrika_vaz/akb/">Аккумуляторы</a>
                <div class="nav-dropdown-wrapper">
                    <a href="/autoservice/">Автосервис</a>
                    <div class="nav-dropdown">
                        <a href="/autoservice/diagnostika-i-remont-podveski/">Диагностика и ремонт подвески</a>
                        <a href="/autoservice/zamena-masla-v-dvigatele/">Замена масла в двигателе</a>
                        <a href="/autoservice/tekhnicheskoe-obsluzhivanie/">Техническое обслуживание и мелкий ремонт</a>
                        <a href="/autoservice/remont-tormoznoy-sistemy/">Ремонт тормозной системы</a>
                        <a href="/autoservice/zamena-tsepi-remnya-grm/">Замена цепи/ремня ГРМ</a>
                        <a href="/autoservice/zamena-masla-v-transmissii/">Замена масла в трансмиссии</a>
                        <a href="/autoservice/zamena-filtrov/">Замена фильтров</a>
                        <a href="/autoservice/" class="nav-dropdown__all">Все услуги автосервиса →</a>
                    </div>
                </div>
                <a href="/shinomontazh/" class="header-nav__cta">Запись на шиномонтаж <span class="header-nav__cta-arrow"><svg class="icon"><use href="#icon-arrow-up-right"></use></svg></span></a>
            </nav>
        </div>
    </div>

    <!-- Мобильная нижняя панель (≤768px, см. .tabbar в style.css): основные
         действия под большим пальцем, шапка при этом сжимается до логотипа и
         поиска. "Каталог" открывает тот же флайаут, что и кнопка в шапке
         (main.js), — без JS это обычная ссылка на /catalog/. -->
    <?php $curPage = $APPLICATION->GetCurPage(false); ?>
    <nav class="tabbar" aria-label="Быстрое меню">
        <a href="/" class="tabbar__item<?= $curPage === '/' ? ' is-active' : '' ?>">
            <svg class="icon"><use href="#icon-home"></use></svg><span>Главная</span>
        </a>
        <a href="/catalog/" class="tabbar__item<?= strpos($curPage, '/catalog/') === 0 ? ' is-active' : '' ?>" id="tabbarCatalog">
            <svg class="icon"><use href="#icon-grid"></use></svg><span>Каталог</span>
        </a>
        <a href="/personal/favorites/" class="tabbar__item<?= strpos($curPage, '/personal/favorites/') === 0 ? ' is-active' : '' ?>">
            <span class="tabbar__icon">
                <svg class="icon"><use href="#icon-heart"></use></svg>
                <span class="tabbar__badge" data-badge="fav"<?= $favQty > 0 ? '' : ' style="display:none;"' ?>><?= $favQty ?></span>
            </span><span>Избранное</span>
        </a>
        <a href="/cart/" class="tabbar__item<?= strpos($curPage, '/cart/') === 0 ? ' is-active' : '' ?>">
            <span class="tabbar__icon">
                <svg class="icon"><use href="#icon-cart"></use></svg>
                <span class="tabbar__badge" data-badge="cart"<?= $cartQty > 0 ? '' : ' style="display:none;"' ?>><?= $cartQty ?></span>
            </span><span>Корзина</span>
        </a>
        <a href="<?= $userLink ?>" class="tabbar__item<?= (strpos($curPage, '/personal/') === 0 && strpos($curPage, '/personal/favorites/') !== 0) || strpos($curPage, '/auth/') === 0 ? ' is-active' : '' ?>">
            <svg class="icon"><use href="#icon-user"></use></svg><span><?= $userLabel ?></span>
        </a>
    </nav>

    <main class="main">
