<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="checkout-block">
    <div class="checkout-block__title">
        <span class="checkout-block__num">3</span> Способ доставки
    </div>
    <?php if (!empty($arResult['DELIVERY'])): ?>
    <div class="delivery-options">
        <?php foreach ($arResult['DELIVERY'] as $delivery): ?>
        <label class="delivery-option <?= $delivery['CHECKED'] === 'Y' ? 'active' : '' ?>">
            <div class="delivery-option__radio"></div>
            <input type="radio" name="DELIVERY_ID" value="<?= $delivery['ID'] ?>"
                   <?= $delivery['CHECKED'] === 'Y' ? 'checked' : '' ?> style="display:none;">
            <div class="delivery-option__info">
                <div class="delivery-option__name"><?= $delivery['NAME'] ?></div>
                <?php if (!empty($delivery['DESCRIPTION'])): ?>
                    <div class="delivery-option__desc"><?= $delivery['DESCRIPTION'] ?></div>
                <?php endif; ?>
            </div>
            <div class="delivery-option__price">
                <?= !empty($delivery['PRICE_FORMATTED']) ? $delivery['PRICE_FORMATTED'] : 'Бесплатно' ?>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="color:#999;">Укажите город, чтобы увидеть доступные способы доставки</p>
    <?php endif; ?>
</div>