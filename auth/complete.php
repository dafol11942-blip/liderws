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

<div class="container" style="max-width:480px;margin:40px auto;">
    <h2>Завершите регистрацию</h2>
    <p>Укажите имя, фамилию и email — они нужны для оформления заказов и уведомлений.</p>

    <?php if ($error): ?>
        <div style="background:#fff0f0;border:1px solid #f5c6cb;color:#721c24;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="backurl" value="<?= htmlspecialchars($backurl) ?>">

        <div style="margin-bottom:12px;">
            <label>Имя *</label><br>
            <input type="text" name="NAME" value="<?= htmlspecialchars($arCurrentUser['NAME'] ?? '') ?>" required style="width:100%;padding:8px;">
        </div>
        <div style="margin-bottom:12px;">
            <label>Фамилия *</label><br>
            <input type="text" name="LAST_NAME" value="<?= htmlspecialchars($arCurrentUser['LAST_NAME'] ?? '') ?>" required style="width:100%;padding:8px;">
        </div>
        <div style="margin-bottom:12px;">
            <label>Email *</label><br>
            <input type="email" name="EMAIL" value="<?= htmlspecialchars($arCurrentUser['EMAIL'] ?? '') ?>" required style="width:100%;padding:8px;">
        </div>

        <button type="submit" class="btn btn--primary">Продолжить</button>
    </form>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
