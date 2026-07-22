<?php
// === ОБРАБОТЧИК СОЗДАНИЯ ЗАКАЗА ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmorder']) && $_POST['confirmorder'] === 'Y' && check_bitrix_sessid()) {

    CModule::IncludeModule('sale');
    CModule::IncludeModule('catalog');

    $siteId = SITE_ID;
    $userId = $USER->IsAuthorized() ? $USER->GetID() : \CSaleUser::GetAnonymousUserID();

    $order = \Bitrix\Sale\Order::create($siteId, $userId, 'RUB');
    $order->setPersonTypeId(1);

    // Товары из корзины
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), $siteId);
    if ($basket->count() == 0) {
        return;
    }
    $order->setBasket($basket);

    // Доставка
    $deliveryId = (int)($_POST['DELIVERY_ID'] ?? 0);
    if ($deliveryId > 0) {
        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $service = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId);
        if ($service) {
            $shipment->setFields([
                'DELIVERY_ID' => $service->getId(),
                'DELIVERY_NAME' => $service->getName(),
            ]);
            // Привязываем корзину к отгрузке
            $shipmentItemCollection = $shipment->getShipmentItemCollection();
            foreach ($basket as $basketItem) {
                $item = $shipmentItemCollection->createItem($basketItem);
                $item->setQuantity($basketItem->getQuantity());
            }
        }
    }

    // Оплата
    $paymentId = (int)($_POST['PAY_SYSTEM_ID'] ?? 0);
    if ($paymentId > 0) {
        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $payment->setFields(['PAY_SYSTEM_ID' => $paymentId]);
    }

    // Свойства заказа
    $propertyCollection = $order->getPropertyCollection();
    foreach ($propertyCollection as $property) {
        $propId = $property->getPropertyId();
        if (isset($_POST['ORDER_PROP_' . $propId]) && $_POST['ORDER_PROP_' . $propId] !== '') {
            $property->setValue($_POST['ORDER_PROP_' . $propId]);
        }
    }

    // Комментарий
    if (!empty($_POST['ORDER_DESCRIPTION'])) {
        $order->setField('USER_DESCRIPTION', $_POST['ORDER_DESCRIPTION']);
    }

    // Финальный расчёт (цены, суммы оплаты и т.д.)
    $order->doFinalAction(true);

    // Сохраняем
    $result = $order->save();

    if ($result->isSuccess()) {
        $orderId = $result->getId();
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_order.log',
            "=== " . date('Y-m-d H:i:s') . " === ORDER CREATED: #" . $orderId . "\n",
            FILE_APPEND
        );
        LocalRedirect('/order/?ORDER_ID=' . $orderId . '&ORDER_CONFIRMED=Y');
        exit;
    } else {
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_order.log',
            "=== " . date('Y-m-d H:i:s') . " === ORDER ERROR: " . implode('; ', $result->getErrorMessages()) . "\n",
            FILE_APPEND
        );
    }
}
