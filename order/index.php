<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/require_phone_auth.php");

// Наша обработка оформления заказа (согласия, окно оплаты под заказ у
// поставщика, dispatchSupplierOrders()) должна ЗАБИРАТЬ POST оформления
// заказа ДО того, как до него доберётся штатный движок компонента ниже.
// Раньше order_create_handler.php подключался только из шаблона компонента
// (после его отработки) — это работало, пока штатный движок компонента не
// мог сам сохранить заказ (ему для этого не хватало заполненного свойства
// LOCATION, см. order_location_handler.php). Как только LOCATION стало
// подставляться по умолчанию, движок компонента начал сам успешно создавать
// заказ и опустошать корзину ДО шаблона — наш обработчик впоследствии видел
// уже пустую корзину и просто молча выходил, а согласия/отправка поставщику
// не выполнялись (заказы №221, №222 — без статуса от поставщика).
// Подключаем здесь, ДО IncludeComponent: на "confirmorder=Y" наш код сам
// создаёт заказ и делает LocalRedirect()+exit, так что компонент ниже для
// этого запроса вообще не выполняется. На обычном GET/без этого поля код
// ничего не делает, просто объявляет функции (используются в шаблоне).
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/order_create_handler.php");

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
