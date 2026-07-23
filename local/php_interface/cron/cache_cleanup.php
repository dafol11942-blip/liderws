<?php
/**
 * Крон: инвалидация и очистка кэша поставщиков
 * Запуск: каждые 30 минут через crontab
 *
 * Логика:
 *  1. is_active=0 для строк старше 4 часов
 *  2. DELETE строк старше 48 часов
 *  3. Статистика в лог
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/cache_cleanup_' . date('Y-m-d') . '.log';

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

clog('=== cache_cleanup START ===');

// Шаг 1: деактивируем строки старше 4 часов
$db->query("UPDATE b_supplier_stock SET is_active = 0
            WHERE is_active = 1 AND last_updated < NOW() - INTERVAL 4 HOUR");
clog('Деактивировано (>4ч):  ' . $db->affected_rows . ' строк');

// Шаг 2: удаляем строки старше 48 часов
$db->query("DELETE FROM b_supplier_stock
            WHERE last_updated < NOW() - INTERVAL 48 HOUR");
clog('Удалено (>48ч):        ' . $db->affected_rows . ' строк');

// Шаг 3: статистика по поставщикам
$stat = $db->query(
    "SELECT supplier_code,
            SUM(is_active=1) as active_cnt,
            COUNT(*)         as total_cnt
     FROM b_supplier_stock
     GROUP BY supplier_code
     ORDER BY active_cnt DESC"
);
$totalActive = 0;
while ($row = $stat->fetch_assoc()) {
    clog(sprintf('  %-15s active=%-5d total=%d',
        $row['supplier_code'], $row['active_cnt'], $row['total_cnt']));
    $totalActive += (int)$row['active_cnt'];
}
clog('ИТОГО активных: ' . $totalActive);
clog('=== cache_cleanup DONE ===');

$db->close();
