<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

CModule::IncludeModule('sale');

header('Content-Type: application/json');

$isMgr = isManager();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$qty = (int)($_GET['quantity'] ?? 0);

$noIdActions = ['clear', 'selectAll', 'stashUnselected'];
if (!in_array($action, ['update', 'delete', 'clear', 'select', 'selectAll', 'stashUnselected']) || (!$id && !in_array($action, $noIdActions))) {
    echo json_encode(['status' => 'error', 'message' => 'bad request']);
    exit;
}

if ($action === 'update' && $qty > 0 && $qty <= 999) {
    CSaleBasket::Update($id, ['QUANTITY' => $qty]);
}

if ($action === 'delete') {
    CSaleBasket::Delete($id);
}

// Чекбокс позиции в корзине хранится как свойство CART_SELECTED ('Y'/'N').
// DELAY_BUY (штатный признак «отложено» в Bitrix) на этом проекте не
// существует как поле D7-сущности \Bitrix\Sale\Internals\Basket ("Unknown
// field definition"), поэтому пришлось завести своё свойство — тем же
// проверенным способом, каким уже пишутся SUPPLIER_* (см.
// local/ajax/order_from_supplier.php).
if ($action === 'select' || $action === 'selectAll') {
    $selected = ($_GET['value'] ?? 'Y') === 'Y';
    $propValue = $selected ? 'Y' : 'N';
    try {
        $basket = \Bitrix\Sale\Basket::loadItemsForFUser(CSaleBasket::GetBasketUserID(), SITE_ID);

        $upsertSelected = function ($basketItem) use ($propValue) {
            $props = $basketItem->getPropertyCollection();
            foreach ($props as $p) {
                if ($p->getField('CODE') === 'CART_SELECTED') {
                    $p->setField('VALUE', $propValue);
                    return;
                }
            }
            $p = $props->createItem();
            $p->setFields(['NAME' => 'Выбрано в корзине', 'CODE' => 'CART_SELECTED', 'VALUE' => $propValue]);
        };

        if ($action === 'select') {
            $basketItem = $basket->getItemById($id);
            if ($basketItem) {
                $upsertSelected($basketItem);
            }
        } else {
            foreach ($basket as $basketItem) {
                $upsertSelected($basketItem);
            }
        }

        $saveResult = $basket->save();
        if (!$saveResult->isSuccess()) {
            echo json_encode(['status' => 'error', 'message' => implode('; ', $saveResult->getErrorMessages())]);
            exit;
        }
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => get_class($e) . ': ' . $e->getMessage()]);
        exit;
    }
}

// «Перейти к оформлению»: на этом проекте sale.order.ajax берёт в заказ все
// строки корзины без исключений (нет рабочего штатного механизма фильтрации
// вроде DELAY_BUY, см. выше) — поэтому неотмеченные чекбоксом позиции перед
// переходом на /order/ временно удаляются из корзины (со снимком данных в
// сессии) и возвращаются обратно при следующем заходе на /cart/, см.
// restoreStashedCartItems() в local/php_interface/init.php и cart/index.php.
if ($action === 'stashUnselected') {
    try {
        $basket = \Bitrix\Sale\Basket::loadItemsForFUser(CSaleBasket::GetBasketUserID(), SITE_ID);
        $stashed = [];
        $toDelete = [];

        foreach ($basket as $basketItem) {
            $itemProps = [];
            foreach ($basketItem->getPropertyCollection() as $p) {
                $itemProps[] = [
                    'NAME'  => $p->getField('NAME'),
                    'CODE'  => $p->getField('CODE'),
                    'VALUE' => $p->getField('VALUE'),
                ];
            }

            $isSelectedItem = true;
            $isSupplierItem = false;
            foreach ($itemProps as $pr) {
                if ($pr['CODE'] === 'CART_SELECTED') $isSelectedItem = ($pr['VALUE'] !== 'N');
                if ($pr['CODE'] === 'SUPPLIER_NAME' && $pr['VALUE'] !== '') $isSupplierItem = true;
            }
            if ($isSelectedItem) continue;

            $bid = $basketItem->getId();
            $stashed[] = [
                'PRODUCT_ID'  => $basketItem->getProductId(),
                'QUANTITY'    => $basketItem->getQuantity(),
                'PRICE'       => $basketItem->getPrice(),
                'CURRENCY'    => $basketItem->getCurrency(),
                'NAME'        => $basketItem->getField('NAME'),
                'IS_SUPPLIER' => $isSupplierItem,
                'PROPS'       => $itemProps,
                'ORDER_META'  => $isSupplierItem ? loadSupplierBasketOrderMeta($bid) : [],
            ];
            $toDelete[] = $bid;
        }

        foreach ($toDelete as $bid) {
            CSaleBasket::Delete($bid);
        }

        if (!empty($stashed)) {
            $existing = $_SESSION['CART_STASHED_ITEMS'] ?? [];
            $_SESSION['CART_STASHED_ITEMS'] = array_merge($existing, $stashed);
        }

        echo json_encode(['status' => 'ok', 'stashedCount' => count($stashed)]);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => get_class($e) . ': ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'clear') {
    $clearRes = CSaleBasket::GetList(
        [],
        ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID],
        false, false, ['ID']
    );
    while ($row = $clearRes->Fetch()) {
        CSaleBasket::Delete($row['ID']);
    }
}

// Пересчёт
$totalSum = 0;
$totalClientSum = 0; // только для менеджера (см. cart-summary__row--client в корзине)
$totalQty = 0;
$totalQtyAll = 0; // счётчик в шапке — все позиции корзины, вне зависимости от чекбоксов
$itemSum = 0;
$itemClientSum = null;

$bRes = CSaleBasket::GetList(
    [],
    ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID]
);

while ($b = $bRes->Fetch()) {
    $qty = (int)$b['QUANTITY'];
    $sum = (float)$b['PRICE'] * $qty;

    // Свойства позиции читаем одним запросом без фильтра по CODE (проверенный
    // рабочий путь, как в sale.basket.basket/lider_style/template.php) —
    // CSaleBasket::GetList() сам поле CART_SELECTED (как и DELAY_BUY) не отдаёт.
    $propsMap = [];
    $propsRes = CSaleBasket::GetPropsList([], ['BASKET_ID' => $b['ID']]);
    while ($pr = $propsRes->Fetch()) {
        $propsMap[$pr['CODE']] = $pr['VALUE'];
    }
    $isSelected = ($propsMap['CART_SELECTED'] ?? 'Y') !== 'N';
    $totalQtyAll += $qty;

    // Клиентская сумма — та же логика, что и в шаблоне корзины: для менеджера
    // у заказных позиций поставщика берём наценку от SUPPLIER_PRICE_BASE, для
    // остального (товар своего склада, обычный покупатель) — как есть.
    $clientSum = $sum;
    if ($isMgr && isset($propsMap['SUPPLIER_PRICE_BASE'])) {
        $clientSum = getClientPrice((float)$propsMap['SUPPLIER_PRICE_BASE']) * $qty;
    }

    // Итоги в сайдбаре считаются только по отмеченным позициям (чекбоксы в
    // корзине).
    if ($isSelected) {
        $totalSum += $sum;
        $totalQty += $qty;
        $totalClientSum += $clientSum;
    }

    if ($b['ID'] == $id) {
        $itemSum = $sum;
        $itemClientSum = $isMgr ? $clientSum : null;
    }
}

$_SESSION['CART_QTY'] = $totalQtyAll; // держим счётчик в шапке (header.php) без запроса к БД

echo json_encode([
    'status' => 'ok',
    'itemSum' => number_format($itemSum, 0, ',', ' ') . ' ₽',
    'itemClientSum' => $itemClientSum !== null ? number_format($itemClientSum, 0, ',', ' ') . ' ₽' : null,
    'totalSum' => number_format($totalSum, 0, ',', ' ') . ' ₽',
    'totalClientSum' => $isMgr ? (number_format($totalClientSum, 0, ',', ' ') . ' ₽') : null,
    'totalQty' => $totalQty,
    'totalQtyAll' => $totalQtyAll,
]);
