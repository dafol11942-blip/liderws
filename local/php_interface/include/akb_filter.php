<?php
/**
 * «Подбор аккумуляторов» — блок над товарами раздела АКБ: ёмкость и пусковой
 * ток ползунками, полярность чекбоксами с числом товаров, габариты «не больше».
 * Свойства AKB_* заполняет cron/fill_akb_properties.php из наименования.
 *
 * Блок появляется в любом разделе, где у товаров в наличии заполнено хотя бы
 * одно свойство AKB_*; параметры — свои (akb_*), независимо от умного фильтра
 * в сайдбаре, поэтому свойства AKB_* в сайдбаре не выводятся.
 */

const AKB_FILTER_RANGES = [
    'cap' => ['code' => 'AKB_CAPACITY', 'title' => 'Ёмкость, А·ч'],
    'cur' => ['code' => 'AKB_CURRENT',  'title' => 'Пусковой ток, А'],
];
const AKB_FILTER_SIZES = [
    'len' => ['code' => 'AKB_LENGTH', 'title' => 'Длина'],
    'wid' => ['code' => 'AKB_WIDTH',  'title' => 'Ширина'],
    'hei' => ['code' => 'AKB_HEIGHT', 'title' => 'Высота'],
];
const AKB_FILTER_POLARITY_LABELS = [
    'reverse' => 'Обратная (о.п.)',
    'direct'  => 'Прямая (п.п.)',
];

/**
 * Границы значений в разделе, выбранное в запросе и готовый кусок фильтра
 * для catalog.section. null — в разделе нет аккумуляторов со свойствами.
 */
function akbFilterState(int $iblockId, int $sectionId, array $request): ?array
{
    if ($sectionId <= 0) return null;
    $propIds = [];
    foreach (array_merge(array_column(AKB_FILTER_RANGES + AKB_FILTER_SIZES, 'code'), ['AKB_POLARITY']) as $code) {
        $p = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'ACTIVE' => 'Y'])->Fetch();
        if ($p) $propIds[$code] = (int)$p['ID'];
    }
    if (!isset($propIds['AKB_CAPACITY'])) return null;

    $enums = [];
    if (isset($propIds['AKB_POLARITY'])) {
        $rs = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => $propIds['AKB_POLARITY']]);
        while ($e = $rs->Fetch()) {
            $enums[$e['XML_ID']] = ['ID' => (int)$e['ID'], 'LABEL' => AKB_FILTER_POLARITY_LABELS[$e['XML_ID']] ?? $e['VALUE'], 'COUNT' => 0];
        }
    }

    $numeric = array_column(AKB_FILTER_RANGES + AKB_FILTER_SIZES, 'code');
    $select = ['ID'];
    foreach ($numeric as $code) {
        if (isset($propIds[$code])) $select[] = 'PROPERTY_' . $code;
    }
    if ($enums) $select[] = 'PROPERTY_AKB_POLARITY';

    $bounds = [];
    $hasData = false;
    $rs = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'SECTION_ID' => $sectionId,
        'INCLUDE_SUBSECTIONS' => 'Y', '>=CATALOG_QUANTITY' => 1,
    ], false, false, $select);
    while ($row = $rs->Fetch()) {
        foreach ($numeric as $code) {
            $v = $row['PROPERTY_' . $code . '_VALUE'] ?? null;
            if ($v === null || $v === '') continue;
            $v = (float)$v;
            $bounds[$code] = isset($bounds[$code]) ? [min($bounds[$code][0], $v), max($bounds[$code][1], $v)] : [$v, $v];
            $hasData = true;
        }
        $pol = (string)($row['PROPERTY_AKB_POLARITY_ENUM_ID'] ?? '');
        foreach ($enums as &$e) {
            if ($pol !== '' && (int)$pol === $e['ID']) { $e['COUNT']++; $hasData = true; }
        }
        unset($e);
    }
    if (!$hasData) return null;

    $num = static function ($v): ?float {
        $v = str_replace([' ', ','], ['', '.'], trim((string)$v));
        return is_numeric($v) ? (float)$v : null;
    };
    $filter = [];
    $selected = [];
    foreach (AKB_FILTER_RANGES as $key => $def) {
        if (!isset($bounds[$def['code']])) continue;
        [$lo, $hi] = $bounds[$def['code']];
        $min = $num($request["akb_{$key}_min"] ?? '');
        $max = $num($request["akb_{$key}_max"] ?? '');
        // Значение на границе — не ограничение: иначе отсекались бы товары без свойства.
        if ($min !== null && $min > $lo) { $filter['>=PROPERTY_' . $def['code']] = $min; $selected["akb_{$key}_min"] = $min; }
        if ($max !== null && $max < $hi) { $filter['<=PROPERTY_' . $def['code']] = $max; $selected["akb_{$key}_max"] = $max; }
    }
    foreach (AKB_FILTER_SIZES as $key => $def) {
        if (!isset($bounds[$def['code']])) continue;
        $max = $num($request["akb_{$key}"] ?? '');
        if ($max !== null && $max < $bounds[$def['code']][1]) { $filter['<=PROPERTY_' . $def['code']] = $max; $selected["akb_{$key}"] = $max; }
    }
    $pol = array_intersect(array_keys($enums), (array)($request['akb_pol'] ?? []));
    if ($pol) {
        $filter['PROPERTY_AKB_POLARITY'] = array_map(fn($x) => $enums[$x]['ID'], array_values($pol));
        $selected['akb_pol'] = array_values($pol);
    }

    return ['BOUNDS' => $bounds, 'POLARITY' => array_filter($enums, fn($e) => $e['COUNT'] > 0), 'SELECTED' => $selected, 'FILTER' => $filter];
}

function akbFilterRender(array $state): string
{
    $h = static fn($s) => htmlspecialcharsbx((string)$s);
    $fmt = static fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    $sel = $state['SELECTED'];
    ob_start(); ?>
    <div class="akb-filter">
        <form class="akb-filter__form" id="akbFilterForm" method="get" action="">
            <div class="akb-filter__title"><svg class="icon"><use href="#icon-battery"></use></svg> Подбор аккумуляторов</div>
            <div class="akb-filter__grid">
                <?php foreach (AKB_FILTER_RANGES as $key => $def):
                    if (!isset($state['BOUNDS'][$def['code']])) continue;
                    [$lo, $hi] = $state['BOUNDS'][$def['code']];
                    if ($lo == $hi) continue;
                    $vMin = $sel["akb_{$key}_min"] ?? null;
                    $vMax = $sel["akb_{$key}_max"] ?? null; ?>
                    <div class="akb-filter__field">
                        <div class="akb-filter__label"><?= $def['title'] ?></div>
                        <div class="filter__price-range">
                            <div class="filter__range">
                                <input type="text" inputmode="numeric" class="filter__input filter__input--half" data-role="min" name="akb_<?= $key ?>_min" value="<?= $vMin !== null ? $fmt($vMin) : '' ?>" placeholder="<?= $fmt($lo) ?>">
                                <span class="filter__range-sep">–</span>
                                <input type="text" inputmode="numeric" class="filter__input filter__input--half" data-role="max" name="akb_<?= $key ?>_max" value="<?= $vMax !== null ? $fmt($vMax) : '' ?>" placeholder="<?= $fmt($hi) ?>">
                            </div>
                            <div class="filter__slider" data-bound-min="<?= $fmt($lo) ?>" data-bound-max="<?= $fmt($hi) ?>">
                                <div class="filter__slider-track"><div class="filter__slider-fill"></div></div>
                                <input type="range" class="filter__slider-input" data-role="min" min="<?= $fmt($lo) ?>" max="<?= $fmt($hi) ?>" step="1" value="<?= $fmt($vMin ?? $lo) ?>">
                                <input type="range" class="filter__slider-input" data-role="max" min="<?= $fmt($lo) ?>" max="<?= $fmt($hi) ?>" step="1" value="<?= $fmt($vMax ?? $hi) ?>">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($state['POLARITY']): ?>
                    <div class="akb-filter__field">
                        <div class="akb-filter__label">Полярность</div>
                        <div class="akb-filter__checks">
                            <?php foreach ($state['POLARITY'] as $xml => $e): ?>
                                <label class="filter__checkbox">
                                    <input type="checkbox" name="akb_pol[]" value="<?= $h($xml) ?>"<?= in_array($xml, $sel['akb_pol'] ?? [], true) ? ' checked' : '' ?>>
                                    <span class="filter__checkmark"></span>
                                    <span class="filter__label"><?= $h($e['LABEL']) ?></span>
                                    <span class="filter__count"><?= $e['COUNT'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $sizes = array_filter(AKB_FILTER_SIZES, fn($d) => isset($state['BOUNDS'][$d['code']])); ?>
                <?php if ($sizes): ?>
                    <div class="akb-filter__field">
                        <div class="akb-filter__label">Габариты не больше, мм</div>
                        <div class="akb-filter__sizes">
                            <?php foreach ($sizes as $key => $def): ?>
                                <label class="akb-filter__size">
                                    <span><?= $def['title'] ?></span>
                                    <input type="text" inputmode="numeric" class="filter__input" name="akb_<?= $key ?>" value="<?= isset($sel["akb_{$key}"]) ? $fmt($sel["akb_{$key}"]) : '' ?>" placeholder="до <?= $fmt($state['BOUNDS'][$def['code']][1]) ?>">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="akb-filter__buttons">
                <button type="submit" class="btn btn--primary btn--sm">Подобрать</button>
                <a href="?" class="btn btn--outline btn--sm akb-filter__reset">Сбросить</a>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
