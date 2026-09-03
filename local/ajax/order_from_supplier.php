<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Только POST']));
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/OfferTokenStore.php');
use Lider\Search\OfferTokenStore;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$article      = trim($data['article'] ?? '');
$brand        = trim($data['brand'] ?? '');
$supplier     = trim($data['supplier'] ?? 'moskvorechie');
$quantity     = max(1, (int)($data['quantity'] ?? 1));
$taskId       = trim($data['task'] ?? '');
$offerToken   = trim($data['offer_token'] ?? '');

if (empty($article)) {
    die(json_encode(['success' => false, 'message' => 'Не указан артикул']));
}

// Токен предложения — единственный надёжный источник настоящего названия склада
// (клиенту оно не передаётся, если он не из группы «Менеджер», см. search/ajax.php).
// Также используется, чтобы не дать заказать больше, чем реально было в наличии
// на момент поиска.
$resolvedOffer = ($taskId && $offerToken) ? OfferTokenStore::resolve($taskId, $offerToken) : null;
if ($resolvedOffer) {
    $supplier = $resolvedOffer['supplier'] ?: $supplier;
    $quantity = min($quantity, max(1, (int)$resolvedOffer['quantity']));
} else {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] offer_token не найден (task=' . $taskId . '), склад не будет сохранён' . "\n",
        FILE_APPEND
    );
}

try {
    CModule::IncludeModule('sale');
    CModule::IncludeModule('catalog');

    $factory   = getSupplierFactory();
    $connector = $factory->get($supplier);
    if (!$connector) die(json_encode(['success' => false, 'message' => 'Поставщик не найден']));

    $item = $connector->getDetail($article, $brand);
    if (!$item) die(json_encode(['success' => false, 'message' => 'Товар не найден']));

    $realWarehouse = $resolvedOffer['warehouse'] ?? ($item->warehouse ?? '');
    $deliveryDays  = $resolvedOffer['delivery_days']  ?? ($item->deliveryDays ?? -1);
    $deliveryLabel = $resolvedOffer['delivery_label'] ?? ($item->deliveryLabel ?? null);
    $deliveryTime  = $resolvedOffer['delivery_time']  ?? ($item->deliveryTimeLabel ?? null);
    $qtyAvail      = $resolvedOffer['quantity'] ?? ($item->quantity ?? $quantity);

    require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');
    $price = getDisplayPrice($item->price);

    // Служебный товар
    $xmlId = 'SUPPLIER_ORDER_' . $supplier;
    $exist = CIBlockElement::GetList([], ['IBLOCK_ID' => 42, 'XML_ID' => $xmlId, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID']);
    $productId = ($el = $exist->Fetch()) ? $el['ID'] : 0;

    if (!$productId) {
        $el = new CIBlockElement;
        $productId = $el->Add([
            'IBLOCK_ID' => 42, 'NAME' => 'Заказная позиция (' . $connector->getName() . ')',
            'XML_ID' => $xmlId, 'ACTIVE' => 'Y',
        ]);
    }
    if (!$productId) die(json_encode(['success' => false, 'message' => 'Ошибка создания товара']));

    // Добавляем в корзину
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );

    $existItem = null;
    foreach ($basket as $basketItem) {
        $props = $basketItem->getPropertyCollection();
        if ($basketItem->getProductId() == $productId
            && in_array($article, (array)$props->getItemValues('SUPPLIER_ARTICLE'))
            && in_array($brand, (array)$props->getItemValues('SUPPLIER_BRAND'))
        ) { $existItem = $basketItem; break; }
    }

    // Создаёт свойство, если его ещё нет, иначе обновляет значение существующего.
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

    if ($existItem) {
        $existItem->setField('QUANTITY', $existItem->getQuantity() + $quantity);
        // Мы только что получили свежие данные от поставщика — обновляем TTL и срок
        // доставки/остаток позиции, чтобы повторное добавление считалось подтверждением
        // актуальности (см. TTL корзины 12ч в /cart/).
        $props = $existItem->getPropertyCollection();
        $upsertProp($props, 'SUPPLIER_DELIVERY_DAYS',  'Срок доставки (дн)', $deliveryDays);
        $upsertProp($props, 'SUPPLIER_DELIVERY_LABEL', 'Срок доставки',      (string)$deliveryLabel);
        $upsertProp($props, 'SUPPLIER_DELIVERY_TIME',  'Время доставки',     (string)$deliveryTime);
        $upsertProp($props, 'SUPPLIER_QTY_AVAIL',      'Остаток у поставщика', $qtyAvail);
        $upsertProp($props, 'SUPPLIER_ADDED_AT',       'Подтверждено',       (string)time());
    } else {
        $basketItem = $basket->createItem('catalog', $productId);
        $basketItem->setFields([
            'QUANTITY' => $quantity, 'CURRENCY' => 'RUB',
            'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
            'PRICE' => $price, 'CUSTOM_PRICE' => 'Y', 'NAME' => $item->name,
        ]);

        $props = $basketItem->getPropertyCollection();
        $list = [
            ['SUPPLIER_ARTICLE',       'Артикул',            $article],
            ['SUPPLIER_BRAND',         'Бренд',              $brand],
            ['SUPPLIER_NAME',          'Поставщик',          $supplier],
            ['SUPPLIER_WAREHOUSE',     'Склад',              $realWarehouse],
            ['SUPPLIER_TITLE',         'Название',           $item->name],
            ['SUPPLIER_PRICE_BASE',    'Закупочная цена',    $item->price],
            ['SUPPLIER_DELIVERY_DAYS', 'Срок доставки (дн)', $deliveryDays],
            ['SUPPLIER_DELIVERY_LABEL','Срок доставки',      (string)$deliveryLabel],
            ['SUPPLIER_DELIVERY_TIME', 'Время доставки',     (string)$deliveryTime],
            ['SUPPLIER_QTY_AVAIL',     'Остаток у поставщика', $qtyAvail],
            ['SUPPLIER_ADDED_AT',      'Подтверждено',       (string)time()],
        ];
        foreach ($list as [$code, $name, $value]) {
            $p = $props->createItem();
            $p->setFields(['NAME' => $name, 'CODE' => $code, 'VALUE' => $value]);
        }
    }

    $basket->save();
    echo json_encode(['success' => true, 'message' => 'Добавлено в корзину', 'cart_url' => '/personal/cart/']);

} catch (\Throwable $e) {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n", FILE_APPEND
    );
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
