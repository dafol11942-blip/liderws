<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('sale');

// Покажем структуру корзины через API
$basket = CSaleBasket::GetList([], [
    'FUSER_ID' => CSaleBasket::GetBasketUserID(),
    'ORDER_ID' => 'NULL',
    'LID' => SITE_ID
]);

echo '<h3>CSaleBasket (первые 3 товара):</h3><pre>';
$i = 0;
while ($item = $basket->Fetch()) {
    if ($i++ >= 3) break;
    print_r($item);
}
echo '</pre>';

// Покажем результат компонента
echo '<hr><h3>Компонент sale.basket.basket (lider_style):</h3>';
$APPLICATION->IncludeComponent(
    "bitrix:sale.basket.basket",
    "lider_style",
    array("PATH_TO_ORDER" => "/order/", "SET_TITLE" => "Y", "HIDE_COUPON" => "Y"),
    false
);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
