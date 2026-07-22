<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');

$iblockId = 42;
$updated = 0;
$skipped = 0;

// Получаем все активные свойства
$res = CIBlockProperty::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'ACTIVE' => 'Y',
]);

$ibp = new CIBlockProperty;

while ($prop = $res->GetNext()) {
    // Только списки (L) и числа (N), исключаем IN_STOCK
    if (!in_array($prop['PROPERTY_TYPE'], ['L', 'N'])) continue;
    if ($prop['CODE'] === 'IN_STOCK') {
        $skipped++;
        continue;
    }

    $result = $ibp->Update($prop['ID'], ['SMART_FILTER' => 'Y']);
    if ($result) {
        $updated++;
    } else {
        echo "Ошибка: {$prop['CODE']} — {$ibp->LAST_ERROR}\n";
    }
}

echo "\nГотово! Включено: $updated, пропущено (IN_STOCK): $skipped\n";
