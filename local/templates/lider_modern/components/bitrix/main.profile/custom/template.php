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
            <input type="tel" id="lk-phone-display" value="<?= htmlspecialchars($arResult['arUser']['PERSONAL_PHONE']) ?>" disabled>
            <button type="button" id="lk-change-phone-btn" class="btn btn--secondary" style="margin-top:8px;">Изменить номер</button>
        </div>
    </div>

    <label class="pd-consent">
        <input type="checkbox" name="agree_pd" value="Y" required>
        <span>Я согласен(на) с условиями <a href="/soglasie/" target="_blank">Политики обработки персональных данных</a></span>
    </label>

    <input type="hidden" name="save" value="Y">

    <button type="submit" class="btn btn--primary"><svg class="icon"><use href="#icon-save"></use></svg> Сохранить изменения</button>
</form>

<div id="lk-change-phone-widget" style="display:none;margin-top:16px;max-width:420px;">
    <div id="lk-change-phone-input-step">
        <div class="form-field">
            <label for="lk-new-phone-input">Новый номер телефона</label>
            <input type="tel" id="lk-new-phone-input" placeholder="+7 (___) ___-__-__" autocomplete="tel">
        </div>
        <button type="button" id="lk-send-code-btn" class="btn btn--primary">Получить код</button>
    </div>
    <div id="lk-change-phone-code-step" style="display:none;margin-top:12px;">
        <div class="form-field">
            <label for="lk-new-phone-code-input">Код из SMS</label>
            <input type="text" id="lk-new-phone-code-input" inputmode="numeric" maxlength="4" placeholder="• • • •" autocomplete="one-time-code">
        </div>
        <button type="button" id="lk-verify-code-btn" class="btn btn--primary">Подтвердить</button>
        <button type="button" id="lk-resend-code-btn" class="btn btn--secondary" disabled>Отправить код повторно (<span id="lk-resend-timer">60</span>)</button>
    </div>
</div>
<div id="lk-change-phone-message" style="display:none;margin-top:12px;padding:12px 16px;border-radius:8px;"></div>

<script>
(function () {
    var RESEND_COOLDOWN = 60;

    var btn = document.getElementById('lk-change-phone-btn');
    var container = document.getElementById('lk-change-phone-widget');
    var msgBox = document.getElementById('lk-change-phone-message');
    var phoneDisplay = document.getElementById('lk-phone-display');

    var phoneStep = document.getElementById('lk-change-phone-input-step');
    var codeStep = document.getElementById('lk-change-phone-code-step');
    var phoneInput = document.getElementById('lk-new-phone-input');
    var codeInput = document.getElementById('lk-new-phone-code-input');
    var sendBtn = document.getElementById('lk-send-code-btn');
    var verifyBtn = document.getElementById('lk-verify-code-btn');
    var resendBtn = document.getElementById('lk-resend-code-btn');
    var resendTimerEl = document.getElementById('lk-resend-timer');

    var resendTimer = null;

    function showMessage(text, isError) {
        msgBox.textContent = text;
        msgBox.style.display = 'block';
        msgBox.style.background = isError ? '#fff0f0' : '#f0fff4';
        msgBox.style.border = '1px solid ' + (isError ? '#f5c6cb' : '#c3e6cb');
        msgBox.style.color = isError ? '#721c24' : '#155724';
    }

    function startResendCooldown() {
        var secondsLeft = RESEND_COOLDOWN;
        resendBtn.disabled = true;
        resendTimerEl.textContent = secondsLeft;
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
        var phone = phoneInput.value.trim();
        if (phone === '') {
            showMessage('Введите номер телефона', true);
            return;
        }
        sendBtn.disabled = true;
        fetch('/ajax/smsru_send_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                sendBtn.disabled = false;
                if (result.success) {
                    phoneStep.style.display = 'none';
                    codeStep.style.display = 'block';
                    codeInput.value = '';
                    codeInput.focus();
                    startResendCooldown();
                } else {
                    showMessage(result.message || 'Не удалось отправить код', true);
                }
            })
            .catch(function () {
                sendBtn.disabled = false;
                showMessage('Ошибка соединения с сервером', true);
            });
    }

    btn.addEventListener('click', function () {
        container.style.display = 'block';
        btn.style.display = 'none';
    });

    sendBtn.addEventListener('click', sendCode);
    resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) return;
        sendCode();
    });

    verifyBtn.addEventListener('click', function () {
        var code = codeInput.value.trim();
        if (code === '') {
            showMessage('Введите код из SMS', true);
            return;
        }
        verifyBtn.disabled = true;
        fetch('/ajax/smsru_change_phone.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: phoneInput.value.trim(),
                code: code,
                sessid: BX.bitrix_sessid()
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                verifyBtn.disabled = false;
                if (result.success) {
                    phoneDisplay.value = result.phone;
                    container.style.display = 'none';
                    if (resendTimer) clearInterval(resendTimer);
                    showMessage('Номер телефона успешно изменён', false);
                } else {
                    showMessage(result.message || 'Не удалось изменить номер', true);
                }
            })
            .catch(function () {
                verifyBtn.disabled = false;
                showMessage('Ошибка соединения с сервером', true);
            });
    });
})();
</script>
