<?php
/**
 * Стоимость Яндекс Доставки для формы оформления заказа после ввода адреса.
 * Заказ собирается так же, как в order_create_handler.php, но не сохраняется —
 * цену считает сам модуль twinpx.yaexpress (запрос к Яндексу по адресу).
 */
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
// Сюда приходит вся форма оформления — без этого обработчик ниже создал бы заказ.
unset($_POST['confirmorder'], $_REQUEST['confirmorder']);
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/order_create_handler.php';

header('Content-Type: application/json; charset=utf-8');

$respond = function (array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    $respond(['ok' => false, 'error' => 'Обновите страницу и попробуйте снова']);
    return;
}

$deliveryId = (int)($_POST['DELIVERY_ID'] ?? 0);
if (!isYandexExpressDelivery($deliveryId)) {
    $respond(['ok' => false, 'error' => 'Расчёт доступен только для Яндекс Доставки']);
    return;
}

CModule::IncludeModule('sale');
CModule::IncludeModule('catalog');

try {
    $fullBasket = \Bitrix\Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), SITE_ID);
    [$basket] = buildOrderBasketFromSelected($fullBasket, SITE_ID);
    if ($basket->count() == 0) {
        $respond(['ok' => false, 'error' => 'Корзина пуста']);
        return;
    }

    global $USER;
    $userId = $USER->IsAuthorized() ? (int)$USER->GetID() : \CSaleUser::GetAnonymousUserID();
    $order = \Bitrix\Sale\Order::create(SITE_ID, $userId, 'RUB');
    $order->setPersonTypeId(1);
    $order->setBasket($basket);
    $shipment = addCheckoutShipment($order, $basket, $deliveryId);
    applyCheckoutOrderProps($order, $_POST);

    $calc = $shipment ? $shipment->calculateDelivery() : null;
    if (!$calc || !$calc->isSuccess()) {
        $respond([
            'ok' => false,
            'error' => $calc ? (implode('; ', $calc->getErrorMessages()) ?: 'Не удалось рассчитать доставку по этому адресу') : 'Служба доставки недоступна',
        ]);
        return;
    }

    $price = (float)$calc->getPrice();
    $respond([
        'ok' => true,
        'price' => $price,
        'priceFormatted' => formatRub($price),
        'period' => (string)$calc->getPeriodDescription(),
    ]);
} catch (\Throwable $e) {
    $respond(['ok' => false, 'error' => 'Не удалось рассчитать доставку, попробуйте позже']);
}
