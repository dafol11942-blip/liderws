<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('catalog');

$rsProducts = CCatalogProduct::GetList([], [], false, false, ['ID', 'QUANTITY', 'AVAILABLE']);
$updated = 0;

while ($arProduct = $rsProducts->Fetch()) {
    $qty = (int)$arProduct['QUANTITY'];
    $available = $arProduct['AVAILABLE'];

    $shouldBe = ($qty > 0) ? 'Y' : 'N';

    if ($available !== $shouldBe) {
        CCatalogProduct::Update($arProduct['ID'], ['AVAILABLE' => $shouldBe]);
        $updated++;
    }
}

echo "Исправлено AVAILABLE: $updated товаров\n";
