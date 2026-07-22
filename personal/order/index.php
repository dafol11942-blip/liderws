<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Оформление заказа"); ?>

<div class="checkout-page">
    <?php $APPLICATION->IncludeComponent(
        "bitrix:sale.order.ajax",
        ".default",
        array(
            "PAY_FROM_ACCOUNT" => "N",
            "COUNT_DELIVERY_TAX" => "N",
            "ALLOW_AUTO_REGISTER" => "Y",
            "SEND_NEW_USER_NOTIFY" => "Y",
            "DELIVERY_NO_AJAX" => "N",
            "TEMPLATE_LOCATION" => "popup",
            "PATH_TO_BASKET" => "/personal/cart/",
            "PATH_TO_PERSONAL" => "/personal/",
            "PATH_TO_PAYMENT" => "/personal/order/payment/",
            "SET_TITLE" => "N",
        ),
        false
    ); ?>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>