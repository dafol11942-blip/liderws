<?php
/**
 * Ценообразование в зависимости от группы пользователя.
 *
 * Группа «Менеджер» (ID=7) → закупочная цена (без наценки)
 * Обычный клиент             → каскадная наценка по цене товара (см. PRICING_TIERS)
 *
 * Подключение:
 *   require $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';
 *
 * Использование:
 *   $priceForClient = getDisplayPrice($basePrice); // закупка для менеджера, клиентская для остальных
 *   $clientPrice    = getClientPrice($basePrice);  // клиентская цена всегда, независимо от роли
 */

// ==================== НАСТРОЙКИ ====================

define('PRICING_MANAGER_GROUP_ID', 7);

// Каскадная наценка: [верхняя граница диапазона (не включая, null = без верхней границы), % наценки, округление вверх до]
// Диапазон цены товара определяет наценку целиком (не маржинально по частям).
define('PRICING_TIERS', [
    [500,   100, 50],
    [1000,  70,  50],
    [5000,  45,  50],
    [10000, 40,  50],
    [null,  35,  100],
]);

// ===================================================

if (!function_exists('getClientPrice')) {
    function getClientPrice(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        foreach (PRICING_TIERS as [$upperBound, $percent, $roundTo]) {
            if ($upperBound === null || $basePrice < $upperBound) {
                $price = $basePrice * (1 + $percent / 100);
                if ($roundTo > 0) {
                    $price = ceil($price / $roundTo) * $roundTo;
                }
                return round($price, 2);
            }
        }

        return round($basePrice, 2);
    }
}

if (!function_exists('getDisplayPrice')) {
    function getDisplayPrice(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        if (isManager()) {
            return round($basePrice, 2);
        }

        return getClientPrice($basePrice);
    }
}

if (!function_exists('isManager')) {
    function isManager(): bool
    {
        // Статический кеш — чтобы не дёргать базу при каждом вызове функции
        static $isManagerCache = null;

        if ($isManagerCache !== null) {
            return $isManagerCache;
        }

        global $USER;

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            $isManagerCache = false;
            return false;
        }

        $userGroups = $USER->GetUserGroupArray();

        if (defined('PRICING_MANAGER_GROUP_ID') && PRICING_MANAGER_GROUP_ID > 0) {
            if (in_array((string)PRICING_MANAGER_GROUP_ID, $userGroups, true)) {
                $isManagerCache = true;
                return true;
            }
        }

        $filter = ['ID' => implode(' | ', $userGroups)];
        $groups = \CGroup::GetList('id', 'asc', $filter);
        while ($g = $groups->Fetch()) {
            $name = mb_strtolower(trim($g['NAME']));
            if (strpos($name, 'менеджер') !== false || strpos($name, 'manager') !== false) {
                $isManagerCache = true;
                return true;
            }
        }

        $isManagerCache = false;
        return false;
    }
}
