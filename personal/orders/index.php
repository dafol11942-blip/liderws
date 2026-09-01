<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("История заказов"); ?>

<div class="lk-layout">
    <?php $lkNavActive = 'orders'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
    <div class="lk-content">
        <h2>Мои заказы</h2>
        <?php $APPLICATION->IncludeComponent(
            "bitrix:sale.personal.order",
            "modern",
            array(
                "SEF_MODE" => "N",
                "ORDERS_PER_PAGE" => "10",
                "PATH_TO_PAYMENT" => "/personal/order/payment/",
                "PATH_TO_BASKET" => "/personal/cart/",
                "SET_TITLE" => "N",
                "HISTORIC_STATUSES" => array("___SHOW_ALL___"),
                "CACHE_TYPE" => "N",
                "DEFAULT_SORT" => "DATE_INSERT",
            ),
            false
        ); ?>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
