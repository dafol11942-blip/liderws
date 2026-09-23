<?php

namespace Lider\Sms;

/**
 * Клиент sms.ru (https://sms.ru/api) — классическая отправка SMS с текстом
 * через /sms/send. Пришёл на замену SMS AERO MobileID: тот делал "тихую"
 * верификацию номера без кода (фингерпринт + подтверждение оператором),
 * у sms.ru такого продукта нет — только отправка текста. Поэтому код
 * подтверждения теперь генерируется и проверяется на нашей стороне
 * (см. Lider\Auth\PhoneAuthCodeService), этот класс отвечает только за то,
 * чтобы сообщение с готовым текстом дошло до абонента.
 */
class SmsRuClient
{
    private $apiId;
    private $timeout;

    public function __construct(string $apiId, int $timeout = 10)
    {
        $this->apiId = $apiId;
        $this->timeout = $timeout;
    }

    /**
     * @return array{0:bool,1:string} [успех, текст ошибки при неуспехе]
     */
    public function sendMessage(string $phone, string $message): array
    {
        if ($this->apiId === '') {
            return [false, 'sms.ru не настроен (пустой api_id)'];
        }

        $ch = curl_init('https://sms.ru/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'api_id' => $this->apiId,
                'to'     => $phone,
                'msg'    => $message,
                'json'   => 1,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlErr !== null) {
            return [false, 'sms.ru недоступен: ' . $curlErr];
        }

        $body = json_decode((string)$raw, true);
        if (!is_array($body) || ($body['status'] ?? '') !== 'OK') {
            return [false, (string)($body['status_text'] ?? 'Ошибка отправки SMS')];
        }

        $smsStatus = $body['sms'][$phone] ?? null;
        if (!is_array($smsStatus) || ($smsStatus['status'] ?? '') !== 'OK') {
            return [false, (string)($smsStatus['status_text'] ?? 'Оператор отклонил сообщение')];
        }

        return [true, ''];
    }
}
