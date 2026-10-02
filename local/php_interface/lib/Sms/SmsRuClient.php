<?php

namespace Lider\Sms;

/**
 * Клиент SMS.RU (sms.ru/api) — обычные информационные SMS-уведомления о
 * заказе. Авторизация — единственный токен api_id (не связано с мобильной
 * авторизацией MobileID, Lider\Auth\MobileIdClient, — у неё свой провайдер
 * и свои ключи, её это не затрагивает).
 */
class SmsRuClient
{
    private $apiId;
    private $from;
    private $baseUrl;
    private $timeout;

    public function __construct(string $apiId, string $from = '', string $baseUrl = 'https://sms.ru', int $timeout = 10)
    {
        $this->apiId   = $apiId;
        $this->from    = $from;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function isConfigured(): bool
    {
        return $this->apiId !== '';
    }

    /**
     * Отправляет SMS. $phone — в любом формате, нормализуется до 7XXXXXXXXXX.
     * @return array{0:bool,1:array} [success, decodedBody]
     */
    public function send(string $phone, string $text): array
    {
        if (!$this->isConfigured()) {
            return [false, ['error' => 'smsru not configured']];
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return [false, ['error' => 'empty phone']];
        }

        $params = [
            'api_id' => $this->apiId,
            'to'     => $digits,
            'msg'    => $text,
            'json'   => 1,
        ];
        if ($this->from !== '') {
            $params['from'] = $this->from;
        }

        $url = $this->baseUrl . '/sms/send?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlErr !== null) {
            return [false, ['error' => 'smsru unavailable', 'details' => $curlErr]];
        }

        $body = json_decode((string)$raw, true);
        if (!is_array($body)) {
            return [false, ['error' => 'invalid response from smsru', 'raw' => $raw]];
        }

        // Верхнеуровневый status относится к самому запросу; статус КОНКРЕТНОГО
        // номера — отдельно внутри sms[номер].status (может не дойти даже при
        // status=OK запроса в целом, например из-за неверного/заблокированного номера).
        $topOk    = ($body['status'] ?? '') === 'OK';
        $smsOk    = ($body['sms'][$digits]['status'] ?? '') === 'OK';

        return [$topOk && $smsOk, $body];
    }
}
