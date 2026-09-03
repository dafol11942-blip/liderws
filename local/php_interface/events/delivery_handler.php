<?php
/**
 * Обработчик: подменяет описание доставки на реальную дату из свойств корзины.
 * 
 * События Bitrix:
 *   OnSaleComponentOrderOneStepComplete
 *   OnSaleComponentOrderResultPrepared
 */

use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

// Перехватываем результат компонента оформления заказа
$eventManager->addEventHandler('sale', 'OnSaleComponentOrderResultPrepared', function($result, $arUserResult, $request) {
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );
    
    $maxDays = 0;
    $deliveryText = '';

    // BasketPropertiesCollection не имеет метода getItemValues() — читаем свойство
    // по CODE вручную через перебор коллекции.
    $readProp = function ($props, string $code) {
        foreach ($props as $p) {
            if ($p->getField('CODE') === $code) return $p->getField('VALUE');
        }
        return null;
    };

    foreach ($basket as $basketItem) {
        $props = $basketItem->getPropertyCollection();
        $days  = (int)($readProp($props, 'SUPPLIER_DELIVERY_DAYS') ?? 0);
        $label = (string)($readProp($props, 'SUPPLIER_DELIVERY_LABEL') ?? '');
        $time  = (string)($readProp($props, 'SUPPLIER_DELIVERY_TIME') ?? '');
        $text  = trim($label . ($time !== '' ? ' ' . $time : ''));

        if ($days > $maxDays) {
            $maxDays = $days;
            $deliveryText = $text;
        }
    }
    
    if ($maxDays === 0 && !empty($deliveryText)) {
        $deliveryDate = 'Сегодня (' . date('d.m.Y') . ')';
    } elseif ($maxDays === 1) {
        $deliveryDate = 'Завтра (' . date('d.m.Y', strtotime('+1 day')) . ')';
    } elseif ($maxDays > 1) {
        $deliveryDate = date('d.m.Y', strtotime("+{$maxDays} days"));
    } else {
        $deliveryDate = 'Уточняется';
    }
    
    // Подменяем описание во всех доставках
    if (isset($result['DELIVERY']) && is_array($result['DELIVERY'])) {
        foreach ($result['DELIVERY'] as &$delivery) {
            if (!empty($deliveryDate)) {
                $delivery['PERIOD_DESCRIPTION'] = $deliveryDate;
                $delivery['DESCRIPTION'] = 'Доставка: ' . $deliveryDate;
            }
        }
        unset($delivery);
    }
    
    return new \Bitrix\Main\EventResult(
        \Bitrix\Main\EventResult::SUCCESS,
        ['RESULT' => $result]
    );
});
