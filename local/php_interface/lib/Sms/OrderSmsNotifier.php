<?php

namespace Lider\Sms;

use Lider\Auth\PhoneNumberNormalizer;

/**
 * SMS-уведомления клиенту о ключевых событиях заказа. Отправка никогда не
 * должна ломать основной поток (создание заказа / крон смены статусов) —
 * любая ошибка только логируется.
 */
class OrderSmsNotifier
{
    public const EVENT_CREATED    = 'created';
    public const EVENT_IN_TRANSIT = 'in_transit';
    public const EVENT_READY      = 'ready';
    public const EVENT_CANCELLED  = 'cancelled';

    public static function notify(int $orderId, string $event, array $context = []): void
    {
        try {
            $client = function_exists('getSmsAeroClient') ? getSmsAeroClient() : null;
            if (!$client || !$client->isConfigured()) {
                self::log("Заказ №{$orderId}: SMS '{$event}' не отправлено — smsaero_config.php отсутствует/не заполнен");
                return;
            }

            $order = \Bitrix\Sale\Order::load($orderId);
            if (!$order) return;

            $phone = self::getOrderPhone($order);
            if ($phone === null) {
                self::log("Заказ №{$orderId}: SMS '{$event}' не отправлено — не найден телефон");
                return;
            }

            $text = self::buildText($orderId, $event, $context);
            if ($text === null) return;

            [$success, $body] = $client->send($phone, $text);
            if ($success) {
                self::log("Заказ №{$orderId}: SMS '{$event}' отправлено на {$phone}");
            } else {
                self::log("Заказ №{$orderId}: SMS '{$event}' не доставлено — " . json_encode($body, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            self::log("Заказ №{$orderId}: SMS '{$event}' упало — " . $e->getMessage());
        }
    }

    private static function buildText(int $orderId, string $event, array $context): ?string
    {
        switch ($event) {
            case self::EVENT_CREATED:
                $sum = isset($context['sum']) ? ', на сумму ' . $context['sum'] . ' ₽' : '';
                return "Лидер: заказ №{$orderId} оформлен{$sum}. Следить за статусом — liderws.ru/personal/orders/";
            case self::EVENT_IN_TRANSIT:
                return "Лидер: заказ №{$orderId} в пути от поставщика.";
            case self::EVENT_READY:
                return "Лидер: заказ №{$orderId} готов к выдаче.";
            case self::EVENT_CANCELLED:
                return "Лидер: заказ №{$orderId} отменён. Подробности — liderws.ru/personal/orders/";
            default:
                return null;
        }
    }

    /**
     * Телефон для уведомления: сперва свойство заказа (как в чекауте —
     * see sale.order.ajax/lider_style/template.php, определение по имени
     * свойства, т.к. TYPE на этой инсталляции не 'TEL'/'PHONE'), иначе —
     * профиль привязанного пользователя.
     */
    private static function getOrderPhone(\Bitrix\Sale\Order $order): ?string
    {
        foreach ($order->getPropertyCollection() as $prop) {
            $name = (string)$prop->getField('NAME');
            if (mb_stripos($name, 'телефон') !== false) {
                $val = trim((string)$prop->getField('VALUE'));
                $normalized = PhoneNumberNormalizer::normalize($val);
                if ($normalized !== null) return $normalized;
            }
        }

        $userId = (int)$order->getUserId();
        if ($userId > 0) {
            $arUser = \CUser::GetByID($userId)->Fetch();
            if ($arUser && !empty($arUser['PERSONAL_PHONE'])) {
                return PhoneNumberNormalizer::normalize($arUser['PERSONAL_PHONE']);
            }
        }

        return null;
    }

    private static function log(string $msg): void
    {
        if (function_exists('logSupplierOrderDispatch')) {
            logSupplierOrderDispatch($msg);
            return;
        }
        error_log($msg);
    }
}
