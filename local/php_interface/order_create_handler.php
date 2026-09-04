<?php
// === ОБРАБОТЧИК СОЗДАНИЯ ЗАКАЗА ===

// Один флаг на все автоматизированные заказы у поставщиков (не только ПартКом).
// true — тестовый режим (поставщик всё проверяет и отвечает, но заказ не уходит
// в работу). false — БОЕВОЙ режим: заказ реально уходит в работу поставщику.
//
// Переключено на боевой режим 2026-09-04 — механика проверена на реальном
// тестовом заказе (№172, flagTest=1, success:true). С этого момента ЛЮБОЙ
// заказ на сайте с позициями от ПартКома реально уйдёт поставщику.
if (!defined('SUPPLIER_ORDER_TEST_MODE')) define('SUPPLIER_ORDER_TEST_MODE', false);

if (!function_exists('logSupplierOrderDispatch')) {
    function logSupplierOrderDispatch(string $message): void
    {
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}

// Пишет один заказ у поставщика (b_supplier_order) и его позиции
// (b_supplier_order_item). Таблицы создаются один раз вручную через Adminer —
// см. local/php_interface/db/supplier_order_tables.sql. Тот же способ доступа к
// БД в рантайме, что уже используется в search/ajax.php (cacheDb()/forSql()) —
// raw SQL через \Bitrix\Main\Application::getConnection(), без незнакомых методов.
if (!function_exists('saveSupplierOrderRecord')) {
    function saveSupplierOrderRecord(int $orderId, string $supplierCode, bool $testMode, string $submitStatus, array $items, array $result): void
    {
        try {
            $db     = \Bitrix\Main\Application::getConnection();
            $helper = $db->getSqlHelper();

            $requestJson  = $helper->forSql(json_encode($items, JSON_UNESCAPED_UNICODE));
            $responseJson = $helper->forSql(json_encode($result['raw'] ?? null, JSON_UNESCAPED_UNICODE));
            $errorMessage = $helper->forSql((string)($result['error'] ?? ''));

            $db->query(
                "INSERT INTO b_supplier_order (ORDER_ID, SUPPLIER_CODE, TEST_MODE, SUBMIT_STATUS, REQUEST_JSON, RESPONSE_JSON, ERROR_MESSAGE)
                 VALUES ({$orderId}, '" . $helper->forSql($supplierCode) . "', " . ($testMode ? 1 : 0) . ",
                         '" . $helper->forSql($submitStatus) . "', '{$requestJson}', '{$responseJson}', '{$errorMessage}')"
            );

            $supplierOrderRow = $db->query('SELECT LAST_INSERT_ID() AS id')->fetch();
            $supplierOrderId  = (int)($supplierOrderRow['id'] ?? 0);
            if (!$supplierOrderId || empty($items)) return;

            $values = [];
            foreach ($items as $item) {
                $basketItemId = (int)($item['basket_item_id'] ?? 0);
                $values[] = "({$supplierOrderId}, " . ($basketItemId ?: 'NULL') . ",
                    '" . $helper->forSql((string)$item['article']) . "',
                    '" . $helper->forSql((string)$item['brand']) . "',
                    " . (int)$item['quantity'] . ",
                    " . (float)$item['price_base'] . ",
                    '" . $helper->forSql((string)$item['reference']) . "')";
            }
            $db->query(
                'INSERT INTO b_supplier_order_item (SUPPLIER_ORDER_ID, BASKET_ITEM_ID, ARTICLE, BRAND, QUANTITY, PRICE, REFERENCE)
                 VALUES ' . implode(',', $values)
            );
        } catch (\Throwable $e) {
            logSupplierOrderDispatch('saveSupplierOrderRecord: ' . $e->getMessage());
        }
    }
}

// Точка интеграции: для каждого поставщика, чьи позиции есть в этом заказе,
// собирает универсальные строки (без завязки на конкретного поставщика) и
// вызывает placeOrder() на его коннекторе, если тот реализует SupplierOrderable.
// Ошибка любого поставщика не должна ломать уже сохранённый наш заказ — только
// логируется.
if (!function_exists('dispatchSupplierOrders')) {
    /** @return bool true, если хотя бы один поставщик подтвердил приём заказа (success=true) */
    function dispatchSupplierOrders(int $orderId, \Bitrix\Sale\Basket $basket, string $buyerName = ''): bool
    {
        // BasketPropertiesCollection не имеет метода getItemValues() — читаем
        // свойство по CODE вручную через перебор коллекции (см. order_from_supplier.php).
        $readProp = function ($props, string $code) {
            foreach ($props as $p) {
                if ($p->getField('CODE') === $code) return $p->getField('VALUE');
            }
            return null;
        };

        $bySupplier = [];
        foreach ($basket as $basketItem) {
            $props        = $basketItem->getPropertyCollection();
            $supplierCode = (string)($readProp($props, 'SUPPLIER_NAME') ?? '');
            if ($supplierCode === '') continue; // товар со своего склада — мимо

            $article = (string)($readProp($props, 'SUPPLIER_ARTICLE') ?? '');
            if ($article === '') continue;

            $orderMeta = json_decode((string)($readProp($props, 'SUPPLIER_ORDER_META') ?? '[]'), true);
            if (!is_array($orderMeta)) $orderMeta = [];

            $bySupplier[$supplierCode][] = [
                'basket_item_id' => $basketItem->getId(),
                'article'        => $article,
                'brand'          => (string)($readProp($props, 'SUPPLIER_BRAND') ?? ''),
                'price_base'     => (float)($readProp($props, 'SUPPLIER_PRICE_BASE') ?? $basketItem->getPrice()),
                'quantity'       => $basketItem->getQuantity(),
                'order_meta'     => $orderMeta,
                'reference'      => $orderId . '_' . $basketItem->getId(),
                'comment'        => 'Заказ №' . $orderId . ($buyerName !== '' ? ', ' . $buyerName : '') . ' с сайта liderws.ru',
            ];
        }

        if (empty($bySupplier)) return false;

        $factory = function_exists('getSupplierFactory') ? getSupplierFactory() : null;
        $anySent = false;

        foreach ($bySupplier as $supplierCode => $items) {
            $connector = $factory ? $factory->get($supplierCode) : null;

            if (!$connector instanceof \Lider\Supplier\SupplierOrderable) {
                logSupplierOrderDispatch("Заказ №{$orderId}: поставщик '{$supplierCode}' не поддерживает автоматическое оформление (SupplierOrderable не реализован), позиций: " . count($items));
                continue;
            }

            try {
                $result = $connector->placeOrder($items, SUPPLIER_ORDER_TEST_MODE);
            } catch (\Throwable $e) {
                $result = ['http_code' => null, 'success' => false, 'raw' => null, 'error' => $e->getMessage()];
            }

            // Подтверждено первым живым ответом ПартКома: успех — это
            // {"success":true,...} в теле ответа, не просто HTTP 200.
            $submitStatus = !empty($result['success']) ? 'sent' : 'error';
            if ($submitStatus === 'sent') $anySent = true;

            saveSupplierOrderRecord($orderId, $supplierCode, SUPPLIER_ORDER_TEST_MODE, $submitStatus, $items, $result);

            // Видно менеджеру в стандартной админке: Заказы → заказ №N → История.
            try {
                CModule::IncludeModule('sale');
                \Bitrix\Sale\OrderHistory::addAction(
                    'SALE_ORDER',
                    $orderId,
                    'SUPPLIER_ORDER_' . strtoupper($supplierCode),
                    $orderId,
                    null,
                    [
                        'SUPPLIER'      => $supplierCode,
                        'TEST_MODE'     => SUPPLIER_ORDER_TEST_MODE,
                        'SUBMIT_STATUS' => $submitStatus,
                        'HTTP_CODE'     => $result['http_code'],
                        'ERROR'         => $result['error'],
                        'RESPONSE'      => $result['raw'],
                    ]
                );
            } catch (\Throwable $e) {
                logSupplierOrderDispatch('OrderHistory::addAction: ' . $e->getMessage());
            }

            logSupplierOrderDispatch("Заказ №{$orderId} → {$supplierCode}: {$submitStatus}, http=" . ($result['http_code'] ?? 'null') . ', err=' . ($result['error'] ?? '-'));
        }

        return $anySent;
    }
}

// Статус SO "Заказан у поставщика" — создать один раз в админке (Настройки →
// Магазин → Заказы → Статусы заказов), код "SO", сортировка между "N/S" (100) и
// "F" (200), напр. 150. Выставляется автоматически, как только хотя бы один
// поставщик подтвердил приём заказа — без ручного вмешательства менеджера, это
// просто отражение уже свершившегося факта. Смена статуса "Получен от
// поставщика" (SR) — намеренно НЕ автоматическая, менеджер переводит её сам,
// глядя на живой статус позиции у поставщика (см. result_modifier.php).
if (!function_exists('advanceOrderStatusAfterSupplierDispatch')) {
    function advanceOrderStatusAfterSupplierDispatch(int $orderId): void
    {
        try {
            $orderObj = \Bitrix\Sale\Order::load($orderId);
            if (!$orderObj) return;
            $orderObj->setField('STATUS_ID', 'SO');
            $saveResult = $orderObj->save();
            if (!$saveResult->isSuccess()) {
                logSupplierOrderDispatch("Заказ №{$orderId}: не удалось выставить статус SO — " . implode('; ', $saveResult->getErrorMessages()));
            }
        } catch (\Throwable $e) {
            logSupplierOrderDispatch("Заказ №{$orderId}: смена статуса на SO упала — " . $e->getMessage());
        }
    }
}

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

        // ФИО покупателя — в комментарий поставщику (менеджеру на площадке
        // поставщика нужно видеть, для кого заказ, не только номер заказа).
        // getPayerName() читает свойство, помеченное "плательщик" в настройках
        // типа плательщика; ORDER_PROP_2 — тот же резерв, что и в чекауте
        // (см. lider_style/template.php), на случай нестандартной конфигурации.
        $buyerName = '';
        try {
            $buyerName = trim((string)$order->getPropertyCollection()->getPayerName());
        } catch (\Throwable $e) {}
        if ($buyerName === '') {
            $buyerName = trim((string)($_POST['ORDER_PROP_2'] ?? ''));
        }

        // Реальные заказы у поставщиков (см. план "Реальный заказ у поставщика").
        // Наш заказ уже сохранён — сбой здесь НЕ должен помешать покупателю
        // увидеть страницу "Спасибо за заказ", только залогироваться.
        try {
            $anySupplierOrderSent = dispatchSupplierOrders($orderId, $basket, $buyerName);
        } catch (\Throwable $e) {
            $anySupplierOrderSent = false;
            logSupplierOrderDispatch("dispatchSupplierOrders упал целиком: " . $e->getMessage());
        }
        if ($anySupplierOrderSent) {
            advanceOrderStatusAfterSupplierDispatch($orderId);
        }

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
