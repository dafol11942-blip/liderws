<?php
/**
 * Обработчик доставки для заказных товаров от поставщиков.
 * Берёт срок доставки из свойств элемента корзины (SUPPLIER_DELIVERY_DAYS).
 */
 
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Shipment;

class SupplierDeliveryHandler extends \Bitrix\Sale\Delivery\Services\Base
{
    public static function getClassTitle()
    {
        return 'Доставка поставщика (по сроку)';
    }

    public static function getClassDescription()
    {
        return 'Рассчитывает дату доставки на основе срока поставщика';
    }

    protected function calculateConcrete(Shipment $shipment): CalculationResult
    {
        $result = new CalculationResult();

        // BasketPropertiesCollection не имеет метода getItemValues() — читаем свойство
        // по CODE вручную через перебор коллекции.
        $readProp = function ($props, string $code) {
            foreach ($props as $p) {
                if ($p->getField('CODE') === $code) return $p->getField('VALUE');
            }
            return null;
        };

        $maxDays = 0;
        $deliveryTexts = [];

        $basket = $shipment->getCollection()->getOrder()->getBasket();
        foreach ($basket as $basketItem) {
            $props = $basketItem->getPropertyCollection();
            $days  = (int)($readProp($props, 'SUPPLIER_DELIVERY_DAYS') ?? 0);
            $label = (string)($readProp($props, 'SUPPLIER_DELIVERY_LABEL') ?? '');
            $time  = (string)($readProp($props, 'SUPPLIER_DELIVERY_TIME') ?? '');
            $text  = trim($label . ($time !== '' ? ' ' . $time : ''));

            if ($days > $maxDays) $maxDays = $days;
            if ($text) $deliveryTexts[] = $text;
        }

        $deliveryTexts = array_unique($deliveryTexts);

        if ($maxDays === 0) {
            $result->setDescription('Сегодня');
            $result->setPeriodDescription('Сегодня');
        } elseif ($maxDays === 1) {
            $result->setDescription('Завтра');
            $result->setPeriodDescription('Завтра');
        } else {
            $date = date('d.m.Y', strtotime("+{$maxDays} days"));
            $result->setDescription($date);
            $result->setPeriodDescription($date);
        }

        $result->setDeliveryPrice(0);
        $result->setDeliveryPriceCalculate(true);

        return $result;
    }

    public function isCalculatePriceImmediately(): bool
    {
        return true;
    }

    public static function whetherAdminExtraServicesShow(): bool
    {
        return false;
    }
}
