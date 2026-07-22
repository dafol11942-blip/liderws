<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="checkout-block">
    <div class="checkout-block__title">
        <span class="checkout-block__num">4</span> Способ оплаты
    </div>
    <?php if (!empty($arResult['PAYMENT'])): ?>
    <div class="payment-options">
        <?php foreach ($arResult['PAYMENT'] as $payment): ?>
        <label class="payment-option <?= $payment['CHECKED'] === 'Y' ? 'active' : '' ?>">
            <div class="payment-option__radio"></div>
            <input type="radio" name="PAY_SYSTEM_ID" value="<?= $payment['ID'] ?>"
                   <?= $payment['CHECKED'] === 'Y' ? 'checked' : '' ?> style="display:none;">
            <div class="payment-option__name"><?= $payment['NAME'] ?></div>
        </label>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="color:#999;">Выберите доставку, чтобы увидеть способы оплаты</p>
    <?php endif; ?>
</div>