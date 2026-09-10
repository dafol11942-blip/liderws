<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Запчасти для ТО по автомобилю — подбор онлайн | ЛИДЕР Елабуга");
$APPLICATION->SetPageProperty("description", "Подберите запчасти для планового ТО по марке, модели и модификации автомобиля. Масла, фильтры, свечи, колодки и другие детали с доставкой по Елабуге.");
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
