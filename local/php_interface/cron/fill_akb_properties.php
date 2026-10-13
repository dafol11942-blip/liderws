<?php
/**
 * Свойства аккумуляторов из наименования: ёмкость, пусковой ток, полярность,
 * длина / ширина / высота. 1С выгружает АКБ без этих свойств, а всё нужное
 * есть в названии:
 *   «Аккумулятор 6 СТ 62 о.п. L ТАЗ (Тюмень Стандарт) (580А) (242х175х190)»
 *     6 СТ 62      → ёмкость 62 А·ч
 *     о.п. / п.п.  → полярность обратная / прямая
 *     (580А)       → пусковой ток 580 А
 *     (242х175х190)→ длина 242, ширина 175, высота 190 мм
 *
 * Свойства создаются в каждом инфоблоке ветки, где есть раздел АКБ (CODE akb
 * или название «АКБ…»/«Аккумулятор…»), с SMART_FILTER=Y — появляются в фильтре
 * раздела и в «Характеристиках» товара. 1С эти свойства не знает (их нет в
 * выгрузке) и не затирает; новые товары подхватит повторный запуск — он
 * заполняет только пустые значения, то, что не распознано, не пишет.
 *
 * Запуск (по умолчанию — пробный, ничего не пишет):
 *   /usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/php_interface/cron/fill_akb_properties.php
 * Применить:
 *   ... fill_akb_properties.php --apply
 * Перезаписать уже заполненные значения распознанными:
 *   ... fill_akb_properties.php --apply --overwrite
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

const AKB_POLARITY_VALUES = [
    'reverse' => 'Обратная (о.п.)',
    'direct'  => 'Прямая (п.п.)',
];

// Порядок = порядок в «Характеристиках» и в фильтре.
const AKB_PROPERTIES = [
    'AKB_CAPACITY' => ['name' => 'Ёмкость, А·ч',        'type' => 'N', 'sort' => 100],
    'AKB_CURRENT'  => ['name' => 'Пусковой ток, А',     'type' => 'N', 'sort' => 110],
    'AKB_POLARITY' => ['name' => 'Полярность',          'type' => 'L', 'sort' => 120],
    'AKB_LENGTH'   => ['name' => 'Длина, мм',           'type' => 'N', 'sort' => 130],
    'AKB_WIDTH'    => ['name' => 'Ширина, мм',          'type' => 'N', 'sort' => 140],
    'AKB_HEIGHT'   => ['name' => 'Высота, мм',          'type' => 'N', 'sort' => 150],
];

echo ($apply ? '=== ПРИМЕНЕНИЕ ===' : '=== ПРОБНЫЙ ЗАПУСК (без --apply ничего не пишется) ===') . PHP_EOL;

/**
 * Разбор наименования. Возвращает только распознанные поля:
 * лучше пустое свойство, чем неверное значение в фильтре.
 */
function akbParseName(string $name): array
{
    $result = [];
    $n = str_replace(["\xC2\xA0", '×'], [' ', 'х'], $name);

    // Ёмкость: «6 СТ 62», «6СТ-190», «6 CT 75» (латиница), «6МТС-9» (мото).
    if (preg_match('/(?<!\d)6\s*-?\s*(?:[СC][ТT]|МТС)\s*-?\s*(\d{1,3})(?!\d)/iu', $n, $m)) {
        $result['AKB_CAPACITY'] = (int)$m[1];
    } elseif (preg_match('/(?<![\d.,])(\d{1,3})\s*(?:А\s*[\/·.]?\s*ч|Ah)(?![а-яёa-z])/iu', $n, $m)) {
        $result['AKB_CAPACITY'] = (int)$m[1];
    }

    // Пусковой ток: «(580А)», «(870A)», «580А EN», «EN 580».
    if (preg_match('/\(\s*(\d{2,4})\s*[АA]\s*\)/u', $n, $m)
        || preg_match('/(?<![\d.,])(\d{3,4})\s*[АA](?![а-яёa-z])/iu', $n, $m)
        || preg_match('/\bEN\s*(\d{3,4})(?!\d)/i', $n, $m)) {
        $result['AKB_CURRENT'] = (int)$m[1];
    }

    // Полярность: «о.п.» / «п.п.» (бывает «о/п», «оп»), или словами.
    $reverse = preg_match('/(?<![а-яё])о\s*[.\/]?\s*п(?![а-яё])|обратн/iu', $n);
    $direct  = preg_match('/(?<![а-яё])п\s*[.\/]?\s*п(?![а-яё])|прям(?:ая|ой)\s+полярн/iu', $n);
    if ($reverse xor $direct) {
        $result['AKB_POLARITY'] = $reverse ? 'reverse' : 'direct';
    }

    // Размер: «(242х175х190)» — кириллическая/латинская х, *, ×.
    if (preg_match('/(?<!\d)(\d{2,3})\s*[хxХX*]\s*(\d{2,3})\s*[хxХX*]\s*(\d{2,3})(?!\d)/u', $n, $m)) {
        $result['AKB_LENGTH'] = (int)$m[1];
        $result['AKB_WIDTH']  = (int)$m[2];
        $result['AKB_HEIGHT'] = (int)$m[3];
    }

    return $result;
}

/** Разделы АКБ ветки (верхние из найденных; подразделы берутся через INCLUDE_SUBSECTIONS). */
function akbSections(int $iblockId): array
{
    $found = [];
    $rs = CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
        false, ['ID', 'NAME', 'CODE', 'LEFT_MARGIN', 'RIGHT_MARGIN']);
    while ($s = $rs->Fetch()) {
        $isAkb = mb_strtolower((string)$s['CODE']) === 'akb'
            || preg_match('/^(акб|аккумулятор)/iu', trim((string)$s['NAME']));
        if (!$isAkb) continue;
        // Вложенный в уже найденный раздел — и так попадёт в выборку.
        foreach ($found as $parent) {
            if ($s['LEFT_MARGIN'] > $parent['LEFT_MARGIN'] && $s['RIGHT_MARGIN'] < $parent['RIGHT_MARGIN']) continue 2;
        }
        $found[] = $s;
    }
    return $found;
}

/** Создаёт недостающие свойства, возвращает [CODE => ID] и ID вариантов полярности. */
function akbEnsureProperties(int $iblockId, bool $apply, array $sections): array
{
    $ids = [];
    $enumIds = [];
    $usesSectionProps = CIBlock::GetArrayByID($iblockId, 'SECTION_PROPERTY') === 'Y';
    $ibp = new CIBlockProperty();

    foreach (AKB_PROPERTIES as $code => $def) {
        $prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
        if ($prop) {
            $ids[$code] = (int)$prop['ID'];
        } elseif ($apply) {
            $fields = [
                'IBLOCK_ID'     => $iblockId,
                'NAME'          => $def['name'],
                'CODE'          => $code,
                'PROPERTY_TYPE' => $def['type'],
                'SORT'          => $def['sort'],
                'ACTIVE'        => 'Y',
                'MULTIPLE'      => 'N',
                'FILTRABLE'     => 'Y',
                'SMART_FILTER'  => 'Y',
            ];
            if ($def['type'] === 'L') {
                $fields['LIST_TYPE'] = 'L';
                $fields['VALUES'] = [];
                $sort = 10;
                foreach (AKB_POLARITY_VALUES as $xml => $value) {
                    $fields['VALUES'][] = ['VALUE' => $value, 'XML_ID' => $xml, 'SORT' => $sort, 'DEF' => 'N'];
                    $sort += 10;
                }
            }
            // Инфоблок с привязкой свойств к разделам: показываем свойства
            // только в разделах АКБ, а не во всём каталоге ветки.
            if ($usesSectionProps) {
                $fields['SECTION_PROPERTY'] = 'N';
            }
            $id = (int)$ibp->Add($fields);
            if (!$id) {
                echo "  ОШИБКА создания $code: {$ibp->LAST_ERROR}" . PHP_EOL;
                continue;
            }
            $ids[$code] = $id;
            echo "  создано свойство $code «{$def['name']}» (#$id)" . PHP_EOL;
            if ($usesSectionProps) {
                foreach ($sections as $s) {
                    CIBlockSectionPropertyLink::Add((int)$s['ID'], $id, ['SMART_FILTER' => 'Y']);
                }
            }
        } else {
            echo "  будет создано свойство $code «{$def['name']}»" . PHP_EOL;
        }
    }

    if (isset($ids['AKB_POLARITY'])) {
        $rs = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $ids['AKB_POLARITY']]);
        while ($e = $rs->Fetch()) {
            $enumIds[$e['XML_ID']] = (int)$e['ID'];
        }
        // Свойство создано руками без нужных вариантов — добавляем.
        foreach (AKB_POLARITY_VALUES as $xml => $value) {
            if (isset($enumIds[$xml]) || !$apply) continue;
            $enumIds[$xml] = (int)(new CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $ids['AKB_POLARITY'], 'VALUE' => $value, 'XML_ID' => $xml]);
        }
    }

    return [$ids, $enumIds];
}

$grand = ['total' => 0, 'filled' => 0, 'skipped' => 0];
foreach (CATALOG_BRANCHES as $slug => $branch) {
    $iblockId = (int)$branch['id'];
    $sections = akbSections($iblockId);
    if (!$sections) continue;

    echo PHP_EOL . "### {$branch['name']} (инфоблок $iblockId): разделы "
        . implode(', ', array_map(fn($s) => "#{$s['ID']} «{$s['NAME']}»", $sections)) . PHP_EOL;

    [$propIds, $enumIds] = akbEnsureProperties($iblockId, $apply, $sections);

    $stats = ['total' => 0, 'filled' => 0, 'skipped' => 0] + array_fill_keys(array_keys(AKB_PROPERTIES), 0);
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

            // В разделе бывают клеммы, зарядные устройства — их не трогаем.
            if (!preg_match('/аккумулятор|акб|(?<!\d)6\s*-?\s*(?:[СC][ТT]|МТС)/iu', $name)) {
                $stats['skipped']++;
                continue;
            }
            $stats['total']++;

            $parsed = akbParseName($name);
            $missing = array_diff(array_keys(AKB_PROPERTIES), array_keys($parsed));
            if ($missing) {
                $unparsed[] = "#$id $name — нет: " . implode(', ', $missing);
            }
            if (!$parsed) continue;

            // Текущие значения — чтобы не перетереть заполненное вручную.
            $current = [];
            if ($propIds) {
                $rsProp = CIBlockElement::GetProperty($iblockId, $id, [], ['EMPTY' => 'N']);
                while ($p = $rsProp->Fetch()) {
                    if (isset(AKB_PROPERTIES[$p['CODE']])) $current[$p['CODE']] = $p['VALUE'];
                }
            }

            $write = [];
            $shown = [];
            foreach ($parsed as $code => $value) {
                if (!$overwrite && isset($current[$code]) && $current[$code] !== '' && $current[$code] !== null) continue;
                if ($code === 'AKB_POLARITY') {
                    $shown[] = 'полярность ' . AKB_POLARITY_VALUES[$value];
                    if (isset($enumIds[$value])) $write[$code] = $enumIds[$value];
                } else {
                    $shown[] = AKB_PROPERTIES[$code]['name'] . ' ' . $value;
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

    if ($unparsed) {
        echo PHP_EOL . "  Распознано не всё (" . count($unparsed) . "):" . PHP_EOL;
        foreach ($unparsed as $line) echo "    $line" . PHP_EOL;
    }
    echo PHP_EOL . "  Аккумуляторов: {$stats['total']}, к заполнению: {$stats['filled']}, пропущено не-АКБ: {$stats['skipped']}" . PHP_EOL;
    foreach (AKB_PROPERTIES as $code => $def) {
        echo "    {$def['name']}: {$stats[$code]}" . PHP_EOL;
    }
    foreach (['total', 'filled', 'skipped'] as $k) $grand[$k] += $stats[$k];

    if ($apply) {
        CIBlock::clearIblockTagCache($iblockId);
    }
}

echo PHP_EOL . "ИТОГО: аккумуляторов {$grand['total']}, заполнено/к заполнению {$grand['filled']}" . PHP_EOL;
if (!$apply) {
    echo 'Ничего не записано. Для записи запустите с --apply.' . PHP_EOL;
}
