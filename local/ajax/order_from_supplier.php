<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Только POST']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$article      = trim($data['article'] ?? '');
$brand        = trim($data['brand'] ?? '');
$supplier     = trim($data['supplier'] ?? 'moskvorechie');
$quantity     = max(1, (int)($data['quantity'] ?? 1));
$deliveryDays = (int)($data['delivery_days'] ?? 0);
$deliveryText = trim($data['delivery_text'] ?? '');

if (empty($article)) {
    die(json_encode(['success' => false, 'message' => 'Не указан артикул']));
}

try {
    CModule::IncludeModule('sale');
    CModule::IncludeModule('catalog');

    $factory   = getSupplierFactory();
    $connector = $factory->get($supplier);
    if (!$connector) die(json_encode(['success' => false, 'message' => 'Поставщик не найден']));

    $item = $connector->getDetail($article, $brand);
    if (!$item) die(json_encode(['success' => false, 'message' => 'Товар не найден']));

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

    if ($existItem) {
        $existItem->setField('QUANTITY', $existItem->getQuantity() + $quantity);
    } else {
        $basketItem = $basket->createItem('catalog', $productId);
        $basketItem->setFields([
            'QUANTITY' => $quantity, 'CURRENCY' => 'RUB',
            'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
            'PRICE' => $price, 'CUSTOM_PRICE' => 'Y', 'NAME' => $item->name,
        ]);

        $props = $basketItem->getPropertyCollection();
        $list = [
            ['SUPPLIER_ARTICLE',       'Артикул',          $article],
            ['SUPPLIER_BRAND',         'Бренд',            $brand],
            ['SUPPLIER_NAME',          'Поставщик',        $supplier],
            ['SUPPLIER_TITLE',         'Название',         $item->name],
            ['SUPPLIER_PRICE_BASE',    'Закупочная цена',  $item->price],
            ['SUPPLIER_DELIVERY_DAYS', 'Срок доставки (дн)', $deliveryDays],
            ['SUPPLIER_DELIVERY_TEXT', 'Доставка',         $deliveryText],
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
