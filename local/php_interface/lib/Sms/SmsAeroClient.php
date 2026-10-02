<?php

namespace Lider\Sms;

/**
 * Клиент обычного SMS API SMS AERO (gate.smsaero.ru/v2) — НЕ MobileID
 * (мобильная авторизация, Lider\Auth\MobileIdClient). Используется для
 * информационных SMS-уведомлений о заказе. Авторизация — HTTP Basic Auth
 * (email личного кабинета + API-ключ из раздела "API"), запросы GET с
 * query-параметрами — стандартная схема API SMS AERO v2.
 */
class SmsAeroClient
{
    private $email;
    private $apiKey;
    private $sign;
    private $baseUrl;
    private $timeout;

    public function __construct(string $email, string $apiKey, string $sign = '', string $baseUrl = 'https://gate.smsaero.ru/v2', int $timeout = 10)
    {
        $this->email   = $email;
        $this->apiKey  = $apiKey;
        $this->sign    = $sign;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function isConfigured(): bool
    {
        return $this->email !== '' && $this->apiKey !== '';
    }

    /**
     * Отправляет SMS. $phone — в любом формате, нормализуется до 7XXXXXXXXXX.
     * @return array{0:bool,1:array} [success, decodedBody]
     */
    public function send(string $phone, string $text): array
    {
        if (!$this->isConfigured()) {
            return [false, ['error' => 'smsaero not configured']];
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return [false, ['error' => 'empty phone']];
        }

        $params = [
            'number' => $digits,
            'text'   => $text,
        ];
        if ($this->sign !== '') {
            $params['sign'] = $this->sign;
        }

        $url = $this->baseUrl . '/sms/send?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_USERPWD        => $this->email . ':' . $this->apiKey,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlErr !== null) {
            return [false, ['error' => 'smsaero unavailable', 'details' => $curlErr]];
        }

        $body = json_decode((string)$raw, true);
        if (!is_array($body)) {
            return [false, ['error' => 'invalid response from smsaero', 'raw' => $raw]];
        }

        return [!empty($body['success']), $body];
    }
}
