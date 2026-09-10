<?php
/**
 * Точечная диагностика: почему list_product_properties.php показывает 0%
 * заполненности, хотя autofill_product_properties.php реально записал
 * значения (подтверждено в админке на ID=55184). Смотрим, что именно
 * возвращает GetProperties() для этого товара, и под какими ключами.
 *
 * Запуск: php local/scripts/debug_check_properties.php
 */

$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('iblock');

$id = 55184; // известно: brand_lider=IDEMITSU, STANDART_API=SN/CF — подтверждено в админке

$el = CIBlockElement::GetList([], ['ID' => $id], false, false, ['ID', 'NAME'])->GetNextElement();
if (!$el) {
    die("Элемент $id не найден\n");
}
$arFields = $el->GetFields();
echo "Товар: ID={$arFields['ID']} \"{$arFields['NAME']}\"\n\n";

$props = $el->GetProperties();
echo "Всего ключей в GetProperties(): " . count($props) . "\n";
echo "Первые 15 ключей: " . implode(', ', array_slice(array_keys($props), 0, 15)) . "\n\n";

foreach (['brand_lider', 'STANDART_API', 'KLASS_VYAZKOSTI_SAE'] as $code) {
    echo "=== $code ===\n";
    if (!isset($props[$code])) {
        echo "  ключа '$code' НЕТ среди возвращённых GetProperties()\n\n";
        continue;
    }
    echo "  VALUE: ";
    var_export($props[$code]['VALUE']);
    echo "\n  CODE в самой записи: ";
    var_export($props[$code]['CODE'] ?? '(нет поля CODE)');
    echo "\n\n";
}
