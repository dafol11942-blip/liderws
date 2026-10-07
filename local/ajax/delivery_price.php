<?php
/**
 * Стоимость Яндекс Доставки для формы оформления заказа после ввода адреса.
 * Заказ собирается так же, как в order_create_handler.php, но не сохраняется —
 * цену считает сам модуль twinpx.yaexpress (запрос к Яндексу по адресу).
 * Причины отказов пишутся в /upload/logs/delivery_price_<дата>.log.
 */
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

// Предупреждения PHP/модулей, выведенные в ответ, ломают JSON — собираем их
// в буфер и пишем в лог вместо ответа.
ob_start();

function logDeliveryPrice(string $message): void
{
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/delivery_price_' . date('Y-m-d') . '.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
        FILE_APPEND
    );
}

function respondDeliveryPrice(array $data): void
{
    $stray = '';
    while (ob_get_level() > 0) {
        $stray .= (string)ob_get_clean();
    }
    if (trim($stray) !== '') {
        logDeliveryPrice('Лишний вывод: ' . mb_substr(trim(strip_tags($stray)), 0, 2000));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    $GLOBALS['deliveryPriceResponded'] = true;
}

register_shutdown_function(function () {
    if (!empty($GLOBALS['deliveryPriceResponded'])) return;
    $err = error_get_last();
    logDeliveryPrice('Ответ не сформирован' . ($err ? ': ' . $err['message'] . ' в ' . $err['file'] . ':' . $err['line'] : ''));
    respondDeliveryPrice(['ok' => false, 'error' => 'Ошибка сервера при расчёте доставки']);
});

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
// Сюда приходит вся форма оформления — без этого обработчик ниже создал бы заказ.
unset($_POST['confirmorder'], $_REQUEST['confirmorder']);
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/order_create_handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    respondDeliveryPrice(['ok' => false, 'error' => 'Обновите страницу и попробуйте снова']);
    return;
}

CModule::IncludeModule('sale');
CModule::IncludeModule('catalog');

$deliveryId = (int)($_POST['DELIVERY_ID'] ?? 0);
if (!isYandexExpressDelivery($deliveryId)) {
    respondDeliveryPrice(['ok' => false, 'error' => 'Расчёт доступен только для Яндекс Доставки']);
    return;
}

try {
    $fullBasket = \Bitrix\Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), SITE_ID);
    [$basket] = buildOrderBasketFromSelected($fullBasket, SITE_ID);
    if ($basket->count() == 0) {
        respondDeliveryPrice(['ok' => false, 'error' => 'Корзина пуста']);
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
        $reason = $calc ? implode('; ', $calc->getErrorMessages()) : 'служба доставки не найдена';
        logDeliveryPrice('Расчёт не удался (доставка ' . $deliveryId . '): ' . $reason);
        respondDeliveryPrice([
            'ok' => false,
            'error' => $reason !== '' ? $reason : 'Не удалось рассчитать доставку по этому адресу',
        ]);
        return;
    }

    $price = (float)$calc->getPrice();
    respondDeliveryPrice([
        'ok' => true,
        'price' => $price,
        'priceFormatted' => formatRub($price),
        'period' => (string)$calc->getPeriodDescription(),
    ]);
} catch (\Throwable $e) {
    logDeliveryPrice('Исключение: ' . get_class($e) . ': ' . $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine());
    respondDeliveryPrice(['ok' => false, 'error' => 'Не удалось рассчитать доставку, попробуйте позже']);
}
