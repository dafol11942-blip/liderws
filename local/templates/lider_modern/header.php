<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
    <?php $APPLICATION->ShowHead(); ?>
    <link rel="stylesheet" href="<?= SITE_TEMPLATE_PATH ?>/assets/css/style.css">
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
                <a href="/cart/" class="header__icon" title="Корзина">
                    <svg class="icon"><use href="#icon-cart"></use></svg>
                    <span class="badge" id="cartBadge" style="display:none;">0</span>
                </a>
                <a href="/personal/" class="header__icon" title="Личный кабинет"><svg class="icon"><use href="#icon-user"></use></svg></a>
            </div>
        </div>
    </header>

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
