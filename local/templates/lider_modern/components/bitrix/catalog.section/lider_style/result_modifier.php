<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) return;

// CATALOG_QUANTITY/CATALOG_CAN_BUY_ZERO у этого каталога не совпадают с
// реальным остатком по складам (как на карточке товара — см. её же
// CCatalogStoreProduct::GetList), поэтому наличие считаем так же, как там.
CModule::IncludeModule('catalog');

$removedCount = 0;
foreach ($arResult['ITEMS'] as $key => $item) {
    $totalAmount = 0;
    $dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $item['ID']], false, false, ['AMOUNT']);
    while ($arStore = $dbStore->Fetch()) {
        $totalAmount += (int)$arStore['AMOUNT'];
    }

    if ($totalAmount <= 0) {
        unset($arResult['ITEMS'][$key]);
        $removedCount++;
    }
}

if ($removedCount > 0 && isset($arResult['NAV_RESULT'])) {
    $nav = &$arResult['NAV_RESULT'];
    $nav->NavRecordCount = max(0, (int)$nav->NavRecordCount - $removedCount);
    $nav->NavPageCount = ceil($nav->NavRecordCount / $nav->NavPageSize);

    // Если текущая страница > максимума — редирект на последнюю
    if ($nav->NavPageNomer > $nav->NavPageCount && $nav->NavPageCount > 0) {
        $url = $APPLICATION->GetCurPageParam('PAGEN_1=' . $nav->NavPageCount, ['PAGEN_1']);
        LocalRedirect($url);
    }
}
