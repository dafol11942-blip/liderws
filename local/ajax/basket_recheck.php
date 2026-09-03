<?php
/**
 * Ревалидация позиции корзины у поставщика (TTL корзины — 12ч, см. /cart/).
 *
 * POST id   — ID позиции корзины (Bitrix\Sale\BasketItem)
 * POST mode — check (сравнить с текущими данными, ничего не меняя, кроме случая
 *             "изменений нет" — тогда TTL молча продлевается) | apply (принять новые
 *             цену/срок/остаток и продлить TTL)
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Только POST']));
}

$id   = (int)($_POST['id'] ?? 0);
$mode = trim($_POST['mode'] ?? 'check');

if (!$id || !in_array($mode, ['check', 'apply'], true)) {
    die(json_encode(['status' => 'error', 'message' => 'Некорректный запрос']));
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

try {
    CModule::IncludeModule('sale');

    // loadItemsForFUser грузит корзину ТОЛЬКО текущего пользователя — если ID
    // принадлежит чужой корзине, getItemById() вернёт null, чужие данные не утекут.
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );

    $basketItem = $basket->getItemById($id);
    if (!$basketItem) {
        die(json_encode(['status' => 'error', 'message' => 'Позиция не найдена']));
    }

    $props = $basketItem->getPropertyCollection();
    // BasketPropertiesCollection не имеет метода getItemValues() — единственный
    // документированный способ прочитать значение по CODE — перебор через getField().
    $getProp = function (string $code) use ($props) {
        foreach ($props as $p) {
            if ($p->getField('CODE') === $code) return $p->getField('VALUE');
        }
        return null;
    };

    $article  = (string)$getProp('SUPPLIER_ARTICLE');
    $brand    = (string)$getProp('SUPPLIER_BRAND');
    $supplier = (string)$getProp('SUPPLIER_NAME');

    if ($article === '' || $supplier === '') {
        die(json_encode(['status' => 'error', 'message' => 'У позиции нет данных поставщика']));
    }

    $factory   = getSupplierFactory();
    $connector = $factory->get($supplier);
    if (!$connector || !$connector->isAvailable()) {
        die(json_encode(['status' => 'error', 'message' => 'Поставщик недоступен']));
    }

    $searchUrl = '/search/?q=' . urlencode($article) . '&brand=' . urlencode($brand) . '&number=' . urlencode($article);

    $freshItem = $connector->getDetail($article, $brand);
    if (!$freshItem || $freshItem->price <= 0 || $freshItem->quantity <= 0) {
        echo json_encode(['status' => 'not_found', 'search_url' => $searchUrl]);
        exit;
    }

    $requestedQty    = $basketItem->getQuantity();
    $oldPrice        = (float)$basketItem->getPrice();
    $oldDeliveryDays = (int)($getProp('SUPPLIER_DELIVERY_DAYS') ?? -1);

    $newPrice         = getDisplayPrice($freshItem->price);
    $newDeliveryDays  = (int)($freshItem->deliveryDays ?? -1);
    $newDeliveryLabel = $freshItem->deliveryLabel;
    $newDeliveryTime  = $freshItem->deliveryTimeLabel;
    $newQtyAvail      = $freshItem->quantity;

    $priceChanged    = abs($newPrice - $oldPrice) > 0.01;
    $deliveryChanged = $newDeliveryDays !== $oldDeliveryDays;
    $qtyInsufficient = $newQtyAvail < $requestedQty;
    $changed         = $priceChanged || $deliveryChanged || $qtyInsufficient;

    // Создаёт свойство, если его ещё нет, иначе обновляет значение существующего —
    // тот же приём, что и в order_from_supplier.php при повторном добавлении позиции.
    $upsertProp = function ($props, string $code, string $name, $value) {
        foreach ($props as $p) {
            if ($p->getField('CODE') === $code) {
                $p->setField('VALUE', $value);
                return;
            }
        }
        $p = $props->createItem();
        $p->setFields(['NAME' => $name, 'CODE' => $code, 'VALUE' => $value]);
    };

    if ($mode === 'check') {
        if (!$changed) {
            $upsertProp($props, 'SUPPLIER_ADDED_AT', 'Подтверждено', (string)time());
            $basket->save();
            echo json_encode(['status' => 'unchanged', 'added_at' => time()]);
            exit;
        }

        echo json_encode([
            'status'        => 'changed',
            'qty_requested' => $requestedQty,
            'previous'      => [
                'price'          => $oldPrice,
                'delivery_days'  => $oldDeliveryDays,
                'delivery_label' => $getProp('SUPPLIER_DELIVERY_LABEL'),
                'delivery_time'  => $getProp('SUPPLIER_DELIVERY_TIME'),
            ],
            'current' => [
                'price'          => $newPrice,
                'delivery_days'  => $newDeliveryDays,
                'delivery_label' => $newDeliveryLabel,
                'delivery_time'  => $newDeliveryTime,
                'qty_avail'      => $newQtyAvail,
            ],
        ]);
        exit;
    }

    // mode === 'apply' — принимаем новые условия
    $finalQty = $qtyInsufficient ? max(1, $newQtyAvail) : $requestedQty;
    $basketItem->setFields(['PRICE' => $newPrice, 'QUANTITY' => $finalQty]);

    $upsertProp($props, 'SUPPLIER_PRICE_BASE',     'Закупочная цена',      $freshItem->price);
    $upsertProp($props, 'SUPPLIER_DELIVERY_DAYS',  'Срок доставки (дн)',  $newDeliveryDays);
    $upsertProp($props, 'SUPPLIER_DELIVERY_LABEL', 'Срок доставки',       (string)$newDeliveryLabel);
    $upsertProp($props, 'SUPPLIER_DELIVERY_TIME',  'Время доставки',      (string)$newDeliveryTime);
    $upsertProp($props, 'SUPPLIER_QTY_AVAIL',      'Остаток у поставщика', $newQtyAvail);
    $upsertProp($props, 'SUPPLIER_ADDED_AT',       'Подтверждено',        (string)time());

    $basket->save();

    echo json_encode([
        'status'    => 'ok',
        'price'     => $newPrice,
        'price_fmt' => number_format($newPrice, 0, ',', ' ') . ' ₽',
        'quantity'  => $finalQty,
        'sum_fmt'   => number_format($newPrice * $finalQty, 0, ',', ' ') . ' ₽',
        'delivery_days'  => $newDeliveryDays,
        'delivery_label' => $newDeliveryLabel,
        'delivery_time'  => $newDeliveryTime,
        'qty_clamped'    => $qtyInsufficient,
        'added_at'       => time(),
    ]);

} catch (\Throwable $e) {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] basket_recheck: ' . $e->getMessage() . "\n", FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
