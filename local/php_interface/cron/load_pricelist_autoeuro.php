<?php
/**
 * Загрузка прайс-листов Авто-Евро
 * Скачивает RAR по прямой ссылке, парсит CSV, пишет в b_supplier_stock
 * Крон: раз в сутки, рекомендуется в 11:00 МСК
 */

define('SUPPRESS_OUTPUT', true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$startTime = microtime(true);

// ==================== КОНФИГУРАЦИЯ ====================
define('SUPPLIER_CODE', 'autoeuro');
define('RAR_URL', 'https://price.autoeuro.ru/PriceAE_(3026794917).rar');
define('SOURCE_TYPE', 'pricelist');
define('PRICELIST_DIR', '/var/www/u3564357/data/www/liderws.ru/upload/pricelists/' . SUPPLIER_CODE);

// ==================== ЛОГГЕР ====================
$logFile = '/var/www/u3564357/data/www/liderws.ru/upload/logs/load_pricelist_autoeuro_' . date('Y-m-d') . '.log';
function logMsg($msg) {
    global $logFile;
    $line = date('H:i:s') . "  " . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}
logMsg("=== СТАРТ: загрузка прайс-листа Авто-Евро ===");

// ==================== ПОДГОТОВКА ДИРЕКТОРИЙ ====================
if (!is_dir(PRICELIST_DIR)) {
    mkdir(PRICELIST_DIR, 0755, true);
    logMsg("Создана директория: " . PRICELIST_DIR);
}

// ==================== СКАЧИВАНИЕ RAR ====================
$rarPath = PRICELIST_DIR . '/autoeuro_' . date('Ymd_His') . '.rar';
logMsg("Скачивание RAR из " . RAR_URL);

$ch = curl_init(RAR_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_USERAGENT => 'LiderWS PriceLoader/1.0',
]);
$rarData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$rarData) {
    logMsg("ОШИБКА скачивания: HTTP {$httpCode}, curl_error: {$curlError}");
    exit(1);
}

$rarSize = strlen($rarData);
file_put_contents($rarPath, $rarData);
logMsg("Скачано: {$rarSize} байт → {$rarPath}");

// ==================== РАСПАКОВКА ====================
$extractDir = PRICELIST_DIR . '/csv_' . date('Ymd_His');
// Чистим старые CSV директории
exec("rm -rf " . escapeshellarg(PRICELIST_DIR) . "/csv_* 2>/dev/null");
mkdir($extractDir, 0755, true);
logMsg("Распаковка в {$extractDir}...");

$cmd = "cd " . escapeshellarg($extractDir) . " && /usr/bin/unrar x -y " . escapeshellarg($rarPath) . " 2>&1";
exec($cmd, $output, $retCode);
logMsg("unrar завершился с кодом: {$retCode}");
if ($retCode !== 0) {
    logMsg("ОШИБКА распаковки: " . implode("\n", $output));
    unlink($rarPath);
    exit(1);
}

// ==================== ПОИСК CSV ====================
$csvFiles = glob($extractDir . '/*.csv');
$totalCsv = count($csvFiles);
logMsg("Найдено CSV файлов: {$totalCsv}");

if ($totalCsv === 0) {
    logMsg("ОШИБКА: нет CSV после распаковки");
    unlink($rarPath);
    exec("rm -rf " . escapeshellarg($extractDir));
    exit(1);
}

// ==================== ПОДКЛЮЧЕНИЕ К БД ====================
$dbHost = 'localhost';
$dbName = 'u3564357_liderws_db';
$dbUser = 'u3564357_liderws';
$dbPass = "S)'uAp]3.\$@wWd-";

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
    ]);
} catch (PDOException $e) {
    logMsg("ОШИБКА подключения к БД: " . $e->getMessage());
    exit(1);
}

// ==================== ДЕАКТИВАЦИЯ СТАРЫХ ЗАПИСЕЙ ====================
logMsg("Деактивация старых записей autoeuro...");
$stmt = $pdo->query("SELECT COUNT(*) FROM b_supplier_stock WHERE supplier_code = 'autoeuro' AND source_type = 'pricelist' AND is_active = 1");
$wasActive = $stmt->fetchColumn();
$pdo->exec("UPDATE b_supplier_stock SET is_active = 0, last_updated = NOW() WHERE supplier_code = 'autoeuro' AND source_type = 'pricelist' AND is_active = 1");
logMsg("Деактивировано записей: {$wasActive}");

// ==================== НОРМАЛИЗАЦИЯ ====================
function normalizeArticle($art) {
    return mb_strtolower(
        str_replace(
            [' ', '-', '.', '/', '\\', '_', '+', '(', ')', ',', "'", '"'],
            '',
            trim($art)
        ),
        'UTF-8'
    );
}

function normalizeBrand($brand) {
    return mb_strtolower(
        str_replace(
            [' ', '-', '.', '/', '\\', '_', '+', '(', ')', ',', "'", '"'],
            '',
            trim($brand)
        ),
        'UTF-8'
    );
}

// ==================== КАРТА СКЛАДОВ ====================
$warehouseMap = [
    'Belgorod' => 'Белгород',
    'Bessarabka' => 'Бессарабка',
    'Cheboksary' => 'Чебоксары',
    'Chelyabinsk' => 'Челябинск',
    'Ekaterinburg' => 'Екатеринбург',
    'Kazany_CS' => 'Казань ЦС',
    'Krasnodar' => 'Краснодар',
    'Kursk' => 'Курск',
    'Moskva_CS_Mytishchi' => 'Москва ЦС (Мытищи)',
    'Moskva_CS_Novaya_Riga' => 'Москва ЦС (Новая Рига)',
    'Naberezhnye_Chelny' => 'Набережные Челны',
    'Nizhniy_Novgorod' => 'Нижний Новгород',
    'Orenburg' => 'Оренбург',
    'Rostov_Na_Donu' => 'Ростов-На-Дону',
    'Rzhev' => 'Склад Ржев',
    'Samara' => 'Самара',
    'Sankt_Peterburg_CS' => 'Санкт-Петербург ЦС',
    'Sheremetyevo' => 'Шереметьево',
    'Stavropol' => 'Ставрополь',
    'Ufa' => 'Уфа',
    'Ulyanovsk' => 'Ульяновск',
    'Vladikavkaz' => 'Владикавказ',
    'Voronezh' => 'Воронеж',
    'Yaroslavl' => 'Ярославль',
    'Lobnya' => 'Склад Лобня',
];

function extractWarehouseFromFilename($filename) {
    global $warehouseMap;
    // PriceAE_3026794917_Naberezhnye_Chelny_2026-07-31_10-37-13.csv
    $parts = explode('_', $filename);
    $nameParts = [];
    $foundId = false;
    foreach ($parts as $part) {
        if ($foundId && preg_match('/^\d{4}-\d{2}-\d{2}$/', $part)) {
            break;
        }
        if ($foundId) {
            $nameParts[] = $part;
        }
        if (preg_match('/^\d{10,}$/', $part)) {
            $foundId = true;
        }
    }
    $key = implode('_', $nameParts);
    return $warehouseMap[$key] ?? str_replace('_', ' ', $key);
}

// ==================== ПОДГОТОВКА INSERT ====================
$insertSQL = "INSERT INTO b_supplier_stock 
    (supplier_code, source_type, article, article_normalized, brand, brand_normalized, 
     name, price, quantity, warehouse_name, stock_id, is_active, last_updated)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE 
        price = VALUES(price), 
        quantity = VALUES(quantity), 
        name = VALUES(name),
        is_active = 1,
        last_updated = NOW()";
$insertStmt = $pdo->prepare($insertSQL);

// ==================== ПАРСИНГ ВСЕХ CSV ====================
$totalInserted = 0;
$batch = [];
$batchSize = 500;

foreach ($csvFiles as $csvPath) {
    $csvName = basename($csvPath);
    $warehouse = extractWarehouseFromFilename($csvName);
    logMsg("Обработка: {$csvName} → {$warehouse}");
    
    $fh = fopen($csvPath, 'r');
    if (!$fh) {
        logMsg("  ОШИБКА открытия файла");
        continue;
    }
    
    // Заголовок
    $header = fgetcsv($fh, 0, ',');
    if (!$header) { fclose($fh); continue; }
    // Чистим BOM
    if (substr($header[0], 0, 3) === "\xEF\xBB\xBF") {
        $header[0] = substr($header[0], 3);
    }
    
    $fileRows = 0;
    while (($row = fgetcsv($fh, 0, ',')) !== false) {
        if (count($row) < 10) continue;
        
        $brand = trim($row[0] ?? '');
        $article = trim($row[3] ?? '');     // НомерПроизводителя
        $originalArticle = trim($row[4] ?? '');
        $description = trim($row[5] ?? '');
        $price = str_replace(',', '.', trim($row[6] ?? '0'));
        $quantity = intval(trim($row[8] ?? '0'));
        
        if (empty($brand) || empty($article) || floatval($price) <= 0 || $quantity <= 0) {
            continue;
        }
        
        $brandNorm = normalizeBrand($brand);
        $articleNorm = normalizeArticle($article);
        
        $batch[] = [
            SUPPLIER_CODE,
            SOURCE_TYPE,
            $article,
            $articleNorm,
            $brand,
            $brandNorm,
            $description,        // → name
            floatval($price),
            $quantity,
            $warehouse,
            $stockId,
        ];
        $fileRows++;
        
        if (count($batch) >= $batchSize) {
            foreach ($batch as $b) {
                try {
                    $insertStmt->execute($b);
                    $totalInserted++;
                } catch (PDOException $e) {
                    if ($e->getCode() != 23000) {
                        // пропускаем
                    }
                }
            }
            $batch = [];
        }
    }
    
    fclose($fh);
    logMsg("  OK: {$fileRows} строк");
}

// Досылаем остаток
if (!empty($batch)) {
    foreach ($batch as $b) {
        try {
            $insertStmt->execute($b);
            $totalInserted++;
        } catch (PDOException $e) {}
    }
}

// ==================== УДАЛЕНИЕ НЕАКТИВНЫХ ====================
$stmt = $pdo->query("SELECT COUNT(*) FROM b_supplier_stock WHERE supplier_code = 'autoeuro' AND source_type = 'pricelist' AND is_active = 0");
$inactiveCount = $stmt->fetchColumn();
if ($inactiveCount > 0) {
    $pdo->exec("DELETE FROM b_supplier_stock WHERE supplier_code = 'autoeuro' AND source_type = 'pricelist' AND is_active = 0");
    logMsg("Удалено неактивных записей: {$inactiveCount}");
}

// ==================== ОЧИСТКА ====================
unlink($rarPath);
logMsg("RAR удалён: {$rarPath}");
exec("rm -rf " . escapeshellarg($extractDir));
logMsg("CSV директория удалена: {$extractDir}");

// ==================== ИТОГИ ====================
$activeStmt = $pdo->query("SELECT COUNT(*) FROM b_supplier_stock WHERE supplier_code = 'autoeuro' AND source_type = 'pricelist' AND is_active = 1");
$activeCount = $activeStmt->fetchColumn();
$elapsed = round(microtime(true) - $startTime, 2);

logMsg("=== ЗАВЕРШЕНИЕ ===");
logMsg("Вставлено строк: {$totalInserted}");
logMsg("Активных записей всего: {$activeCount}");
logMsg("Время выполнения: {$elapsed} сек");