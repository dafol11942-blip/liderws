<?php
/**
 * Добавляет недостающие варианты значений в справочники списочных
 * свойств — программный аналог "Каталог -> Свойства -> нужное свойство
 * -> добавить вариант" в админке, чтобы не кликать руками.
 *
 * Сырой SQL в b_iblock_property_enum сюда намеренно не используется:
 * Bitrix кэширует списки вариантов свойств, и прямой INSERT этот кэш не
 * инвалидирует (значения могут не появиться в фильтре без ручной чистки
 * кэша), плюс нет защиты от дублей при повторном запуске. Через
 * \Bitrix\Iblock\PropertyEnumerationTable::add() всё это учтено.
 *
 * Список ниже — то, что нашёл find_missing_dictionary_values.php для
 * трансмиссионных масел (KLASS_VYAZKOSTI_SAE, STANDART_API). Дополняйте
 * массив $TO_ADD под свои находки и перезапускайте — уже существующие
 * значения (сравнение без учёта регистра/пробелов вокруг дефиса)
 * пропускаются, повторный запуск безопасен.
 *
 * После добавления перезапустите autofill_product_properties.php — он
 * сам подхватит новые значения у товаров, где они пустые.
 *
 * Запуск: php local/scripts/add_dictionary_values.php
 */

$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');

use Bitrix\Iblock\PropertyEnumerationTable;

$IBLOCK_ID = 42;

// code свойства => список значений, которые нужно завести
$TO_ADD = [
    'KLASS_VYAZKOSTI_SAE' => ['75W-90', '75W-85', '80W-90'],
    'STANDART_API' => ['GL-4', 'GL-5', 'GL-4/GL-5'],
];

function normalizeKey(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = preg_replace('/\s*-\s*/u', '-', $value);
    return mb_strtoupper($value, 'UTF-8');
}

echo "========================================\n";
echo "  Добавление вариантов в справочники свойств\n";
echo "========================================\n\n";

foreach ($TO_ADD as $code => $values) {
    $prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code])->Fetch();
    if (!$prop) {
        echo "⚠️  Свойство '$code' не найдено в инфоблоке $IBLOCK_ID — пропуск\n";
        continue;
    }
    if ($prop['PROPERTY_TYPE'] !== 'L') {
        echo "⚠️  Свойство '$code' не списочного типа (L) — пропуск\n";
        continue;
    }
    $propertyId = (int)$prop['ID'];

    // Текущие значения — для проверки на дубли и чтобы новые встали в сортировке после них
    $existing = [];
    $maxSort = 100;
    $enumRes = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'CODE' => $code]);
    while ($row = $enumRes->Fetch()) {
        $existing[normalizeKey($row['VALUE'])] = true;
        $maxSort = max($maxSort, (int)$row['SORT']);
    }

    echo "[$code] {$prop['NAME']}:\n";
    foreach ($values as $value) {
        if (isset($existing[normalizeKey($value)])) {
            echo "  = \"$value\" уже есть — пропуск\n";
            continue;
        }
        $maxSort += 10;
        $result = PropertyEnumerationTable::add([
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $value,
            'DEF' => 'N',
            'SORT' => $maxSort,
        ]);
        if ($result->isSuccess()) {
            echo "  + \"$value\" добавлено (ID={$result->getId()})\n";
            $existing[normalizeKey($value)] = true;
        } else {
            echo "  ❌ \"$value\": " . implode(', ', $result->getErrorMessages()) . "\n";
        }
    }
    echo "\n";
}

echo "========================================\n";
echo "Готово. Теперь перезапустите autofill_product_properties.php —\n";
echo "новые значения подхватятся у товаров, где эти свойства ещё пустые.\n";
echo "========================================\n";
