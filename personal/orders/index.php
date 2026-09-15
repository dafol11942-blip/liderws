<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/require_phone_auth.php");
$APPLICATION->SetPageProperty("title", "История заказов — личный кабинет ЛИДЕР");
$APPLICATION->SetTitle("История заказов");

// Ядро bitrix:sale.personal.order.list само добавляет 'CANCELED' => 'N' в фильтр,
// если в запросе нет show_all=Y (см. class.php: $showAll === 'N' — ветка истории/
// отмены), причём независимо от параметра HISTORIC_STATUSES ниже. Без этого
// отменённые заказы (в т.ч. автоотменённые по неоплате, см.
// local/php_interface/cron/payment_hold_sweep.php) молча пропадают из списка,
// хотя шаблон modern/template.php их прекрасно умеет показывать с плашкой "Отменён".
$_REQUEST['show_all'] = 'Y';
?>

<div class="lk-layout">
    <?php $lkNavActive = 'orders'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
    <div class="lk-content">
        <h2>Мои заказы</h2>
        <?php $APPLICATION->IncludeComponent(
            "bitrix:sale.personal.order",
            "modern",
            array(
                "SEF_MODE" => "N",
                // Постраничной навигации в шаблоне нет (шапка со списком заказов
                // рендерится целиком, без "показать ещё"), поэтому грузим сразу
                // всю историю — иначе фильтр и списки "Способ доставки"/"Поставщик"
                // в шапке видели бы только последние 10 заказов, а не всю историю.
                "ORDERS_PER_PAGE" => "1000",
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
