<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Каталог автозапчастей"); ?>

<div class="breadcrumbs">
<?php
$APPLICATION->IncludeComponent(
    "bitrix:breadcrumb",
    "",
    [
        "START_FROM" => "0",
        "PATH" => "",
        "SITE_ID" => SITE_ID,
    ],
    false
);
?>
</div>

<div class="breadcrumbs">
    <ul>
        <?php
        $arChain = $APPLICATION->GetNavChain($APPLICATION->GetCurDir());
        $last = count($arChain) - 1;
        foreach ($arChain as $i => $arItem):
            if ($i < $last): ?>
                <li><a href="<?= $arItem['LINK'] ?>"><?= htmlspecialchars($arItem['TITLE']) ?></a></li>
            <?php else: ?>
                <li><?= htmlspecialchars($arItem['TITLE']) ?></li>
            <?php endif;
        endforeach;
        ?>
    </ul>
</div>

<div class="catalog-page">
    <!-- Боковой фильтр (пока заглушка, позже подключим bitrix:catalog.filter) -->
    <aside class="catalog-sidebar">
        <h3 class="catalog-sidebar__title">Фильтр</h3>
        <?php $APPLICATION->IncludeComponent(
            "bitrix:catalog.smart.filter",
            "lider_style",
            [
                "IBLOCK_TYPE" => "catalog",
                "IBLOCK_ID" => "1",
                "SECTION_ID" => "",
                "FILTER_NAME" => "arrFilter",
                "PRICE_CODE" => ["BASE"],
                "CACHE_TYPE" => "A",
                "CACHE_TIME" => "36000000",
                "SAVE_IN_SESSION" => "N",
                "PAGER_PARAMS_NAME" => "arrPager",
            ],
            false
        ); ?>
    </aside>

    <!-- Правая часть -->
    <div class="catalog-main">
        <!-- Панель сортировки -->
        <div class="catalog-toolbar">
            <div class="catalog-toolbar__sort">
                Сортировка:
                <select onchange="window.location.href=this.value">
                    <option value="?sort=popular">По популярности</option>
                    <option value="?sort=price_asc">Цена ↑</option>
                    <option value="?sort=price_desc">Цена ↓</option>
                    <option value="?sort=name">По названию</option>
                </select>
            </div>
        </div>

        <!-- Товары -->
        <?php $APPLICATION->IncludeComponent(
            "bitrix:catalog.section",
            "lider_style",
            [
                "IBLOCK_TYPE" => "catalog",
                "IBLOCK_ID" => "1",
                "SECTION_ID" => $_REQUEST["SECTION_ID"] ?? "",
                "SECTION_CODE" => "",
                "ELEMENT_SORT_FIELD" => $_GET["sort"] === "price_asc" ? "catalog_PRICE_1" : ($_GET["sort"] === "name" ? "name" : "sort"),
                "ELEMENT_SORT_ORDER" => $_GET["sort"] === "price_desc" ? "desc" : "asc",
                "FILTER_NAME" => "arrFilter",
                "INCLUDE_SUBSECTIONS" => "Y",
                "SHOW_ALL_WO_SECTION" => "Y",
                "HIDE_NOT_AVAILABLE" => "Y",            // ← добавить
                "HIDE_NOT_AVAILABLE_OFFERS" => "Y", 
                "PAGE_ELEMENT_COUNT" => "12",
                "LINE_ELEMENT_COUNT" => "4",
                "PROPERTY_CODE" => ["ARTICLE", "BRAND"],
                "OFFERS_FIELD_CODE" => [],
                "OFFERS_PROPERTY_CODE" => [],
                "OFFERS_SORT_FIELD" => "sort",
                "OFFERS_SORT_ORDER" => "asc",
                "DISPLAY_TOP_PAGER" => "N",
                "DISPLAY_BOTTOM_PAGER" => "Y",
                "PAGER_TITLE" => "Товары",
                "PAGER_TEMPLATE" => ".default",
                "CACHE_TYPE" => "A",
                "CACHE_TIME" => "36000000",
            ],
            false
        ); ?>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>