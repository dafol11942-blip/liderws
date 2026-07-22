<?php
/**
 * Импорт каталога ТО из XLSX в Highload-блоки Битрикс
 * Использует SimpleXLSX (лёгкий, построчное чтение)
 */

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

$xlsxPath = __DIR__ . '/catalogTO.xlsx';

if (!file_exists($xlsxPath)) {
    die("❌ Файл '$xlsxPath' не найден!\n");
}

function getHlClass(string $hlName)
{
    static $cache = [];
    if (isset($cache[$hlName])) return $cache[$hlName];

    $hl = HighloadBlockTable::getRow(['filter' => ['=NAME' => $hlName]]);
    if (!$hl) {
        echo "❌ HL-блок '$hlName' не найден!\n";
        return $cache[$hlName] = null;
    }
    return $cache[$hlName] = HighloadBlockTable::compileEntity($hl)->getDataClass();
}

function readSheet($xlsx, string $sheetName): array
{
    $sheetIdx = null;
    foreach ($xlsx->sheetNames() as $i => $name) {
        if ($name === $sheetName) {
            $sheetIdx = $i;
            break;
        }
    }
    if ($sheetIdx === null) {
        echo "❌ Лист '$sheetName' не найден!\n";
        return [];
    }

    return $xlsx->rows($sheetIdx);
}

echo "========================================\n";
echo "  Импорт каталога ТО (SimpleXLSX)\n";
echo "========================================\n\n";

$xlsx = new \Shuchkin\SimpleXLSX($xlsxPath);
if (!$xlsx) {
    die("❌ Не удалось открыть XLSX: " . \Shuchkin\SimpleXLSX::parseError() . "\n");
}

// ----- 1. МАРКИ -----
echo ">>> 1/6. Импорт МАРОК...\n";
$class = getHlClass('AutoBrands');
if ($class) {
    $rows  = readSheet($xlsx, 'Марки');
    $header = array_shift($rows);
    $count = 0;
    foreach ($rows as $row) {
        if (empty($row[0])) continue;
        $r = $class::add([
            'UF_BRAND_ID' => (int)$row[0],
            'UF_NAME'     => trim($row[1]),
            'UF_CODE'     => \CUtil::translit(trim($row[1]), 'ru', ['replace_space' => '_', 'replace_other' => '_']),
        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено марок: $count\n\n";
}

// ----- 2. МОДЕЛИ -----
echo ">>> 2/6. Импорт МОДЕЛЕЙ...\n";
$class = getHlClass('AutoModels');
if ($class) {
    $rows   = readSheet($xlsx, 'Модели');
    $header = array_shift($rows);
    $count  = 0;
    foreach ($rows as $row) {
        if (empty($row[2])) continue;
        $r = $class::add([
            'UF_MODEL_ID'  => (int)$row[2],
            'UF_BRAND_ID'  => (int)$row[0],
            'UF_NAME'      => trim($row[3]),
            'UF_YEAR_FROM' => (int)$row[4],
            'UF_YEAR_TO'   => (int)$row[5],
        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено моделей: $count\n\n";
}

// ----- 3. МОДИФИКАЦИИ -----
echo ">>> 3/6. Импорт МОДИФИКАЦИЙ...\n";
$class = getHlClass('AutoModifications');
if ($class) {
    $rows   = readSheet($xlsx, 'Модификации');
    $header = array_shift($rows);
    $count  = 0;
    foreach ($rows as $row) {
        if (empty($row[2])) continue;
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
'UF_START_DATE'          => !empty($row[13]) ? new \Bitrix\Main\Type\Date(date('Y-m-d', strtotime($row[13]))) : null,
'UF_END_DATE'            => !empty($row[14]) ? new \Bitrix\Main\Type\Date(date('Y-m-d', strtotime($row[14]))) : null,        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено модификаций: $count\n\n";
}

// ----- 4. ЗАПЧАСТИ -----
echo ">>> 4/6. Импорт ЗАПЧАСТЕЙ...\n";
$class = getHlClass('AutoParts');
if ($class) {
    $rows   = readSheet($xlsx, 'Запчасти');
    $header = array_shift($rows);
    $count  = 0;
    foreach ($rows as $row) {
        if (empty($row[2])) continue;
        $r = $class::add([
            'UF_MODIFICATION_ID'   => (int)$row[0],
            'UF_ITEM_NAME'         => trim($row[3]),
            'UF_PART_NUMBER'       => trim($row[4]),
            'UF_QUANTITY'          => (int)$row[5],
            'UF_COMMENT'           => trim((string)$row[6]),
            'UF_IS_NECESSARY'      => ($row[7] === 'TRUE' || $row[7] === true) ? 1 : 0,
            'UF_MANUFACTURER_NAME' => trim((string)$row[9]),
            'UF_CATEGORY_ID'       => (int)$row[10],
        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено запчастей: $count\n\n";
}

// ----- 5. МАСЛА -----
echo ">>> 5/6. Импорт МАСЕЛ...\n";
$class = getHlClass('AutoOils');
if ($class) {
    $rows   = readSheet($xlsx, 'Масла');
    $header = array_shift($rows);
    $count  = 0;
    foreach ($rows as $row) {
        if (empty($row[3])) continue;
        $r = $class::add([
            'UF_MODIFICATION_ID'   => (int)$row[0],
            'UF_GROUP_NAME'        => trim($row[2]),
            'UF_TYPE_NAME'         => trim($row[3]),
            'UF_ART_NUMBER'        => trim($row[4]),
            'UF_VOLUME'            => (float)$row[5],
            'UF_MANUFACTURER_NAME' => trim((string)$row[9]),
            'UF_ORDER_POSITION'    => (int)$row[10],
        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено масел/жидкостей: $count\n\n";
}

// ----- 6. СПЕЦИФИКАЦИИ -----
echo ">>> 6/6. Импорт СПЕЦИФИКАЦИЙ...\n";
$class = getHlClass('AutoSpecifications');
if ($class) {
    $rows   = readSheet($xlsx, 'Спецификации');
    $header = array_shift($rows);
    $count  = 0;
    foreach ($rows as $row) {
        if (empty($row[2])) continue;
        $r = $class::add([
            'UF_MODIFICATION_ID' => (int)$row[0],
            'UF_NAME'            => trim($row[2]),
            'UF_SEO_URL'         => trim((string)$row[3]),
            'UF_VOLUME'          => (float)$row[4],
            'UF_COMMENT'         => trim((string)$row[5]),
            'UF_PROPERTIES'      => trim((string)$row[7]),
        ]);
        if ($r->isSuccess()) $count++;
    }
    echo "   ✅ Добавлено спецификаций: $count\n\n";
}

echo "========================================\n";
echo "  🎉 Импорт завершён!\n";
echo "========================================\n";
