<?php
/**
 * Действия покупателя над уже оформленным заказом: онлайн-оплата и отмена.
 * Общие для страницы "Заказ оформлен" (sale.order.ajax/lider_style), деталей
 * заказа (sale.personal.order/modern/detail.php) и списка заказов
 * (sale.personal.order.list/modern) — правила одни на все три места.
 */

use Bitrix\Sale\Order;

if (!function_exists('orderHasSupplierItems')) {
    /** Есть позиция "под заказ" у поставщика (свойство корзины SUPPLIER_NAME). */
    function orderHasSupplierItems(Order $order): bool
    {
        foreach ($order->getBasket() as $basketItem) {
            foreach ($basketItem->getPropertyCollection() as $p) {
                if ($p->getField('CODE') === 'SUPPLIER_NAME' && (string)$p->getField('VALUE') !== '') {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('orderHasPaidPayment')) {
    /** Оплачен целиком или хотя бы частично — деньги уже у магазина. */
    function orderHasPaidPayment(Order $order): bool
    {
        if ($order->isPaid()) return true;
        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isPaid()) return true;
        }
        return false;
    }
}

// Платёжная система "Без оплаты (менеджер)" — видна и доступна только группе
// менеджеров, работает в обход правил для заказного товара (создаётся
// скриптом local/scripts/add_manager_pay_system.php).
if (!defined('MANAGER_PAY_SYSTEM_CODE')) define('MANAGER_PAY_SYSTEM_CODE', 'manager_no_payment');

if (!function_exists('getPaySystemRow')) {
    function getPaySystemRow(int $paySystemId): ?array
    {
        static $cache = [];
        if ($paySystemId <= 0) return null;
        if (!array_key_exists($paySystemId, $cache)) {
            $row = \Bitrix\Sale\PaySystem\Manager::getById($paySystemId);
            $cache[$paySystemId] = $row ?: null;
        }
        return $cache[$paySystemId];
    }
}

if (!function_exists('isManagerPaySystem')) {
    function isManagerPaySystem(int $paySystemId): bool
    {
        $ps = getPaySystemRow($paySystemId);
        return $ps && (string)($ps['CODE'] ?? '') === MANAGER_PAY_SYSTEM_CODE;
    }
}

if (!function_exists('isCashPaySystem')) {
    /**
     * Оплата наличными (флаг IS_CASH платёжной системы или обработчик cash).
     * Менеджерская "Без оплаты" наличной не считается — на неё правила для
     * заказного товара не распространяются.
     */
    function isCashPaySystem(int $paySystemId): bool
    {
        $ps = getPaySystemRow($paySystemId);
        if (!$ps || isManagerPaySystem($paySystemId)) return false;
        return ($ps['IS_CASH'] ?? 'N') === 'Y' || ($ps['ACTION_FILE'] ?? '') === 'cash';
    }
}

if (!function_exists('isOnlinePaySystem')) {
    /** Онлайн-оплата картой (Альфа-Банк и т.п.) — всё, что не наличные и не менеджерская. */
    function isOnlinePaySystem(int $paySystemId): bool
    {
        $ps = getPaySystemRow($paySystemId);
        return $ps && !isManagerPaySystem($paySystemId) && !isCashPaySystem($paySystemId)
            && ($ps['ACTION_FILE'] ?? '') !== 'inner';
    }
}

if (!function_exists('getOrderPaymentHoldDeadline')) {
    /** Unix-время, до которого надо оплатить заказ (окно оплаты), или null. */
    function getOrderPaymentHoldDeadline(int $orderId): ?int
    {
        if ($orderId <= 0) return null;
        try {
            $row = \Bitrix\Main\Application::getConnection()->query(
                "SELECT UNIX_TIMESTAMP(DEADLINE) AS TS FROM b_supplier_order_payment_hold
                 WHERE ORDER_ID = {$orderId} AND DISPATCHED = 0 AND CANCELED = 0"
            )->fetch();
        } catch (\Throwable $e) {
            return null;
        }
        return $row ? (int)$row['TS'] : null;
    }
}

if (!function_exists('getOrderUnpaidPayment')) {
    function getOrderUnpaidPayment(Order $order): ?\Bitrix\Sale\Payment
    {
        foreach ($order->getPaymentCollection() as $payment) {
            if (!$payment->isPaid() && !$payment->isInner()) return $payment;
        }
        return null;
    }
}

if (!function_exists('getOrderSwitchablePaySystems')) {
    /**
     * Онлайн-способы оплаты, на которые покупатель может переключить заказ
     * с наличных (с учётом ограничений платёжных систем — по доставке и т.п.).
     * Пусто, если заказ оплачен/отменён или уже оплачивается онлайн.
     */
    function getOrderSwitchablePaySystems(Order $order): array
    {
        if ($order->getField('CANCELED') === 'Y' || orderHasPaidPayment($order)) return [];
        $payment = getOrderUnpaidPayment($order);
        if (!$payment) return [];
        $currentId = (int)$payment->getPaymentSystemId();
        if (isOnlinePaySystem($currentId) || isManagerPaySystem($currentId)) return [];

        $result = [];
        try {
            $available = \Bitrix\Sale\PaySystem\Manager::getListWithRestrictions($payment);
        } catch (\Throwable $e) {
            $available = [];
        }
        foreach ($available as $ps) {
            $id = (int)($ps['ID'] ?? 0);
            if ($id <= 0 || $id === $currentId || ($ps['ACTIVE'] ?? 'Y') !== 'Y' || !isOnlinePaySystem($id)) continue;
            $logo = (int)($ps['LOGOTIP'] ?? 0) > 0 ? CFile::GetFileArray((int)$ps['LOGOTIP']) : null;
            $result[] = [
                'ID' => $id,
                'NAME' => (string)($ps['NAME'] ?? ''),
                'LOGO' => is_array($logo) ? (string)($logo['SRC'] ?? '') : '',
            ];
        }
        return $result;
    }
}

if (!function_exists('changeOrderPaySystem')) {
    /** Переключение неоплаченной оплаты заказа на онлайн-способ (с перепроверкой на сервере). */
    function changeOrderPaySystem(Order $order, int $paySystemId): \Bitrix\Main\Result
    {
        $result = new \Bitrix\Main\Result();
        $allowedIds = array_column(getOrderSwitchablePaySystems($order), 'ID');
        $payment = getOrderUnpaidPayment($order);
        if (!$payment || !in_array($paySystemId, $allowedIds, true)) {
            $result->addError(new \Bitrix\Main\Error('Этот способ оплаты недоступен для заказа'));
            return $result;
        }
        $ps = getPaySystemRow($paySystemId);
        $setResult = $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemId,
            'PAY_SYSTEM_NAME' => (string)($ps['NAME'] ?? ''),
        ]);
        if (!$setResult->isSuccess()) {
            $result->addErrors($setResult->getErrors());
            return $result;
        }
        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            $result->addErrors($saveResult->getErrors());
        }
        return $result;
    }
}

if (!function_exists('handleCustomerPaySystemChangeRequest')) {
    /**
     * POST change_pay_system=<ID платёжной системы> + pay_order=<ID заказа> со
     * страницы заказа. Только владелец заказа. После смены — обратно на
     * страницу заказа к форме оплаты (#pay).
     */
    function handleCustomerPaySystemChangeRequest(): void
    {
        global $USER, $APPLICATION;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['change_pay_system']) || !check_bitrix_sessid()) {
            return;
        }
        $orderId = (int)($_POST['pay_order'] ?? 0);
        $order = $orderId > 0 ? Order::load($orderId) : null;
        if (!$order || !$USER->IsAuthorized() || (int)$order->getUserId() !== (int)$USER->GetID()) {
            $status = 'Заказ не найден';
        } else {
            $changeResult = changeOrderPaySystem($order, (int)$_POST['change_pay_system']);
            $status = $changeResult->isSuccess() ? 'ok' : implode('; ', $changeResult->getErrorMessages());
        }
        LocalRedirect($APPLICATION->GetCurPageParam('pay_change=' . urlencode($status), ['pay_change', 'order_cancel', 'order_cancel_id']) . '#pay');
    }
}

if (!function_exists('isYandexExpressDelivery')) {
    /**
     * Служба модуля twinpx.yaexpress ("Экспресс-доставка от Яндекс Доставка").
     * Модуль работает только с предоплатой на сайте и сам не проверяет оплату
     * при оформлении через наш order_create_handler.php.
     */
    function isYandexExpressDelivery(int $deliveryId): bool
    {
        static $cache = [];
        if ($deliveryId <= 0) return false;
        if (!array_key_exists($deliveryId, $cache)) {
            $d = \Bitrix\Sale\Delivery\Services\Manager::getById($deliveryId);
            $class = mb_strtolower((string)($d['CLASS_NAME'] ?? ''));
            $cache[$deliveryId] = $d && (
                str_contains($class, 'twinpx') || str_contains($class, 'yaexpress')
                || mb_stripos((string)($d['NAME'] ?? ''), 'Яндекс Доставка') !== false
            );
        }
        return $cache[$deliveryId];
    }
}

if (!function_exists('isCourierAddressPropName')) {
    /** Свойство заказа с адресом для курьера — показывается на вкладке "Курьер", а не в контактах. */
    function isCourierAddressPropName(string $name): bool
    {
        return (bool)preg_match('/адрес|улиц|^дом|квартир|подъезд|этаж|домофон|курьер/iu', trim($name));
    }
}

if (!function_exists('isCourierAddressRequiredPropName')) {
    /** Без этих полей курьер не найдёт адрес — обязательны для курьерской доставки. */
    function isCourierAddressRequiredPropName(string $name): bool
    {
        return (bool)preg_match('/адрес|улиц|^дом/iu', trim($name));
    }
}

if (!function_exists('isPickupDelivery')) {
    /** Самовывоз — по имени службы, как и в форме оформления (других признаков у этих служб нет). */
    function isPickupDelivery(int $deliveryId): bool
    {
        if ($deliveryId <= 0) return false;
        $d = \Bitrix\Sale\Delivery\Services\Manager::getById($deliveryId);
        return $d && mb_stripos((string)($d['NAME'] ?? ''), 'самовывоз') !== false;
    }
}

if (!function_exists('getYandexExpressDeliveryId')) {
    /** ID активной службы Яндекс Доставки или 0. */
    function getYandexExpressDeliveryId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = 0;
            foreach (\Bitrix\Sale\Delivery\Services\Manager::getActiveList() as $serviceId => $row) {
                if (isYandexExpressDelivery((int)$serviceId)) { $id = (int)$serviceId; break; }
            }
        }
        return $id;
    }
}

if (!function_exists('getOrderMainShipment')) {
    function getOrderMainShipment(Order $order): ?\Bitrix\Sale\Shipment
    {
        foreach ($order->getShipmentCollection() as $shipment) {
            if (!$shipment->isSystem()) return $shipment;
        }
        return null;
    }
}

if (!function_exists('getOrderDeliveryRequestBlockReason')) {
    /**
     * null — покупатель может оформить Яндекс Доставку уже оформленного заказа;
     * иначе — почему нельзя. Заказной товар от поставщика — когда заказ в статусе
     * SR «Товар готов к выдаче», товар из наличия — когда заказ полностью оплачен.
     */
    function getOrderDeliveryRequestBlockReason(Order $order): ?string
    {
        if ($order->getField('CANCELED') === 'Y') return 'Заказ отменён';
        $status = (string)$order->getField('STATUS_ID');
        if ($status === 'F') return 'Заказ уже выполнен';
        if ($status === 'SX') return 'Заказ отменён поставщиком';
        $shipment = getOrderMainShipment($order);
        if (!$shipment) return 'В заказе нет отгрузки';
        if (isYandexExpressDelivery((int)$shipment->getDeliveryId())) return 'Доставка уже оформлена';
        if ($shipment->isShipped()) return 'Заказ уже выдан';
        if (getYandexExpressDeliveryId() <= 0) return 'Доставка временно недоступна';
        if (orderHasSupplierItems($order)) {
            if ($status !== 'SR') return 'Доставку можно будет оформить, когда товар будет готов к выдаче';
        } elseif (!$order->isPaid()) {
            return 'Доставку можно оформить после полной оплаты заказа';
        }
        return null;
    }
}

if (!function_exists('getOrderCourierAddressProps')) {
    /** Свойства заказа с адресом для курьера (см. isCourierAddressPropName()). */
    function getOrderCourierAddressProps(Order $order): array
    {
        $props = [];
        foreach ($order->getPropertyCollection() as $property) {
            $row = $property->getProperty();
            if (($row['TYPE'] ?? '') === 'LOCATION' || !isCourierAddressPropName((string)$property->getName())) continue;
            $props[] = $property;
        }
        return $props;
    }
}

if (!function_exists('prepareOrderYandexDelivery')) {
    /**
     * Переводит отгрузку заказа на Яндекс Доставку с адресом из $post
     * (ORDER_PROP_<ID>) и ценой, рассчитанной модулем. Заказ НЕ сохраняет —
     * так же считается цена для показа. data: price, period.
     */
    function prepareOrderYandexDelivery(Order $order, array $post): \Bitrix\Main\Result
    {
        $result = new \Bitrix\Main\Result();
        $reason = getOrderDeliveryRequestBlockReason($order);
        if ($reason !== null) {
            $result->addError(new \Bitrix\Main\Error($reason));
            return $result;
        }

        foreach (getOrderCourierAddressProps($order) as $property) {
            $value = trim((string)($post['ORDER_PROP_' . $property->getPropertyId()] ?? ''));
            $required = isCourierAddressRequiredPropName((string)$property->getName())
                || ($property->getProperty()['REQUIRED'] ?? 'N') === 'Y';
            if ($required && $value === '') {
                $result->addError(new \Bitrix\Main\Error('Укажите адрес доставки'));
                return $result;
            }
            $property->setValue($value);
        }
        // Город в форме не выбирается — службы доставки берут его отсюда.
        foreach ($order->getPropertyCollection() as $property) {
            if (($property->getProperty()['TYPE'] ?? '') === 'LOCATION' && (string)$property->getValue() === '') {
                $code = function_exists('getDefaultShopLocationCode') ? getDefaultShopLocationCode() : '';
                if ($code !== '') $property->setValue($code);
            }
        }

        $deliveryId = getYandexExpressDeliveryId();
        $service = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId);
        $shipment = getOrderMainShipment($order);
        $setResult = $shipment->setFields([
            'DELIVERY_ID' => $deliveryId,
            'DELIVERY_NAME' => $service ? $service->getName() : 'Яндекс Доставка',
        ]);
        if (!$setResult->isSuccess()) {
            $result->addErrors($setResult->getErrors());
            return $result;
        }

        $calc = $shipment->calculateDelivery();
        if (!$calc->isSuccess()) {
            $result->addError(new \Bitrix\Main\Error(implode('; ', $calc->getErrorMessages()) ?: 'Не удалось рассчитать доставку по этому адресу'));
            return $result;
        }
        $price = (float)$calc->getPrice();
        $shipment->setBasePriceDelivery($price, true);
        $result->setData(['price' => $price, 'period' => (string)$calc->getPeriodDescription()]);
        return $result;
    }
}

if (!function_exists('getOnlinePaySystemIdForOrder')) {
    /** Способ онлайн-оплаты для доплаты: тот же, что уже в заказе, иначе первый активный. */
    function getOnlinePaySystemIdForOrder(Order $order): int
    {
        foreach ($order->getPaymentCollection() as $payment) {
            $id = (int)$payment->getPaymentSystemId();
            if (isOnlinePaySystem($id)) return $id;
        }
        $res = \Bitrix\Sale\PaySystem\Manager::getList(['filter' => ['=ACTIVE' => 'Y'], 'select' => ['ID'], 'order' => ['SORT' => 'ASC']]);
        while ($row = $res->fetch()) {
            if (isOnlinePaySystem((int)$row['ID'])) return (int)$row['ID'];
        }
        return 0;
    }
}

if (!function_exists('requestOrderYandexDelivery')) {
    /**
     * Оформление Яндекс Доставки покупателем для уже оформленного заказа:
     * отгрузка → Яндекс с ценой модуля, отдельная оплата картой на сумму
     * доставки (модуль работает только с предоплатой), пометка менеджеру.
     */
    function requestOrderYandexDelivery(Order $order, array $post): \Bitrix\Main\Result
    {
        $result = prepareOrderYandexDelivery($order, $post);
        if (!$result->isSuccess()) return $result;
        $price = (float)$result->getData()['price'];

        if ($price > 0) {
            $paySystemId = getOnlinePaySystemIdForOrder($order);
            $paySystem = $paySystemId > 0 ? \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId) : null;
            if (!$paySystem) {
                $result->addError(new \Bitrix\Main\Error('Нет доступного способа онлайн-оплаты'));
                return $result;
            }
            $payment = $order->getPaymentCollection()->createItem($paySystem);
            $payment->setField('SUM', $price);
        }

        $note = date('d.m.Y H:i') . ': покупатель оформил Яндекс Доставку из личного кабинета, стоимость '
            . number_format($price, 2, ',', ' ') . ' ₽ (отдельная оплата картой). Если после оплаты заявка '
            . 'не появилась в «Активных заявках» модуля — создайте её в заказе кнопкой «Экспресс-доставка от Яндекс Доставка».';
        $order->setField('COMMENTS', trim((string)$order->getField('COMMENTS') . "\n" . $note));

        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            $result->addErrors($saveResult->getErrors());
        }
        return $result;
    }
}

if (!function_exists('handleCustomerDeliveryRequest')) {
    /**
     * POST request_delivery=<ID заказа> + ORDER_PROP_<ID> со страницы заказа.
     * Только владелец. Успех — к форме оплаты доставки (#pay), ошибка — к блоку
     * доставки (#delivery) с текстом в ?delivery_request=.
     */
    function handleCustomerDeliveryRequest(): void
    {
        global $USER, $APPLICATION;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['request_delivery']) || !check_bitrix_sessid()) {
            return;
        }
        $orderId = (int)$_POST['request_delivery'];
        $order = $orderId > 0 ? Order::load($orderId) : null;
        if (!$order || !$USER->IsAuthorized() || (int)$order->getUserId() !== (int)$USER->GetID()) {
            $status = 'Заказ не найден';
        } else {
            $requestResult = requestOrderYandexDelivery($order, $_POST);
            $status = $requestResult->isSuccess() ? 'ok' : implode('; ', $requestResult->getErrorMessages());
        }
        LocalRedirect($APPLICATION->GetCurPageParam('delivery_request=' . urlencode($status), ['delivery_request', 'pay_change', 'order_cancel', 'order_cancel_id']) . ($status === 'ok' ? '#pay' : '#delivery'));
    }
}

if (!function_exists('renderYandexAddressSuggest')) {
    /**
     * Подсказки адреса (API Геосаджеста) по Елабуге для полей
     * .courier-address__input[data-suggest="Y"]. $loadApi — подключить скрипт
     * API Карт (если на странице его ещё нет). Без ключа — ничего не выводит.
     */
    function renderYandexAddressSuggest(bool $loadApi): void
    {
        $mapsKey = function_exists('getYandexMapsApiKey') ? getYandexMapsApiKey() : '';
        $suggestKey = function_exists('getYandexSuggestApiKey') ? getYandexSuggestApiKey() : '';
        if ($mapsKey === '' || $suggestKey === '') return;
        if ($loadApi): ?>
<script src="<?= htmlspecialcharsbx('https://api-maps.yandex.ru/2.1/?apikey=' . urlencode($mapsKey) . '&lang=ru_RU&suggest_apikey=' . urlencode($suggestKey)) ?>"></script>
        <?php endif; ?>
<script>
(function () {
    if (typeof ymaps === 'undefined') {
        console.warn('Подсказки адреса: API Яндекс Карт не загрузился');
        return;
    }
    ymaps.ready(function () {
        var provider = {
            // options от SuggestView содержат provider — передавать их в
            // ymaps.suggest нельзя, иначе он вызовет этот же provider (рекурсия).
            suggest: function (request, options) {
                var promise = ymaps.suggest('Елабуга, ' + request, { results: (options && options.results) || 7 });
                promise.then(null, function (err) {
                    console.warn('Подсказки адреса (API Геосаджеста):', err && err.message ? err.message : err);
                });
                return promise;
            }
        };
        document.querySelectorAll('.courier-address__input[data-suggest="Y"]').forEach(function (input) {
            var view = new ymaps.SuggestView(input, { provider: provider, results: 7 });
            view.events.add('select', function (e) {
                input.value = e.get('item').value;
                input.dispatchEvent(new Event('input'));
            });
        });
    });
})();
</script>
        <?php
    }
}

if (!function_exists('getOrderCancelBlockReason')) {
    /**
     * null — покупатель может отменить заказ сам; иначе — почему нельзя
     * (текст для подсказки покупателю).
     */
    function getOrderCancelBlockReason(Order $order): ?string
    {
        if ($order->getField('CANCELED') === 'Y') return 'Заказ уже отменён';
        if ($order->getField('STATUS_ID') === 'F') return 'Заказ уже выполнен';
        if (orderHasPaidPayment($order)) return 'Заказ оплачен — для отмены свяжитесь с магазином';
        if (orderHasSupplierItems($order)) return 'В заказе есть товары под заказ у поставщика — для отмены свяжитесь с магазином';
        return null;
    }
}

if (!function_exists('cancelOrderByCustomer')) {
    /** Отмена покупателем; условия перепроверяются здесь же, на сервере. */
    function cancelOrderByCustomer(Order $order): \Bitrix\Main\Result
    {
        $result = new \Bitrix\Main\Result();
        $reason = getOrderCancelBlockReason($order);
        if ($reason !== null) {
            $result->addError(new \Bitrix\Main\Error($reason));
            return $result;
        }
        $order->setField('CANCELED', 'Y');
        $order->setField('REASON_CANCELED', 'Отменён покупателем в личном кабинете');
        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            $result->addErrors($saveResult->getErrors());
        }
        return $result;
    }
}

if (!function_exists('handleCustomerOrderCancelRequest')) {
    /**
     * POST cancel_order=<ID> из списка/деталей заказа. Доступ — владелец заказа
     * или менеджер. После обработки — редирект на ту же страницу (PRG), итог
     * показывается через ?order_cancel=ok|<текст ошибки>.
     */
    function handleCustomerOrderCancelRequest(): void
    {
        global $USER, $APPLICATION;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cancel_order']) || !check_bitrix_sessid()) {
            return;
        }
        $orderId = (int)$_POST['cancel_order'];
        $order = $orderId > 0 ? Order::load($orderId) : null;
        $isMgr = function_exists('isManager') && isManager();
        if (!$order || (!$isMgr && (!$USER->IsAuthorized() || (int)$order->getUserId() !== (int)$USER->GetID()))) {
            $status = 'Заказ не найден';
        } else {
            $cancelResult = cancelOrderByCustomer($order);
            $status = $cancelResult->isSuccess() ? 'ok' : implode('; ', $cancelResult->getErrorMessages());
        }
        LocalRedirect($APPLICATION->GetCurPageParam('order_cancel=' . urlencode($status) . '&order_cancel_id=' . $orderId, ['order_cancel', 'order_cancel_id']));
    }
}

if (!function_exists('getOrderOnlinePayForms')) {
    /**
     * Формы онлайн-оплаты от обработчиков платёжных систем (Альфа-Банк и т.п.)
     * для всех неоплаченных некэшевых оплат заказа. Каждая запись:
     * ['name' => ..., 'logo' => URL|'' , 'html' => форма|'' , 'error' => текст|''].
     * Доступ к заказу проверяет вызывающий код.
     */
    function getOrderOnlinePayForms(Order $order): array
    {
        $forms = [];
        if ($order->getField('CANCELED') === 'Y') return $forms;
        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isPaid() || $payment->isInner()) continue;
            $paySvc = \Bitrix\Sale\PaySystem\Manager::getObjectById($payment->getPaymentSystemId());
            if (!$paySvc || $paySvc->getField('IS_CASH') === 'Y' || $paySvc->getField('ACTION_FILE') === 'cash') continue;

            $logoFile = (int)$paySvc->getField('LOGOTIP') > 0 ? CFile::GetFileArray((int)$paySvc->getField('LOGOTIP')) : null;
            $form = [
                'name' => (string)$paySvc->getField('NAME'),
                'logo' => is_array($logoFile) ? (string)($logoFile['SRC'] ?? '') : '',
                'html' => '',
                'error' => '',
            ];
            try {
                $initResult = $paySvc->initiatePay($payment, null, \Bitrix\Sale\PaySystem\BaseServiceHandler::STRING);
                if ($initResult->isSuccess()) {
                    $form['html'] = (string)$initResult->getTemplate();
                } else {
                    $form['error'] = implode('; ', $initResult->getErrorMessages());
                }
            } catch (\Throwable $e) {
                $form['error'] = $e->getMessage();
            }
            $forms[] = $form;
        }
        // Банк возвращает покупателя на /order/payment-success/ или
        // /order/payment-fail/ без номера заказа — запоминаем, какой заказ
        // оплачивали последним, чтобы страница возврата его показала.
        if ($forms) {
            $_SESSION['LIDER_PAY_ORDER_ID'] = (int)$order->getId();
        }
        return $forms;
    }
}

if (!function_exists('renderOrderOnlinePayForms')) {
    /** Карточки оплаты (разметка + стили, в т.ч. перекрытие стилей модуля rbs.payment). */
    function renderOrderOnlinePayForms(array $forms): void
    {
        if (!$forms) return;
        static $stylesPrinted = false;
        if (!$stylesPrinted) {
            $stylesPrinted = true;
            ?>
<style>
.confirm-pay { max-width: 480px; margin: 0 auto 24px; padding: 20px; border: 1.5px solid var(--border); border-radius: 16px; text-align: center; background: var(--white, #fff); box-sizing: border-box; }
.confirm-pay__head { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-bottom: 14px; }
.confirm-pay__logo { display: block; max-width: 140px; max-height: 44px; object-fit: contain; }
.confirm-pay__title { font-weight: 700; font-size: 15px; }
.confirm-pay__error { color: var(--red); font-size: 14px; margin: 0; }
/* Шаблон модуля rbs.payment (Альфа-Банк) приходит со своими стилями
   (Arial, зелёная кнопка, серая рамка) — приводим к дизайну сайта.
   Специфичность .confirm-pay .rbs__* выше, чем у body .rbs__* модуля. */
.confirm-pay .rbs__wrapper, .confirm-pay .rbs__wrapper * { font-family: inherit; }
.confirm-pay .rbs__wrapper { margin: 0; text-align: center; }
.confirm-pay .rbs__content {
    max-width: none; padding: 0; border: 0; margin: 0 0 12px;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.confirm-pay .rbs__price-string { font-size: 14px; font-weight: 400; color: var(--gray); }
.confirm-pay .rbs__price-string b { display: block; margin-top: 4px; font-size: 26px; font-weight: 800; color: var(--black); }
.confirm-pay .rbs__payment-link {
    display: block; width: 100%; max-width: 320px; margin: 4px 0 0; box-sizing: border-box;
    padding: 14px 24px; border-radius: 14px;
    background: var(--blue) !important; color: #fff !important;
    font-size: 15px; font-weight: 700; line-height: 1.3;
    box-shadow: 0 6px 18px rgba(102,139,234,0.35);
    transition: transform var(--transition), box-shadow var(--transition), filter var(--transition);
}
.confirm-pay .rbs__payment-link:hover { filter: brightness(1.05); transform: translateY(-1px); box-shadow: 0 8px 22px rgba(102,139,234,0.45); }
.confirm-pay .rbs__payment-description { font-size: 12px; color: var(--gray); }
.confirm-pay .rbs__footer { padding-top: 12px; border-top: 1px dashed var(--border); }
.confirm-pay .rbs__description { max-width: none; font-size: 12px; line-height: 1.45; color: var(--gray); text-align: center; }
.confirm-pay .rbs__error-message { font-size: 14px; color: var(--black); }
.confirm-pay .rbs__error-code { font-size: 16px; color: var(--red); }
</style>
            <?php
        }
        foreach ($forms as $cp): ?>
            <div class="confirm-pay">
                <div class="confirm-pay__head">
                    <?php if ($cp['logo'] !== ''): ?>
                    <img class="confirm-pay__logo" src="<?= htmlspecialcharsbx($cp['logo']) ?>" alt="<?= htmlspecialcharsbx($cp['name']) ?>">
                    <?php endif; ?>
                    <div class="confirm-pay__title"><?= htmlspecialcharsbx($cp['name']) ?></div>
                </div>
                <?php if ($cp['html'] !== ''): ?>
                <div class="confirm-pay__body"><?= $cp['html'] ?></div>
                <?php else: ?>
                <p class="confirm-pay__error">Не удалось подготовить оплату<?= $cp['error'] !== '' ? ': ' . htmlspecialcharsbx($cp['error']) : '' ?>. Свяжитесь с нами, и мы поможем оплатить заказ.</p>
                <?php endif; ?>
            </div>
        <?php endforeach;
    }
}
