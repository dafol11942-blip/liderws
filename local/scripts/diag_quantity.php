<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('catalog');
CModule::IncludeModule('iblock');

// Без фильтра — все товары
$rsProducts = CCatalogProduct::GetList([], [], false, false, ['ID', 'QUANTITY', 'TIMESTAMP_X']);
$total = 0;
$withQty = 0;
$zeroQty = 0;
$sample = [];
$sampleZero = [];

while ($arProduct = $rsProducts->Fetch()) {
    $total++;
    $qty = (int)$arProduct['QUANTITY'];
    if ($qty > 0) {
        $withQty++;
        if (count($sample) < 5) {
            $res = CIBlockElement::GetByID($arProduct['ID']);
            if ($el = $res->Fetch()) {
                $sample[] = "ID:{$arProduct['ID']} | {$el['NAME']} | QTY:$qty | Updated:{$arProduct['TIMESTAMP_X']}";
            }
        }
    } else {
        $zeroQty++;
    }
}

echo "=== ДИАГНОСТИКА ===\n";
echo "Всего товаров: $total\n";
echo "С QUANTITY > 0: $withQty\n";
echo "С QUANTITY = 0: $zeroQty\n\n";

if (!empty($sample)) {
    echo "Примеры с QTY > 0:\n";
    echo implode("\n", $sample) . "\n";
} else {
    echo "Нет товаров с QUANTITY > 0!\n";
}
