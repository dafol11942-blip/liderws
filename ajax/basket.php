<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

CModule::IncludeModule('sale');

header('Content-Type: application/json');

$isMgr = isManager();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$qty = (int)($_GET['quantity'] ?? 0);

$noIdActions = ['clear', 'selectAll'];
if (!in_array($action, ['update', 'delete', 'clear', 'select', 'selectAll']) || (!$id && !in_array($action, $noIdActions))) {
    echo json_encode(['status' => 'error', 'message' => 'bad request']);
    exit;
}

// update/delete раньше действовали на $id без проверки владельца — CSaleBasket::
// Update()/Delete() (старый D6 API) сами такую проверку не делают, поэтому
// любой посетитель мог менять/удалять чужие позиции в корзине, зная её ID
// (см. security review). Как и в action=select/clear ниже —
// сначала грузим корзину ТЕКУЩЕГО fuser'а и действуем только на найденном в
// ней элементе.
if (($action === 'update' && $qty > 0 && $qty <= 999) || $action === 'delete') {
    $ownBasket = \Bitrix\Sale\Basket::loadItemsForFUser(CSaleBasket::GetBasketUserID(), SITE_ID);
    $ownItem = $ownBasket->getItemById($id);
    if ($ownItem) {
        if ($action === 'update') {
            $ownItem->setField('QUANTITY', $qty);
        } else {
            $ownItem->delete();
        }
        $ownBasket->save();
    }
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

// Раньше здесь было action=stashUnselected: неотмеченные чекбоксом позиции
// перед переходом на /order/ физически удалялись из корзины (со снимком в
// $_SESSION) и возвращались обратно при заходе на /cart/. Убрано — снимок в
// PHP-сессии не связан с аккаунтом и «воскрешал» давно снятые с продажи
// позиции при следующем оформлении (в т.ч. на другом устройстве товар пропадал
// вовсе, т.к. сессия per-браузер). Теперь выбор чекбоксом влияет только на то,
// какие позиции попадут в заказ (фильтр по CART_SELECTED — см.
// order_create_handler.php и sale.order.ajax/lider_style/template.php), сама
// корзина при переходе к оформлению не трогается.

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
