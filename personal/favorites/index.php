<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Избранное"); ?>

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
            <a href="/personal/orders/">📦 История заказов</a>
            <a href="/personal/favorites/" class="active">⭐ Избранное</a>
            <a href="/personal/bonus/">🎁 Бонусная программа</a>
            <a href="/?logout=yes" class="lk-nav--logout">🚪 Выйти</a>
        </nav>
    </aside>
    <div class="lk-content">
        <h2>⭐ Избранное</h2>
        <div class="empty-state">
            <div class="empty-state__icon">⭐</div>
            <h3>Скоро появится</h3>
            <p>Раздел избранного находится в разработке</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
