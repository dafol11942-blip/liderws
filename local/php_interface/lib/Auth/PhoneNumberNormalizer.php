<?php

namespace Lider\Auth;

/**
 * Приводит телефон в любом формате ("+7 (916) 123-45-67", "89161234567",
 * "9161234567"...) к единому канону "7XXXXXXXXXX" для сравнения/хранения.
 */
class PhoneNumberNormalizer
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string)$raw);
        if ($digits === '') {
            return null;
        }
        $last10 = substr($digits, -10);
        if (strlen($last10) !== 10) {
            return null;
        }
        return '7' . $last10;
    }
}
