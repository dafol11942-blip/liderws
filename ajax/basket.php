<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

CModule::IncludeModule('sale');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$qty = (int)($_GET['quantity'] ?? 0);

if (!in_array($action, ['update', 'delete', 'clear']) || (!$id && $action !== 'clear')) {
    echo json_encode(['status' => 'error', 'message' => 'bad request']);
    exit;
}

if ($action === 'update' && $qty > 0 && $qty <= 999) {
    CSaleBasket::Update($id, ['QUANTITY' => $qty]);
}

if ($action === 'delete') {
    CSaleBasket::Delete($id);
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
$totalQty = 0;
$itemSum = 0;

$bRes = CSaleBasket::GetList(
    [],
    ['FUSER_ID' => CSaleBasket::GetBasketUserID(), 'ORDER_ID' => 'NULL', 'LID' => SITE_ID]
);

while ($b = $bRes->Fetch()) {
    $sum = (float)$b['PRICE'] * (int)$b['QUANTITY'];
    $totalSum += $sum;
    $totalQty += (int)$b['QUANTITY'];
    if ($b['ID'] == $id) {
        $itemSum = $sum;
    }
}

echo json_encode([
    'status' => 'ok',
    'itemSum' => number_format($itemSum, 0, ',', ' ') . ' ₽',
    'totalSum' => number_format($totalSum, 0, ',', ' ') . ' ₽',
    'totalQty' => $totalQty,
]);
