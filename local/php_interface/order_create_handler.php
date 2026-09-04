<?php
// === ОБРАБОТЧИК СОЗДАНИЯ ЗАКАЗА ===

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php'); // isManager()

// Один флаг на все автоматизированные заказы у поставщиков (не только ПартКом).
// true — тестовый режим (поставщик всё проверяет и отвечает, но заказ не уходит
// в работу). false — БОЕВОЙ режим: заказ реально уходит в работу поставщику.
//
// Переключено на боевой режим 2026-09-04 — механика проверена на реальном
// тестовом заказе (№172, flagTest=1, success:true). С этого момента ЛЮБОЙ
// заказ на сайте с позициями от ПартКома реально уйдёт поставщику.
if (!defined('SUPPLIER_ORDER_TEST_MODE')) define('SUPPLIER_ORDER_TEST_MODE', false);

// Не-менеджер оформляет заказ с хотя бы одной позицией от поставщика — заказ
// поставщику не уходит сразу, а ждёт оплаты это количество минут (см. план
// "Оплата в течение 15 минут"). Не наступит — заказ отменяется автоматически
// (local/php_interface/cron/payment_hold_sweep.php).
if (!defined('ORDER_PAYMENT_HOLD_MINUTES')) define('ORDER_PAYMENT_HOLD_MINUTES', 15);

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

// Есть ли в корзине хоть одна позиция "под заказ" у поставщика (свойство
// SUPPLIER_NAME) — та же проверка, что в начале dispatchSupplierOrders(),
// но без реальной отправки: нужна ДО решения, отправлять заказ сразу или
// сначала подождать оплату.
if (!function_exists('basketHasSupplierItems')) {
    function basketHasSupplierItems(\Bitrix\Sale\Basket $basket): bool
    {
        foreach ($basket as $basketItem) {
            foreach ($basketItem->getPropertyCollection() as $p) {
                if ($p->getField('CODE') === 'SUPPLIER_NAME' && (string)$p->getField('VALUE') !== '') {
                    return true;
                }
            }
        }
        return false;
    }
}

// Заводит "удержание" заказа до оплаты (b_supplier_order_payment_hold, создаётся
// один раз вручную через Adminer — см.
// local/php_interface/db/order_payment_hold_table.sql). Дедлайн считает БД
// (NOW() + N минут), не PHP-время — так cron и обработчик оплаты сверяются
// с одним и тем же часами независимо от таймзоны воркера.
if (!function_exists('createOrderPaymentHold')) {
    function createOrderPaymentHold(int $orderId, int $holdMinutes): void
    {
        try {
            $db = \Bitrix\Main\Application::getConnection();
            $db->query(
                'INSERT INTO b_supplier_order_payment_hold (ORDER_ID, DEADLINE, DISPATCHED, CANCELED)
                 VALUES (' . $orderId . ", DATE_ADD(NOW(), INTERVAL {$holdMinutes} MINUTE), 0, 0)"
            );
        } catch (\Throwable $e) {
            logSupplierOrderDispatch("Заказ №{$orderId}: не удалось создать удержание оплаты — " . $e->getMessage());
        }
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

// Общая точка для обоих потребителей удержания оплаты — быстрого пути
// (OnSaleOrderPaid, local/php_interface/init.php) и подстраховки по расписанию
// (local/php_interface/cron/payment_hold_sweep.php). Идемпотентна: DISPATCHED=1
// выставляется в том же запросе, что и сама отправка, повторный вызов на уже
// отправленный заказ просто вернёт false и ничего не пошлёт повторно.
if (!function_exists('dispatchHeldOrderIfPaid')) {
    function dispatchHeldOrderIfPaid(int $orderId): bool
    {
        $db = \Bitrix\Main\Application::getConnection();

        $hold = $db->query(
            "SELECT * FROM b_supplier_order_payment_hold WHERE ORDER_ID = {$orderId} AND DISPATCHED = 0 AND CANCELED = 0"
        )->fetch();
        if (!$hold) return false; // не под удержанием — либо обычный заказ, либо уже разобран

        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order) return false;

        // Payment::isPaid() (не Order::isPaid()) — тот же способ проверки, что
        // уже используется в detail.php при показе оплаты по заказу.
        $isPaid = false;
        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isPaid()) { $isPaid = true; break; }
        }
        if (!$isPaid) return false;

        $basket = $order->getBasket();
        $buyerName = '';
        try {
            $buyerName = trim((string)$order->getPropertyCollection()->getPayerName());
        } catch (\Throwable $e) {}

        $anySent = false;
        try {
            $anySent = dispatchSupplierOrders($orderId, $basket, $buyerName);
        } catch (\Throwable $e) {
            logSupplierOrderDispatch("Заказ №{$orderId}: dispatchSupplierOrders (после оплаты) упал целиком — " . $e->getMessage());
        }
        if ($anySent) {
            advanceOrderStatusAfterSupplierDispatch($orderId);
        }

        $db->query("UPDATE b_supplier_order_payment_hold SET DISPATCHED = 1 WHERE ORDER_ID = {$orderId}");
        logSupplierOrderDispatch("Заказ №{$orderId}: оплата получена, отправлен поставщику (anySent=" . ($anySent ? '1' : '0') . ").");

        return true;
    }
}

// Дедлайн (b_supplier_order_payment_hold.DEADLINE) истёк, а оплаты нет —
// системная отмена заказа. Используем нативное поле CANCELED (не отдельный
// STATUS_ID) — так отменённый заказ сразу понятен в стандартной админке, без
// необходимости заводить там новый статус вручную.
if (!function_exists('cancelUnpaidHeldOrder')) {
    function cancelUnpaidHeldOrder(int $orderId): bool
    {
        $db = \Bitrix\Main\Application::getConnection();

        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order) {
            // заказа больше нет — закрываем удержание, чтобы cron не крутил его вечно
            $db->query("UPDATE b_supplier_order_payment_hold SET CANCELED = 1 WHERE ORDER_ID = {$orderId}");
            return false;
        }

        $order->setField('CANCELED', 'Y');
        $order->setField('REASON_CANCELED', 'Автоматическая отмена: оплата не поступила в течение ' . ORDER_PAYMENT_HOLD_MINUTES . ' минут');
        $saveResult = $order->save();

        if (!$saveResult->isSuccess()) {
            logSupplierOrderDispatch("Заказ №{$orderId}: не удалось отменить по неоплате — " . implode('; ', $saveResult->getErrorMessages()));
            return false;
        }

        $db->query("UPDATE b_supplier_order_payment_hold SET CANCELED = 1 WHERE ORDER_ID = {$orderId}");
        logSupplierOrderDispatch("Заказ №{$orderId}: автоотмена — оплата не поступила в течение " . ORDER_PAYMENT_HOLD_MINUTES . " минут.");
        return true;
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

        // Не-менеджер с хотя бы одной позицией "под заказ" у поставщика — заказ
        // поставщику не уходит сразу: сначала 15 минут на оплату (см. план
        // "Оплата в течение 15 минут"). Менеджеры (оформляют заказы за клиентов
        // по телефону/в офисе) и заказы только своим складом — прежнее поведение,
        // отправка сразу.
        $requiresPaymentHold = !isManager() && basketHasSupplierItems($basket);

        $redirectUrl = '/order/?ORDER_ID=' . $orderId . '&ORDER_CONFIRMED=Y';

        if ($requiresPaymentHold) {
            createOrderPaymentHold($orderId, ORDER_PAYMENT_HOLD_MINUTES);
            logSupplierOrderDispatch("Заказ №{$orderId}: отправка поставщику отложена до оплаты (окно " . ORDER_PAYMENT_HOLD_MINUTES . " мин).");
            $redirectUrl .= '&PAYMENT_HOLD=Y&HOLD_MIN=' . ORDER_PAYMENT_HOLD_MINUTES;
        } else {
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
        }

        LocalRedirect($redirectUrl);
        exit;
    } else {
        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/debug_order.log',
            "=== " . date('Y-m-d H:i:s') . " === ORDER ERROR: " . implode('; ', $result->getErrorMessages()) . "\n",
            FILE_APPEND
        );
    }
}
