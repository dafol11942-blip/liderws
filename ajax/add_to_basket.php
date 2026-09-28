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
    CModule::IncludeModule('catalog');
    $availableQty = 0;
    $rsCatalogProduct = \CCatalogProduct::GetList([], ['ID' => $productId], false, false, ['QUANTITY']);
    if ($arCatalogProduct = $rsCatalogProduct->Fetch()) {
        $availableQty = (int)$arCatalogProduct['QUANTITY'];
    }
    if ($availableQty <= 0) {
        die(json_encode(['status' => 'error', 'message' => 'Товара нет в наличии']));
    }

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

    // Нельзя положить в корзину больше, чем реально есть на складе — ни
    // разово, ни суммарно с тем, что там уже лежит (иначе через несколько
    // добавлений можно превысить остаток, даже если каждый запрос по
    // отдельности укладывался в лимит).
    $clamped = false;
    if ($existItem) {
        $newQuantity = $existItem->getQuantity() + $quantity;
        if ($newQuantity > $availableQty) {
            $newQuantity = $availableQty;
            $clamped = true;
        }
        $existItem->setField('QUANTITY', $newQuantity);
    } else {
        if ($quantity > $availableQty) {
            $quantity = $availableQty;
            $clamped = true;
        }
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
        'message'  => $clamped ? "В наличии только {$availableQty} шт. — добавлено максимум" : 'Товар добавлен в корзину!',
        'clamped'  => $clamped,
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