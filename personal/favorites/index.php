<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Избранное"); ?>

<div class="lk-layout">
    <?php $lkNavActive = 'favorites'; require $_SERVER["DOCUMENT_ROOT"] . "/local/templates/lider_modern/include/lk-sidebar.php"; ?>
    <div class="lk-content">
        <h2><svg class="icon"><use href="#icon-star"></use></svg> Избранное</h2>
        <div class="empty-state">
            <div class="empty-state__icon"><svg class="icon"><use href="#icon-star"></use></svg></div>
            <h3>Скоро появится</h3>
            <p>Раздел избранного находится в разработке</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
