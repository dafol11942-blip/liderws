<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");

// Обрабатываем прямое добавление (если AJAX не сработал)
if ($_REQUEST['action'] === 'ADD2BASKET' && !empty($_REQUEST['id'])) {
    if (CModule::IncludeModule('sale')) {
        $productId = (int)$_REQUEST['id'];
        $quantity  = (int)($_REQUEST['quantity'] ?? 1);
        if ($quantity <= 0) $quantity = 1;

        try {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getId(),
                \Bitrix\Main\Context::getCurrent()->getSite()
            );

            $existItem = null;
            foreach ($basket as $basketItem) {
                if ($basketItem->getProductId() == $productId) {
                    $existItem = $basketItem;
                    break;
                }
            }

            if ($existItem) {
                $existItem->setField('QUANTITY', $existItem->getQuantity() + $quantity);
            } else {
                $item = $basket->createItem('catalog', $productId);
                $item->setFields([
                    'QUANTITY'               => $quantity,
                    'CURRENCY'               => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                    'LID'                    => \Bitrix\Main\Context::getCurrent()->getSite(),
                    'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
                ]);
            }

            $basket->save();

            // Редирект на чистую корзину без параметров
            LocalRedirect('/personal/cart/');
        } catch (\Exception $e) {
            // Ошибка — просто покажем корзину
        }
    }
}
?>

<div class="basket-page">
    <?php $APPLICATION->IncludeComponent(
        "bitrix:sale.basket.basket",
        ".default",
        array(
            "PATH_TO_ORDER" => "/personal/order/",
            "HIDE_COUPON" => "N",
            "COLUMNS_LIST" => array("NAME", "PRICE", "QUANTITY", "SUM", "DELETE"),
            "QUANTITY_FLOAT" => "N",
            "PRICE_VAT_SHOW_VALUE" => "N",
            "SET_TITLE" => "N",
        ),
        false
    ); ?>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>