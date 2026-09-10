<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

if (empty($arResult['ITEMS'])) {
    return;
}
?>
<div class="filter">
    <form name="<?= $arResult['FILTER_NAME'] . '_form' ?>" action="<?= $arResult['FORM_ACTION'] ?>" method="get" id="smartFilterForm">

        <!-- Скрытое поле: включает фильтрацию -->
        <input type="hidden" name="set_filter" value="Y">

        <?php foreach ($arResult['HIDDEN'] as $arItem): ?>
            <input type="hidden" name="<?= $arItem['CONTROL_NAME'] ?>" value="<?= $arItem['HTML_VALUE'] ?>">
        <?php endforeach; ?>

        <?php foreach ($arResult['ITEMS'] as $key => $arItem):
    $isRange = isset($arItem['VALUES']['MIN']['VALUE']); // цена или числовое свойство

    // Списочные: пропускаем, если нет значений
    if (!$isRange && empty($arItem['VALUES'])) continue;

    // Числовые: пропускаем, если MIN и MAX совпадают (нет диапазона) и оба пустые
    if ($isRange) {
        $min = $arItem['VALUES']['MIN']['VALUE'] ?? null;
        $max = $arItem['VALUES']['MAX']['VALUE'] ?? null;
        if (($min === null && $max === null) || $min === $max) continue;
    }
?>
            <div class="filter__box">
                <div class="filter__title" onclick="this.parentElement.classList.toggle('closed')">
                    <?= $arItem['NAME'] ?>
                    <span class="filter__arrow">▾</span>
                </div>
                <div class="filter__body">

                    <?php if ($isRange): ?>
                        <?php
                        $minVal = $arItem['VALUES']['MIN']['HTML_VALUE'] ?: $arItem['VALUES']['MIN']['VALUE'];
                        $maxVal = $arItem['VALUES']['MAX']['HTML_VALUE'] ?: $arItem['VALUES']['MAX']['VALUE'];
                        $boundMin = (float)$arItem['VALUES']['MIN']['VALUE'];
                        $boundMax = (float)$arItem['VALUES']['MAX']['VALUE'];
                        $sliderStep = (floor($boundMin) == $boundMin && floor($boundMax) == $boundMax) ? 1 : 0.01;
                        ?>
                        <div class="filter__price-range">
                            <div class="filter__range">
                                <input
                                    type="text"
                                    class="filter__input filter__input--half"
                                    data-role="min"
                                    name="<?= $arItem['VALUES']['MIN']['CONTROL_NAME'] ?>"
                                    id="<?= $arItem['VALUES']['MIN']['CONTROL_ID'] ?>"
                                    value="<?= $minVal ?>"
                                    placeholder="<?= number_format($arItem['VALUES']['MIN']['VALUE'], 0, ',', ' ') ?>"
                                >
                                <span class="filter__range-sep">–</span>
                                <input
                                    type="text"
                                    class="filter__input filter__input--half"
                                    data-role="max"
                                    name="<?= $arItem['VALUES']['MAX']['CONTROL_NAME'] ?>"
                                    id="<?= $arItem['VALUES']['MAX']['CONTROL_ID'] ?>"
                                    value="<?= $maxVal ?>"
                                    placeholder="<?= number_format($arItem['VALUES']['MAX']['VALUE'], 0, ',', ' ') ?>"
                                >
                            </div>
                            <div class="filter__slider" data-bound-min="<?= $boundMin ?>" data-bound-max="<?= $boundMax ?>">
                                <div class="filter__slider-track"><div class="filter__slider-fill"></div></div>
                                <input type="range" class="filter__slider-input" data-role="min" min="<?= $boundMin ?>" max="<?= $boundMax ?>" step="<?= $sliderStep ?>" value="<?= $minVal !== '' ? $minVal : $boundMin ?>">
                                <input type="range" class="filter__slider-input" data-role="max" min="<?= $boundMin ?>" max="<?= $boundMax ?>" step="<?= $sliderStep ?>" value="<?= $maxVal !== '' ? $maxVal : $boundMax ?>">
                            </div>
                        </div>

                    <?php else: ?>
                        <?php
                        $visibleValues = array_filter($arItem['VALUES'], function ($ar) {
                            return !(empty($ar['VALUE']) && $ar['VALUE'] !== '0');
                        });
                        $showLimit = 6;
                        $totalValues = count($visibleValues);
                        $idx = 0;
                        ?>
                        <?php if ($totalValues > 10): ?>
                            <input type="text" class="filter__search" placeholder="Поиск...">
                        <?php endif; ?>
                        <?php foreach ($visibleValues as $val => $ar): $idx++; ?>
                            <?php if ($idx === $showLimit + 1): ?><div class="filter__more-items" hidden><?php endif; ?>
                            <label class="filter__checkbox <?= $ar['DISABLED'] ? 'filter__checkbox--disabled' : '' ?>">
                                <input
                                    type="checkbox"
                                    name="<?= $ar['CONTROL_NAME'] ?>"
                                    value="<?= $ar['HTML_VALUE'] ?>"
                                    <?= $ar['CHECKED'] ? 'checked' : '' ?>
                                    <?= $ar['DISABLED'] ? 'disabled' : '' ?>
                                >
                                <span class="filter__checkmark"></span>
                                <span class="filter__label"><?= $ar['VALUE'] ?></span>
                                <?php if (isset($ar['ELEMENT_COUNT']) && $ar['ELEMENT_COUNT'] !== ''): ?>
                                    <span class="filter__count"><?= $ar['ELEMENT_COUNT'] ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($totalValues > $showLimit): ?>
                            </div>
                            <button type="button" class="filter__show-more" data-more-label="Показать ещё (<?= $totalValues - $showLimit ?>)" data-less-label="Скрыть"><?= 'Показать ещё (' . ($totalValues - $showLimit) . ')' ?></button>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

        <div class="filter__buttons">
            <button type="submit" class="btn btn--primary btn--sm filter__btn">Показать</button>
           <a href="<?= $arResult['FORM_ACTION'] ?>" class="btn btn--outline btn--sm filter__btn" onclick="this.href=window.location.pathname;return true;">Сбросить</a>
        </div>
    </form>
</div>
