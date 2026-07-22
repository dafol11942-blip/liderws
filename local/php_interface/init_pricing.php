<?php
/**
 * Ценообразование в зависимости от группы пользователя.
 *
 * Группа «Менеджер» (ID=7) → закупочная цена (без наценки)
 * Обычный клиент             → +40% к закупочной цене
 *                               мин. наценка 50 ₽
 *                               округление до 10
 *
 * Подключение:
 *   require $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';
 *
 * Использование:
 *   $priceForClient = getDisplayPrice($basePrice);
 */

// ==================== НАСТРОЙКИ ====================

define('PRICING_MANAGER_GROUP_ID', 7);
define('PRICING_MARKUP_PERCENT', 40);
define('PRICING_MIN_PROFIT', 50);
define('PRICING_ROUND_TO', 10);

// ===================================================

if (!function_exists('getDisplayPrice')) {
    function getDisplayPrice(float $basePrice, ?float $markup = null): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        if (isManager()) {
            return round($basePrice, 2);
        }

        $markupPercent = $markup ?? PRICING_MARKUP_PERCENT;
        $markupAmount  = $basePrice * ($markupPercent / 100);

        if (defined('PRICING_MIN_PROFIT') && $markupAmount < PRICING_MIN_PROFIT) {
            $markupAmount = PRICING_MIN_PROFIT;
        }

        $price = $basePrice + $markupAmount;

        $roundTo = defined('PRICING_ROUND_TO') ? PRICING_ROUND_TO : 0;
        if ($roundTo > 0) {
            $price = ceil($price / $roundTo) * $roundTo;
        }

        return round($price, 2);
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
