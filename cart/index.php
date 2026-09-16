<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Корзина — ЛИДЕР, автозапчасти в Елабуге");
$APPLICATION->SetTitle("Корзина");

// Миграционная подчистка: старый механизм (action=stashUnselected, убран, см.
// ajax/basket.php) складывал снимок неотмеченных чекбоксом позиций в PHP-сессию
// при переходе на /order/. Новые снимки больше не создаются, но у кого-то ещё
// может остаться старый в текущей сессии — возвращаем его в корзину, как и
// раньше, и больше никогда не трогаем. Саму функцию можно будет удалить, когда
// станет ясно, что оставшихся снимков в сессиях не осталось.
if (function_exists('restoreStashedCartItems')) {
    restoreStashedCartItems();
}
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