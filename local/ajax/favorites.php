<?php
/**
 * Избранное: своя позиция каталога (TYPE=catalog) и заказная позиция у поставщика
 * (TYPE=supplier, снимок цены/остатка/срока доставки + TTL — см. b_user_favorites,
 * local/php_interface/db/user_favorites_table.sql).
 *
 * POST action=toggle_catalog  {product_id}
 * POST action=toggle_supplier {article, brand, supplier, task, offer_token, quantity}
 * POST action=recheck         {id, mode: check|apply} — ревалидация снимка у поставщика,
 *      логика 1:1 с local/ajax/basket_recheck.php, но над строкой b_user_favorites.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Только POST']));
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/OfferTokenStore.php');
use Lider\Search\OfferTokenStore;

global $USER;

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;
$action = trim($data['action'] ?? '');

if (!$USER->IsAuthorized()) {
    die(json_encode(['status' => 'error', 'code' => 'auth_required', 'message' => 'Нужно войти']));
}
$userId = (int)$USER->GetID();

$db     = \Bitrix\Main\Application::getConnection();
$helper = $db->getSqlHelper();

try {
    if ($action === 'toggle_catalog') {
        $productId = (int)($data['product_id'] ?? 0);
        if ($productId <= 0) die(json_encode(['status' => 'error', 'message' => 'Не указан товар']));

        $exist = $db->query("SELECT ID FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'catalog' AND PRODUCT_ID = {$productId}")->fetch();
        if ($exist) {
            $db->query("DELETE FROM b_user_favorites WHERE ID = " . (int)$exist['ID']);
            $active = false;
        } else {
            $db->query("INSERT INTO b_user_favorites (USER_ID, TYPE, PRODUCT_ID) VALUES ({$userId}, 'catalog', {$productId})");
            $active = true;
        }

        echo json_encode(['status' => 'ok', 'active' => $active, 'count' => getFavoritesCount($userId)]);
        exit;
    }

    if ($action === 'toggle_supplier') {
        $article  = trim($data['article'] ?? '');
        $brand    = trim($data['brand'] ?? '');
        $supplier = trim($data['supplier'] ?? '');
        $taskId   = trim($data['task'] ?? '');
        $offerToken = trim($data['offer_token'] ?? '');

        if ($article === '' || $supplier === '') {
            die(json_encode(['status' => 'error', 'message' => 'Не указан товар']));
        }

        $artSql = $helper->forSql($article);
        $brSql  = $helper->forSql($brand);
        $supSql = $helper->forSql($supplier);
        $exist = $db->query("SELECT ID FROM b_user_favorites WHERE USER_ID = {$userId} AND TYPE = 'supplier' AND ARTICLE = '{$artSql}' AND BRAND = '{$brSql}' AND SUPPLIER = '{$supSql}'")->fetch();

        if ($exist) {
            $db->query("DELETE FROM b_user_favorites WHERE ID = " . (int)$exist['ID']);
            echo json_encode(['status' => 'ok', 'active' => false, 'count' => getFavoritesCount($userId)]);
            exit;
        }

        CModule::IncludeModule('catalog');

        // Тот же порядок, что и в order_from_supplier.php: сначала пробуем уже полученные
        // при поиске данные (без лишнего сетевого запроса к поставщику), и только если токен
        // не найден/истёк — идём за свежими данными напрямую.
        $resolvedOffer = ($taskId && $offerToken) ? OfferTokenStore::resolve($taskId, $offerToken) : null;

        $itemName      = (string)($resolvedOffer['name'] ?? '');
        $basePrice     = (float)($resolvedOffer['price'] ?? 0);
        $deliveryDays  = $resolvedOffer['delivery_days'] ?? null;
        $deliveryLabel = $resolvedOffer['delivery_label'] ?? null;
        $deliveryTime  = $resolvedOffer['delivery_time'] ?? null;
        $qtyAvail      = (int)($resolvedOffer['quantity'] ?? 0);
        $returnable    = array_key_exists('returnable', $resolvedOffer ?? []) ? (bool)$resolvedOffer['returnable'] : null;

        if ($itemName === '' || $basePrice <= 0) {
            $factory   = getSupplierFactory();
            $connector = $factory->get($supplier);
            if (!$connector) die(json_encode(['status' => 'error', 'message' => 'Поставщик не найден']));

            $freshItem = $connector->getDetail($article, $brand);
            if (!$freshItem || $freshItem->price <= 0) {
                die(json_encode(['status' => 'error', 'message' => 'Товар не найден']));
            }
            $itemName      = $freshItem->name;
            $basePrice     = $freshItem->price;
            $deliveryDays  = $freshItem->deliveryDays ?? -1;
            $deliveryLabel = $freshItem->deliveryLabel;
            $deliveryTime  = $freshItem->deliveryTimeLabel;
            $qtyAvail      = $freshItem->quantity ?? 0;
            $returnable    = (bool)($freshItem->returnable ?? true);
        }
        $deliveryDays = $deliveryDays ?? -1;
        $returnable   = $returnable ?? true;

        require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');
        $priceDisplay = getDisplayPrice($basePrice);

        $nameSql  = $helper->forSql($itemName);
        $delLabelSql = $helper->forSql((string)$deliveryLabel);
        $delTimeSql  = $helper->forSql((string)$deliveryTime);
        $now = time();
        // sprintf('%.4F', ...), а не прямая интерполяция float в строку — PHP-каст float→string
        // может уйти в запятую как разделитель под локалью ru_RU (LC_NUMERIC), сломав SQL.
        $basePriceSql    = sprintf('%.4F', $basePrice);
        $priceDisplaySql = sprintf('%.4F', $priceDisplay);

        $db->query(
            "INSERT INTO b_user_favorites
                (USER_ID, TYPE, ARTICLE, BRAND, SUPPLIER, SNAP_NAME, SNAP_PRICE_BASE, SNAP_PRICE_DISPLAY,
                 SNAP_DELIVERY_DAYS, SNAP_DELIVERY_LABEL, SNAP_DELIVERY_TIME, SNAP_QTY_AVAIL, SNAP_RETURNABLE, CONFIRMED_AT)
             VALUES
                ({$userId}, 'supplier', '{$artSql}', '{$brSql}', '{$supSql}', '{$nameSql}', {$basePriceSql}, {$priceDisplaySql},
                 " . (int)$deliveryDays . ", '{$delLabelSql}', '{$delTimeSql}', " . (int)$qtyAvail . ", '" . ($returnable ? 'Y' : 'N') . "', {$now})"
        );

        echo json_encode(['status' => 'ok', 'active' => true, 'count' => getFavoritesCount($userId)]);
        exit;
    }

    if ($action === 'recheck') {
        $id   = (int)($data['id'] ?? 0);
        $mode = trim($data['mode'] ?? 'check');
        if (!$id || !in_array($mode, ['check', 'apply'], true)) {
            die(json_encode(['status' => 'error', 'message' => 'Некорректный запрос']));
        }

        $row = $db->query("SELECT * FROM b_user_favorites WHERE ID = {$id} AND USER_ID = {$userId} AND TYPE = 'supplier'")->fetch();
        if (!$row) die(json_encode(['status' => 'error', 'message' => 'Позиция не найдена']));

        $article  = (string)$row['ARTICLE'];
        $brand    = (string)$row['BRAND'];
        $supplier = (string)$row['SUPPLIER'];

        $factory   = getSupplierFactory();
        $connector = $factory->get($supplier);
        if (!$connector || !$connector->isAvailable()) {
            die(json_encode(['status' => 'error', 'message' => 'Поставщик недоступен']));
        }

        $searchUrl = '/search/?q=' . urlencode($article) . '&brand=' . urlencode($brand) . '&number=' . urlencode($article);

        require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');
        $freshItem = $connector->getDetail($article, $brand);
        if (!$freshItem || $freshItem->price <= 0 || $freshItem->quantity <= 0) {
            echo json_encode(['status' => 'not_found', 'search_url' => $searchUrl]);
            exit;
        }

        $oldPriceDisplay = (float)$row['SNAP_PRICE_DISPLAY'];
        $oldDeliveryDays = (int)$row['SNAP_DELIVERY_DAYS'];

        $newPriceDisplay  = getDisplayPrice($freshItem->price);
        $newDeliveryDays  = (int)($freshItem->deliveryDays ?? -1);
        $newDeliveryLabel = $freshItem->deliveryLabel;
        $newDeliveryTime  = $freshItem->deliveryTimeLabel;
        $newQtyAvail      = $freshItem->quantity;

        $priceChanged    = abs($newPriceDisplay - $oldPriceDisplay) > 0.01;
        $deliveryChanged = $newDeliveryDays !== $oldDeliveryDays;
        $changed         = $priceChanged || $deliveryChanged;

        if ($mode === 'check') {
            if (!$changed) {
                $db->query("UPDATE b_user_favorites SET CONFIRMED_AT = " . time() . " WHERE ID = {$id}");
                echo json_encode(['status' => 'unchanged', 'confirmed_at' => time()]);
                exit;
            }

            echo json_encode([
                'status'   => 'changed',
                'previous' => [
                    'price'          => $oldPriceDisplay,
                    'delivery_days'  => $oldDeliveryDays,
                    'delivery_label' => $row['SNAP_DELIVERY_LABEL'],
                    'delivery_time'  => $row['SNAP_DELIVERY_TIME'],
                ],
                'current' => [
                    'price'          => $newPriceDisplay,
                    'delivery_days'  => $newDeliveryDays,
                    'delivery_label' => $newDeliveryLabel,
                    'delivery_time'  => $newDeliveryTime,
                    'qty_avail'      => $newQtyAvail,
                ],
            ]);
            exit;
        }

        // mode === 'apply' — принимаем новые условия, перезаписываем снимок
        $nameSql     = $helper->forSql($freshItem->name);
        $delLabelSql = $helper->forSql((string)$newDeliveryLabel);
        $delTimeSql  = $helper->forSql((string)$newDeliveryTime);
        $returnable  = (bool)($freshItem->returnable ?? true);
        $basePriceSql    = sprintf('%.4F', $freshItem->price);
        $priceDisplaySql = sprintf('%.4F', $newPriceDisplay);

        $db->query(
            "UPDATE b_user_favorites SET
                SNAP_NAME = '{$nameSql}', SNAP_PRICE_BASE = {$basePriceSql}, SNAP_PRICE_DISPLAY = {$priceDisplaySql},
                SNAP_DELIVERY_DAYS = " . (int)$newDeliveryDays . ", SNAP_DELIVERY_LABEL = '{$delLabelSql}', SNAP_DELIVERY_TIME = '{$delTimeSql}',
                SNAP_QTY_AVAIL = " . (int)$newQtyAvail . ", SNAP_RETURNABLE = '" . ($returnable ? 'Y' : 'N') . "', CONFIRMED_AT = " . time() . "
             WHERE ID = {$id}"
        );

        echo json_encode([
            'status'       => 'ok',
            'price_fmt'    => number_format($newPriceDisplay, 0, ',', ' ') . ' ₽',
            'delivery_days'  => $newDeliveryDays,
            'delivery_label' => $newDeliveryLabel,
            'delivery_time'  => $newDeliveryTime,
            'confirmed_at' => time(),
        ]);
        exit;
    }

    die(json_encode(['status' => 'error', 'message' => 'Неизвестное действие']));

} catch (\Throwable $e) {
    @file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/supplier_orders_error.log',
        '[' . date('Y-m-d H:i:s') . '] favorites: ' . $e->getMessage() . "\n", FILE_APPEND
    );
    echo json_encode(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
