<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/');
    die();
}

$backurl = (string)($_REQUEST['backurl'] ?? '/personal/');
if ($backurl === '' || $backurl[0] !== '/') {
    $backurl = '/personal/';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $name = trim((string)($_POST['NAME'] ?? ''));
    $lastName = trim((string)($_POST['LAST_NAME'] ?? ''));
    $email = trim((string)($_POST['EMAIL'] ?? ''));

    if ($name === '' || $lastName === '') {
        $error = 'Укажите имя и фамилию';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите корректный email';
    } else {
        $user = new CUser();
        $ok = $user->Update($USER->GetID(), [
            'NAME'      => $name,
            'LAST_NAME' => $lastName,
            'EMAIL'     => $email,
        ]);
        if ($ok) {
            LocalRedirect($backurl);
            die();
        }
        $error = $user->LAST_ERROR ?: 'Не удалось сохранить данные';
    }
}

$arCurrentUser = \CUser::GetByID($USER->GetID())->Fetch();

$APPLICATION->SetTitle("Завершение регистрации");
?>

<div class="container auth-page">
    <div class="auth-card">
        <h2>Завершите регистрацию</h2>
        <p class="auth-card__hint">Укажите имя, фамилию и email — они нужны для оформления заказов и уведомлений</p>

        <?php if ($error): ?>
            <div class="auth-card__error" style="margin-bottom:16px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="backurl" value="<?= htmlspecialchars($backurl) ?>">

            <div class="form-field">
                <label>Имя <span class="required-mark">*</span></label>
                <input type="text" name="NAME" value="<?= htmlspecialchars($arCurrentUser['NAME'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label>Фамилия <span class="required-mark">*</span></label>
                <input type="text" name="LAST_NAME" value="<?= htmlspecialchars($arCurrentUser['LAST_NAME'] ?? '') ?>" required>
            </div>
            <div class="form-field">
                <label>Email <span class="required-mark">*</span></label>
                <input type="email" name="EMAIL" value="<?= htmlspecialchars($arCurrentUser['EMAIL'] ?? '') ?>" required>
            </div>

            <button type="submit" class="btn btn--primary">Продолжить</button>
        </form>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
