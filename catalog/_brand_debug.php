<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');

header('Content-Type: text/plain; charset=UTF-8');

$iblockId = 42;

echo "=== Свойства с именем «Бренд» (b_iblock_property) ===\n";
$dbProps = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'NAME' => 'Бренд']);
$brandPropId = 0;
$brandPropCode = '';
while ($arProp = $dbProps->Fetch()) {
    echo "ID={$arProp['ID']} CODE={$arProp['CODE']} FILTRABLE={$arProp['FILTRABLE']} PROPERTY_TYPE={$arProp['PROPERTY_TYPE']} MULTIPLE={$arProp['MULTIPLE']}\n";
    if (!$brandPropId) {
        $brandPropId = (int)$arProp['ID'];
        $brandPropCode = $arProp['CODE'];
    }
}

echo "\n=== Также ищем CML2_MANUFACTURER (родное свойство 1С) ===\n";
$dbProps2 = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'CML2_MANUFACTURER']);
while ($arProp = $dbProps2->Fetch()) {
    echo "ID={$arProp['ID']} CODE={$arProp['CODE']} NAME={$arProp['NAME']} FILTRABLE={$arProp['FILTRABLE']} PROPERTY_TYPE={$arProp['PROPERTY_TYPE']}\n";
}

if ($brandPropId) {
    echo "\n=== Значения свойства ID={$brandPropId} ($brandPropCode) среди активных товаров ===\n";
    global $DB;
    $rs = $DB->Query("
        SELECT VALUE, COUNT(*) AS CNT
        FROM b_iblock_element_property ep
        INNER JOIN b_iblock_element e ON e.ID = ep.IBLOCK_ELEMENT_ID AND e.ACTIVE = 'Y' AND e.IBLOCK_ID = {$iblockId}
        WHERE ep.IBLOCK_PROPERTY_ID = {$brandPropId} AND ep.VALUE IS NOT NULL AND ep.VALUE != ''
        GROUP BY VALUE
        ORDER BY CNT DESC
        LIMIT 15
    ");
    $total = 0;
    while ($row = $rs->Fetch()) {
        echo "VALUE='{$row['VALUE']}' CNT={$row['CNT']}\n";
        $total++;
    }
    echo "(показаны топ-15 по частоте; всего активных товаров в инфоблоке — см. предыдущую диагностику)\n";
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
