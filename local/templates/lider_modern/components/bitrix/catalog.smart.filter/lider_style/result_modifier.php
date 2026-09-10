<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) return;

// «Бренд» должен идти в сайдбаре сразу за блоком «Категория» — выносим его
// в начало списка пунктов фильтра (порядок остальных пунктов не трогаем).
foreach ($arResult['ITEMS'] as $key => $item) {
    if (($item['CODE'] ?? '') === 'brand_lider') {
        $brandItem = $item;
        unset($arResult['ITEMS'][$key]);
        $arResult['ITEMS'] = [$key => $brandItem] + $arResult['ITEMS'];
        break;
    }
}
