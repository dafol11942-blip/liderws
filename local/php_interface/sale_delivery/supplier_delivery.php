<?php
/**
 * Автоматизированная служба доставки: «Доставка поставщика»
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Shipment;

Loc::loadMessages(__FILE__);

class SupplierDeliveryHandler extends \Bitrix\Sale\Delivery\Services\Base
{
    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
    }

    public static function getClassTitle(): string
    {
        return 'Доставка поставщика (по сроку)';
    }

    public static function getClassDescription(): string
    {
        return 'Дата доставки рассчитывается по срокам поставщика';
    }

    protected function calculateConcrete(Shipment $shipment): CalculationResult
    {
        $result = new CalculationResult();
        $maxDays = 0;

        $basket = $shipment->getCollection()->getOrder()->getBasket();

        foreach ($basket as $basketItem) {
            $props = $basketItem->getPropertyCollection();
            $daysValue = $props->getItemValues('SUPPLIER_DELIVERY_DAYS');
            
            if ($daysValue) {
                $days = is_array($daysValue) ? (int)reset($daysValue) : (int)$daysValue;
                if ($days > $maxDays) {
                    $maxDays = $days;
                }
            }
        }

        if ($maxDays === 0) {
            $description = 'Сегодня';
            $periodTo = 0;
        } elseif ($maxDays === 1) {
            $description = 'Завтра, ' . date('d.m', strtotime('+1 day'));
            $periodTo = 1;
        } else {
            $description = date('d.m.Y', strtotime("+{$maxDays} days"));
            $periodTo = $maxDays;
        }

        $result->setDescription($description);
        $result->setPeriodDescription($periodTo . ' дн.');
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
