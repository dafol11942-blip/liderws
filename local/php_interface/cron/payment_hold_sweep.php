<?php
/**
 * Крон: разбирает заказы "под удержанием оплаты" (b_supplier_order_payment_hold —
 * см. план "Оплата в течение 15 минут", local/php_interface/order_create_handler.php).
 * Не-менеджер оформил заказ с хотя бы одной позицией от поставщика — заказ не
 * уходит поставщику сразу, ждёт оплаты ORDER_PAYMENT_HOLD_MINUTES минут:
 *   - оплата пришла  → dispatchHeldOrderIfPaid() отправляет заказ поставщику;
 *   - дедлайн истёк, оплаты нет → cancelUnpaidHeldOrder() отменяет заказ
 *     (нативное поле CANCELED='Y', без нового STATUS_ID).
 *
 * Основной триггер на "оплата пришла" — событие OnSaleOrderPaid
 * (local/php_interface/init.php, срабатывает сразу). Этот крон — подстраховка
 * на случай, если событие не сработало, ПЛЮС единственный источник для
 * автоотмены по дедлайну (у неё нет события-триггера, только время).
 * Запуск: раз в 1 минуту через crontab.
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/payment_hold_sweep_' . date('Y-m-d') . '.log';

function clog(string $msg): void {
    global $logFile;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

// Тот же приём бутстрапа, что в supplier_order_status_aggregate.php: session_start()
// внутри prolog_before.php падает, если до него в поток уже что-то выведено.
ob_start();
try {
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    define('NO_KEEP_STATISTIC', true);
    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
    CModule::IncludeModule('sale');
    require_once $docRoot . '/local/php_interface/order_create_handler.php';
    ob_end_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    file_put_contents($logFile, '[' . date('H:i:s') . '] Bitrix bootstrap failed: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    exit(1);
}

$db = \Bitrix\Main\Application::getConnection();
$rows = $db->query(
    'SELECT ORDER_ID, DEADLINE FROM b_supplier_order_payment_hold WHERE DISPATCHED = 0 AND CANCELED = 0'
)->fetchAll();

if (!$rows) {
    clog('Нет заказов под удержанием — выхожу.');
    exit(0);
}

foreach ($rows as $row) {
    $orderId = (int)$row['ORDER_ID'];

    try {
        if (dispatchHeldOrderIfPaid($orderId)) {
            clog("Заказ №{$orderId}: оплачен, отправлен поставщику.");
            continue;
        }

        if (strtotime((string)$row['DEADLINE']) <= time()) {
            if (cancelUnpaidHeldOrder($orderId)) {
                clog("Заказ №{$orderId}: дедлайн истёк без оплаты — отменён.");
            } else {
                clog("Заказ №{$orderId}: дедлайн истёк, но отменить не удалось (см. supplier_orders_*.log).");
            }
        }
    } catch (\Throwable $e) {
        clog("Заказ №{$orderId}: сбой — " . $e->getMessage());
    }
}
