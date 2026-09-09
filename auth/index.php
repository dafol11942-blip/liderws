<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

global $USER;

$backurl = (string)($_REQUEST['backurl'] ?? '/personal/');
if ($backurl === '' || $backurl[0] !== '/') {
    $backurl = '/personal/';
}

if ($USER->IsAuthorized()) {
    LocalRedirect($backurl);
    die();
}

$APPLICATION->SetTitle("Авторизация");
?>

<div class="container" style="max-width:480px;margin:40px auto;">
    <h2>Вход по номеру телефона</h2>
    <div id="mobileid-login-widget"></div>
    <div id="mobileid-login-error" style="display:none;background:#fff0f0;border:1px solid #f5c6cb;color:#721c24;padding:12px 16px;border-radius:8px;margin-top:16px;"></div>
</div>

<script src="https://cdn.smsaero.ru/mid-widget/1/mobileid-widget.min.js" onerror="document.getElementById('mobileid-login-error').textContent='Не удалось загрузить скрипт виджета авторизации (cdn.smsaero.ru недоступен)'; document.getElementById('mobileid-login-error').style.display='block';"></script>
<script>
(function () {
    var backurl = <?= json_encode($backurl) ?>;
    var errorBox = document.getElementById('mobileid-login-error');

    function showError(text) {
        errorBox.textContent = text;
        errorBox.style.display = 'block';
    }

    if (typeof MobileIDWidget === 'undefined') {
        showError('Виджет авторизации не загрузился. Обновите страницу или попробуйте позже.');
        return;
    }

    var widget;
    try {
        widget = new MobileIDWidget({
        tokenUrl: '/ajax/mobileid_token.php',
        resultView: 'text',
        allowChangePhone: true,
        input: {
            autoSubmitOtp: false,
            otpLength: 4
        },
        texts: {
            phoneLabel: 'Номер телефона',
            phonePlaceholder: '+7 (___) ___-__-__',
            submitPhone: 'Получить код',
            otpLabel: 'Введите код из SMS',
            otpPlaceholder: '• • • •',
            submitOtp: 'Подтвердить',
            back: 'Изменить номер',
            pendingText: 'Отправляем запрос...',
            successTitle: 'Номер подтверждён',
            rejectedTitle: 'Верификация отклонена',
            retryBtn: 'Попробовать снова',
            rateLimitTitle: 'Превышен лимит запросов'
        },
        onVerified: function (data) {
            fetch('/ajax/mobileid_siteverify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: data.session_id,
                    verify_token: data.verify_token
                })
            })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    if (result.success) {
                        window.location.href = backurl;
                    } else {
                        showError(result.message || 'Не удалось подтвердить номер');
                    }
                })
                .catch(function () {
                    showError('Ошибка соединения с сервером');
                });
        },
        onRejected: function () {
            showError('Верификация отклонена, попробуйте снова');
        },
        onError: function (err) {
            showError((err && err.message) || 'Ошибка виджета авторизации');
        },
        onRateLimit: function () {
            showError('Превышен лимит запросов, попробуйте позже');
        }
        });
        widget.mount('#mobileid-login-widget');
    } catch (e) {
        console.error('MobileIDWidget init error', e);
        showError('Ошибка инициализации виджета авторизации: ' + e.message);
    }
})();
</script>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
