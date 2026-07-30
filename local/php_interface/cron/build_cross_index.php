<?php
/**
 * Ночной крон: заполнение b_cross_index через UMAPI
 * Источники: b_supplier_stock + upload/1c_nomenclature.csv
 * Запуск: php local/php_interface/cron/build_cross_index.php
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use \Lider\Search\BrandNormalizer;

const UMAPI_BASE = 'https://api.umapi.ru/v2/cross/parts/Analogs/pro';
const UMAPI_KEY  = '52606cd0-b1fd-4a5e-a8e3-ad9fbef16435';
const DELAY_US   = 200000;
const CSV_FILE   = '/var/www/u3564357/data/www/liderws.ru/upload/1c_nomenclature.csv';
const LOG_DIR    = '/var/www/u3564357/data/www/liderws.ru/upload/logs/';

$logFile = LOG_DIR . 'build_cross_index_' . date('Y-m-d_H-i-s') . '.log';
@mkdir(LOG_DIR, 0755, true);

function logger(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

$db     = \Bitrix\Main\Application::getConnection();
$helper = $db->getSqlHelper();

// ─── 1. Сбор пар из b_supplier_stock ─────────────────────────
logger('Шаг 1: пары из b_supplier_stock...');
$dbRows = $db->query(
    "SELECT DISTINCT article, brand 
     FROM b_supplier_stock 
     WHERE is_active = 1 AND article != '' AND brand != ''"
)->fetchAll();

logger('  b_supplier_stock: ' . count($dbRows) . ' уникальных пар');

// ─── 2. Сбор пар из CSV 1С ───────────────────────────────────
$csvPairs = [];
if (file_exists(CSV_FILE)) {
    logger('Шаг 2: пары из CSV 1С...');
    $handle = fopen(CSV_FILE, 'r');
    $header = fgetcsv($handle, 0, ';');
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $art = trim($row[0] ?? '');
        $brd = trim($row[1] ?? '');
        if ($art !== '' && $brd !== '') {
            $csvPairs[] = ['article' => $art, 'brand' => $brd];
        }
    }
    fclose($handle);
    logger('  CSV 1С: ' . count($csvPairs) . ' позиций');
} else {
    logger('  ⚠️ CSV не найден: ' . CSV_FILE . ' — пропускаем');
}

// ─── 3. Объединение + дедупликация ───────────────────────────
logger('Шаг 3: объединение и дедупликация...');
$seen  = [];
$pairs = [];

foreach ($dbRows as $r) {
    $key = BrandNormalizer::normalizeArticle($r['article']) . '|' . BrandNormalizer::normalize($r['brand']);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $pairs[] = ['article' => $r['article'], 'brand' => $r['brand']];
    }
}

$csvNew = 0;
foreach ($csvPairs as $r) {
    $key = BrandNormalizer::normalizeArticle($r['article']) . '|' . BrandNormalizer::normalize($r['brand']);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $pairs[] = ['article' => $r['article'], 'brand' => $r['brand']];
        $csvNew++;
    }
}

logger('  Всего уникальных пар: ' . count($pairs));
logger('  Новых из CSV: ' . $csvNew);

// ─── 4. UMAPI → INSERT ───────────────────────────────────────
logger('Шаг 4: запросы к UMAPI...');
$total   = count($pairs);
$inserts = 0;
$errors  = 0;
$empty   = 0;

foreach ($pairs as $i => $pair) {
    $artNorm   = BrandNormalizer::normalizeArticle($pair['article']);
    $brandNorm = BrandNormalizer::normalize($pair['brand']);

    $url = UMAPI_BASE . '/' . urlencode($artNorm) . '/' . urlencode($brandNorm) . '/false';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'X-App-Key: ' . UMAPI_KEY,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        $errors++;
        if ($errors <= 3) {
            logger("  ❌ [$artNorm / $brandNorm] HTTP=$httpCode err=$curlErr");
        }
        usleep(DELAY_US);
        continue;
    }

    $data    = json_decode($response, true);
    $analogs = $data['data'] ?? $data['analogs'] ?? $data ?? [];

    if (empty($analogs) || !is_array($analogs)) {
        $empty++;
        usleep(DELAY_US);
        continue;
    }

    $values = [];
    foreach ($analogs as $a) {
        $crossArt   = BrandNormalizer::normalizeArticle($a['article'] ?? '');
        $crossBrand = BrandNormalizer::normalize($a['brand'] ?? '');
        $weight     = intval($a['weight'] ?? 0);
        $title      = mb_substr($a['title'] ?? '', 0, 500);

        if (empty($crossArt) || empty($crossBrand)) continue;

        $values[] = sprintf(
            "('%s','%s','%s','%s',%d,'%s',NOW())",
            $helper->forSql($artNorm),
            $helper->forSql($brandNorm),
            $helper->forSql($crossArt),
            $helper->forSql($crossBrand),
            $weight,
            $helper->forSql($title)
        );
    }

    if (!empty($values)) {
        $sql = "INSERT IGNORE INTO b_cross_index 
                (article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm, weight, title_keywords, created_at)
                VALUES " . implode(',', $values);
        $db->query($sql);
        $inserts += count($values);
    }

    if (($i + 1) % 50 === 0) {
        logger("  Прогресс: " . ($i + 1) . "/$total, вставлено $inserts связей");
    }

    usleep(DELAY_US);
}

logger("========================================");
logger("ГОТОВО.");
logger("Всего пар:         $total");
logger("Вставлено связей:  $inserts");
logger("Пустых ответов:    $empty");
logger("Ошибок:            $errors");
logger("Лог: $logFile");