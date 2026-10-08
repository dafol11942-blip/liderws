<?php
/**
 * Диагностика: почему на странице заказа нет формы оплаты / блока
 * "Оплатить картой онлайн" (getOrderOnlinePayForms / getOrderSwitchablePaySystems,
 * local/php_interface/include/order_actions.php).
 *
 * Запуск: php local/scripts/diag_pay_switch.php <ID заказа>
 */
$_SERVER["DOCUMENT_ROOT"] = "/var/www/u3564357/data/www/liderws.ru";
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('sale');

use Bitrix\Sale\PaySystem\Manager;
use Bitrix\Sale\Services\PaySystem\Restrictions\Manager as RestrictionManager;

$orderId = (int)($argv[1] ?? 0);
$order = $orderId > 0 ? \Bitrix\Sale\Order::load($orderId) : null;
if (!$order) {
    echo "Заказ не найден. Использование: php local/scripts/diag_pay_switch.php <ID>\n";
    exit(1);
}

$flags = function (int $id): string {
    return sprintf('cash=%s online=%s manager=%s',
        isCashPaySystem($id) ? 'Y' : 'N',
        isOnlinePaySystem($id) ? 'Y' : 'N',
        isManagerPaySystem($id) ? 'Y' : 'N');
};

echo "Заказ №{$orderId}: CANCELED={$order->getField('CANCELED')}, STATUS={$order->getField('STATUS_ID')}, оплачен=" . (orderHasPaidPayment($order) ? 'Y' : 'N') . "\n\n";

echo "Оплаты заказа:\n";
foreach ($order->getPaymentCollection() as $payment) {
    $psId = (int)$payment->getPaymentSystemId();
    $row = getPaySystemRow($psId);
    printf("  #%d PAY_SYSTEM_ID=%d NAME=%s SUM=%s PAID=%s | ACTION_FILE=%s IS_CASH=%s | %s\n",
        $payment->getId(), $psId, $row['NAME'] ?? '?', $payment->getSum(), $payment->isPaid() ? 'Y' : 'N',
        $row['ACTION_FILE'] ?? '?', $row['IS_CASH'] ?? '?', $flags($psId));
}

$unpaid = getOrderUnpaidPayment($order);
echo "\nВсе активные платёжные системы и проверка ограничений для неоплаченной оплаты:\n";
$res = Manager::getList(['filter' => ['=ACTIVE' => 'Y'], 'order' => ['SORT' => 'ASC']]);
while ($ps = $res->fetch()) {
    $severity = '-';
    if ($unpaid) {
        try {
            $severity = (string)RestrictionManager::checkService((int)$ps['ID'], $unpaid);
        } catch (\Throwable $e) {
            $severity = 'ОШИБКА: ' . $e->getMessage();
        }
    }
    printf("  ID=%d %s | ACTION_FILE=%s IS_CASH=%s CODE=%s ENTITY=%s | %s | ограничения (0 = доступна): %s\n",
        $ps['ID'], $ps['NAME'], $ps['ACTION_FILE'], $ps['IS_CASH'], $ps['CODE'] ?? '', $ps['ENTITY_REGISTRY_TYPE'] ?? '',
        $flags((int)$ps['ID']), $severity);
}

echo "\ngetListWithRestrictions: ";
if ($unpaid) {
    try {
        echo implode(', ', array_keys(Manager::getListWithRestrictions($unpaid))) ?: '(пусто)';
    } catch (\Throwable $e) {
        echo 'ОШИБКА: ' . $e->getMessage();
    }
} else {
    echo 'нет неоплаченной оплаты';
}
echo "\n\nИтог: формы оплаты = " . count(getOrderOnlinePayForms($order))
   . ", способы для переключения = " . json_encode(array_column(getOrderSwitchablePaySystems($order), 'NAME'), JSON_UNESCAPED_UNICODE) . "\n";
