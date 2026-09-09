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

<div class="container auth-page">
    <div class="auth-card">
        <h2>Вход по номеру телефона</h2>
        <p class="auth-card__hint">Отправим код подтверждения по SMS</p>
        <div id="mobileid-login-widget"></div>
        <div id="mobileid-login-error" class="auth-card__error" style="display:none;"></div>
    </div>
</div>

<script src="<?= SITE_TEMPLATE_PATH ?>/assets/js/mobileid-widget.min.js" onerror="document.getElementById('mobileid-login-error').textContent='Не удалось загрузить скрипт виджета авторизации'; document.getElementById('mobileid-login-error').style.display='block';"></script>
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
        theme: {
            primaryColor: '#668BEA',
            primaryHover: '#465B91',
            primaryText: '#ffffff',
            bgColor: '#ffffff',
            inputBg: '#ffffff',
            inputBorder: '#E2E2E2',
            inputBorderFocus: '#668BEA',
            inputText: '#000000',
            labelColor: '#000000',
            hintColor: '#666666',
            errorColor: '#c0392b',
            successColor: '#2f8a44',
            rejectedColor: '#c0392b',
            spinnerColor: '#668BEA',
            borderRadius: '10px',
            fontFamily: 'Nunito, sans-serif',
            fontSize: '14px',
            padding: '11px 14px',
            gapLabel: '5px',
            gap: '14px'
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
