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

    <input type="hidden" name="save" value="Y">

    <button type="submit" class="btn btn--primary"><svg class="icon"><use href="#icon-save"></use></svg> Сохранить изменения</button>
</form>

<div id="lk-change-phone-widget" style="display:none;margin-top:16px;max-width:420px;"></div>
<div id="lk-change-phone-message" style="display:none;margin-top:12px;padding:12px 16px;border-radius:8px;"></div>

<script src="https://cdn.smsaero.ru/mid-widget/1/mobileid-widget.min.js"></script>
<script>
(function () {
    var btn = document.getElementById('lk-change-phone-btn');
    var container = document.getElementById('lk-change-phone-widget');
    var msgBox = document.getElementById('lk-change-phone-message');
    var phoneDisplay = document.getElementById('lk-phone-display');
    var mounted = false;

    function showMessage(text, isError) {
        msgBox.textContent = text;
        msgBox.style.display = 'block';
        msgBox.style.background = isError ? '#fff0f0' : '#f0fff4';
        msgBox.style.border = '1px solid ' + (isError ? '#f5c6cb' : '#c3e6cb');
        msgBox.style.color = isError ? '#721c24' : '#155724';
    }

    btn.addEventListener('click', function () {
        container.style.display = 'block';
        btn.style.display = 'none';
        if (mounted) return;
        mounted = true;

        var widget = new MobileIDWidget({
            tokenUrl: '/ajax/mobileid_token.php',
            resultView: 'text',
            allowChangePhone: true,
            texts: {
                phoneLabel: 'Новый номер телефона',
                phonePlaceholder: '+7 (___) ___-__-__',
                submitPhone: 'Получить код',
                otpLabel: 'Введите код из SMS',
                submitOtp: 'Подтвердить'
            },
            theme: {
                primaryColor: '#668BEA',
                primaryHover: '#465B91',
                primaryText: '#ffffff',
                inputBorder: '#E2E2E2',
                inputBorderFocus: '#668BEA',
                borderRadius: '10px',
                fontFamily: 'Nunito, sans-serif',
                fontSize: '14px'
            },
            onVerified: function (data) {
                fetch('/ajax/mobileid_change_phone.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: data.session_id,
                        verify_token: data.verify_token,
                        sessid: BX.bitrix_sessid()
                    })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (result) {
                        if (result.success) {
                            phoneDisplay.value = result.phone;
                            container.style.display = 'none';
                            showMessage('Номер телефона успешно изменён', false);
                        } else {
                            showMessage(result.message || 'Не удалось изменить номер', true);
                        }
                    })
                    .catch(function () {
                        showMessage('Ошибка соединения с сервером', true);
                    });
            },
            onRejected: function () {
                showMessage('Верификация отклонена', true);
            },
            onError: function (err) {
                showMessage((err && err.message) || 'Ошибка виджета', true);
            }
        });
        widget.mount('#lk-change-phone-widget');
    });
})();
</script>
