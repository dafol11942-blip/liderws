<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('catalog');

global $DB;

// Получаем сумму остатков по складам для каждого товара
$storeSums = [];
$res = $DB->Query("SELECT PRODUCT_ID, SUM(AMOUNT) as TOTAL FROM b_catalog_store_product GROUP BY PRODUCT_ID");
while ($row = $res->Fetch()) {
    $storeSums[(int)$row['PRODUCT_ID']] = (int)$row['TOTAL'];
}

echo "Товаров с записями на складах: " . count($storeSums) . "\n";

// Проходим по всем товарам каталога
$rsProducts = CCatalogProduct::GetList([], [], false, false, ['ID', 'QUANTITY']);
$updated = 0;
$zeroed = 0;
$skipped = 0;

while ($arProduct = $rsProducts->Fetch()) {
    $pid = (int)$arProduct['ID'];
    $currentQty = (int)$arProduct['QUANTITY'];

    if (isset($storeSums[$pid])) {
        // Есть записи на складах — ставим сумму
        $newQty = $storeSums[$pid];
    } else {
        // Нет записей — обнуляем
        $newQty = 0;
    }

    if ($currentQty !== $newQty) {
        CCatalogProduct::Update($pid, ['QUANTITY' => $newQty]);
        if ($newQty === 0) {
            $zeroed++;
        } else {
            $updated++;
        }
    } else {
        $skipped++;
    }
}

echo "\n=== ГОТОВО ===\n";
echo "Обновлено (изменено количество): $updated\n";
echo "Обнулено (не было на складах): $zeroed\n";
echo "Пропущено (без изменений): $skipped\n";
