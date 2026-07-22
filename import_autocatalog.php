<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;

$connection = Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

function readCsvFile($filePath)
{
    if (!file_exists($filePath)) {
        echo "<p style='color:red;'>File not found: $filePath</p>";
        return null;
    }
    $raw = file_get_contents($filePath);
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    $firstLine = strtok($raw, "\n");
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    $tmp = $filePath . '.tmp';
    file_put_contents($tmp, $raw);
    $h = fopen($tmp, 'r');
    if (!$h) return null;
    fgetcsv($h, 0, $delimiter);
    return ['handle' => $h, 'delimiter' => $delimiter, 'tmp' => $tmp];
}

function safeDate($str)
{
    $str = trim($str);
    if ($str === '') return 'NULL';
    $str = preg_replace('/\.\d{3}Z?/', '', $str);
    $ts = strtotime($str);
    return ($ts === false) ? 'NULL' : "'" . date('Y-m-d H:i:s', $ts) . "'";
}
function safeFloat($v) { $v = trim($v); return ($v !== '' && is_numeric($v)) ? (float)$v : 'NULL'; }
function safeInt($v) { $v = trim($v); return ($v !== '' && is_numeric($v)) ? (int)$v : 'NULL'; }

echo "<h2>Import catalog TO</h2>";

// ===== MARKS =====
echo "<h3>Marks...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/marks.csv');
if ($f) {
    $cnt = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 2) continue;
        $id = (int)$row[0];
        $name = $sqlHelper->forSql(trim($row[1]));
        if ($id <= 0 || $name === '') continue;
        $connection->queryExecute("INSERT INTO lider_auto_brand (ID, NAME) VALUES ($id, '$name') ON DUPLICATE KEY UPDATE NAME = VALUES(NAME)");
        $cnt++;
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Marks: $cnt</p>";
}

// ===== MODELS =====
echo "<h3>Models...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/models.csv');
if ($f) {
    $cnt = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 4) continue;
        $brandId = (int)$row[0];
        $modelId = (int)$row[2];
        $modelName = $sqlHelper->forSql(trim($row[3]));
        $yf = (isset($row[4]) && $row[4] !== '') ? (int)$row[4] : 'NULL';
        $yt = (!empty($row[5]) && $row[5] !== '') ? (int)$row[5] : 'NULL';
        if ($modelId <= 0 || $brandId <= 0) continue;
        $connection->queryExecute("INSERT INTO lider_auto_model (ID, BRAND_ID, NAME, YEAR_FROM, YEAR_TO) VALUES ($modelId, $brandId, '$modelName', $yf, $yt) ON DUPLICATE KEY UPDATE BRAND_ID=VALUES(BRAND_ID), NAME=VALUES(NAME), YEAR_FROM=VALUES(YEAR_FROM), YEAR_TO=VALUES(YEAR_TO)");
        $cnt++;
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Models: $cnt</p>";
}

// ===== MODIFICATIONS =====
echo "<h3>Modifications...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/modifications.csv');
if ($f) {
    $cnt = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 15) continue;
        $modelId = (int)$row[0];
        $modId = (int)$row[2];
        $fullName = $sqlHelper->forSql(trim($row[3]));
        $engineCode = $sqlHelper->forSql(trim($row[4]));
        $bodyType = $sqlHelper->forSql(trim($row[5]));
        $fuel = $sqlHelper->forSql(trim($row[6]));
        $hp = (int)$row[7];
        $capacity = safeFloat($row[8]);
        $cylinders = safeInt($row[9]);
        $valves = safeInt($row[10]);
        $valvesTotal = safeInt($row[11]);
        $motorType = $sqlHelper->forSql(trim($row[12]));
        $startDate = safeDate($row[13]);
        $endDate = safeDate($row[14]);
        if ($modId <= 0) continue;
        $connection->queryExecute("INSERT INTO lider_auto_modification (ID, MODEL_ID, FULL_NAME, ENGINE_CODE, BODY_TYPE, FUEL, HORSE_POWER, ENGINE_CAPACITY, CYLINDERS, VALVES_PER_CYLINDER, VALVES_TOTAL, MOTOR_TYPE, START_DATE, END_DATE) VALUES ($modId, $modelId, '$fullName', '$engineCode', '$bodyType', '$fuel', $hp, $capacity, $cylinders, $valves, $valvesTotal, '$motorType', $startDate, $endDate) ON DUPLICATE KEY UPDATE MODEL_ID=VALUES(MODEL_ID), FULL_NAME=VALUES(FULL_NAME), ENGINE_CODE=VALUES(ENGINE_CODE), BODY_TYPE=VALUES(BODY_TYPE), FUEL=VALUES(FUEL), HORSE_POWER=VALUES(HORSE_POWER), ENGINE_CAPACITY=VALUES(ENGINE_CAPACITY), CYLINDERS=VALUES(CYLINDERS), VALVES_PER_CYLINDER=VALUES(VALVES_PER_CYLINDER), VALVES_TOTAL=VALUES(VALVES_TOTAL), MOTOR_TYPE=VALUES(MOTOR_TYPE), START_DATE=VALUES(START_DATE), END_DATE=VALUES(END_DATE)");
        $cnt++;
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Modifications: $cnt</p>";
}

// ===== ITEMS =====
echo "<h3>Items...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/items.csv');
if ($f) {
    $cnt = 0;
    $skipped = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 11) continue;
        $modId = (int)$row[0];
        $itemId = (int)$row[2];
        if ($modId <= 0 || $itemId <= 0) {
            $skipped++;
            continue;
        }
        $itemName = $sqlHelper->forSql(trim($row[3]));
        $partNumber = $sqlHelper->forSql(trim($row[4]));
        $quantity = (int)$row[5];
        $comment = $sqlHelper->forSql(trim($row[6]));
        $isNecessary = (strtoupper(trim($row[7])) === 'TRUE') ? 'Y' : 'N';
        $manId = (int)$row[8];
        $manName = $sqlHelper->forSql(trim($row[9]));
        $catId = (int)$row[10];
        $connection->queryExecute("INSERT INTO lider_auto_item (ID, MODIFICATION_ID, NAME, PART_NUMBER, QUANTITY, COMMENT_TEXT, IS_NECESSARY, MANUFACTURER_ID, MANUFACTURER_NAME, CATEGORY_ID) VALUES ($itemId, $modId, '$itemName', '$partNumber', $quantity, '$comment', '$isNecessary', $manId, '$manName', $catId) ON DUPLICATE KEY UPDATE MODIFICATION_ID=VALUES(MODIFICATION_ID), NAME=VALUES(NAME), PART_NUMBER=VALUES(PART_NUMBER), QUANTITY=VALUES(QUANTITY), COMMENT_TEXT=VALUES(COMMENT_TEXT), IS_NECESSARY=VALUES(IS_NECESSARY), MANUFACTURER_ID=VALUES(MANUFACTURER_ID), MANUFACTURER_NAME=VALUES(MANUFACTURER_NAME), CATEGORY_ID=VALUES(CATEGORY_ID)");
        $cnt++;
        if ($cnt % 5000 === 0) {
            echo "<p>... $cnt rows</p>";
            ob_flush();
            flush();
        }
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Items: $cnt (skipped: $skipped)</p>";
}

// ===== OILS =====
echo "<h3>Oils...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/oils.csv');
if ($f) {
    $cnt = 0;
    $skipped = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 11) continue;
        $modId = (int)$row[0];
        if ($modId <= 0) {
            $skipped++;
            continue;
        }
        $groupName = $sqlHelper->forSql(trim($row[2]));
        $originalName = $sqlHelper->forSql(trim($row[3]));
        $artNumber = $sqlHelper->forSql(trim($row[4]));
        $volume = safeFloat($row[5]);
        $catalogId = safeInt($row[6]);
        $commentName = $sqlHelper->forSql(trim($row[7]));
        $manId = safeInt($row[8]);
        $manName = $sqlHelper->forSql(trim($row[9]));
        $orderPos = (int)$row[10];
        $connection->queryExecute("INSERT INTO lider_auto_oil (MODIFICATION_ID, GROUP_NAME, ORIGINAL_NAME, ART_NUMBER, VOLUME, CATALOG_ID, COMMENT_NAME, MANUFACTURER_ID, MANUFACTURER_NAME, ORDER_POSITION) VALUES ($modId, '$groupName', '$originalName', '$artNumber', $volume, $catalogId, '$commentName', $manId, '$manName', $orderPos)");
        $cnt++;
        if ($cnt % 10000 === 0) {
            echo "<p>... $cnt rows</p>";
            ob_flush();
            flush();
        }
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Oils: $cnt (skipped: $skipped)</p>";
}

// ===== SPECS =====
echo "<h3>Specs...</h3>";
$f = readCsvFile($_SERVER['DOCUMENT_ROOT'] . '/upload/catalog_to/specs.csv');
if ($f) {
    $cnt = 0;
    $skipped = 0;
    while (($row = fgetcsv($f['handle'], 0, $f['delimiter'])) !== false) {
        if (count($row) < 8) continue;
        $modId = (int)$row[0];
        if ($modId <= 0) {
            $skipped++;
            continue;
        }
        $name = $sqlHelper->forSql(trim($row[2]));
        $seoUrl = $sqlHelper->forSql(trim($row[3]));
        $volume = safeFloat($row[4]);
        $comment = $sqlHelper->forSql(trim($row[5]));
        $catItemId = safeInt($row[6]);
        $properties = $sqlHelper->forSql(trim($row[7]));
        $connection->queryExecute("INSERT INTO lider_auto_spec (MODIFICATION_ID, NAME, SEO_URL, VOLUME, COMMENT_TEXT, CATALOG_ITEM_ID, PROPERTIES) VALUES ($modId, '$name', '$seoUrl', $volume, '$comment', $catItemId, '$properties')");
        $cnt++;
        if ($cnt % 10000 === 0) {
            echo "<p>... $cnt rows</p>";
            ob_flush();
            flush();
        }
    }
    fclose($f['handle']); @unlink($f['tmp']);
    echo "<p>Specs: $cnt (skipped: $skipped)</p>";
}

echo "<h2 style='color:green;'>Done!</h2>";