<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("История заказов"); ?>

<div class="lk-layout">
    <aside class="lk-sidebar">
        <div class="lk-user-block">
            <div class="lk-user-avatar">
                <?= mb_substr($USER->GetFullName() ?: $USER->GetLogin(), 0, 1) ?>
            </div>
            <div class="lk-user-name"><?= $USER->GetFullName() ?: $USER->GetLogin() ?></div>
        </div>
        <nav class="lk-nav">
            <a href="/personal/">👤 Профиль</a>
            <a href="/personal/orders/" class="active">📦 История заказов</a>
            <a href="/personal/favorites/">⭐ Избранное</a>
            <a href="/personal/bonus/">🎁 Бонусная программа</a>
            <a href="/?logout=yes" class="lk-nav--logout">🚪 Выйти</a>
        </nav>
    </aside>
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
