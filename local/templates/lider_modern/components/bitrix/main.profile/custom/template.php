<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php if (!empty($arResult['strProfileError'])): ?>
    <div style="background:#fff0f0;border:1px solid #f5c6cb;color:#721c24;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;">
        <?= $arResult['strProfileError'] ?>
    </div>
<?php endif; ?>

<?php if (!empty($arResult['OK_MESSAGE'])): ?>
    <div style="background:#f0fff4;border:1px solid #c3e6cb;color:#155724;padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;">
        <?= $arResult['OK_MESSAGE'] ?>
    </div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data" class="profile-form">
    <?= bitrix_sessid_post() ?>

    <div class="form-row">
        <div class="form-field">
            <label>Имя <span class="required-mark">*</span></label>
            <input type="text" name="NAME" value="<?= htmlspecialchars($arResult['arUser']['NAME']) ?>" required>
        </div>
        <div class="form-field">
            <label>Фамилия</label>
            <input type="text" name="LAST_NAME" value="<?= htmlspecialchars($arResult['arUser']['LAST_NAME']) ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>Email <span class="required-mark">*</span></label>
            <input type="email" name="EMAIL" value="<?= htmlspecialchars($arResult['arUser']['EMAIL']) ?>" required>
        </div>
        <div class="form-field">
            <label>Телефон</label>
            <input type="tel" name="PERSONAL_PHONE" value="<?= htmlspecialchars($arResult['arUser']['PERSONAL_PHONE']) ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field">
            <label>Новый пароль</label>
            <input type="password" name="NEW_PASSWORD" placeholder="Оставьте пустым, чтобы не менять">
        </div>
        <div class="form-field">
            <label>Подтверждение пароля</label>
            <input type="password" name="NEW_PASSWORD_CONFIRM" placeholder="Повторите новый пароль">
        </div>
    </div>

    <input type="hidden" name="save" value="Y">

    <button type="submit" class="btn btn--primary">💾 Сохранить изменения</button>
</form>
