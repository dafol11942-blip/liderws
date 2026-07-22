<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="checkout-block">
    <div class="checkout-block__title">
        <span class="checkout-block__num">2</span> Контактные данные
    </div>
    <div class="form-row">
        <label>Имя и фамилия</label>
        <input type="text" name="ORDER_PROP_<?= $arResult['BUYER']['NAME_ID'] ?? 2 ?>"
               value="<?= htmlspecialchars($arResult['BUYER']['NAME'] ?? '') ?>"
               placeholder="Иван Петров">
    </div>
    <div class="form-row">
        <label>Телефон *</label>
        <input type="tel" name="ORDER_PROP_<?= $arResult['BUYER']['PHONE_ID'] ?? 3 ?>"
               value="<?= htmlspecialchars($arResult['BUYER']['PHONE'] ?? '') ?>"
               placeholder="+7 (999) 123-45-67" required>
    </div>
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="ORDER_PROP_<?= $arResult['BUYER']['EMAIL_ID'] ?? 4 ?>"
               value="<?= htmlspecialchars($arResult['BUYER']['EMAIL'] ?? '') ?>"
               placeholder="mail@example.com">
    </div>
</div>