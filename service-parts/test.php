<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Тест");

echo "<p>=== Тест компонента ===</p>";

$APPLICATION->IncludeComponent(
    "mycompany:auto.to.catalog",
    ".default",
    ['PAGE_MODE' => 'full'],
    false
);

echo "<p>=== Конец ===</p>";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
