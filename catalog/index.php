<?php
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    ob_start();
}
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
CModule::IncludeModule('sale');

// Обязательно для работы фильтра
global $arrFilter;

$APPLICATION->SetTitle("Каталог автозапчастей");

// CATALOG_BRANCHES и resolveEffectiveRoot() определены в
// local/php_interface/init.php — общие для этого файла и header.php.

// --- Парсим URL: первый сегмент — ветка каталога ---
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?');
$path = trim($requestUri, '/');
// Срезаем и "catalog/", и голый "catalog" (сам /catalog/ после trim — это
// "catalog" без слэша, иначе он принимался за неизвестную ветку и уходил в 404).
$path = (string)preg_replace('#^catalog(/|$)#', '', $path);
$allSegments = $path ? explode('/', $path) : [];

// Последний сегмент URL не нашёлся в своей ветке — ищем такой раздел или товар
// в любой ветке и уводим 301 на правильный адрес (старые ссылки вида
// /catalog/masla_i_tekhnicheskie_zhidkosti/..., адреса из прежнего инфоблока),
// иначе отдаём настоящий 404 вместо показа корня каталога с кодом 200
// (мягкий 404 размножал в индексе одинаковые страницы).
$redirectOrNotFound = function (string $code) {
    if ($code !== '') {
        foreach (CATALOG_BRANCHES as $slug => $info) {
            $found = CIBlockSection::GetList([], ['IBLOCK_ID' => $info['id'], 'CODE' => $code, 'ACTIVE' => 'Y'], false, ['ID'])->Fetch()
                ?: CIBlockElement::GetList([], ['IBLOCK_ID' => $info['id'], 'CODE' => $code, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID'])->Fetch();
            if ($found) {
                LocalRedirect('/catalog/' . $slug . '/' . $code . '/', false, '301 Moved Permanently');
            }
        }
    }
    \Bitrix\Iblock\Component\Tools::process404('', true, true, true);
};

// Прежние адреса веток (до разделения каталога на три инфоблока).
const CATALOG_LEGACY_BRANCHES = ['masla_i_tekhnicheskie_zhidkosti' => 'maslo'];

$branch = $allSegments[0] ?? '';
if ($branch !== '' && !isset(CATALOG_BRANCHES[$branch])) {
    if (count($allSegments) === 1 && isset(CATALOG_LEGACY_BRANCHES[$branch])) {
        LocalRedirect('/catalog/' . CATALOG_LEGACY_BRANCHES[$branch] . '/', false, '301 Moved Permanently');
    }
    $redirectOrNotFound((string)end($allSegments));
}
if ($branch === '') {
    require __DIR__ . '/branches.php';
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
    return;
}
$iblockId = CATALOG_BRANCHES[$branch]['id'];
$branchName = CATALOG_BRANCHES[$branch]['name'];
$catalogPrefix = '/catalog/' . $branch . '/';
$effectiveRoot = resolveEffectiveRoot($iblockId);
$effectiveRootId = $effectiveRoot['rootId'];
$effectiveRootSkippedIds = $effectiveRoot['skippedIds'];

$segments = array_slice($allSegments, 1);

$elementCode = null;
$sectionCode = null;
$isElement  = false;

// Проверяем последний сегмент на совпадение с кодом товара при ЛЮБОМ
// количестве сегментов (было только >=2) — у ВАЗ/Иномарки разделов нет
// вовсе, значит URL товара это ровно один сегмент после ветки
// (/catalog/vaz/{код}/), и раньше такой URL всегда трактовался как раздел.
if (count($segments) >= 1) {
    $lastSegment = end($segments);

    $elRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => $lastSegment, 'ACTIVE' => 'Y'],
        false,
        ['nTopCount' => 1],
        ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL', 'DETAIL_PICTURE', 'PREVIEW_PICTURE', 'PREVIEW_TEXT']
    );
    if ($elFound = $elRes->GetNext()) {
        $elementCode = $lastSegment;
        $sectionSegments = array_slice($segments, 0, -1);
        $sectionCode = implode('/', $sectionSegments);
        $isElement = true;
    } else {
        $sectionCode = implode('/', $segments);
    }
} else {
    $sectionCode = '';
}

$sectionId = 0;
if ($sectionCode) {
    $res = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => end($segments)], false, ['ID', 'NAME', 'DESCRIPTION', 'PICTURE']);
    if ($arSection = $res->GetNext()) {
        $sectionId = $arSection['ID'];
    }
}
if (!$isElement && $sectionCode && !$sectionId) {
    $redirectOrNotFound((string)end($segments));
}

// --- Сортировка (общая для раздела и корня каталога) ---
$currentSort = $_GET['sort'] ?? 'popular';
switch ($currentSort) {
    case 'price_asc':  $sortField = 'catalog_PRICE_1'; $sortOrder = 'asc';  break;
    case 'price_desc': $sortField = 'catalog_PRICE_1'; $sortOrder = 'desc'; break;
    case 'stock':       $sortField = 'CATALOG_QUANTITY'; $sortOrder = 'desc'; break;
    case 'name':        $sortField = 'name'; $sortOrder = 'asc'; break;
    default:            $sortField = 'sort'; $sortOrder = 'asc';
}

// --- Дерево категорий в сайдбаре: разворачивается на месте (стрелкой),
// без перехода на страницу раздела, с любого уровня — включая корень
// каталога. Ветка текущего раздела раскрыта по умолчанию, остальные свёрнуты.
// Ссылки на разделы — просто "/catalog/<CODE>/": роутинг в этом файле
// резолвит раздел только по последнему сегменту URL (см. "--- Парсим URL
// ---" выше), поэтому префикс пути можно не собирать — так же делают
// хлебные крошки и плитки подразделов.
$sidebarSectionsByParent = [];
$rsAllSections = CIBlockSection::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], false, ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID']);
while ($row = $rsAllSections->GetNext()) {
    $sidebarSectionsByParent[(int)$row['IBLOCK_SECTION_ID']][] = $row;
}

$sidebarActivePath = [];
if ($sectionId > 0) {
    $rsActiveChain = CIBlockSection::GetNavChain($iblockId, $sectionId, ['ID']);
    while ($arChainItem = $rsActiveChain->GetNext()) {
        $sidebarActivePath[] = (int)$arChainItem['ID'];
    }
}

// Дерево в сайдбаре не должно раскрываться до самых листьев — у конечных
// разделов (например, брендов масла внутри "Масло моторное") бывают
// десятки подпунктов, и на этом уровне список превращается в простыню.
// Такие подразделы остаются доступны через плитки "Подразделы" на
// странице раздела, а сайдбар ограничен основными уровнями структуры.
const SIDEBAR_TREE_MAX_DEPTH = 2;

function renderCategoryTreeNode($section, $sectionsByParent, $activePath, $currentSectionId, $catalogPrefix, $depth = 1) {
    $id = (int)$section['ID'];
    $children = $depth < SIDEBAR_TREE_MAX_DEPTH ? ($sectionsByParent[$id] ?? []) : [];
    $isOpen = in_array($id, $activePath, true);
    $isActive = ($id === (int)$currentSectionId);

    $html = '<div class="filter__tree-node' . ($isOpen ? ' filter__tree-node--open' : '') . '">';
    $html .= '<div class="filter__tree-row">';
    $html .= $children
        ? '<button type="button" class="filter__tree-toggle" aria-label="Развернуть"></button>'
        : '<span class="filter__tree-spacer"></span>';
    $html .= '<a href="' . $catalogPrefix . $section['CODE'] . '/" class="filter__cat-link' . ($isActive ? ' active' : '') . '">' . htmlspecialchars($section['NAME']) . '</a>';
    $html .= '</div>';
    if ($children) {
        $html .= '<div class="filter__tree-children">';
        foreach ($children as $child) {
            $html .= renderCategoryTreeNode($child, $sectionsByParent, $activePath, $currentSectionId, $catalogPrefix, $depth + 1);
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

$sidebarCategoryTreeHtml = '';
foreach (($sidebarSectionsByParent[$effectiveRootId] ?? []) as $topSection) {
    $sidebarCategoryTreeHtml .= renderCategoryTreeNode($topSection, $sidebarSectionsByParent, $sidebarActivePath, $sectionId, $catalogPrefix);
}
?>

<?php
// --- Хлебные крошки ---
$breadcrumbs = [];
$breadcrumbs[] = ['NAME' => 'Главная', 'LINK' => '/'];
$breadcrumbs[] = ['NAME' => 'Каталог автозапчастей', 'LINK' => '/catalog/'];
$breadcrumbs[] = ['NAME' => $branchName, 'LINK' => $catalogPrefix];

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
        if (in_array((int)$arSec['ID'], $effectiveRootSkippedIds, true)) {
            continue; // скрытая техническая обёртка (может быть несколько подряд) — см. resolveEffectiveRoot()
        }
        $breadcrumbs[] = ['NAME' => $arSec['NAME'], 'LINK' => $catalogPrefix . $arSec['CODE'] . '/'];
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

// Заголовок вкладки браузера/SEO — по названию товара, раздела или общий для корня ветки.
// Обязательно SetPageProperty (а не только SetTitle): title, заданный компонентами каталога
// после require bitrix/header.php, до <title> в шаблоне не долетает — см. /index.php.

// Тексты для корня каждой ветки: у всех трёх раньше был один и тот же
// title и не было ни description, ни H1 — дубли для поисковиков.
$branchSeo = [
    'vaz' => [
        'h1'          => 'Запчасти для ВАЗ (LADA)',
        'suffix'      => 'для ВАЗ',
        'title'       => 'Запчасти ВАЗ (LADA) в Елабуге — купить в магазине ЛИДЕР',
        'description' => 'Автозапчасти для ВАЗ и LADA в Елабуге: более 20 000 наименований в наличии. Официальный субдилер «LADA-Деталь». Самовывоз из двух магазинов, подбор по VIN.',
    ],
    'inomarki' => [
        'h1'          => 'Запчасти для иномарок',
        'suffix'      => 'для иномарок',
        'title'       => 'Запчасти для иномарок в Елабуге — в наличии и под заказ | ЛИДЕР',
        'description' => 'Более 10 000 запчастей для иномарок в наличии в Елабуге, под заказ — от 4 часов. Фильтры, колодки, подвеска, ГРМ. Подбор по VIN по оригинальным каталогам.',
    ],
    'maslo' => [
        'h1'          => 'Масла и технические жидкости',
        'suffix'      => '',
        'title'       => 'Моторные масла и технические жидкости в Елабуге | ЛИДЕР',
        'description' => 'Моторные и трансмиссионные масла Shell, Mobil, Castrol, ZIC, G-Energy, Лукойл, Роснефть, Газпром, тормозные и охлаждающие жидкости. Сертифицированная точка продаж в Елабуге.',
    ],
][$branch] ?? ['h1' => $branchName, 'suffix' => '', 'title' => $branchName . ' — ЛИДЕР', 'description' => ''];

$pageNum = 0;
foreach ($_GET as $navKey => $navValue) {
    if (preg_match('/^PAGEN_\d+$/', $navKey) && is_scalar($navValue)) {
        $pageNum = max($pageNum, (int)$navValue);
    }
}
$pageSuffix = $pageNum > 1 ? ' — страница ' . $pageNum : '';

$catalogPageName = \Lider\Seo\Seo::text(end($breadcrumbs)['NAME'] ?? 'Каталог автозапчастей');
$catalogH1 = $branchSeo['h1'];
if ($isElement) {
    // Артикул и бренд — в description: по ним ищут чаще, чем по названию.
    $elementId = (int)$elFound['ID'];
    $elementProps = [];
    foreach (array_filter(['CML2_ARTICLE', 'CML2_MANUFACTURER', getBrandPropertyCode($iblockId)]) as $propCode) {
        $propRes = CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => $propCode]);
        // У свойств-списков VALUE — ID варианта, текст лежит в VALUE_ENUM.
        $propRow = $propRes->Fetch();
        $propValue = $propRow ? trim((string)(($propRow['PROPERTY_TYPE'] ?? '') === 'L' ? $propRow['VALUE_ENUM'] : $propRow['VALUE'])) : '';
        if ($propValue !== '') {
            $elementProps[$propCode] = $propValue;
        }
    }
    $elementArticle = $elementProps['CML2_ARTICLE'] ?? '';
    $elementBrand = $elementProps['CML2_MANUFACTURER'] ?? (array_values(array_diff_key($elementProps, ['CML2_ARTICLE' => 1]))[0] ?? '');

    $APPLICATION->SetPageProperty('title', $catalogPageName . ' купить в Елабуге — цена, наличие | ЛИДЕР');
    $APPLICATION->SetPageProperty('description', \Lider\Seo\Seo::truncate(
        $catalogPageName
        . ($elementArticle !== '' && mb_stripos($catalogPageName, $elementArticle) === false ? ', артикул ' . $elementArticle : '')
        . ($elementBrand !== '' ? ', ' . $elementBrand : '')
        . '. Купить в Елабуге в магазине автозапчастей ЛИДЕР: цена и наличие, самовывоз из двух магазинов, подбор аналогов.'
    ));
    \Lider\Seo\Seo::setCanonical(catalogElementUrl($branch, $elFound));
    \Lider\Seo\Seo::setOgType('product');
    \Lider\Seo\Seo::addShops(); // на них ссылается Offer.availableAtOrFrom в разметке товара
    $elementPicture = (int)($elFound['DETAIL_PICTURE'] ?: $elFound['PREVIEW_PICTURE']);
    if ($elementPicture > 0) {
        \Lider\Seo\Seo::setOgImage((string)CFile::GetPath($elementPicture));
    }
} elseif ($sectionId > 0) {
    $sectionSuffix = $branchSeo['suffix'];
    if ($sectionSuffix !== '' && preg_match('/ваз|lada|лада|иномар/iu', $catalogPageName)) {
        $sectionSuffix = '';
    }
    $catalogH1 = trim($catalogPageName . ' ' . $sectionSuffix);
    $sectionText = \Lider\Seo\Seo::text($arSection['~DESCRIPTION'] ?? '');
    $APPLICATION->SetPageProperty('title', $catalogH1 . ' — купить в Елабуге, цены и наличие | ЛИДЕР' . $pageSuffix);
    $APPLICATION->SetPageProperty('description', \Lider\Seo\Seo::truncate($sectionText !== ''
        ? $sectionText
        : $catalogH1 . ' в наличии в магазине автозапчастей ЛИДЕР в Елабуге. Актуальные цены и остатки, самовывоз с пр-та Нефтяников, 4 и ул. Баки Урманче, 17а, подбор по VIN.'
    ) . $pageSuffix);
    \Lider\Seo\Seo::setCanonical($catalogPrefix . $segments[count($segments) - 1] . '/', true);
} else {
    $APPLICATION->SetPageProperty('title', $branchSeo['title'] . $pageSuffix);
    $APPLICATION->SetPageProperty('description', $branchSeo['description'] . $pageSuffix);
}
echo \Lider\Seo\Seo::breadcrumbs($breadcrumbs);
?>
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
}

// Товары с нулевым остатком не показываем нигде в каталоге. Фильтруем на
// уровне SQL (до пагинации компонента), иначе она режет строго по
// PAGE_ELEMENT_COUNT ДО того, как узнаёт, что часть уже отобранных товаров
// пуста, и страница остаётся недобитой (реально проявилось на ВАЗ/Иномарки:
// самые свежие товары из 1С ещё без остатка сортировались на первую
// страницу и вырезались целиком в result_modifier.php, отдавая пустую
// страницу). Раньше здесь было исключение для корня каталога
// (SHOW_ALL_WO_SECTION) из-за опасения, что фильтр там не работает — на
// инфоблоках 55/56/57 проверено отдельно: фильтр прекрасно работает и в
// этом режиме, поэтому применяем его везде. result_modifier.php остаётся
// как подстраховка на случай гонки между остатком и кэшем компонента.
$arrFilter['>=CATALOG_QUANTITY'] = 1;
?>
<?php if (!$isElement): ?>
<div class="catalog-layout">
    <button type="button" class="catalog-filter-toggle" id="catalogFilterToggle">
        <svg class="icon"><use href="#icon-filter"></use></svg> Фильтр
    </button>
    <div class="catalog-filter-backdrop" id="catalogFilterBackdrop"></div>
    <aside class="catalog-sidebar" id="catalogFilterPanel">
    <?php ob_start(); ?>
        <h3><svg class="icon"><use href="#icon-filter"></use></svg> Фильтр
            <button type="button" class="catalog-filter-close" id="catalogFilterClose" aria-label="Закрыть фильтр">&times;</button>
        </h3>

        <div class="filter__box">
            <div class="filter__title" onclick="this.parentElement.classList.toggle('closed')">
                Категория
                <span class="filter__arrow">▾</span>
            </div>
            <div class="filter__body">
                <a href="<?= $catalogPrefix ?>" class="filter__cat-link filter__cat-link--all<?= $sectionId == 0 ? ' active' : '' ?>">Все товары</a>
                <div class="filter__tree"><?= $sidebarCategoryTreeHtml ?></div>
            </div>
        </div>

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
    <?php
    $sidebarHtml = ob_get_clean();
    echo $sidebarHtml;
    ?>
    </aside>
    <div class="catalog-main" id="catalogMain">
    <?php ob_start(); ?>
        <h1 class="section-title catalog-h1"><?= htmlspecialchars($catalogH1) ?></h1>
<?php else: ?>
    <div class="container">
<?php endif; ?>

        <?php if ($isElement): ?>
            <!-- ===== ДЕТАЛЬНАЯ ТОВАРА ===== -->
            <?php
            $detailBrandPropCode = getBrandPropertyCode($iblockId);
            $APPLICATION->IncludeComponent(
                "bitrix:catalog.element",
                "lider_style",
                array(
                    "IBLOCK_TYPE"      => "1c_catalog",
                    "IBLOCK_ID"        => $iblockId,
                    "ELEMENT_CODE"     => $elementCode,
                    "SECTION_CODE"     => $sectionCode,
                    "SECTION_ID"       => $sectionId,
                    "PROPERTY_CODE"    => array_values(array_filter(["CML2_ARTICLE", "CML2_MANUFACTURER", $detailBrandPropCode, "IN_STOCK"])),
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
                // Короткий адрес, как в сайдбаре и крошках: роутер ищет раздел по
                // последнему сегменту, а один адрес на раздел — без дублей в индексе.
                $subUrl = $catalogPrefix . $sub['CODE'] . '/';
                $imgTag = '';
                if (!empty($sub['PICTURE'])) {
                    $imgPath = CFile::GetPath($sub['PICTURE']);
                    $imgTag = '<img src="' . $imgPath . '" alt="' . htmlspecialchars($sub['NAME']) . '" style="max-height:60px;">';
                }
                $subSectionsHtml .= '
                <a href="' . $subUrl . '" class="category-card">
                    <span class="category-card__icon">' . ($imgTag ?: '<svg class="icon"><use href="#icon-folder"></use></svg>') . '</span>
                    <span class="category-card__name">' . $sub['NAME'] . '</span>
                </a>';
            }
            ?>

            <?php if ($hasSubSections): ?>
                <div class="section-header">
                    <h2 class="section-title"><svg class="icon"><use href="#icon-folder"></use></svg> Подразделы</h2>
                </div>
                <div class="categories-grid" style="margin-bottom: 24px;">
                    <?= $subSectionsHtml ?>
                </div>
            <?php endif; ?>

            <div class="catalog-toolbar">
                <span class="catalog-toolbar__count">Товары в разделе</span>
                <div class="catalog-toolbar__sort">
                    <select>
                        <option value="?sort=popular" <?= $currentSort === 'popular' ? 'selected' : '' ?>>По популярности</option>
                        <option value="?sort=price_asc" <?= $currentSort === 'price_asc' ? 'selected' : '' ?>>Цена ↑</option>
                        <option value="?sort=price_desc" <?= $currentSort === 'price_desc' ? 'selected' : '' ?>>Цена ↓</option>
                        <option value="?sort=stock" <?= $currentSort === 'stock' ? 'selected' : '' ?>>По наличию</option>
                        <option value="?sort=name" <?= $currentSort === 'name' ? 'selected' : '' ?>>По названию</option>
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
                    "INCLUDE_SUBSECTIONS" => "Y",
                    "ELEMENT_SORT_FIELD"  => $sortField,
                    "ELEMENT_SORT_ORDER"  => $sortOrder,
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

            <?php // Описание раздела из админки (поле «Описание» раздела инфоблока) — только на первой странице листинга. ?>
            <?php if ($pageNum <= 1 && trim(strip_tags((string)($arSection['~DESCRIPTION'] ?? ''))) !== ''): ?>
                <div class="catalog-seo-text"><?= $arSection['~DESCRIPTION'] ?></div>
            <?php endif; ?>

        <?php else: ?>
            <!-- ===== КОРЕНЬ КАТАЛОГА ===== -->
            <?php
            $topSections = CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $effectiveRootId, 'ACTIVE' => 'Y'],
                false,
                ['ID', 'NAME', 'CODE', 'PICTURE']
            );
            $topHtml = '';
            while ($top = $topSections->GetNext()) {
                $topUrl = $catalogPrefix . $top['CODE'] . '/';
                $imgTag = '';
                if (!empty($top['PICTURE'])) {
                    $imgPath = CFile::GetPath($top['PICTURE']);
                    $imgTag = '<img src="' . $imgPath . '" alt="' . htmlspecialchars($top['NAME']) . '" style="max-height:60px;">';
                }
                $topHtml .= '
                <a href="' . $topUrl . '" class="category-card">
                    <span class="category-card__icon">' . ($imgTag ?: '<svg class="icon"><use href="#icon-folder"></use></svg>') . '</span>
                    <span class="category-card__name">' . $top['NAME'] . '</span>
                </a>';
            }
            ?>
            <div class="section-header">
                <h2 class="section-title"><svg class="icon"><use href="#icon-box"></use></svg> Каталог товаров</h2>
            </div>
            <div class="categories-grid">
                <?= $topHtml ?>
            </div>

            <div class="section-header mt-20">
                <h2 class="section-title"><svg class="icon"><use href="#icon-star"></use></svg> Все товары</h2>
            </div>
            <div class="catalog-toolbar">
                <span class="catalog-toolbar__count">Товары</span>
                <div class="catalog-toolbar__sort">
                    <select>
                        <option value="?sort=popular" <?= $currentSort === 'popular' ? 'selected' : '' ?>>По популярности</option>
                        <option value="?sort=price_asc" <?= $currentSort === 'price_asc' ? 'selected' : '' ?>>Цена ↑</option>
                        <option value="?sort=price_desc" <?= $currentSort === 'price_desc' ? 'selected' : '' ?>>Цена ↓</option>
                        <option value="?sort=stock" <?= $currentSort === 'stock' ? 'selected' : '' ?>>По наличию</option>
                        <option value="?sort=name" <?= $currentSort === 'name' ? 'selected' : '' ?>>По названию</option>
                    </select>
                </div>
            </div>
            <?php $APPLICATION->IncludeComponent(
                "bitrix:catalog.section",
                "lider_style",
                array(
                    "IBLOCK_TYPE"       => "1c_catalog",
                    "IBLOCK_ID"         => $iblockId,
                    "INCLUDE_SUBSECTIONS" => "Y",
                    "SHOW_ALL_WO_SECTION" => "Y",
                    "ELEMENT_SORT_FIELD"  => $sortField,
                    "ELEMENT_SORT_ORDER"  => $sortOrder,
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
    <?php
    $mainHtml = ob_get_clean();
    if ($isAjax) {
        ob_end_clean();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['filter' => $sidebarHtml, 'results' => $mainHtml], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo $mainHtml;
    ?>
    </div><!-- /catalog-main -->
</div><!-- /catalog-layout -->
<?php else: ?>
    </div><!-- /container -->
<?php endif; ?>

<?php
if ($isAjax) {
    // AJAX-запрос на страницу товара (нет сайдбара/main) — отдаём пустой ответ вместо HTML
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['filter' => '', 'results' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
