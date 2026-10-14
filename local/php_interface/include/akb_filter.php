<?php
/**
 * «Подбор аккумуляторов» — блок над товарами раздела АКБ: ёмкость и пусковой
 * ток ползунками, полярность и тип корпуса чекбоксами с числом товаров,
 * габариты «не больше». Вкладка «По автомобилю» — марка/модель/модификация из
 * базы ТО, параметры считает include/akb_fitment.php (рекомендация, не точная
 * применяемость): в фильтр уходят габариты корпуса, тип корпуса и полярность.
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
// Списочные свойства: ключ параметра запроса (akb_{key}[]) => свойство и подписи вариантов по XML_ID.
const AKB_FILTER_LISTS = [
    'pol'  => ['code' => 'AKB_POLARITY',  'title' => 'Полярность',  'labels' => ['reverse' => 'Обратная (о.п.)', 'direct' => 'Прямая (п.п.)']],
    'case' => ['code' => 'AKB_CASE_TYPE', 'title' => 'Тип корпуса', 'labels' => ['european' => 'Европейский', 'asian' => 'Азиатский']],
];

/**
 * Границы значений в разделе, выбранное в запросе и готовый кусок фильтра
 * для catalog.section. null — в разделе нет аккумуляторов со свойствами.
 */
function akbFilterState(int $iblockId, int $sectionId, array $request): ?array
{
    if ($sectionId <= 0) return null;
    $propIds = [];
    foreach (array_column(AKB_FILTER_RANGES + AKB_FILTER_SIZES + AKB_FILTER_LISTS, 'code') as $code) {
        $p = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'ACTIVE' => 'Y'])->Fetch();
        if ($p) $propIds[$code] = (int)$p['ID'];
    }
    if (!isset($propIds['AKB_CAPACITY'])) return null;

    // [ключ списка => [XML_ID => [ID, LABEL, COUNT]]]
    $enums = [];
    foreach (AKB_FILTER_LISTS as $key => $def) {
        if (!isset($propIds[$def['code']])) continue;
        $rs = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => $propIds[$def['code']]]);
        while ($e = $rs->Fetch()) {
            $enums[$key][$e['XML_ID']] = ['ID' => (int)$e['ID'], 'LABEL' => $def['labels'][$e['XML_ID']] ?? $e['VALUE'], 'COUNT' => 0];
        }
    }

    $numeric = array_column(AKB_FILTER_RANGES + AKB_FILTER_SIZES, 'code');
    $select = ['ID'];
    foreach ($numeric as $code) {
        if (isset($propIds[$code])) $select[] = 'PROPERTY_' . $code;
    }
    foreach (array_keys($enums) as $key) $select[] = 'PROPERTY_' . AKB_FILTER_LISTS[$key]['code'];

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
        foreach ($enums as $key => &$values) {
            $enumId = (int)($row['PROPERTY_' . AKB_FILTER_LISTS[$key]['code'] . '_ENUM_ID'] ?? 0);
            foreach ($values as &$e) {
                if ($enumId === $e['ID']) { $e['COUNT']++; $hasData = true; }
            }
            unset($e);
        }
        unset($values);
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
    $lists = [];
    foreach ($enums as $key => $values) {
        $chosen = array_values(array_intersect(array_keys($values), (array)($request["akb_{$key}"] ?? [])));
        if ($chosen) {
            $filter['PROPERTY_' . AKB_FILTER_LISTS[$key]['code']] = array_map(fn($x) => $values[$x]['ID'], $chosen);
            $selected["akb_{$key}"] = $chosen;
        }
        $values = array_filter($values, fn($e) => $e['COUNT'] > 0);
        if ($values) $lists[$key] = $values;
    }

    // Подбор по автомобилю (akb_car = ID модификации) заменяет фильтр по параметрам.
    $car = akbFitmentForModification((int)($request['akb_car'] ?? 0));
    if ($car) {
        $filter = [
            '>=PROPERTY_AKB_LENGTH' => $car['LENGTH'] - 10,
            '<=PROPERTY_AKB_LENGTH' => $car['LENGTH'] + 5,
            '<=PROPERTY_AKB_WIDTH'  => $car['WIDTH'] + 5,
            '<=PROPERTY_AKB_HEIGHT' => $car['HEIGHT'] + 5,
        ];
        if (isset($enums['pol'][$car['POLARITY']])) $filter['PROPERTY_AKB_POLARITY'] = $enums['pol'][$car['POLARITY']]['ID'];
        if (isset($enums['case'][$car['CASE']])) $filter['PROPERTY_AKB_CASE_TYPE'] = $enums['case'][$car['CASE']]['ID'];
        $selected = [];
    }

    return ['BOUNDS' => $bounds, 'LISTS' => $lists, 'SELECTED' => $selected, 'FILTER' => $filter, 'CAR' => $car];
}

function akbFilterRender(array $state): string
{
    $h = static fn($s) => htmlspecialcharsbx((string)$s);
    $fmt = static fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    $sel = $state['SELECTED'];
    $car = $state['CAR'];
    $tab = $car ? 'car' : 'params';
    ob_start(); ?>
    <div class="akb-filter">
        <div class="akb-filter__title"><svg class="icon"><use href="#icon-battery"></use></svg> Подбор аккумуляторов</div>
        <div class="akb-filter__tabs" role="tablist">
            <button type="button" class="akb-filter__tab<?= $tab === 'params' ? ' is-active' : '' ?>" data-tab="params">По параметрам</button>
            <button type="button" class="akb-filter__tab<?= $tab === 'car' ? ' is-active' : '' ?>" data-tab="car">По автомобилю</button>
        </div>
        <form class="akb-filter__form js-akb-form" id="akbFilterForm" data-panel="params" method="get" action=""<?= $tab !== 'params' ? ' hidden' : '' ?>>
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

                <?php foreach ($state['LISTS'] as $key => $values): ?>
                    <div class="akb-filter__field">
                        <div class="akb-filter__label"><?= AKB_FILTER_LISTS[$key]['title'] ?></div>
                        <div class="akb-filter__checks">
                            <?php foreach ($values as $xml => $e): ?>
                                <label class="filter__checkbox">
                                    <input type="checkbox" name="akb_<?= $key ?>[]" value="<?= $h($xml) ?>"<?= in_array($xml, $sel["akb_{$key}"] ?? [], true) ? ' checked' : '' ?>>
                                    <span class="filter__checkmark"></span>
                                    <span class="filter__label"><?= $h($e['LABEL']) ?></span>
                                    <span class="filter__count"><?= $e['COUNT'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

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
        <form class="akb-filter__form js-akb-form" id="akbCarForm" data-panel="car" method="get" action=""<?= $tab !== 'car' ? ' hidden' : '' ?>
              data-brand="<?= $car ? $car['BRAND_ID'] : '' ?>" data-model="<?= $car ? $car['MODEL_ID'] : '' ?>" data-mod="<?= $car ? $car['MOD_ID'] : '' ?>">
            <div class="akb-filter__car">
                <select class="akb-filter__select" data-role="brand" aria-label="Марка"><option value="">— Марка —</option></select>
                <select class="akb-filter__select" data-role="model" aria-label="Модель" disabled><option value="">— Модель —</option></select>
                <select class="akb-filter__select" name="akb_car" data-role="mod" aria-label="Модификация" disabled><option value="">— Модификация —</option></select>
            </div>
            <?php if ($car): ?>
                <div class="akb-filter__result">
                    Для <b><?= $h($car['CAR']) ?></b> рекомендуется: корпус <?= $car['LENGTH'] ?>×<?= $car['WIDTH'] ?>×<?= $car['HEIGHT'] ?> мм
                    (<?= $h(AKB_FILTER_LISTS['case']['labels'][$car['CASE']]) ?>), полярность <?= $h(mb_strtolower(AKB_FILTER_LISTS['pol']['labels'][$car['POLARITY']])) ?>,
                    ёмкость <?= $car['CAP_FROM'] ?>–<?= $car['CAP_TO'] ?> А·ч<?= $car['AGM'] ? '; при системе Start-Stop — AGM или EFB' : '' ?>.
                </div>
            <?php endif; ?>
            <div class="akb-filter__notice">
                Подбор по автомобилю носит рекомендательный характер: параметры рассчитаны по типовым данным для марки,
                модели и двигателя. Перед покупкой сверьте размеры, полярность и ёмкость со старым аккумулятором
                или с руководством по эксплуатации автомобиля.
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
