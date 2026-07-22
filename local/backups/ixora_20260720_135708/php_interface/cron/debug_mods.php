<?php
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
Loader::includeModule('highloadblock');

$xlsx = new \Shuchkin\SimpleXLSX(__DIR__ . '/catalogTO.xlsx');

// Найдём индекс листа Модификации
$sheetIdx = null;
foreach ($xlsx->sheetNames() as $i => $name) {
    if ($name === 'Модификации') { $sheetIdx = $i; break; }
}

$rows = $xlsx->rows($sheetIdx);
$header = array_shift($rows);
echo "Заголовки: " . implode(' | ', $header) . "\n";
echo "Кол-во колонок в заголовке: " . count($header) . "\n\n";

// Покажем первые 3 строки
for ($i = 0; $i < min(3, count($rows)); $i++) {
    echo "--- Строка $i (колонок: " . count($rows[$i]) . ") ---\n";
    foreach ($rows[$i] as $j => $v) {
        echo "  [$j] " . substr(var_export($v, true), 0, 80) . "\n";
    }
    echo "\n";
}

// Пробуем одну вставку
$class = HighloadBlockTable::compileEntity(
    HighloadBlockTable::getRow(['filter' => ['=NAME' => 'AutoModifications']])
)->getDataClass();

echo "=== Пробная вставка первой строки ===\n";
$row = $rows[0];
try {
    $r = $class::add([
        'UF_MODIFICATION_ID'     => (int)$row[2],
        'UF_MODEL_ID'            => (int)$row[0],
        'UF_FULL_NAME'           => trim($row[3]),
        'UF_ENGINE_CODE'         => trim((string)$row[4]),
        'UF_CONSTRUCTION_TYPE'   => trim((string)$row[5]),
        'UF_FUEL'                => trim((string)$row[6]),
        'UF_HORSE_POWER'         => (int)$row[7],
        'UF_ENGINE_CAPACITY'     => (float)$row[8],
        'UF_NUMBER_OF_CYLINDERS' => (int)$row[9],
        'UF_VALVES'              => (int)$row[10],
        'UF_VALVES_TOTAL'        => (int)$row[11],
        'UF_MOTOR_TYPE'          => trim((string)$row[12]),
        'UF_START_DATE'          => !empty($row[13]) ? date('Y-m-d', strtotime($row[13])) : null,
        'UF_END_DATE'            => !empty($row[14]) ? date('Y-m-d', strtotime($row[14])) : null,
    ]);
    if ($r->isSuccess()) {
        echo "✅ Успешно! ID: " . $r->getId() . "\n";
    } else {
        echo "❌ Ошибки:\n";
        foreach ($r->getErrorMessages() as $e) {
            echo "   - $e\n";
        }
    }
} catch (\Exception $e) {
    echo "💥 Исключение: " . $e->getMessage() . "\n";
}
