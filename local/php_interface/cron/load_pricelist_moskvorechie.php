<?php
/**
 * Загрузка прайс-листов Москворечье в b_supplier_stock
 * 
 * Запуск: php local/php_interface/cron/load_pricelist_moskvorechie.php
 * Крон: каждый час, с 06:00 до 20:00
 * 
 * Файлы: /upload/pricelists/moskvorechie/[склад]_[дата]_[время].csv
 */

set_time_limit(0);
ini_set('memory_limit', '256M');

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/cli/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use Lider\Search\BrandNormalizer;

const SUPPLIER_CODE = 'moskvorechie';
const PRICELIST_DIR  = '/var/www/u3564357/data/www/liderws.ru/upload/pricelists/moskvorechie';
const LOG_DIR        = '/var/www/u3564357/data/www/liderws.ru/upload/logs';
const BATCH_SIZE     = 500;  // строк в одной INSERT-пачке

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

logMsg("=== ЗАПУСК: загрузка прайс-листов Москворечье ===");

if (!is_dir(PRICELIST_DIR)) {
    logMsg("❌ Папка не найдена: " . PRICELIST_DIR);
    exit(1);
}

$files = glob(PRICELIST_DIR . '/*.csv');
if (empty($files)) {
    logMsg("⚠️ CSV-файлы не найдены");
    exit(0);
}

// Сортируем: сначала меньшие файлы (быстрее прогрев)
usort($files, function($a, $b) { return filesize($a) <=> filesize($b); });

logMsg("Найдено файлов: " . count($files) . " (" . round(array_sum(array_map('filesize', $files)) / 1048576, 1) . " MB)");

// ==================== ДЕАКТИВАЦИЯ СТАРЫХ ====================

logMsg("Деактивация старых строк source_type='pricelist'...");
$db->exec("UPDATE b_supplier_stock SET is_active = 0 WHERE supplier_code = '" . SUPPLIER_CODE . "' AND source_type = 'pricelist'");
$deactivated = $db->query("SELECT ROW_COUNT()")->fetchColumn();
logMsg("Деактивировано: {$deactivated} строк");

// ==================== ПОДГОТОВКА INSERT ====================

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

// ==================== ОБРАБОТКА ФАЙЛОВ ====================

$grandTotal = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'rows' => 0];

foreach ($files as $filePath) {
    $fileName = basename($filePath);
    $fileSize = round(filesize($filePath) / 1048576, 1);
    logMsg("──────────────────────────────────────────");
    logMsg("Файл: {$fileName} ({$fileSize} MB)");
    
    // Склад из имени файла
    $warehouseName = extractWarehouseFromFilename($fileName);
    $warehouseCode = generateWarehouseCode($warehouseName);
    logMsg("  Склад: {$warehouseName} ({$warehouseCode})");
    
    // Открываем файл
    $fh = fopen($filePath, 'r');
    if (!$fh) {
        logMsg("  ❌ Ошибка открытия");
        continue;
    }
    
    // Определяем кодировку по первой строке
    $firstLine = fgets($fh);
    rewind($fh);
    $encoding = mb_detect_encoding($firstLine, ['windows-1251', 'utf-8', 'cp1251'], true);
    $isWin1251 = ($encoding && $encoding !== 'UTF-8');
    logMsg("  Кодировка: " . ($isWin1251 ? 'Windows-1251' : 'UTF-8'));
    
    $fileStats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'rows' => 0];
    $batch = [];
    $lineNum = 0;
    
    while (($line = fgets($fh)) !== false) {
        $lineNum++;
        
        // Пропускаем первые 2 строки («На складе» + заголовки)
        if ($lineNum <= 2) continue;
        
        $line = trim($line);
        if ($line === '') continue;
        
        // Конвертируем кодировку
        if ($isWin1251) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1251');
        }
        
        $cols = str_getcsv($line, ',', '"', '\\');
        if (count($cols) < 10) {
            $fileStats['skipped']++;
            continue;
        }
        
        $brand   = trim($cols[1] ?? '');
        $article = trim($cols[2] ?? '');
        $name    = trim($cols[3] ?? '');
        $qtyRaw  = trim($cols[7] ?? '');
        $priceRaw= trim($cols[9] ?? '');
        
        if ($brand === '' || $article === '') {
            $fileStats['skipped']++;
            continue;
        }
        
        $quantity = ($qtyRaw === '' || $qtyRaw === '-' || $qtyRaw === '0') ? 0 : (int)$qtyRaw;
        $price = (float)str_replace(',', '.', $priceRaw);
        
        // Пропускаем нулевые
        if ($quantity <= 0 && $price <= 0) {
            $fileStats['skipped']++;
            continue;
        }
        
        $articleNorm = BrandNormalizer::normalizeArticle($article);
        $brandNorm   = BrandNormalizer::normalize($brand);
        $stockId     = md5(SUPPLIER_CODE . '|' . $articleNorm . '|' . $brandNorm . '|' . $warehouseCode);
        
        $batch[] = [
            SUPPLIER_CODE, $article, $articleNorm,
            $brand, $brandNorm, $name,
            $price, $quantity,
            $warehouseName, $warehouseCode,
            $stockId
        ];
        $fileStats['rows']++;
        
        // Сбрасываем пачку
        if (count($batch) >= BATCH_SIZE) {
            flushBatch($db, $insertStmt, $batch, $fileStats);
            $batch = [];
        }
        
        // Прогресс каждые 50 000 строк
        if ($fileStats['rows'] % 50000 === 0) {
            logMsg("  ... {$fileStats['rows']} строк (+{$fileStats['inserted']} / ↻{$fileStats['updated']} / ✗{$fileStats['skipped']})");
        }
    }
    
    // Остаток пачки
    if (!empty($batch)) {
        flushBatch($db, $insertStmt, $batch, $fileStats);
    }
    
    fclose($fh);
    
    logMsg("  ИТОГ: {$fileStats['rows']} строк (+{$fileStats['inserted']} / ↻{$fileStats['updated']} / ✗{$fileStats['skipped']})");
    
    $grandTotal['inserted'] += $fileStats['inserted'];
    $grandTotal['updated']  += $fileStats['updated'];
    $grandTotal['skipped']  += $fileStats['skipped'];
    $grandTotal['rows']     += $fileStats['rows'];
    
    // В архив
    $archiveDir = PRICELIST_DIR . '/archive';
    if (!is_dir($archiveDir)) mkdir($archiveDir, 0755, true);
    rename($filePath, $archiveDir . '/' . $fileName . '.' . date('Ymd_His') . '.processed');
}

// ==================== ИТОГ ====================

logMsg("==============================================");
logMsg("ВСЕГО: {$grandTotal['rows']} строк (+{$grandTotal['inserted']} / ↻{$grandTotal['updated']} / ✗{$grandTotal['skipped']})");
logMsg("Деактивировано старых: {$deactivated}");

// Проверка результата
$active = $db->query("SELECT COUNT(*) FROM b_supplier_stock WHERE supplier_code = '" . SUPPLIER_CODE . "' AND source_type = 'pricelist' AND is_active = 1")->fetchColumn();
logMsg("Активных строк Москворечье (pricelist): {$active}");
logMsg("=== ЗАВЕРШЕНИЕ ===");

// ==================== ФУНКЦИИ ====================

function flushBatch(PDO $db, PDOStatement $stmt, array &$batch, array &$stats): void
{
    try {
        $db->beginTransaction();
        
        foreach ($batch as $row) {
            $stmt->execute($row);
            if ($stmt->rowCount() == 1) {
                $stats['inserted']++;
            } else {
                $stats['updated']++;
            }
        }
        
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        
        // Пачка не вставилась — пробуем по одному
        foreach ($batch as $row) {
            try {
                $stmt->execute($row);
                if ($stmt->rowCount() == 1) {
                    $stats['inserted']++;
                } else {
                    $stats['updated']++;
                }
            } catch (\Throwable $e2) {
                $stats['skipped']++;
            }
        }
    }
}

function getDbConnection(): PDO
{
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u3564357_liderws_db;charset=utf8mb4",
        'u3564357_liderws',
        "S)'uAp]3.\$@wWd-",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function extractWarehouseFromFilename(string $filename): string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    // Паттерн: [склад]_[дата]_[время]  например: МоскваВОСТОК_2026-07-31_09-00-14
    if (preg_match('/^(.+)_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}(?:-\d{2})?)$/', $name, $m)) {
        return $m[1];
    }
    return $name;
}

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
    return 'msk_' . preg_replace('/[^a-z0-9_]/', '', $translit);
}