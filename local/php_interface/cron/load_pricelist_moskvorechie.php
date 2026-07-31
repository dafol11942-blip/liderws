<?php
/**
 * Загрузка прайс-листов Москворечье в b_supplier_stock
 * 
 * Запуск: php local/php_interface/cron/load_pricelist_moskvorechie.php
 * Крон: раз в час (или раз в сутки)
 * 
 * Файлы: /upload/pricelists/moskvorechie/[склад]_[дата]_[время].csv
 * Склады: Набережные Челны, Нижний Новгород, Самара, Ростов-на-Дону, РЦ Томилино
 */

// Без лимитов по времени и памяти
set_time_limit(0);
ini_set('memory_limit', '512M');

// DOCUMENT_ROOT в CLI
$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/cli/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use Lider\Search\BrandNormalizer;

const SUPPLIER_CODE = 'moskvorechie';
const PRICELIST_DIR  = '/var/www/u3564357/data/www/liderws.ru/upload/pricelists/moskvorechie';
const LOG_DIR        = '/var/www/u3564357/data/www/liderws.ru/upload/logs';

// ==================== ИНИЦИАЛИЗАЦИЯ ====================

$logFile = LOG_DIR . '/load_pricelist_moskvorechie_' . date('Y-m-d') . '.log';
if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);

function logMsg(string $msg, bool $toConsole = true): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    file_put_contents($logFile, $line . "\n", FILE_APPEND);
    if ($toConsole) echo $line . "\n";
}

$db = getDbConnection();

// ==================== ПОИСК CSV-ФАЙЛОВ ====================

logMsg("=== ЗАПУСК загрузки прайс-листов Москворечье ===");

if (!is_dir(PRICELIST_DIR)) {
    logMsg("❌ Папка не найдена: " . PRICELIST_DIR . ". Создаю...");
    mkdir(PRICELIST_DIR, 0755, true);
    logMsg("⚠️ Папка создана, но файлов нет. Ждём загрузки от Москворечья.");
    exit(0);
}

$files = glob(PRICELIST_DIR . '/*.csv');
if (empty($files)) {
    logMsg("⚠️ CSV-файлы не найдены в " . PRICELIST_DIR);
    exit(0);
}

logMsg("Найдено файлов: " . count($files));

// ==================== ДЕАКТИВАЦИЯ СТАРЫХ ДАННЫХ ====================

logMsg("Деактивация старых строк source_type='pricelist' для " . SUPPLIER_CODE . "...");
$stmt = $db->prepare("UPDATE b_supplier_stock SET is_active = 0 WHERE supplier_code = ? AND source_type = 'pricelist'");
$stmt->execute([SUPPLIER_CODE]);
$deactivated = $stmt->rowCount();
logMsg("Деактивировано: {$deactivated} строк");

// ==================== ОБРАБОТКА ФАЙЛОВ ====================

$totalInserted = 0;
$totalUpdated  = 0;
$totalSkipped  = 0;
$totalRows     = 0;

// Подготовленный INSERT ... ON DUPLICATE KEY UPDATE
$insertSql = "INSERT INTO b_supplier_stock 
    (supplier_code, article, article_normalized, brand, brand_normalized, name, 
     price, quantity, warehouse_name, warehouse_code, source_type, source_updated,
     delivery_days, is_sched, multiplicity, stock_id, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pricelist', NOW(), 0, 0, 1, ?, 1)
    ON DUPLICATE KEY UPDATE
        price = VALUES(price),
        quantity = VALUES(quantity),
        name = VALUES(name),
        source_updated = NOW(),
        is_active = 1";
$insertStmt = $db->prepare($insertSql);

foreach ($files as $filePath) {
    $fileName = basename($filePath);
    logMsg("--- Обработка: {$fileName} ---");
    
    // Определяем склад из имени файла: [склад]_[дата]_[время].csv
    $warehouseName = extractWarehouseFromFilename($fileName);
    $warehouseCode = generateWarehouseCode($warehouseName);
    logMsg("  Склад: {$warehouseName} (код: {$warehouseCode})");
    
    // Читаем файл
    $content = file_get_contents($filePath);
    if ($content === false) {
        logMsg("  ❌ Ошибка чтения файла");
        continue;
    }
    
    // Конвертируем Windows-1251 → UTF-8
    $encoding = mb_detect_encoding($content, ['windows-1251', 'utf-8', 'cp1251'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // Разбиваем на строки
    $lines = explode("\n", trim($content));
    if (count($lines) < 3) {
        logMsg("  ⚠️ Файл пустой или слишком короткий");
        continue;
    }
    
    $fileInserted = 0;
    $fileUpdated  = 0;
    $fileSkipped  = 0;
    
    // Пропускаем строку 0 («На складе») и строку 1 (заголовки)
    for ($i = 2; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if ($line === '') continue;
        
        $cols = str_getcsv($line, ',', '"', '\\');
        if (count($cols) < 10) continue;
        
        $totalRows++;
        
        // Извлекаем поля
        $brand   = trim($cols[1] ?? '');  // Производитель
        $article = trim($cols[2] ?? '');  // Номер производителя
        $name    = trim($cols[3] ?? '');  // Наименование
        $qtyRaw  = trim($cols[7] ?? '');  // Наличие на складе
        $priceRaw= trim($cols[9] ?? '');  // Цена
        
        // Пропускаем пустые артикулы/бренды
        if ($brand === '' || $article === '') {
            $fileSkipped++;
            continue;
        }
        
        // Парсим количество («-» → 0)
        $quantity = ($qtyRaw === '' || $qtyRaw === '-' || $qtyRaw === '0') ? 0 : (int)$qtyRaw;
        
        // Парсим цену
        $price = (float)str_replace(',', '.', $priceRaw);
        
        // Пропускаем товар с нулевым остатком и нулевой ценой
        if ($quantity <= 0 && $price <= 0) {
            $fileSkipped++;
            continue;
        }
        
        // Нормализация
        $articleNorm = BrandNormalizer::normalizeArticle($article);
        $brandNorm   = BrandNormalizer::normalize($brand);
        
        // Уникальный stock_id для прайс-листа
        $stockId = md5(SUPPLIER_CODE . '|' . $articleNorm . '|' . $brandNorm . '|' . $warehouseCode);
        
        try {
            $insertStmt->execute([
                SUPPLIER_CODE,    // supplier_code
                $article,         // article (оригинальный)
                $articleNorm,     // article_normalized
                $brand,           // brand (оригинальный)
                $brandNorm,       // brand_normalized
                $name,            // name (описание)
                $price,           // price
                $quantity,        // quantity
                $warehouseName,   // warehouse_name
                $warehouseCode,   // warehouse_code
                $stockId,         // stock_id
            ]);
            
            if ($insertStmt->rowCount() == 1) {
                $fileInserted++;
            } else {
                $fileUpdated++;
            }
        } catch (\Throwable $e) {
            logMsg("  ⚠️ Ошибка строки {$i}: " . $e->getMessage(), true);
            $fileSkipped++;
        }
    }
    
    logMsg("  Результат: +{$fileInserted} новых, ↻{$fileUpdated} обновлено, ✗{$fileSkipped} пропущено");
    $totalInserted += $fileInserted;
    $totalUpdated  += $fileUpdated;
    $totalSkipped  += $fileSkipped;
    
    // Перемещаем обработанный файл в архив
    $archiveDir = PRICELIST_DIR . '/archive';
    if (!is_dir($archiveDir)) mkdir($archiveDir, 0755, true);
    $archivePath = $archiveDir . '/' . $fileName . '.' . date('Ymd_His') . '.processed';
    rename($filePath, $archivePath);
    logMsg("  Файл перемещён в архив: " . basename($archivePath));
}

// ==================== ИТОГ ====================

logMsg("==============================================");
logMsg("ИТОГО обработано строк: {$totalRows}");
logMsg("  Новых: {$totalInserted}");
logMsg("  Обновлено: {$totalUpdated}");
logMsg("  Пропущено: {$totalSkipped}");
logMsg("  Деактивировано старых: {$deactivated}");
logMsg("=== ЗАВЕРШЕНИЕ ===");

// ==================== ФУНКЦИИ ====================

function getDbConnection(): PDO
{
    $host = 'localhost';
    $dbname = 'u3564357_liderws_db';
    $user = 'u3564357_liderws';
    $pass = "S)'uAp]3.\$@wWd-";
    
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    return $pdo;
}

/**
 * Извлекает название склада из имени файла
 * Пример: "Набережные Челны_2026-07-31_14-00.csv" → "Набережные Челны"
 */
function extractWarehouseFromFilename(string $filename): string
{
    // Убираем расширение
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // Паттерн: [склад]_[дата]_[время]
    // Дата в формате YYYY-MM-DD, время HH-MM
    $pattern = '/^(.+)_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2})$/';
    if (preg_match($pattern, $name, $matches)) {
        return $matches[1];
    }
    
    // Если не совпало — возвращаем имя файла без расширения
    return $name;
}

/**
 * Генерирует код склада (транслит)
 */
function generateWarehouseCode(string $name): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>'',
    ];
    $lower = mb_strtolower(trim($name));
    $translit = '';
    foreach (mb_str_split($lower) as $char) {
        $translit .= $map[$char] ?? $char;
    }
    $clean = preg_replace('/[^a-z0-9_]/', '', $translit);
    return 'msk_' . substr($clean, 0, 20);
}