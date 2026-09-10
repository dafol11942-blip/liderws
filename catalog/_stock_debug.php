<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

header('Content-Type: text/plain; charset=UTF-8');

$iblockId = 42;

$rsTotal = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], ['IBLOCK_ID'], false, ['ID']);
$total = 0;
if ($arTotal = $rsTotal->Fetch()) {
    $total = (int)$arTotal['CNT'];
}
echo "Всего активных товаров в инфоблоке {$iblockId}: {$total}\n\n";

global $DB;

$rs = $DB->Query("
    SELECT COUNT(*) AS CNT FROM (
        SELECT sp.PRODUCT_ID
        FROM b_catalog_store_product sp
        INNER JOIN b_iblock_element e ON e.ID = sp.PRODUCT_ID AND e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'
        GROUP BY sp.PRODUCT_ID
        HAVING SUM(sp.AMOUNT) > 0
    ) t
");
$row = $rs->Fetch();
echo "Товаров ИЗ ЭТОГО ИНФОБЛОКА с реальным остатком (SUM(AMOUNT)>0): " . (int)$row['CNT'] . "\n";

$rs = $DB->Query("
    SELECT COUNT(DISTINCT sp.PRODUCT_ID) AS CNT
    FROM b_catalog_store_product sp
    INNER JOIN b_iblock_element e ON e.ID = sp.PRODUCT_ID AND e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'
");
$row = $rs->Fetch();
echo "Товаров ИЗ ЭТОГО ИНФОБЛОКА, у которых есть хоть одна строка в b_catalog_store_product: " . (int)$row['CNT'] . "\n";

$rs = $DB->Query("
    SELECT COUNT(*) AS CNT
    FROM b_iblock_element e
    WHERE e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'
    AND NOT EXISTS (SELECT 1 FROM b_catalog_store_product sp WHERE sp.PRODUCT_ID = e.ID)
");
$row = $rs->Fetch();
echo "Товаров ИЗ ЭТОГО ИНФОБЛОКА БЕЗ единой строки в b_catalog_store_product: " . (int)$row['CNT'] . "\n\n";

$rs = $DB->Query("
    SELECT COUNT(*) AS CNT
    FROM b_catalog_product cp
    INNER JOIN b_iblock_element e ON e.ID = cp.ID AND e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'
    WHERE cp.QUANTITY > 0
");
$row = $rs->Fetch();
echo "Товаров ИЗ ЭТОГО ИНФОБЛОКА с CATALOG_QUANTITY > 0: " . (int)$row['CNT'] . "\n";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
