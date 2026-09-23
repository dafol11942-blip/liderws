<?php

namespace Lider\Auth;

/**
 * Хранит и проверяет одноразовые коды подтверждения номера телефона —
 * классический OTP-по-SMS, пришедший на замену "тихой" верификации SMS AERO
 * MobileID (фингерпринт устройства + подтверждение без ввода кода). Один
 * активный код на телефон: новый запрос замещает предыдущий, кроме случая
 * cooldown повторной отправки (см. issueCode()). Таблица b_phone_auth_code
 * создаётся вручную (см. STAGES.md / чат с инструкцией миграции).
 */
class PhoneAuthCodeService
{
    private const CODE_TTL_SECONDS = 300; // 5 минут
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    /**
     * Генерирует новый код и сохраняет его хэш. Возвращает код (для отправки
     * по SMS) либо null, если действует cooldown повторной отправки —
     * дублирует защиту sms.ru (код 233) на нашей стороне, чтобы не тратить
     * запрос впустую.
     */
    public static function issueCode(string $phone): ?string
    {
        $db = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        $phoneEsc = $helper->forSql($phone);

        $existing = $db->query("SELECT DATE_CREATE FROM b_phone_auth_code WHERE PHONE = '{$phoneEsc}'")->fetch();
        if ($existing) {
            $createdAt = strtotime($existing['DATE_CREATE']);
            if ($createdAt !== false && (time() - $createdAt) < self::RESEND_COOLDOWN_SECONDS) {
                return null;
            }
        }

        $code = (string)random_int(1000, 9999);
        $codeHash = self::hashCode($phone, $code);
        $now = date('Y-m-d H:i:s');
        $expire = date('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS);

        $db->query(
            "REPLACE INTO b_phone_auth_code (PHONE, CODE_HASH, ATTEMPTS, DATE_CREATE, DATE_EXPIRE)
             VALUES ('{$phoneEsc}', '" . $helper->forSql($codeHash) . "', 0, '{$now}', '{$expire}')"
        );

        return $code;
    }

    /**
     * @return true|string true при успехе, иначе текст ошибки для пользователя
     */
    public static function verifyCode(string $phone, string $code)
    {
        $db = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();
        $phoneEsc = $helper->forSql($phone);

        $row = $db->query("SELECT * FROM b_phone_auth_code WHERE PHONE = '{$phoneEsc}'")->fetch();
        if (!$row) {
            return 'Код не запрошен или уже использован, запросите новый';
        }

        if (strtotime($row['DATE_EXPIRE']) < time()) {
            $db->query("DELETE FROM b_phone_auth_code WHERE PHONE = '{$phoneEsc}'");
            return 'Код истёк, запросите новый';
        }

        if ((int)$row['ATTEMPTS'] >= self::MAX_ATTEMPTS) {
            $db->query("DELETE FROM b_phone_auth_code WHERE PHONE = '{$phoneEsc}'");
            return 'Слишком много попыток, запросите новый код';
        }

        if (!hash_equals($row['CODE_HASH'], self::hashCode($phone, $code))) {
            $db->query("UPDATE b_phone_auth_code SET ATTEMPTS = ATTEMPTS + 1 WHERE PHONE = '{$phoneEsc}'");
            return 'Неверный код';
        }

        $db->query("DELETE FROM b_phone_auth_code WHERE PHONE = '{$phoneEsc}'");
        return true;
    }

    private static function hashCode(string $phone, string $code): string
    {
        return hash('sha256', $phone . ':' . $code);
    }
}
