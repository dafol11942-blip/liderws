<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Личный кабинет"); ?>

<div class="lk-layout">
    <aside class="lk-sidebar">
        <div class="lk-user-block">
            <div class="lk-user-avatar">
                <?= mb_substr($USER->GetFullName() ?: $USER->GetLogin(), 0, 1) ?>
            </div>
            <div class="lk-user-name"><?= $USER->GetFullName() ?: $USER->GetLogin() ?></div>
        </div>
        <nav class="lk-nav">
            <a href="/personal/" class="active">👤 Профиль</a>
            <a href="/personal/orders/">📦 История заказов</a>
            <a href="/personal/favorites/">⭐ Избранное</a>
            <a href="/personal/bonus/">🎁 Бонусная программа</a>
            <a href="/?logout=yes" class="lk-nav--logout">🚪 Выйти</a>
        </nav>
    </aside>
    <div class="lk-content">
        <h2>Личные данные</h2>
        <?php $APPLICATION->IncludeComponent(
            "bitrix:main.profile",
            "custom",
            array(
                "SET_TITLE" => "N",
                "USER_PROPERTY" => array("PERSONAL_PHONE"),
                "SEND_INFO" => "N",
                "CHECK_RIGHTS" => "N",
            ),
            false
        ); ?>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
