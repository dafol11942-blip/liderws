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
        // ВАЖНО: COMPATIBLE_MODE=Y — не трогать без проверки вживую.
        // Отключение (N) меняет саму форму $arResult при обычном GET —
        // пропадают подставленные данные профиля (ФИО/телефон/email) и
        // почему-то снова появляется поле "Город доставки" (должно быть
        // скрыто, см. order_location_handler.php). Проверено на проде
        // 24.09.2026 — сломало страницу оформления, откачено обратно.
        //
        // Отдельная, ещё не решённая проблема: при Y обычный (не-AJAX) POST
        // формы обрабатывается ШТАТНЫМ движком компонента как оформление
        // заказа — он создаёт заказ и очищает корзину ДО того, как
        // выполнится наш local/php_interface/order_create_handler.php
        // (шаблон подключает его в начале, но движок к этому моменту уже
        // всё сохранил сам). В обход уходят согласия (невозврат, 152-ФЗ),
        // окно оплаты под заказ у поставщика и dispatchSupplierOrders() —
        // реальная отправка заказа поставщику. Нужен другой способ не дать
        // штатному движку сохранить заказ (не через COMPATIBLE_MODE).
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
