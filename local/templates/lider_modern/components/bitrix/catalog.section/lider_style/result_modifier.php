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

// Артикул/бренд в $item['PROPERTIES'] бывают пустыми: у части товаров
// (в т.ч. аналогов в поиске по своему складу) CML2_ARTICLE/CML2_MANUFACTURER
// не заполнены, а данные лежат в свойствах "Артикул"/"Бренд", заведённых
// вручную без CML2_-кода (их нет в PROPERTY_CODE компонента). Дочитываем
// все свойства такого товара напрямую и подставляем найденное.
$propNameAliases = ['CML2_ARTICLE' => 'артикул', 'CML2_MANUFACTURER' => 'бренд'];
foreach ($arResult['ITEMS'] as $key => $item) {
    $missing = [];
    foreach ($propNameAliases as $code => $name) {
        $value = $item['PROPERTIES'][$code]['VALUE'] ?? '';
        if (is_array($value)) $value = reset($value);
        if (trim((string)$value) === '') $missing[$code] = $name;
    }
    if (!$missing) continue;

    $found = [];
    $rsProps = CIBlockElement::GetProperty((int)$item['IBLOCK_ID'], (int)$item['ID'], ['sort' => 'asc'], ['EMPTY' => 'N']);
    while ($prop = $rsProps->Fetch()) {
        $value = trim((string)($prop['VALUE'] ?? ''));
        if ($value === '') continue;
        $propName = mb_strtolower(trim((string)$prop['NAME']));
        foreach ($missing as $code => $name) {
            if (isset($found[$code])) continue;
            if ($prop['CODE'] === $code || $propName === $name) $found[$code] = $value;
        }
    }
    foreach ($found as $code => $value) {
        $arResult['ITEMS'][$key]['PROPERTIES'][$code]['VALUE'] = $value;
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
