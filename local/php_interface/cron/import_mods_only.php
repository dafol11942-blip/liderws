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

// Найти лист Модификации
$sheetIdx = null;
foreach ($xlsx->sheetNames() as $i => $name) {
    if ($name === 'Модификации') { $sheetIdx = $i; break; }
}

$class = HighloadBlockTable::compileEntity(
    HighloadBlockTable::getRow(['filter' => ['=NAME' => 'AutoModifications']])
)->getDataClass();

$rows   = $xlsx->rows($sheetIdx);
$header = array_shift($rows);
$count  = 0;

echo "Импорт модификаций...\n";

foreach ($rows as $row) {
    if (empty($row[2])) continue;

    $result = $class::add([
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
'UF_START_DATE'          => !empty($row[13]) ? new \Bitrix\Main\Type\Date(date('d.m.Y', strtotime($row[13])), 'd.m.Y') : null,
'UF_END_DATE'            => !empty($row[14]) ? new \Bitrix\Main\Type\Date(date('d.m.Y', strtotime($row[14])), 'd.m.Y') : null,
    ]);

    if ($result->isSuccess()) {
        $count++;
    } else {
        echo "❌ Ошибка в строке (modId={$row[2]}): " . implode(', ', $result->getErrorMessages()) . "\n";
    }
}

echo "✅ Добавлено модификаций: $count\n";
