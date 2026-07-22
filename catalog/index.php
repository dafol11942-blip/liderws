<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
CModule::IncludeModule('sale');

// Обязательно для работы фильтра
global $arrFilter;

$APPLICATION->SetTitle("Каталог автозапчастей");

if (!empty($_REQUEST['set_filter']) && $_REQUEST['set_filter'] === 'Y') {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/filter_debug.log',
        date('Y-m-d H:i:s') . ' | GET: ' . json_encode($_GET) . "\n" .
        ' | arrFilter: ' . json_encode($arrFilter ?? []) . "\n",
        FILE_APPEND
    );
}

$iblockId = 42;

// --- Парсим URL ---
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?');
$path = trim($requestUri, '/');
if (strpos($path, 'catalog/') === 0) {
    $path = substr($path, 8);
}
$segments = $path ? explode('/', $path) : [];

$elementCode = null;
$sectionCode = null;
$isElement  = false;

if (count($segments) >= 2) {
    $lastSegment = end($segments);
    
    $elRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => $lastSegment, 'ACTIVE' => 'Y'],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME', 'CODE']
    );
    if ($elFound = $elRes->GetNext()) {
        $elementCode = $lastSegment;
        $sectionSegments = array_slice($segments, 0, -1);
        $sectionCode = implode('/', $sectionSegments);
        $isElement = true;
    } else {
        $sectionCode = implode('/', $segments);
    }
} elseif (count($segments) === 1) {
    $sectionCode = $segments[0];
} else {
    $sectionCode = '';
}

$sectionId = 0;
if ($sectionCode) {
    $res = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => end($segments)], false, ['ID', 'NAME']);
    if ($arSection = $res->GetNext()) {
        $sectionId = $arSection['ID'];
    }
}
?>

<?php
// --- Хлебные крошки ---
$breadcrumbs = [];
$breadcrumbs[] = ['NAME' => 'Главная', 'LINK' => '/'];
$breadcrumbs[] = ['NAME' => 'Каталог автозапчастей', 'LINK' => '/catalog/'];

// Определяем ID раздела для построения цепочки
$chainSectionId = 0;

if ($isElement && $elementCode) {
    // Детальная: получаем раздел товара
    $elRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => $elementCode],
        false,
        ['nTopCount' => 1],
        ['ID', 'IBLOCK_SECTION_ID']
    );
    if ($el = $elRes->GetNext()) {
        $chainSectionId = (int)$el['IBLOCK_SECTION_ID'];
    }
} else {
    // Раздел или корень
    $chainSectionId = $sectionId;
}

// Строим цепочку разделов
if ($chainSectionId > 0) {
    $rsChain = CIBlockSection::GetNavChain($iblockId, $chainSectionId, ['ID', 'NAME', 'CODE']);
    while ($arSec = $rsChain->GetNext()) {
        $breadcrumbs[] = ['NAME' => $arSec['NAME'], 'LINK' => '/catalog/' . $arSec['CODE'] . '/'];
    }
}

// Для детальной — добавляем название товара
if ($isElement && $elementCode) {
    $arElement = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => $elementCode],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME']
    );
    if ($el = $arElement->GetNext()) {
        $breadcrumbs[] = ['NAME' => $el['NAME'], 'LINK' => ''];
    }
}
?>
<div class="breadcrumbs">
    <ul>
        <?php $lastIdx = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $i => $item):
            if ($i < $lastIdx): ?>
                <li><a href="<?= $item['LINK'] ?>"><?= htmlspecialchars($item['NAME']) ?></a></li>
            <?php else: ?>
                <li><?= htmlspecialchars($item['NAME']) ?></li>
            <?php endif;
        endforeach; ?>
    </ul>
</div>
<?php // --- Конец хлебных крошек --- ?>
<?php
// Ручной фильтр по цене (до вызова умного фильтра)
if (!empty($_REQUEST['arrFilter_P1_MIN']) || !empty($_REQUEST['arrFilter_P1_MAX'])) {
    if (!empty($_REQUEST['arrFilter_P1_MIN'])) {
        $arrFilter['>=CATALOG_PRICE_1'] = (int)$_REQUEST['arrFilter_P1_MIN'];
    }
    if (!empty($_REQUEST['arrFilter_P1_MAX'])) {
        $arrFilter['<=CATALOG_PRICE_1'] = (int)$_REQUEST['arrFilter_P1_MAX'];
    }
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/filter_debug.log',
        date('Y-m-d H:i:s') . " | MANUAL arrFilter: " . json_encode($arrFilter) . "\n",
        FILE_APPEND
    );
}
?>
<?php if (!$isElement): ?>
<div class="catalog-layout">
    <aside class="catalog-sidebar">
        <h3>📋 Фильтр</h3>
        <?php $APPLICATION->IncludeComponent(
            "bitrix:catalog.smart.filter",
            "lider_style",
            array(
                "IBLOCK_TYPE"       => "1c_catalog",
                "IBLOCK_ID"         => $iblockId,
				"SECTION_ID"        => $sectionId ?: "",
                "FILTER_NAME"       => "arrFilter",
				"PRICE_CODE" => array("Ручная розничная цена"),
				"CACHE_TYPE"        => "N",
                "CACHE_TIME"        => "0",

                "SAVE_IN_SESSION"   => "N",
                "PAGER_PARAMS_NAME" => "arrPager",
                "INSTANT_RELOAD"    => "N",
                "CONVERT_CURRENCY"  => "Y",
                "CURRENCY_ID"       => "RUB",
                "DISPLAY_ELEMENT_COUNT" => "Y",
            ),
            false
        ); ?>
<?php
        // Чистим и добавляем цену правильно
        if (isset($_REQUEST['set_filter']) && $_REQUEST['set_filter'] === 'Y') {
            // Удаляем кривые ключи, которые умный фильтр добавляет для диапазона
            unset($arrFilter['><CATALOG_PRICE_1']);
            unset($arrFilter['CATALOG_CURRENCY_SCALE_1']);
            unset($arrFilter['FACET_OPTIONS']);
            // Правильные ключи
            if (!empty($_REQUEST['arrFilter_P1_MIN']))
                $arrFilter['>=CATALOG_PRICE_1'] = (int)$_REQUEST['arrFilter_P1_MIN'];
            if (!empty($_REQUEST['arrFilter_P1_MAX']))
                $arrFilter['<=CATALOG_PRICE_1'] = (int)$_REQUEST['arrFilter_P1_MAX'];
        }
        ?>
    </aside>
    <div class="catalog-main">
<?php else: ?>
    <div class="container">
<?php endif; ?>

        <?php if ($isElement): ?>
            <!-- ===== ДЕТАЛЬНАЯ ТОВАРА ===== -->
            <?php $APPLICATION->IncludeComponent(
                "bitrix:catalog.element",
                "lider_style",
                array(
                    "IBLOCK_TYPE"      => "1c_catalog",
                    "IBLOCK_ID"        => $iblockId,
                    "ELEMENT_CODE"     => $elementCode,
                    "SECTION_CODE"     => $sectionCode,
                    "SECTION_ID"       => $sectionId,
                    "PROPERTY_CODE"    => array("CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"),
                    "PRICE_CODE"       => array("Ручная розничная цена"),
                    "PRICE_VAT_INCLUDE"=> "Y",
                    "HIDE_NOT_AVAILABLE"=> "Y",
                    "BASKET_URL"       => "/cart/",
                    "SET_TITLE"        => "Y",
                    "ADD_SECTIONS_CHAIN"=> "Y",
                    "ADD_ELEMENT_CHAIN" => "Y",
                    "CACHE_TYPE"       => "A",
                    "CACHE_TIME"       => "36000000",
                ),
                false
            ); ?>

        <?php elseif ($sectionId > 0): ?>
            <!-- ===== РАЗДЕЛ: подразделы + товары ===== -->
            <?php
            $subSections = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $sectionId, 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'PICTURE']
            );
            $hasSubSections = false;
            $subSectionsHtml = '';
            
            while ($sub = $subSections->GetNext()) {
                $hasSubSections = true;
                $subUrl = '/catalog/' . ($sectionCode ? $sectionCode . '/' : '') . $sub['CODE'] . '/';
                $imgTag = '';
                if (!empty($sub['PICTURE'])) {
                    $imgPath = CFile::GetPath($sub['PICTURE']);
                    $imgTag = '<img src="' . $imgPath . '" alt="' . htmlspecialchars($sub['NAME']) . '" style="max-height:60px;">';
                }
                $subSectionsHtml .= '
                <a href="' . $subUrl . '" class="category-card">
                    <span class="category-card__icon">' . ($imgTag ?: '📁') . '</span>
                    <span class="category-card__name">' . $sub['NAME'] . '</span>
                </a>';
            }
            ?>

            <?php if ($hasSubSections): ?>
                <div class="section-header">
                    <h2 class="section-title">📂 Подразделы</h2>
                </div>
                <div class="categories-grid" style="margin-bottom: 24px;">
                    <?= $subSectionsHtml ?>
                </div>
            <?php endif; ?>

            <div class="catalog-toolbar">
                <span class="catalog-toolbar__count">Товары в разделе</span>
                <div class="catalog-toolbar__sort">
                    <select onchange="window.location.href=this.value">
                        <option value="?sort=popular">По популярности</option>
                        <option value="?sort=price_asc">Цена ↑</option>
                        <option value="?sort=price_desc">Цена ↓</option>
                        <option value="?sort=name">По названию</option>
                    </select>
                </div>
            </div>

            <?php $APPLICATION->IncludeComponent(
                "bitrix:catalog.section",
                "lider_style",
                array(
                    "IBLOCK_TYPE"       => "1c_catalog",
                    "IBLOCK_ID"         => $iblockId,
                    "SECTION_ID"        => $sectionId,
                    "SECTION_CODE"      => $sectionCode,
                    "INCLUDE_SUBSECTIONS" => "N",
                    "ELEMENT_SORT_FIELD"  => $_GET["sort"] === "price_asc" ? "catalog_PRICE_1" : ($_GET["sort"] === "name" ? "name" : "sort"),
                    "ELEMENT_SORT_ORDER"  => $_GET["sort"] === "price_desc" ? "desc" : "asc",
                    "FILTER_NAME"       => "arrFilter",
                    "PRICE_CODE"        => array("Ручная розничная цена"),
                    "PROPERTY_CODE"     => array("CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"),
                    "PAGE_ELEMENT_COUNT"=> "12",
                    "DISPLAY_BOTTOM_PAGER"=> "Y",
                    "PAGER_TITLE"       => "Товары",
                    "PAGER_TEMPLATE"    => ".default",
                    "HIDE_NOT_AVAILABLE" => "Y",
                    "BASKET_URL"        => "/cart/",
                    "CACHE_TYPE"        => "N",
                    "CACHE_TIME"        => "36000000",
                    "SET_TITLE"         => "Y",
                    "ADD_SECTIONS_CHAIN" => "Y",
                ),
                false
            ); ?>

        <?php else: ?>
            <!-- ===== КОРЕНЬ КАТАЛОГА ===== -->
            <?php
            $topSections = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => 0, 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'PICTURE']
            );
            $topHtml = '';
            while ($top = $topSections->GetNext()) {
                $topUrl = '/catalog/' . $top['CODE'] . '/';
                $imgTag = '';
                if (!empty($top['PICTURE'])) {
                    $imgPath = CFile::GetPath($top['PICTURE']);
                    $imgTag = '<img src="' . $imgPath . '" alt="' . htmlspecialchars($top['NAME']) . '" style="max-height:60px;">';
                }
                $topHtml .= '
                <a href="' . $topUrl . '" class="category-card">
                    <span class="category-card__icon">' . ($imgTag ?: '📁') . '</span>
                    <span class="category-card__name">' . $top['NAME'] . '</span>
                </a>';
            }
            ?>
            <div class="section-header">
                <h2 class="section-title">📦 Каталог товаров</h2>
            </div>
            <div class="categories-grid">
                <?= $topHtml ?>
            </div>

            <div class="section-header mt-20">
                <h2 class="section-title">⭐ Все товары</h2>
            </div>
            <div class="catalog-toolbar">
                <span class="catalog-toolbar__count">Товары</span>
                <div class="catalog-toolbar__sort">
                    <select onchange="window.location.href=this.value">
                        <option value="?sort=popular">По популярности</option>
                        <option value="?sort=price_asc">Цена ↑</option>
                        <option value="?sort=price_desc">Цена ↓</option>
                        <option value="?sort=name">По названию</option>
                    </select>
                </div>
            </div>
<?php file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/filter_debug.log',
                date('Y-m-d H:i:s') . " | BEFORE_SECTION arrFilter: " . json_encode($arrFilter ?? 'NULL') . "\n",
                FILE_APPEND
            );
            ?>
            <?php $APPLICATION->IncludeComponent(
                "bitrix:catalog.section",
                "lider_style",
                array(
                    "IBLOCK_TYPE"       => "1c_catalog",
                    "IBLOCK_ID"         => $iblockId,
                    "INCLUDE_SUBSECTIONS" => "Y",
                    "SHOW_ALL_WO_SECTION" => "Y",
                    "ELEMENT_SORT_FIELD"  => $_GET["sort"] === "price_asc" ? "catalog_PRICE_1" : ($_GET["sort"] === "name" ? "name" : "sort"),
                    "ELEMENT_SORT_ORDER"  => $_GET["sort"] === "price_desc" ? "desc" : "asc",
                    "FILTER_NAME"       => "arrFilter",
                    "PRICE_CODE"        => array("Ручная розничная цена"),
                    "PROPERTY_CODE"     => array("CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"),
                    "PAGE_ELEMENT_COUNT"=> "12",
                    "DISPLAY_BOTTOM_PAGER"=> "Y",
                    "PAGER_TITLE"       => "Товары",
                    "PAGER_TEMPLATE"    => ".default",
                    "HIDE_NOT_AVAILABLE" => "Y",
                    "BASKET_URL"        => "/cart/",
                    "CACHE_TYPE"        => "N",
                    "CACHE_TIME"        => "36000000",
                    "SET_TITLE"         => "Y",
                ),
                false
            ); ?>
        <?php endif; ?>

<?php if (!$isElement): ?>
    </div><!-- /catalog-main -->
</div><!-- /catalog-layout -->
<?php else: ?>
    </div><!-- /container -->
<?php endif; ?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
