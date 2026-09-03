<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Убедимся, что модуль sale подключён
if (!CModule::IncludeModule('sale')) {
    die(json_encode(['status' => 'error', 'message' => 'Модуль sale не подключён']));
}

$productId = (int)($_REQUEST['id'] ?? 0);
$quantity  = (int)($_REQUEST['quantity'] ?? 1);

if ($productId <= 0) {
    die(json_encode(['status' => 'error', 'message' => 'Не указан ID товара']));
}
if ($quantity <= 0) {
    $quantity = 1;
}

try {
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );

    // Проверяем, есть ли уже такой товар
    $existItem = null;
    foreach ($basket as $basketItem) {
        if ($basketItem->getProductId() == $productId) {
            $existItem = $basketItem;
            break;
        }
    }

    if ($existItem) {
        // Увеличиваем количество
        $existItem->setField('QUANTITY', $existItem->getQuantity() + $quantity);
    } else {
        // Добавляем новый товар
        $item = $basket->createItem('catalog', $productId);
        $item->setFields([
            'QUANTITY'               => $quantity,
            'CURRENCY'               => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
            'LID'                    => \Bitrix\Main\Context::getCurrent()->getSite(),
            'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
        ]);
    }

    $basket->save();

    $cartQty = 0;
    foreach ($basket as $bi) {
        $cartQty += (int)$bi->getQuantity();
    }
    $_SESSION['CART_QTY'] = $cartQty; // держим счётчик в шапке (header.php) без запроса к БД

    echo json_encode([
        'status'   => 'ok',
        'message'  => 'Товар добавлен в корзину!',
        'count'    => count($basket->getBasketItems()),
        'cart_qty' => $cartQty,
        'cart_url' => '/cart/',
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Ошибка: ' . $e->getMessage(),
    ]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');