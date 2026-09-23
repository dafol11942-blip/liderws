<?php

namespace Lider\Auth;

/**
 * Клиент SMS AERO MobileID (https://midsdk.smsaero.ru) — адаптация эталонного
 * класса MobileIDBackend из документации вендора под автозагрузку проекта
 * (local/php_interface/lib/autoload.php, namespace Lider\*). Чистый HTTP-клиент,
 * без зависимостей от Bitrix — вся Bitrix-специфичная логика (поиск/создание
 * пользователя, авторизация) живёт в PhoneUserService и вызывающих ajax-эндпоинтах.
 */
class MobileIdClient
{
    private $clientId;
    private $apiSecret;
    private $sdkBackendUrl;
    private $timeout;

    public function __construct(string $clientId, string $apiSecret, string $sdkBackendUrl = 'https://midsdk.smsaero.ru', int $timeout = 10)
    {
        $this->clientId      = $clientId;
        $this->apiSecret     = $apiSecret;
        $this->sdkBackendUrl = rtrim($sdkBackendUrl, '/');
        $this->timeout       = $timeout;
    }

    /**
     * Получить init-токен для виджета/SDK. Вызывается из /api/token.
     * @return array [int $httpStatus, array $body]
     */
    public function getToken(string $fingerprintHash): array
    {
        if ($fingerprintHash === '') {
            return [400, ['error' => 'missing fingerprint_hash']];
        }

        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $this->clientId . $fingerprintHash . $timestamp, $this->apiSecret);

        return $this->request('/api/token', [
            'client_id'        => $this->clientId,
            'fingerprint_hash' => $fingerprintHash,
            'timestamp'        => $timestamp,
            'signature'        => $signature,
        ]);
    }

    /**
     * Проверить результат верификации server-to-server. Вызывается из /api/siteverify.
     * @return array [int $httpStatus, array $body]
     */
    public function siteVerify(string $sessionId, string $verifyToken): array
    {
        if ($sessionId === '' || $verifyToken === '') {
            return [400, ['error' => 'missing session_id or verify_token']];
        }

        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $this->clientId . $sessionId . $timestamp, $this->apiSecret);

        return $this->request('/api/siteverify', [
            'client_id'    => $this->clientId,
            'session_id'   => $sessionId,
            'verify_token' => $verifyToken,
            'timestamp'    => $timestamp,
            'signature'    => $signature,
        ]);
    }

    private function request(string $path, array $payload): array
    {
        $url  = $this->sdkBackendUrl . $path;
        $json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlErr !== null) {
            return [502, ['error' => 'sdk backend unavailable', 'details' => $curlErr]];
        }

        $body = json_decode((string)$raw, true);
        if ($body === null) {
            return [502, ['error' => 'invalid response from sdk backend']];
        }

        return [$httpCode, $body];
    }
}
