<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

header('Content-Type: text/plain; charset=UTF-8');

$iblockId = 42;
global $DB;

echo "=== Свойства, похожие на «Объём» (полные строки b_iblock_property) ===\n";
$rs = $DB->Query("SELECT * FROM b_iblock_property WHERE IBLOCK_ID = {$iblockId} AND NAME LIKE '%бъ%'");
$propIds = [];
while ($row = $rs->Fetch()) {
    print_r($row);
    echo "---\n";
    $propIds[] = $row['ID'];
}

if ($propIds) {
    $rsSec = $DB->Query("SELECT ID, NAME, CODE, LEFT_MARGIN, RIGHT_MARGIN FROM b_iblock_section WHERE IBLOCK_ID = {$iblockId} AND CODE = 'masla_i_tekhnicheskie_zhidkosti'");
    $sec = $rsSec->Fetch();
    echo "\n=== Раздел «Масла и технические жидкости» ===\n";
    print_r($sec);

    foreach ($propIds as $pid) {
        echo "\n=== Значения свойства ID={$pid} среди активных товаров раздела (включая подразделы) ===\n";
        if ($sec) {
            $rsVals = $DB->Query("
                SELECT pe.VALUE, pe.VALUE_NUM, COUNT(*) AS CNT
                FROM b_iblock_element_property pe
                INNER JOIN b_iblock_element e ON e.ID = pe.IBLOCK_ELEMENT_ID AND e.ACTIVE = 'Y'
                INNER JOIN b_iblock_section_element se ON se.IBLOCK_ELEMENT_ID = e.ID
                INNER JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
                WHERE pe.IBLOCK_PROPERTY_ID = {$pid}
                  AND s.LEFT_MARGIN >= {$sec['LEFT_MARGIN']} AND s.RIGHT_MARGIN <= {$sec['RIGHT_MARGIN']}
                GROUP BY pe.VALUE, pe.VALUE_NUM
                ORDER BY CNT DESC
                LIMIT 20
            ");
            while ($v = $rsVals->Fetch()) {
                echo "VALUE=" . var_export($v['VALUE'], true) . " VALUE_NUM=" . var_export($v['VALUE_NUM'], true) . " CNT={$v['CNT']}\n";
            }
        }
    }
} else {
    echo "(свойство с «объ» в названии не найдено в этом инфоблоке)\n";
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
