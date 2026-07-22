<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Запчасти для ТО");
$APPLICATION->AddChainItem("Запчасти для ТО", "/service-parts/");
?>

<?php
$APPLICATION->IncludeComponent(
    "mycompany:auto.to.catalog",
    ".default",
    ['PAGE_MODE' => 'full'],
    false
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
