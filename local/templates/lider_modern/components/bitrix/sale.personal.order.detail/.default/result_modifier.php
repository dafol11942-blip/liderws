<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Роль-зависимая видимость служебных свойств позиции заказа (SUPPLIER_*,
 * см. order_from_supplier.php/basket_recheck.php) на странице "Мои заказы" →
 * детали заказа (bitrix:sale.personal.order.detail, ядровой шаблон .default —
 * сам template.php не переопределяем, только результат перед рендером).
 *
 * Менеджер видит всё как есть (поставщик, склад, закупочная цена и т.д.).
 * Остальные — только срок/время доставки; всё прочее из SUPPLIER_* скрыто
 * (закупочная цена, поставщик, склад — закупочная информация, не для клиента).
 * Свойства обычных товаров (не от поставщика) эта фильтрация не касается.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php');

if (!isManager()) {
    $allowedSupplierCodes = ['SUPPLIER_DELIVERY_LABEL', 'SUPPLIER_DELIVERY_TIME'];
    $allowedSupplierNames = ['Срок доставки', 'Время доставки'];
    // Полный список NAME наших свойств — на случай, если CODE не доедет в этот
    // конкретный результат компонента (перестраховка, а не единственная защита).
    $knownSupplierNames = [
        'Артикул', 'Бренд', 'Поставщик', 'Склад', 'Название', 'Закупочная цена',
        'Срок доставки (дн)', 'Срок доставки', 'Время доставки',
        'Остаток у поставщика', 'Подтверждено', 'Данные для заказа',
    ];

    $filterProps = function (array $props) use ($allowedSupplierCodes, $allowedSupplierNames, $knownSupplierNames): array {
        return array_values(array_filter($props, function ($p) use ($allowedSupplierCodes, $allowedSupplierNames, $knownSupplierNames) {
            $code = (string)($p['CODE'] ?? '');
            $name = (string)($p['NAME'] ?? '');
            $isOurs = (strpos($code, 'SUPPLIER_') === 0) || in_array($name, $knownSupplierNames, true);
            if (!$isOurs) return true; // не наше свойство (обычный товар) — не трогаем
            return in_array($code, $allowedSupplierCodes, true) || in_array($name, $allowedSupplierNames, true);
        }));
    };

    $walk = function (&$node) use (&$walk, $filterProps) {
        if (!is_array($node)) return;
        if (isset($node['PROPS']) && is_array($node['PROPS'])) {
            $node['PROPS'] = $filterProps($node['PROPS']);
        }
        foreach ($node as &$child) {
            if (is_array($child)) $walk($child);
        }
        unset($child);
    };
    $walk($arResult);
}
