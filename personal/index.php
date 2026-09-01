<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Личный кабинет"); ?>

<div class="lk-layout">
    <?php $lkNavActive = 'profile'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
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
