<?php

namespace Lider\Auth;

/**
 * Бизнес-логика авторизации/регистрации по телефону: поиск существующего
 * пользователя Bitrix по PERSONAL_PHONE (устойчиво к разным форматам записи),
 * автосоздание нового пользователя при первом входе по номеру, смена телефона
 * у уже авторизованного пользователя.
 */
class PhoneUserService
{
    /**
     * Ищет активного пользователя по нормализованному телефону.
     *
     * PERSONAL_PHONE у существующих аккаунтов хранится в разных форматах
     * ("+7 (916) 123-45-67", "89161234567" и т.п.), поэтому обычный SQL LIKE
     * по хвосту цифр ненадёжен (пунктуация внутри номера ломает совпадение
     * хвоста, например "...45-67" не оканчивается на "4567"). Вместо этого
     * выбираем только пользователей с непустым PERSONAL_PHONE (обычно
     * небольшая доля от всех строк b_user) и сравниваем нормализованные
     * значения в PHP — надёжно и на реальных объёмах магазина не медленно.
     */
    public static function findUserIdByNormalizedPhone(string $normalizedPhone): ?int
    {
        $db = \Bitrix\Main\Application::getConnection();
        $rows = $db->query(
            "SELECT ID, PERSONAL_PHONE FROM b_user WHERE PERSONAL_PHONE IS NOT NULL AND PERSONAL_PHONE != '' AND ACTIVE = 'Y'"
        )->fetchAll();

        foreach ($rows as $row) {
            if (PhoneNumberNormalizer::normalize($row['PERSONAL_PHONE']) === $normalizedPhone) {
                return (int)$row['ID'];
            }
        }

        return null;
    }

    /**
     * Создаёт нового пользователя по телефону. NAME/LAST_NAME/EMAIL намеренно
     * не заполняются здесь — их обязательное заполнение потребует
     * require_phone_auth.php -> auth/complete.php при первом обращении к
     * личному кабинету/оформлению заказа.
     */
    public static function createUserByPhone(string $normalizedPhone): int
    {
        $login = self::buildUniqueLogin($normalizedPhone);
        $password = self::generateRandomPassword();
        $groupIds = self::getDefaultGroupIds();

        $baseFields = [
            'LOGIN'             => $login,
            'PASSWORD'          => $password,
            'CONFIRM_PASSWORD'  => $password,
            'PERSONAL_PHONE'    => $normalizedPhone,
            'ACTIVE'            => 'Y',
            'LID'               => defined('SITE_ID') ? SITE_ID : 's1',
        ];
        if (!empty($groupIds)) {
            $baseFields['GROUP_ID'] = $groupIds;
        }

        $user = new \CUser();
        $id = $user->Add($baseFields);

        if (!$id) {
            $firstError = strip_tags((string)$user->LAST_ERROR);
            error_log('PhoneUserService::createUserByPhone: первая попытка без email провалилась: ' . $firstError);

            // Некоторые инсталляции Bitrix требуют EMAIL (например, опция
            // "Использовать e-mail в качестве логина"). Пробуем один раз с
            // сгенерированным плейсхолдером — пользователь сможет заменить
            // его на реальный email на шаге auth/complete.php.
            $user = new \CUser();
            $fieldsWithEmail = $baseFields;
            $fieldsWithEmail['EMAIL'] = $login . '@lider.local';
            $id = $user->Add($fieldsWithEmail);

            if (!$id) {
                $secondError = strip_tags((string)$user->LAST_ERROR);
                error_log('PhoneUserService::createUserByPhone: повторная попытка с email тоже провалилась: ' . $secondError);
                throw new \RuntimeException('Не удалось создать пользователя по телефону: ' . $secondError);
            }
        }

        return (int)$id;
    }

    /**
     * @return array{0:int,1:bool} [userId, isNewUser]
     */
    public static function findOrCreateUserId(string $normalizedPhone): array
    {
        $existingId = self::findUserIdByNormalizedPhone($normalizedPhone);
        if ($existingId !== null) {
            return [$existingId, false];
        }

        return [self::createUserByPhone($normalizedPhone), true];
    }

    /**
     * Меняет телефон уже авторизованного пользователя. LOGIN при этом не
     * трогается — он используется только как внутренний идентификатор,
     * созданный один раз при регистрации.
     *
     * @return true|string true при успехе, иначе текст ошибки
     */
    public static function updateUserPhone(int $userId, string $newNormalizedPhone)
    {
        $conflictUserId = self::findUserIdByNormalizedPhone($newNormalizedPhone);
        if ($conflictUserId !== null && $conflictUserId !== $userId) {
            return 'Этот номер уже привязан к другому аккаунту';
        }

        $user = new \CUser();
        $ok = $user->Update($userId, ['PERSONAL_PHONE' => $newNormalizedPhone]);
        if (!$ok) {
            return $user->LAST_ERROR ?: 'Не удалось обновить номер телефона';
        }

        return true;
    }

    private static function buildUniqueLogin(string $normalizedPhone): string
    {
        $base = 'tel_' . substr($normalizedPhone, 1);
        $login = $base;
        $suffix = 1;
        while (\CUser::GetList('ID', 'ASC', ['LOGIN_EQUAL_EXACT' => $login])->Fetch()) {
            $suffix++;
            $login = $base . '_' . $suffix;
        }
        return $login;
    }

    private static function generateRandomPassword(): string
    {
        return bin2hex(random_bytes(12)) . 'Aa1!';
    }

    private static function getDefaultGroupIds(): array
    {
        $raw = \Bitrix\Main\Config\Option::get('main', 'new_user_registration_def_group', '');
        if ($raw === '') {
            return [];
        }
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return array_values($ids);
    }
}
