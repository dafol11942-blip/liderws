<?php
/**
 * Свойства щёток стеклоочистителя из наименования: длина, вторая длина
 * комплекта, тип крепления. Нужны подбору по автомобилю для креплений
 * «крючок»: машине с крючком подходят щётки нужной длины с креплением
 * «крючок» или универсальным (несколько адаптеров). Для остальных креплений
 * подбор идёт по артикулу Bosch (WiperArticles), эти свойства там не нужны.
 *   «ЯЯ Щетка ст/очист 650/450 мм б/к BOSCH AERO TWIN RETRO ( AR813S ) (крючок)»
 *     650/450 мм → длина 650, длина 2 — 450 (комплект)
 *     (крючок)   → крепление «Крючок»
 *   «… 650 мм б/к BOSCH AERO TWIN (AP650U) (4 крепления)» → «Универсальное»
 *   «… (Side pin)», «(Pinch Tab)», «(Push Button)», «(BayonnetArm)» → соответствующее
 * Серии Bosch без пометки в названии: AP…U — универсальное (Multi-Clip),
 * AR…U / AR…S — крючок (Aerotwin Retrofit); каркасные и гибридные без пометки
 * — крючок. Не распознано — не пишем.
 * Расположение: «зад.», rear, у BOSCH серии Hxxx / AxxxH — задняя. Задним и
 * наборам Bosch под конкретную машину (A187S, AM466S) крепление не нужно —
 * их подбор идёт по артикулу.
 *
 * Разделы — «Щетки стеклоочистителя…» в каждой ветке каталога. Свойства
 * создаются с SMART_FILTER=Y; 1С их не знает и не затирает. Повторный запуск
 * заполняет только пустые значения.
 *
 * Запуск (по умолчанию — пробный, ничего не пишет; заодно показывает, какие
 * свойства у щёток уже заполнены из 1С):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/fill_wiper_properties.php
 * Применить:
 *   ... fill_wiper_properties.php --apply
 * Перезаписать уже заполненные значения распознанными:
 *   ... fill_wiper_properties.php --apply --overwrite
 */

$docRoot = realpath(__DIR__ . '/../../..');
$apply = in_array('--apply', $argv ?? [], true);
$overwrite = in_array('--overwrite', $argv ?? [], true);

ob_start();
try {
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
    CModule::IncludeModule('iblock');
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    echo 'Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

const WIPER_MOUNTS = [
    'hook'        => 'Крючок',
    'side_pin'    => 'Side pin',
    'pinch_tab'   => 'Pinch Tab',
    'push_button' => 'Push Button',
    'bayonet'     => 'Bayonet',
    'top_lock'    => 'Top Lock',
    'vatl'        => 'VATL5.1',
    'multi'       => 'Универсальное (несколько адаптеров)',
];

const WIPER_POSITIONS = [
    'front' => 'Передняя',
    'rear'  => 'Задняя',
];

// Крепление по наименованию: XML_ID => шаблон. Совпало несколько — «Универсальное».
const WIPER_MOUNT_PATTERNS = [
    'hook'        => '/крюч|(?<![a-z])hook(?![a-z])|\bAR\d{2,3}[US]\b/iu',
    'side_pin'    => '/side\s*-?\s*pin|сайд\s*пин/iu',
    'pinch_tab'   => '/pinch\s*-?\s*tab/iu',
    'push_button' => '/push\s*-?\s*button/iu',
    'bayonet'     => '/bayon+et|байонет/iu',
    'top_lock'    => '/top\s*-?\s*lock/iu',
    'vatl'        => '/VATL\s*5/iu',
    'multi'       => '/\d\s*креплен|мульти|multi\s*-?\s*clip|универсальн|\bAP\d{3}U\b/iu',
];

// Длина в дюймах => стандартная длина щётки в мм.
const WIPER_INCHES = [
    12 => 300, 13 => 330, 14 => 350, 15 => 380, 16 => 400, 17 => 430, 18 => 450, 19 => 480,
    20 => 500, 21 => 530, 22 => 550, 23 => 580, 24 => 600, 25 => 630, 26 => 650, 27 => 680, 28 => 700,
];

// Порядок = порядок в «Характеристиках» и в фильтре.
const WIPER_PROPERTIES = [
    'WIPER_LENGTH'  => ['name' => 'Длина щётки, мм',               'type' => 'N', 'sort' => 100],
    'WIPER_LENGTH2' => ['name' => 'Длина второй щётки, мм',        'type' => 'N', 'sort' => 110],
    'WIPER_MOUNT'   => ['name' => 'Тип крепления щётки',           'type' => 'L', 'sort' => 120],
    'WIPER_POSITION'=> ['name' => 'Расположение щётки',            'type' => 'L', 'sort' => 90],
];
// Варианты списочных свойств: CODE => [XML_ID => значение].
const WIPER_LIST_VALUES = [
    'WIPER_MOUNT'    => WIPER_MOUNTS,
    'WIPER_POSITION' => WIPER_POSITIONS,
];

// Задняя: «зад.», «задняя», rear; у BOSCH — ещё серии Hxxx и AxxxH (без BOSCH «H100» — это Hyundai).
const WIPER_REAR_PATTERN = '/(?<![а-яё])зад(?:н(?:яя|ий|ие|\.)?)?(?![а-яё])|(?<![a-z])rear(?![a-z])|^(?=.*bosch).*\b(?:H\d{3}|A\d{3}H)\b/iu';
// Наборы Bosch под конкретную машину (A187S, AM466S) — «родное» крепление, подбор по артикулу.
const WIPER_BOSCH_OE_SET_PATTERN = '/\bAM?\d{3}S\b/u';

echo ($apply ? '=== ПРИМЕНЕНИЕ ===' : '=== ПРОБНЫЙ ЗАПУСК (без --apply ничего не пишется) ===') . PHP_EOL;

/** Разбор наименования: только распознанные поля. */
function wiperParseName(string $name): array
{
    $result = [];
    $n = str_replace("\xC2\xA0", ' ', $name);

    // «650/450 мм», «500/500 Renault», «650 мм», «650мм»; комплект — две длины через «/» или «+».
    if (preg_match('/(?<![\d.,])(\d{3})\s*(?:мм)?\s*[\/+]\s*(\d{3})(?![\d.,])/iu', $n, $m)) {
        $result['WIPER_LENGTH'] = (int)$m[1];
        $result['WIPER_LENGTH2'] = (int)$m[2];
    } elseif (preg_match('/(?<![\d\/])(\d{3,4})\s*мм/iu', $n, $m)) {
        $result['WIPER_LENGTH'] = (int)$m[1];
    } elseif (preg_match('/(?<!\d)(\d{2})\s*(?:"|”|″|дюйм)/u', $n, $m) && isset(WIPER_INCHES[(int)$m[1]])) {
        $result['WIPER_LENGTH'] = WIPER_INCHES[(int)$m[1]];
    }

    // Задние подбираются по артикулу Bosch — крепление не определяем.
    if (preg_match(WIPER_REAR_PATTERN, $n)) {
        $result['WIPER_POSITION'] = 'rear';
        return $result;
    }
    $result['WIPER_POSITION'] = 'front';

    $mounts = [];
    foreach (WIPER_MOUNT_PATTERNS as $xml => $rx) {
        if (preg_match($rx, $n)) $mounts[] = $xml;
    }
    if ($mounts) {
        $result['WIPER_MOUNT'] = count($mounts) === 1 ? $mounts[0] : 'multi';
    } elseif (preg_match('/(?<![а-яё])каркасн|гибрид|hybrid/iu', $n)) {
        // Универсальные каркасные и гибридные без пометки — на крючок («бескаркасн» сюда не попадает).
        $result['WIPER_MOUNT'] = 'hook';
    }

    return $result;
}

/** Разделы щёток ветки (верхние из найденных; подразделы — через INCLUDE_SUBSECTIONS). */
function wiperSections(int $iblockId): array
{
    $found = [];
    $rs = CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
        false, ['ID', 'NAME', 'CODE', 'LEFT_MARGIN', 'RIGHT_MARGIN']);
    while ($s = $rs->Fetch()) {
        if (!preg_match('/щ[её]тк\w*\s+стеклоочист|дворник/iu', (string)$s['NAME'])) continue;
        foreach ($found as $parent) {
            if ($s['LEFT_MARGIN'] > $parent['LEFT_MARGIN'] && $s['RIGHT_MARGIN'] < $parent['RIGHT_MARGIN']) continue 2;
        }
        $found[] = $s;
    }
    return $found;
}

/** Создаёт недостающие свойства, возвращает [CODE => ID] и [XML_ID => ID варианта] крепления. */
function wiperEnsureProperties(int $iblockId, bool $apply, array $sections): array
{
    $ids = [];
    $usesSectionProps = CIBlock::GetArrayByID($iblockId, 'SECTION_PROPERTY') === 'Y';
    $ibp = new CIBlockProperty();

    foreach (WIPER_PROPERTIES as $code => $def) {
        $prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
        if ($prop) {
            $ids[$code] = (int)$prop['ID'];
            continue;
        }
        if (!$apply) {
            echo "  будет создано свойство $code «{$def['name']}»" . PHP_EOL;
            continue;
        }
        $fields = [
            'IBLOCK_ID' => $iblockId, 'NAME' => $def['name'], 'CODE' => $code,
            'PROPERTY_TYPE' => $def['type'], 'SORT' => $def['sort'], 'ACTIVE' => 'Y',
            'MULTIPLE' => 'N', 'FILTRABLE' => 'Y', 'SMART_FILTER' => 'Y',
        ];
        if ($def['type'] === 'L') {
            $fields['LIST_TYPE'] = 'L';
            $fields['VALUES'] = [];
            $sort = 10;
            foreach (WIPER_LIST_VALUES[$code] as $xml => $value) {
                $fields['VALUES'][] = ['VALUE' => $value, 'XML_ID' => $xml, 'SORT' => $sort, 'DEF' => 'N'];
                $sort += 10;
            }
        }
        // Свойства — только в разделах щёток, а не во всём каталоге ветки.
        if ($usesSectionProps) $fields['SECTION_PROPERTY'] = 'N';
        $id = (int)$ibp->Add($fields);
        if (!$id) {
            echo "  ОШИБКА создания $code: {$ibp->LAST_ERROR}" . PHP_EOL;
            continue;
        }
        $ids[$code] = $id;
        echo "  создано свойство $code «{$def['name']}» (#$id)" . PHP_EOL;
        if ($usesSectionProps) {
            foreach ($sections as $s) CIBlockSectionPropertyLink::Add((int)$s['ID'], $id, ['SMART_FILTER' => 'Y']);
        }
    }

    // [CODE => [XML_ID => ID варианта]]; варианты, которых нет (добавлены в код позже), — дописываем.
    $enumIds = [];
    foreach (WIPER_LIST_VALUES as $code => $values) {
        if (!isset($ids[$code])) continue;
        $rs = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $ids[$code]]);
        while ($e = $rs->Fetch()) $enumIds[$code][$e['XML_ID']] = (int)$e['ID'];
        foreach ($values as $xml => $value) {
            if (isset($enumIds[$code][$xml]) || !$apply) continue;
            $enumIds[$code][$xml] = (int)(new CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $ids[$code], 'VALUE' => $value, 'XML_ID' => $xml]);
        }
    }

    return [$ids, $enumIds];
}

$grand = ['total' => 0, 'filled' => 0];
foreach (CATALOG_BRANCHES as $slug => $branch) {
    $iblockId = (int)$branch['id'];
    $sections = wiperSections($iblockId);
    if (!$sections) continue;

    echo PHP_EOL . "### {$branch['name']} (инфоблок $iblockId): разделы "
        . implode(', ', array_map(fn($s) => "#{$s['ID']} «{$s['NAME']}»", $sections)) . PHP_EOL;

    [$propIds, $enumIds] = wiperEnsureProperties($iblockId, $apply, $sections);

    $stats = ['total' => 0, 'filled' => 0, 'skipped' => 0] + array_fill_keys(array_keys(WIPER_PROPERTIES), 0);
    $existing = []; // свойства, уже заполненные у щёток: «Название [CODE]» => число товаров
    $unparsed = [];
    $seen = [];
    foreach ($sections as $s) {
        $rs = CIBlockElement::GetList(['NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => (int)$s['ID'], 'INCLUDE_SUBSECTIONS' => 'Y'],
            false, false, ['ID', 'NAME']);
        while ($el = $rs->Fetch()) {
            $id = (int)$el['ID'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $name = (string)$el['NAME'];

            // Держатели, адаптеры, омыватель — не щётки.
            if (!preg_match('/щ[её]тк|дворник|wiper/iu', $name)) {
                $stats['skipped']++;
                continue;
            }
            $stats['total']++;

            $current = [];
            $rsProp = CIBlockElement::GetProperty($iblockId, $id, [], ['EMPTY' => 'N']);
            while ($p = $rsProp->Fetch()) {
                if (isset(WIPER_PROPERTIES[$p['CODE']])) {
                    $current[$p['CODE']] = $p['PROPERTY_TYPE'] === 'L' ? $p['VALUE_XML_ID'] : $p['VALUE'];
                } elseif ($p['CODE'] !== 'CML2_ARTICLE') {
                    $key = "{$p['NAME']} [{$p['CODE']}]";
                    $existing[$key] = ($existing[$key] ?? 0) + 1;
                }
            }

            $parsed = wiperParseName($name);
            // Крепление нужно только передним щёткам, которые подбираются по размеру
            // (не задним и не наборам Bosch под конкретную машину — те по артикулу).
            $required = ['WIPER_LENGTH'];
            if ($parsed['WIPER_POSITION'] === 'front' && !preg_match(WIPER_BOSCH_OE_SET_PATTERN, $name)) $required[] = 'WIPER_MOUNT';
            if ($missing = array_diff($required, array_keys($parsed))) {
                $unparsed[] = "#$id $name — нет: " . implode(', ', $missing);
            }

            $write = [];
            $shown = [];
            foreach ($parsed as $code => $value) {
                if (!$overwrite && isset($current[$code]) && $current[$code] !== '' && $current[$code] !== null) continue;
                if (isset(WIPER_LIST_VALUES[$code])) {
                    $shown[] = WIPER_PROPERTIES[$code]['name'] . ' ' . WIPER_LIST_VALUES[$code][$value];
                    if (isset($enumIds[$code][$value])) $write[$code] = $enumIds[$code][$value];
                } else {
                    $shown[] = WIPER_PROPERTIES[$code]['name'] . ' ' . $value;
                    $write[$code] = $value;
                }
                $stats[$code]++;
            }
            if (!$shown) continue;

            $stats['filled']++;
            echo "  #$id $name" . PHP_EOL . '      → ' . implode('; ', $shown) . PHP_EOL;
            if ($apply && $write) {
                CIBlockElement::SetPropertyValuesEx($id, $iblockId, $write);
            }
        }
    }

    if ($existing) {
        arsort($existing);
        echo PHP_EOL . '  Уже заполненные свойства щёток (из 1С и др.):' . PHP_EOL;
        foreach ($existing as $key => $cnt) echo "    $key — $cnt" . PHP_EOL;
    }
    if ($unparsed) {
        echo PHP_EOL . '  Распознано не всё (' . count($unparsed) . '):' . PHP_EOL;
        foreach ($unparsed as $line) echo "    $line" . PHP_EOL;
    }
    echo PHP_EOL . "  Щёток: {$stats['total']}, к заполнению: {$stats['filled']}, пропущено не-щёток: {$stats['skipped']}" . PHP_EOL;
    foreach (WIPER_PROPERTIES as $code => $def) echo "    {$def['name']}: {$stats[$code]}" . PHP_EOL;
    foreach (['total', 'filled'] as $k) $grand[$k] += $stats[$k];

    if ($apply) CIBlock::clearIblockTagCache($iblockId);
}

echo PHP_EOL . "ИТОГО: щёток {$grand['total']}, заполнено/к заполнению {$grand['filled']}" . PHP_EOL;
if (!$apply) echo 'Ничего не записано. Для записи запустите с --apply.' . PHP_EOL;
