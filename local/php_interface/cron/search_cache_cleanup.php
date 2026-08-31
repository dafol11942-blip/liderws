<?php
/**
 * Крон: очистка кэша search/ajax.php (b_search_offer_cache)
 * Запуск: каждый час через crontab.
 *
 * В отличие от старого b_supplier_stock, здесь нет is_active — строка либо
 * свежая (используется как есть), либо устаревшая (удаляется целиком).
 * Свежесть для чтения кэша определяет сам search/ajax.php (SEARCH_CACHE_TTL_HOURS),
 * здесь только физическая уборка давно устаревших строк.
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/search_cache_cleanup_' . date('Y-m-d') . '.log';

function clog(string $msg): void {
    global $logFile;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
if ($db->connect_error) {
    clog('DB connect failed: ' . $db->connect_error);
    exit(1);
}
$db->set_charset('utf8mb4');

clog('=== search_cache_cleanup START ===');

// Строки старше 24ч физически удаляем (TTL чтения — 6ч, это запас на случай пиковой нагрузки)
$db->query("DELETE FROM b_search_offer_cache WHERE updated_at < NOW() - INTERVAL 24 HOUR");
clog('Удалено (>24ч): ' . $db->affected_rows . ' строк');

$stat = $db->query(
    "SELECT COUNT(*) total, SUM(quantity < 0) sentinels, COUNT(DISTINCT supplier_code) suppliers,
            COUNT(DISTINCT CONCAT(brand_norm,'|',article_norm)) pairs
     FROM b_search_offer_cache"
)->fetch_assoc();
clog(sprintf('Осталось: %d строк (%d сентинелов), %d поставщиков, %d пар',
    (int)$stat['total'], (int)$stat['sentinels'], (int)$stat['suppliers'], (int)$stat['pairs']));

clog('=== search_cache_cleanup DONE ===');
$db->close();
