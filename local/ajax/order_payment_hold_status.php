<?php
/**
 * Статус удержания заказа до оплаты (b_supplier_order_payment_hold) — опрашивается
 * с "Спасибо за заказ" (см. sale.order.ajax/lider_style/template.php), чтобы
 * показать покупателю, что реально произошло с заказом после истечения обратного
 * отсчёта: платёж не пришёл (payment_hold_sweep.php отменил) или пришёл и заказ
 * уже ушёл поставщику (OnSalePaymentPaid из init.php). Раз в несколько секунд —
 * лёгкий raw SQL, без загрузки \Bitrix\Sale\Order.
 */
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

$orderId = (int)($_GET['ORDER_ID'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['status' => 'unknown']);
    return;
}

try {
    $db = \Bitrix\Main\Application::getConnection();
    $hold = $db->query(
        "SELECT DISPATCHED, CANCELED FROM b_supplier_order_payment_hold WHERE ORDER_ID = {$orderId}"
    )->fetch();

    if (!$hold) {
        echo json_encode(['status' => 'unknown']);
        return;
    }

    if ((int)$hold['CANCELED'] === 1) {
        $status = 'canceled';
    } elseif ((int)$hold['DISPATCHED'] === 1) {
        $status = 'dispatched';
    } else {
        // Подстраховка: если по какой-то причине запись в hold-таблице не
        // обновилась, но заказ уже помечен отменённым напрямую в админке —
        // покупатель не должен видеть вечный "ожидаем оплату".
        $order = $db->query("SELECT CANCELED FROM b_sale_order WHERE ID = {$orderId}")->fetch();
        $status = ($order && (string)$order['CANCELED'] === 'Y') ? 'canceled' : 'pending';
    }

    echo json_encode(['status' => $status]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'unknown']);
}
