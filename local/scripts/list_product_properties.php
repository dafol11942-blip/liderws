<?php
/**
 * Диагностика: какие свойства вообще существуют у инфоблока каталога и
 * насколько они заполнены у товаров. Нужен, чтобы понять, какие коды
 * добавить в $PROPERTY_CODES в autofill_product_properties.php — там
 * сейчас захардкожен только список из fix_section_property.php (14 шт.),
 * а реальных свойств в инфоблоке обычно больше.
 *
 * Определения свойств читаются напрямую из b_iblock_property (это
 * стабильная системная таблица, не зависит от способа хранения значений).
 * А вот процент заполненности каждого свойства у товаров считается через
 * официальный API (GetNextElement()->GetProperties()), а не через SQL —
 * значения свойств в Bitrix могут храниться либо в общей таблице
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

// Коды, которые уже участвуют в local/scripts/autofill_product_properties.php
$ALREADY_HANDLED = [
    'CML2_MANUFACTURER', 'TIP_3', 'KLASS_VYAZKOSTI_SAE', 'STANDART_API', 'STANDART_DOT',
    'TIP_SHCHETKI', 'TSOKOL_LAMPY', 'SEZONNOST', 'TIP_DVIGATELYA', 'STORONA_KREPLENIYA',
    'TIP_KREPLENIYA', 'INDEKS_DOPUSKA_VAG', 'TIP', 'TSVET',
];

global $DB;

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
    ];
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

// ----- 3. Отчёт: сначала самые незаполненные (кандидаты на автозаполнение) -----
uasort($properties, static fn($a, $b) => $a['FILLED'] <=> $b['FILLED']);

printf("%-26s %-30s %-6s %-4s %-16s %s\n", 'CODE', 'NAME', 'TYPE', 'MULT', 'ЗАПОЛНЕНО', 'В АВТОЗАПОЛНЕНИИ');
echo str_repeat('-', 110) . "\n";
foreach ($properties as $code => $p) {
    $pct = $total > 0 ? round($p['FILLED'] / $total * 100) : 0;
    $inList = in_array($code, $ALREADY_HANDLED, true) ? '✅' : '';
    printf(
        "%-26s %-30s %-6s %-4s %-16s %s\n",
        $code,
        mb_substr($p['NAME'], 0, 30),
        $p['TYPE'],
        $p['MULTIPLE'] === 'Y' ? 'Y' : '',
        "{$p['FILLED']}/{$total} ({$pct}%)",
        $inList
    );
}

echo "\n========================================\n";
echo "Подсказка: свойства с низким % заполнения и типом L (список) или S (строка)\n";
echo "и без ✅ — кандидаты на добавление в \$PROPERTY_CODES в autofill_product_properties.php\n";
echo "========================================\n";
