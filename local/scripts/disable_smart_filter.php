<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');

$iblockId = 42;
$updated = 0;

$res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']);
$ibp = new CIBlockProperty;

while ($prop = $res->GetNext()) {
    $result = $ibp->Update($prop['ID'], ['SMART_FILTER' => 'N']);
    if ($result) $updated++;
}

echo "Отключено: $updated свойств\n";
