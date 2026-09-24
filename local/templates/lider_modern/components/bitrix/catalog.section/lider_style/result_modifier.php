<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) return;

// Лицензия "Малый бизнес" не поддерживает учёт по складам, разбивка остатков
// в 1С отключена — 1С пишет только плоский QUANTITY, его и берём (как на
// карточке товара — см. её же CCatalogProduct::GetList).
CModule::IncludeModule('catalog');

$ids = array_column($arResult['ITEMS'], 'ID');
$qtyById = [];
if (!empty($ids)) {
    $rsCatalogProduct = CCatalogProduct::GetList([], ['ID' => $ids], false, false, ['ID', 'QUANTITY']);
    while ($arCatalogProduct = $rsCatalogProduct->Fetch()) {
        $qtyById[(int)$arCatalogProduct['ID']] = (int)$arCatalogProduct['QUANTITY'];
    }
}

$removedCount = 0;
foreach ($arResult['ITEMS'] as $key => $item) {
    $totalAmount = $qtyById[(int)$item['ID']] ?? 0;

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
