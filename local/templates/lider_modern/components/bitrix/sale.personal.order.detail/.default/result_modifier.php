<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Роль-зависимая обработка свойств позиции заказа (SUPPLIER_*, см.
 * order_from_supplier.php/basket_recheck.php) на странице "Мои заказы" →
 * детали заказа (bitrix:sale.personal.order.detail, ядровой шаблон .default —
 * сам template.php не переопределяем, только результат перед рендером).
 *
 * Не менеджер: видит только срок/время доставки — весь остальной SUPPLIER_*
 * (поставщик, склад, закупочная цена, служебные данные для заказа) скрыт.
 * Свойства обычных товаров (не от поставщика) не трогаем.
 *
 * Менеджер: видит всё как есть плюс живой статус позиции у поставщика
 * (b_supplier_order_item.STATE_TEXT, обновляется cron/supplier_order_status_poll.php)
 * — по нему менеджер вручную решает, когда переводить заказ в статус SR
 * "Получен от поставщика" (см. план "Реальный заказ у поставщика").
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

$isMgr = isManager();

$allowedSupplierCodes = ['SUPPLIER_DELIVERY_LABEL', 'SUPPLIER_DELIVERY_TIME'];
$allowedSupplierNames = ['Срок доставки', 'Время доставки'];
// Полный список NAME наших свойств — на случай, если CODE не доедет в этот
// конкретный результат компонента (перестраховка, а не единственная защита).
$knownSupplierNames = [
    'Артикул', 'Бренд', 'Поставщик', 'Склад', 'Название', 'Закупочная цена',
    'Срок доставки (дн)', 'Срок доставки', 'Время доставки',
    'Остаток у поставщика', 'Подтверждено', 'Данные для заказа',
];

$isSupplierProp = function ($p) use ($knownSupplierNames) {
    $code = (string)($p['CODE'] ?? '');
    $name = (string)($p['NAME'] ?? '');
    return (strpos($code, 'SUPPLIER_') === 0) || in_array($name, $knownSupplierNames, true);
};

$orderId = (int)($arParams['ID'] ?? $arResult['ID'] ?? 0);

$fetchLiveStatus = function (int $basketItemId) use ($orderId): string {
    if (!$orderId || !$basketItemId) return 'ещё не проверялся';
    try {
        $db     = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        $reference = $orderId . '_' . $basketItemId;
        $row = $db->query(
            "SELECT STATE_TEXT, LAST_CHECKED_AT FROM b_supplier_order_item
             WHERE REFERENCE = '" . $helper->forSql($reference) . "' ORDER BY ID DESC LIMIT 1"
        )->fetch();
    } catch (\Throwable $e) {
        return 'ещё не проверялся';
    }
    if (!$row || $row['STATE_TEXT'] === null || $row['STATE_TEXT'] === '') {
        return $row && $row['LAST_CHECKED_AT'] ? 'нет данных (проверено: ' . $row['LAST_CHECKED_AT'] . ')' : 'ещё не проверялся';
    }
    return $row['STATE_TEXT'] . ($row['LAST_CHECKED_AT'] ? ' (проверено: ' . $row['LAST_CHECKED_AT'] . ')' : '');
};

$processProps = function (array &$node) use ($isMgr, $allowedSupplierCodes, $allowedSupplierNames, $isSupplierProp, $fetchLiveStatus) {
    $hasSupplierProp = false;
    foreach ($node['PROPS'] as $p) {
        if ($isSupplierProp($p)) { $hasSupplierProp = true; break; }
    }
    if (!$hasSupplierProp) return; // обычный товар, не от поставщика — не трогаем

    if (!$isMgr) {
        $node['PROPS'] = array_values(array_filter($node['PROPS'], function ($p) use ($allowedSupplierCodes, $allowedSupplierNames, $isSupplierProp) {
            if (!$isSupplierProp($p)) return true;
            $code = (string)($p['CODE'] ?? '');
            $name = (string)($p['NAME'] ?? '');
            return in_array($code, $allowedSupplierCodes, true) || in_array($name, $allowedSupplierNames, true);
        }));
        return;
    }

    // Менеджер — дополняем живым статусом у поставщика.
    $basketItemId = (int)($node['ID'] ?? $node['BASKET_ID'] ?? $node['ITEM_ID'] ?? 0);
    if ($basketItemId) {
        $node['PROPS'][] = [
            'CODE'  => 'SUPPLIER_LIVE_STATUS',
            'NAME'  => 'Статус у поставщика',
            'VALUE' => $fetchLiveStatus($basketItemId),
        ];
    }
};

$walk = function (&$node) use (&$walk, $processProps) {
    if (!is_array($node)) return;
    if (isset($node['PROPS']) && is_array($node['PROPS'])) {
        $processProps($node);
    }
    foreach ($node as &$child) {
        if (is_array($child)) $walk($child);
    }
    unset($child);
};
$walk($arResult);
