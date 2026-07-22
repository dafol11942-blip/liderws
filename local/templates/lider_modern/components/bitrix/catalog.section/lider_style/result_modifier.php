<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) return;

$removedCount = 0;
foreach ($arResult['ITEMS'] as $key => $item) {
    $qty = (int)($item['CATALOG_QUANTITY'] ?? 0);
    $canBuyZero = ($item['CATALOG_CAN_BUY_ZERO'] ?? 'N') === 'Y';
    if ($qty <= 0 && !$canBuyZero) {
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
