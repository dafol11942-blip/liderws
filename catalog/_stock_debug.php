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
echo "Всего активных товаров в инфоблоке {$iblockId}: {$total}\n";

global $DB;

$rs = $DB->Query("SELECT COUNT(DISTINCT PRODUCT_ID) AS CNT FROM b_catalog_store_product");
$row = $rs->Fetch();
echo "Товаров, у которых ЕСТЬ хоть одна строка в b_catalog_store_product: " . (int)$row['CNT'] . "\n";

$rs = $DB->Query("SELECT COUNT(*) AS CNT FROM (SELECT PRODUCT_ID FROM b_catalog_store_product GROUP BY PRODUCT_ID HAVING SUM(AMOUNT) > 0) t");
$row = $rs->Fetch();
echo "Товаров с реальным остатком (SUM(AMOUNT) > 0) по b_catalog_store_product: " . (int)$row['CNT'] . "\n";

$rs = $DB->Query("SELECT COUNT(*) AS CNT FROM b_catalog_product WHERE QUANTITY > 0");
$row = $rs->Fetch();
echo "Товаров с CATALOG_QUANTITY > 0 (b_catalog_product.QUANTITY): " . (int)$row['CNT'] . "\n";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
