<?php
/**
 * Крон: обновляет локальный кэш "KEYZAK → название склада" Армтека
 * (upload/cache/armtek_warehouses.json), которым пользуется
 * ArmtekConnector::warehouseName() при отдаче результатов поиска (иначе в
 * выдаче виден только голый код склада вроде "MOV0007394").
 *
 * Источник — ws_user/getStoreList: ПОЛНЫЙ справочник по всей сети Армтека
 * (у VKORG=4000 — ~65 000 строк / ~8.5МБ, ~30с на скачивание, проверено живым
 * вызовом). Дёргать его на каждый поиск нельзя — поэтому здесь, отдельным
 * кроном, не чаще раза в сутки (частота согласовать при выкладке crontab).
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$logFile = $docRoot . '/upload/logs/armtek_warehouse_sync_' . date('Y-m-d') . '.log';

function clog(string $msg): void {
    global $logFile;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

set_time_limit(180);
ini_set('memory_limit', '256M');

require_once $docRoot . '/local/php_interface/lib/autoload.php';
require_once $docRoot . '/local/php_interface/init.php';

clog('=== armtek_warehouse_sync START ===');

$connector = getSupplierFactory()->get('armtek');
if (!$connector instanceof \Lider\Supplier\ArmtekConnector) {
    clog('ArmtekConnector не зарегистрирован в фабрике — выход');
    exit(1);
}

$t0  = microtime(true);
$map = $connector->fetchWarehouseMap();
$dt  = round(microtime(true) - $t0, 1);

if (empty($map)) {
    clog("fetchWarehouseMap() вернул пусто за {$dt}с — не трогаю существующий кэш-файл (лучше устаревшие названия, чем никаких)");
    exit(1);
}

$cacheDir  = $docRoot . '/upload/cache';
$cacheFile = $cacheDir . '/armtek_warehouses.json';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// Пишем во временный файл и переименовываем — читатели (ArmtekConnector в
// живых веб-запросах) не должны увидеть частично записанный JSON.
$tmpFile = $cacheFile . '.tmp';
$json    = json_encode($map, JSON_UNESCAPED_UNICODE);
if ($json === false || @file_put_contents($tmpFile, $json) === false || !@rename($tmpFile, $cacheFile)) {
    clog('Не удалось записать кэш-файл ' . $cacheFile);
    exit(1);
}

clog("OK: {$dt}с, складов в справочнике=" . count($map) . ", файл=" . round(strlen($json) / 1024) . "КБ");
