<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['STORES'])) return;
?>

<div class="stores-list">
    <?php foreach ($arResult['STORES'] as $store): ?>
        <div class="stores-item">
            <div class="stores-item__header">
                <span class="stores-item__name">📍 <?= $store['TITLE'] ?></span>
                <?php if ($store['AMOUNT'] > 0): ?>
                    <span class="stores-item__badge stores-item__badge--yes">
                        <?= $store['AMOUNT'] ?> шт.
                    </span>
                <?php else: ?>
                    <span class="stores-item__badge stores-item__badge--no">Нет</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($store['ADDRESS'])): ?>
                <div class="stores-item__address"><?= $store['ADDRESS'] ?></div>
            <?php endif; ?>
            <?php if (!empty($store['SCHEDULE'])): ?>
                <div class="stores-item__schedule">🕒 <?= $store['SCHEDULE'] ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>