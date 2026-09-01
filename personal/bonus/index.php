<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Бонусная программа"); ?>

<div class="lk-layout">
    <?php $lkNavActive = 'bonus'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
    <div class="lk-content">
        <h2><svg class="icon"><use href="#icon-gift"></use></svg> Бонусная программа</h2>
        <div class="empty-state">
            <div class="empty-state__icon"><svg class="icon"><use href="#icon-gift"></use></svg></div>
            <h3>Скоро появится</h3>
            <p>Бонусная программа находится в разработке</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
