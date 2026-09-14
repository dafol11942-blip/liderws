<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

CModule::IncludeModule('sale');

header('Content-Type: application/json');

$isMgr = isManager();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$qty = (int)($_GET['quantity'] ?? 0);

if (!in_array($action, ['update', 'delete', 'clear', 'select', 'selectAll']) || (!$id && !in_array($action, ['clear', 'selectAll']))) {
    echo json_encode(['status' => 'error', 'message' => 'bad request']);
    exit;
}

if ($action === 'update' && $qty > 0 && $qty <= 999) {
    CSaleBasket::Update($id, ['QUANTITY' => $qty]);
}

if ($action === 'delete') {
    CSaleBasket::Delete($id);
}

// Чекбокс позиции в корзине: снятая галка = DELAY_BUY 'Y' — штатный признак
// Bitrix «отложено», такие позиции корзина не отправляет на оформление заказа
// (sale.order.ajax сам исключает их при сборе состава заказа). Пишем через
// ORM-таблицу BasketTable напрямую, в обход бизнес-объекта BasketItem —
// его setField('DELAY_BUY', ...) кидает ArgumentOutOfRangeException на этом
// проекте (видимо, поле помечено недоступным для ручного редактирования на
// уровне бизнес-логики), а прямая запись в колонку БД работает как обычно.
if ($action === 'select' || $action === 'selectAll') {
    $selected = ($_GET['value'] ?? 'Y') === 'Y';
    $delayValue = $selected ? 'N' : 'Y';
    try {
        if ($action === 'select') {
            $upd = \Bitrix\Sale\Internals\BasketTable::update($id, ['DELAY_BUY' => $delayValue]);
            if (!$upd->isSuccess()) {
                echo json_encode(['status' => 'error', 'message' => 'update id=' . $id . ': ' . implode('; ', $upd->getErrorMessages())]);
                exit;
            }
        } else {
            $allRes = CSaleBasket::GetList(
                [],
                ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID],
                false, false, ['ID']
            );
            while ($row = $allRes->Fetch()) {
                $upd = \Bitrix\Sale\Internals\BasketTable::update($row['ID'], ['DELAY_BUY' => $delayValue]);
                if (!$upd->isSuccess()) {
                    echo json_encode(['status' => 'error', 'message' => 'update id=' . $row['ID'] . ': ' . implode('; ', $upd->getErrorMessages())]);
                    exit;
                }
            }
        }
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
    $isSelected = ($b['DELAY_BUY'] ?? 'N') !== 'Y';
    $totalQtyAll += $qty;

    // Клиентская сумма — та же логика, что и в шаблоне корзины
    // (sale.basket.basket/lider_style/template.php): для менеджера у заказных
    // позиций поставщика берём наценку от SUPPLIER_PRICE_BASE, для остального
    // (товар своего склада, обычный покупатель) — как есть, наравне с закупочной.
    $clientSum = $sum;
    if ($isMgr) {
        $priceBase = null;
        $propsRes = CSaleBasket::GetPropsList([], ['BASKET_ID' => $b['ID'], 'CODE' => 'SUPPLIER_PRICE_BASE']);
        if ($pr = $propsRes->Fetch()) {
            $priceBase = (float)$pr['VALUE'];
        }
        if ($priceBase !== null) {
            $clientSum = getClientPrice($priceBase) * $qty;
        }
    }

    // Итоги в сайдбаре считаются только по отмеченным позициям (чекбоксы в
    // корзине) — неотмеченные помечены DELAY_BUY='Y' и не идут в заказ.
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
