<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="checkout-block">
    <div class="checkout-block__title">
        <span class="checkout-block__num">1</span> Город доставки
    </div>
    <div class="form-row">
        <input type="text" name="ORDER_PROP_<?= $arResult['LOCATION']['PROPERTY_ID'] ?? 1 ?>"
               value="<?= htmlspecialchars($arResult['LOCATION']['VALUE'] ?? '') ?>"
               placeholder="Введите ваш город" id="soa-location">
    </div>
</div>