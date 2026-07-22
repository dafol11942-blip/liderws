<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Оформление заказа");
?>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:sale.order.ajax",
    "lider_style",
    array(
        "PAY_FROM_ACCOUNT" => "N",
        "PATH_TO_BASKET" => "/cart/",
        "PATH_TO_PERSONAL" => "/personal/",
        "PATH_TO_PAYMENT" => "/order/payment/",
        "PATH_TO_ORDER" => "/order/",
        "SET_TITLE" => "N",
        "COMPATIBLE_MODE" => "Y",
        "ALLOW_NEW_PROFILE" => "N",
        "SHOW_COUPONS" => "N",
        "USER_CONSENT" => "N",
        "SHOW_TOTAL_ORDER_BUTTON" => "N",
    ),
    false
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
