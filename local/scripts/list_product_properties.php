<?php
/**
 * Диагностика: какие свойства вообще существуют у инфоблока каталога,
 * насколько они заполнены у товаров и (для списочных) сколько вариантов
 * значений вообще определено в справочнике свойства.
 *
 * autofill_product_properties.php сам обрабатывает все свойства типа
 * "список" (L) и "строка" (S), кроме служебных из $EXCLUDE_CODES (список
 * ниже продублирован оттуда для отчёта) — этот скрипт просто показывает,
 * что из этого реально имеет смысл: у свойства должен быть непустой
 * справочник (L) или хотя бы один уже заполненный товар (S), иначе
 * автозаполнению неоткуда брать значения.
 *
 * Определения свойств читаются напрямую из b_iblock_property (это
 * стабильная системная таблица, не зависит от способа хранения значений).
 * А вот заполненность и справочник значений считаются через официальный
 * API — значения свойств в Bitrix могут храниться либо в общей таблице
 * b_iblock_element_property, либо в персональной b_iblock_element_prop_s{ID}
 * в зависимости от настроек инфоблока, и наугад собирать под это SQL
 * ненадёжно (плюс мы уже словили баг в статическом
 * CIBlockElement::GetProperty() на этом сервере — см. autofill_product_properties.php).
 *
 * Запуск: php local/scripts/list_product_properties.php
 */

$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');
set_time_limit(0);

$IBLOCK_ID = 42;

// 0 = весь каталог; если товаров очень много и хочется быстрой прикидки — поставьте число
$LIMIT = 0;

// Должно совпадать с $EXCLUDE_CODES в autofill_product_properties.php
$EXCLUDE_CODES = [
    'CML2_ARTICLE', 'CML2_BASE_UNIT', 'CML2_BAR_CODE', 'CML2_TRAITS', 'CML2_TAXES', 'CML2_ATTRIBUTES',
    'IN_RECOMMEND', 'IN_STOCK',
    'NAIMENOVANIE_TOVARA_V_UCHETNOY_SISTEME_POSTAVSHCHI', 'KOD_TOVARA_V_UCHETNOY_SISTEME_POSTAVSHCHIKA',
];

global $DB;

/**
 * printf("%-Ns", ...) в PHP считает ширину по байтам, а не по символам,
 * поэтому кириллица (2 байта/символ в UTF-8) ломает выравнивание колонок.
 * Дополняем вручную по mb_strlen().
 */
function padDisplay(string $s, int $width): string
{
    $len = mb_strlen($s, 'UTF-8');
    if ($len >= $width) {
        return mb_substr($s, 0, $width);
    }
    return $s . str_repeat(' ', $width - $len);
}

echo "========================================\n";
echo "  Свойства инфоблока $IBLOCK_ID и их заполненность\n";
echo "========================================\n\n";

// ----- 1. Список всех свойств инфоблока (определения — из системной таблицы) -----
$properties = [];
$res = $DB->Query("
    SELECT ID, CODE, NAME, PROPERTY_TYPE, MULTIPLE, IS_REQUIRED, ACTIVE, SORT
    FROM b_iblock_property
    WHERE IBLOCK_ID = " . (int)$IBLOCK_ID . "
    ORDER BY SORT, NAME
");
while ($row = $res->Fetch()) {
    $code = $row['CODE'] !== '' ? $row['CODE'] : ('ID_' . $row['ID']);
    $properties[$code] = [
        'NAME' => $row['NAME'],
        'TYPE' => $row['PROPERTY_TYPE'],
        'MULTIPLE' => $row['MULTIPLE'],
        'REQUIRED' => $row['IS_REQUIRED'],
        'ACTIVE' => $row['ACTIVE'],
        'FILLED' => 0,
        'ENUM_COUNT' => null,
    ];
    if ($row['PROPERTY_TYPE'] === 'L') {
        $enumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
        $cnt = 0;
        while ($enumRes->Fetch()) {
            $cnt++;
        }
        $properties[$code]['ENUM_COUNT'] = $cnt;
    }
}

echo "Всего свойств в инфоблоке: " . count($properties) . "\n\n";

// ----- 2. Проход по товарам через официальный API, считаем заполненность -----
$filter = ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y'];
$dbEl = CIBlockElement::GetList(['ID' => 'ASC'], $filter, false, false, ['ID']);
$total = 0;
while ($obEl = $dbEl->GetNextElement()) {
    $total++;
    $arProps = $obEl->GetProperties();
    foreach ($arProps as $code => $arProp) {
        if (!isset($properties[$code])) {
            continue;
        }
        $value = $arProp['VALUE'];
        $isFilled = is_array($value)
            ? count(array_filter($value, static fn($v) => $v !== null && $v !== '' && $v !== false)) > 0
            : ($value !== null && $value !== '' && $value !== false);
        if ($isFilled) {
            $properties[$code]['FILLED']++;
        }
    }
    if ($LIMIT > 0 && $total >= $LIMIT) {
        break;
    }
}

echo "Товаров проверено: $total\n\n";
echo "----------------------------------------\n\n";

// ----- 3. Отчёт: сначала самые незаполненные -----
uasort($properties, static fn($a, $b) => $a['FILLED'] <=> $b['FILLED']);

echo padDisplay('CODE', 30) . padDisplay('NAME', 26) . padDisplay('TYPE', 5) . padDisplay('MULT', 5)
    . padDisplay('СПРАВОЧНИК', 12) . padDisplay('ЗАПОЛНЕНО', 16) . "АВТОЗАПОЛНЕНИЕ\n";
echo str_repeat('-', 120) . "\n";

$willProcessCount = 0;
foreach ($properties as $code => $p) {
    $pct = $total > 0 ? round($p['FILLED'] / $total * 100) : 0;

    $willProcess = in_array($p['TYPE'], ['L', 'S'], true) && !in_array($code, $EXCLUDE_CODES, true);
    if ($p['TYPE'] === 'L' && $p['ENUM_COUNT'] === 0) {
        $mark = '— пуст справочник';
    } elseif ($willProcess) {
        $mark = '✅ обрабатывается';
        $willProcessCount++;
    } else {
        $mark = in_array($code, $EXCLUDE_CODES, true) ? '— служебное' : '— тип не поддержан';
    }

    echo padDisplay($code, 30)
        . padDisplay(mb_substr($p['NAME'], 0, 24), 26)
        . padDisplay($p['TYPE'], 5)
        . padDisplay($p['MULTIPLE'] === 'Y' ? 'Y' : '', 5)
        . padDisplay($p['ENUM_COUNT'] !== null ? (string)$p['ENUM_COUNT'] : '-', 12)
        . padDisplay("{$p['FILLED']}/{$total} ({$pct}%)", 16)
        . $mark . "\n";
}

echo "\n========================================\n";
echo "Автозаполнением будет обработано свойств: $willProcessCount\n";
echo "Подсказка: для L-свойств с пустым справочником сначала нужно завести\n";
echo "варианты значений в админке (Каталог -> Свойства -> нужное свойство),\n";
echo "иначе автозаполнению неоткуда брать значения.\n";
echo "========================================\n";
