<?php
/**
 * Показывает, каких значений не хватает в справочниках свойств —
 * ищет в названиях товаров фрагменты, похожие на значение (по регулярке
 * для конкретного свойства), и сравнивает с тем, что уже заведено в
 * справочнике. То, чего там нет, — кандидаты на добавление в админке
 * (Каталог -> Свойства -> нужное свойство -> варианты значений).
 *
 * Пример из практики: у "Масло трансмиссионное RIXX GL-4/GL5 (75w90)"
 * бренд заполнился (RIXX есть в справочнике), а класс вязкости SAE и
 * стандарт API — нет, потому что "75W-90" и "GL-4"/"GL-5" в тех
 * справочниках просто не заведены (там только значения для моторных
 * масел). Этот скрипт считает, сколько ещё товаров в такой же ситуации.
 *
 * Ничего не пишет и не меняет — только отчёт. После того как добавите
 * варианты в админке, перезапустите autofill_product_properties.php —
 * он сам их подхватит.
 *
 * Запуск: php local/scripts/find_missing_dictionary_values.php
 */

$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');
set_time_limit(0);

$IBLOCK_ID = 42;

// Для каждого интересующего свойства: регулярка, которая ищет в названии
// похожий на значение фрагмент, и функция, приводящая найденное к канону
// (в таком виде стоит заводить вариант в справочнике). Список можно
// пополнять — это просто добавление нового элемента массива.
$RULES = [
    'KLASS_VYAZKOSTI_SAE' => [
        'label' => 'Класс вязкости SAE',
        'regex' => '/\b(\d{1,2})\s*[Ww]\s*-?\s*(\d{2,3})\b/u',
        'canon' => static function (array $m) {
            return $m[1] . 'W-' . $m[2];
        },
    ],
    'STANDART_API' => [
        'label' => 'Стандарт API',
        'regex' => '/\bGL\s*-?\s*(\d)(?:\s*\/\s*GL\s*-?\s*(\d))?\b/iu',
        'canon' => static function (array $m) {
            $first = 'GL-' . $m[1];
            return !empty($m[2]) ? $first . '/GL-' . $m[2] : $first;
        },
    ],
];

function normalizeKey(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = preg_replace('/\s*-\s*/u', '-', $value);
    return mb_strtoupper($value, 'UTF-8');
}

echo "========================================\n";
echo "  Поиск недостающих значений в справочниках\n";
echo "========================================\n\n";

// ----- 1. Уже существующие значения по каждому свойству из $RULES -----
$existing = []; // code => [normalizeKey => 1]
foreach ($RULES as $code => $rule) {
    $existing[$code] = [];
    $res = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
    while ($row = $res->Fetch()) {
        $existing[$code][normalizeKey($row['VALUE'])] = true;
    }
    echo "[$code] {$rule['label']}: в справочнике уже " . count($existing[$code]) . " вариантов\n";
}
echo "\n----------------------------------------\n\n";

// ----- 2. Проход по всем товарам, извлекаем кандидатов -----
$missing = array_fill_keys(array_keys($RULES), []); // code => [canonValue => ['count'=>N, 'examples'=>[...]]]

$filter = ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y'];
$dbEl = CIBlockElement::GetList(['ID' => 'ASC'], $filter, false, false, ['ID', 'NAME']);
$total = 0;
while ($arEl = $dbEl->Fetch()) {
    $total++;
    $name = $arEl['NAME'];
    foreach ($RULES as $code => $rule) {
        if (!preg_match_all($rule['regex'], $name, $allMatches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($allMatches as $m) {
            $canon = $rule['canon']($m);
            if (isset($existing[$code][normalizeKey($canon)])) {
                continue; // уже есть в справочнике — не кандидат
            }
            if (!isset($missing[$code][$canon])) {
                $missing[$code][$canon] = ['count' => 0, 'examples' => []];
            }
            $missing[$code][$canon]['count']++;
            if (count($missing[$code][$canon]['examples']) < 3) {
                $missing[$code][$canon]['examples'][] = "ID={$arEl['ID']} \"{$name}\"";
            }
        }
    }
}
echo "Товаров проверено: $total\n\n";

// ----- 3. Отчёт -----
foreach ($missing as $code => $candidates) {
    $label = $RULES[$code]['label'];
    echo "========================================\n";
    echo "[$code] $label — отсутствует в справочнике, но встречается в названиях:\n";
    echo "========================================\n";
    if (empty($candidates)) {
        echo "(ничего не найдено — либо справочник полный, либо в названиях нет подходящих паттернов)\n\n";
        continue;
    }
    uasort($candidates, static fn($a, $b) => $b['count'] <=> $a['count']);
    foreach ($candidates as $canon => $info) {
        echo "  \"$canon\" — {$info['count']} товар(ов)\n";
        foreach ($info['examples'] as $ex) {
            echo "      $ex\n";
        }
    }
    echo "\n";
}

echo "========================================\n";
echo "Подсказка: заведите нужные варианты в админке (Каталог -> Свойства ->\n";
echo "соответствующее свойство -> вкладка вариантов значений), затем\n";
echo "перезапустите autofill_product_properties.php — он подхватит их сам.\n";
echo "========================================\n";
