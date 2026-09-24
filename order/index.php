<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/require_phone_auth.php");
$APPLICATION->SetPageProperty("title", "Оформление заказа — ЛИДЕР, автозапчасти в Елабуге");
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
        // COMPATIBLE_MODE=Y заставляет штатный движок компонента самому
        // обрабатывать обычный (не-AJAX) POST формы как оформление заказа —
        // он создаёт заказ и очищает корзину ДО того, как выполнится наш
        // local/php_interface/order_create_handler.php (шаблон подключает
        // его в самом начале, но сам движок к этому моменту уже отработал).
        // В результате заказ создаётся штатным Bitrix-заказом в обход ВСЕЙ
        // нашей логики: согласия на невозврат, 152-ФЗ, окна оплаты под
        // заказ у поставщика и — главное — реальной отправки заказа
        // поставщикам (dispatchSupplierOrders()). Форма на сайте обычная
        // HTML-форма без своего JS-сабмита, так что COMPATIBLE_MODE нам не
        // нужен: оформление всегда идёт через order_create_handler.php.
        "COMPATIBLE_MODE" => "N",
        "ALLOW_NEW_PROFILE" => "N",
        "SHOW_COUPONS" => "N",
        "USER_CONSENT" => "N",
        "SHOW_TOTAL_ORDER_BUTTON" => "N",
    ),
    false
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
