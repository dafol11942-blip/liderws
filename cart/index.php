<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");
?>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:sale.basket.basket",
    "lider_style",
    array(
        "PATH_TO_ORDER" => "/order/",
        "HIDE_COUPON" => "Y",
        "COLUMNS_LIST" => array("NAME", "ARTICLE", "PRICE", "QUANTITY", "SUM"),
        "SET_TITLE" => "Y",
        "USE_PREPAYMENT" => "N",
        "QUANTITY_FLOAT" => "N",
        "ACTION_VARIABLE" => "action",
        "USE_DYNAMIC_SCROLL" => "Y",
    ),
    false
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>