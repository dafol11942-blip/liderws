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
    
    foreach ($basket as $basketItem) {
        $props = $basketItem->getPropertyCollection();
        $days = (int)($props->getItemValues('SUPPLIER_DELIVERY_DAYS') ?: 0);
        $text = (string)($props->getItemValues('SUPPLIER_DELIVERY_TEXT') ?: '');
        
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
