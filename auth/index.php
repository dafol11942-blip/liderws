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

        <label class="pd-consent">
            <input type="checkbox" id="auth-agree-pd" value="Y">
            <span>Я согласен(на) с условиями <a href="/soglasie/" target="_blank">Политики обработки персональных данных</a> и даю согласие на обработку моих персональных данных</span>
        </label>

        <div id="mobileid-login-widget" class="auth-widget-gated"></div>
        <div id="mobileid-login-error" class="auth-card__error" style="display:none;"></div>
    </div>
</div>

<script src="<?= SITE_TEMPLATE_PATH ?>/assets/js/mobileid-widget.min.js" onerror="document.getElementById('mobileid-login-error').textContent='Не удалось загрузить скрипт виджета авторизации'; document.getElementById('mobileid-login-error').style.display='block';"></script>
<script>
(function () {
    var backurl = <?= json_encode($backurl) ?>;
    var errorBox = document.getElementById('mobileid-login-error');
    var widgetGate = document.getElementById('mobileid-login-widget');
    var agreeCheckbox = document.getElementById('auth-agree-pd');

    function showError(text) {
        errorBox.textContent = text;
        errorBox.style.display = 'block';
    }

    // Виджет визуально заблокирован, пока не отмечено согласие на обработку
    // персональных данных — сам виджет стороннего скрипта не даёт встроить
    // чекбокс внутрь себя, поэтому блокируем контейнер снаружи.
    function syncWidgetGate() {
        var agreed = !!(agreeCheckbox && agreeCheckbox.checked);
        widgetGate.style.pointerEvents = agreed ? '' : 'none';
        widgetGate.style.opacity = agreed ? '' : '0.5';
    }
    if (agreeCheckbox) {
        agreeCheckbox.addEventListener('change', syncWidgetGate);
        syncWidgetGate();
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
            if (!agreeCheckbox || !agreeCheckbox.checked) {
                showError('Подтвердите согласие на обработку персональных данных');
                return;
            }
            fetch('/ajax/mobileid_siteverify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: data.session_id,
                    verify_token: data.verify_token,
                    agree_pd: 'Y'
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
