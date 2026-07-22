<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('catalog');

$rsProducts = CCatalogProduct::GetList([], ['QUANTITY' => false], false, false, ['ID', 'QUANTITY', 'TIMESTAMP_X']);
$count = 0;

while ($arProduct = $rsProducts->Fetch()) {
    // Проверяем: не обновлялся больше 30 дней И количество > 0
    $lastUpdate = strtotime($arProduct['TIMESTAMP_X']);
    $thirtyDaysAgo = strtotime('-30 days');

    if ((int)$arProduct['QUANTITY'] > 0 && $lastUpdate < $thirtyDaysAgo) {
        // Дополнительно проверяем, есть ли остатки на складах
        $dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $arProduct['ID']], false, false, ['AMOUNT']);
        $storeTotal = 0;
        while ($arStore = $dbStore->Fetch()) {
            $storeTotal += (int)$arStore['AMOUNT'];
        }

        // Если на складах 0 — обнуляем QUANTITY
        if ($storeTotal <= 0) {
            CCatalogProduct::Update($arProduct['ID'], ['QUANTITY' => 0]);
            $count++;
            echo "Обнулён товар ID: {$arProduct['ID']}, было: {$arProduct['QUANTITY']}\n";
        }
    }
}

echo "\nГотово. Обнулено товаров: $count\n";
