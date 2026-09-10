<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/require_phone_auth.php");
$APPLICATION->SetPageProperty("title", "История заказов — личный кабинет ЛИДЕР");
$APPLICATION->SetTitle("История заказов");

// Если активен любой фильтр из шапки списка (дата/статус/поставщик/артикул) —
// фильтруем на всём доступном наборе заказов, а не только внутри текущей
// страницы пагинации. Пагинация ядрового компонента об этих фильтрах не знает.
$hasOrderFilters = ($_GET['q'] ?? '') !== '' || ($_GET['date_from'] ?? '') !== ''
    || ($_GET['date_to'] ?? '') !== '' || ($_GET['status'] ?? '') !== '' || ($_GET['supplier'] ?? '') !== '';

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
                "ORDERS_PER_PAGE" => $hasOrderFilters ? "1000" : "10",
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
