<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

global $USER;

// Разрешаем редирект только на относительный путь этого же сайта. Раньше
// проверялся только первый символ ('/'), из-за чего "//evil.example/x"
// (тоже начинается с '/') проходило как валидный backurl — браузер трактует
// протокол-относительный URL как переход на ДРУГОЙ домен: window.location.href
// ниже и LocalRedirect() увели бы пользователя на сторонний сайт сразу после
// настоящей SMS-верификации — классическая фишинг-схема (см. security review).
$backurl = (string)($_REQUEST['backurl'] ?? '/personal/');
if (!preg_match('~^/[^/\\\\]~', $backurl) || parse_url($backurl, PHP_URL_HOST) !== null) {
    $backurl = '/personal/';
}

if ($USER->IsAuthorized()) {
    LocalRedirect($backurl);
    die();
}

$APPLICATION->SetPageProperty("title", "Вход и регистрация — ЛИДЕР, автозапчасти в Елабуге");
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

        <div id="auth-form-gated" class="auth-widget-gated">
            <div id="auth-phone-step">
                <div class="form-field">
                    <label for="auth-phone-input">Номер телефона</label>
                    <input type="tel" id="auth-phone-input" placeholder="+7 (___) ___-__-__" autocomplete="tel">
                </div>
                <button type="button" id="auth-send-code-btn" class="btn btn--primary">Получить код</button>
            </div>

            <div id="auth-code-step" style="display:none;">
                <div class="form-field">
                    <label for="auth-code-input">Код из SMS</label>
                    <input type="text" id="auth-code-input" inputmode="numeric" maxlength="4" placeholder="• • • •" autocomplete="one-time-code">
                </div>
                <button type="button" id="auth-verify-code-btn" class="btn btn--primary">Подтвердить</button>
                <button type="button" id="auth-resend-code-btn" class="btn btn--secondary" disabled>Отправить код повторно (<span id="auth-resend-timer">60</span>)</button>
                <button type="button" id="auth-change-phone-btn" class="btn btn--link">Изменить номер</button>
            </div>
        </div>

        <div id="auth-pending" class="auth-card__hint" style="display:none;">Отправляем...</div>
        <div id="auth-login-error" class="auth-card__error" style="display:none;"></div>
    </div>
</div>

<script>
(function () {
    var backurl = <?= json_encode($backurl) ?>;
    var RESEND_COOLDOWN = 60;

    var gated = document.getElementById('auth-form-gated');
    var agreeCheckbox = document.getElementById('auth-agree-pd');
    var errorBox = document.getElementById('auth-login-error');
    var pendingBox = document.getElementById('auth-pending');

    var phoneStep = document.getElementById('auth-phone-step');
    var codeStep = document.getElementById('auth-code-step');
    var phoneInput = document.getElementById('auth-phone-input');
    var codeInput = document.getElementById('auth-code-input');
    var sendBtn = document.getElementById('auth-send-code-btn');
    var verifyBtn = document.getElementById('auth-verify-code-btn');
    var resendBtn = document.getElementById('auth-resend-code-btn');
    var changePhoneBtn = document.getElementById('auth-change-phone-btn');
    var resendTimerEl = document.getElementById('auth-resend-timer');

    var resendTimer = null;

    function showError(text) {
        errorBox.textContent = text;
        errorBox.style.display = 'block';
    }
    function hideError() {
        errorBox.style.display = 'none';
    }
    function setPending(isPending) {
        pendingBox.style.display = isPending ? 'block' : 'none';
        sendBtn.disabled = isPending;
        verifyBtn.disabled = isPending;
    }

    // Форма визуально заблокирована, пока не отмечено согласие на обработку
    // персональных данных.
    function syncGate() {
        var agreed = !!(agreeCheckbox && agreeCheckbox.checked);
        gated.style.pointerEvents = agreed ? '' : 'none';
        gated.style.opacity = agreed ? '' : '0.5';
    }
    if (agreeCheckbox) {
        agreeCheckbox.addEventListener('change', syncGate);
        syncGate();
    }

    function startResendCooldown() {
        var secondsLeft = RESEND_COOLDOWN;
        resendBtn.disabled = true;
        resendTimerEl.textContent = secondsLeft;
        resendBtn.style.display = '';
        if (resendTimer) clearInterval(resendTimer);
        resendTimer = setInterval(function () {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(resendTimer);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Отправить код повторно';
                return;
            }
            resendTimerEl.textContent = secondsLeft;
        }, 1000);
    }

    function sendCode() {
        hideError();
        var phone = phoneInput.value.trim();
        if (phone === '') {
            showError('Введите номер телефона');
            return;
        }
        setPending(true);
        fetch('/ajax/smsru_send_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                setPending(false);
                if (result.success) {
                    phoneStep.style.display = 'none';
                    codeStep.style.display = 'block';
                    codeInput.value = '';
                    codeInput.focus();
                    startResendCooldown();
                } else {
                    showError(result.message || 'Не удалось отправить код');
                }
            })
            .catch(function () {
                setPending(false);
                showError('Ошибка соединения с сервером');
            });
    }

    function verifyCode() {
        if (!agreeCheckbox || !agreeCheckbox.checked) {
            showError('Подтвердите согласие на обработку персональных данных');
            return;
        }
        hideError();
        var code = codeInput.value.trim();
        if (code === '') {
            showError('Введите код из SMS');
            return;
        }
        setPending(true);
        fetch('/ajax/smsru_verify_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: phoneInput.value.trim(),
                code: code,
                agree_pd: 'Y'
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (result.success) {
                    window.location.href = backurl;
                } else {
                    setPending(false);
                    showError(result.message || 'Не удалось подтвердить код');
                }
            })
            .catch(function () {
                setPending(false);
                showError('Ошибка соединения с сервером');
            });
    }

    sendBtn.addEventListener('click', sendCode);
    verifyBtn.addEventListener('click', verifyCode);
    resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) return;
        sendCode();
    });
    changePhoneBtn.addEventListener('click', function () {
        if (resendTimer) clearInterval(resendTimer);
        codeStep.style.display = 'none';
        phoneStep.style.display = 'block';
        hideError();
    });
    codeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') verifyCode();
    });
    phoneInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') sendCode();
    });
})();
</script>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
