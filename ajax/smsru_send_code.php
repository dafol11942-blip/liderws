<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Lider\Auth\PhoneAuthCodeService;
use Lider\Auth\PhoneNumberNormalizer;

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$normalizedPhone = PhoneNumberNormalizer::normalize((string)($input['phone'] ?? ''));

if ($normalizedPhone === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный номер телефона']);
    exit;
}

$code = PhoneAuthCodeService::issueCode($normalizedPhone);
if ($code === null) {
    echo json_encode(['success' => false, 'message' => 'Код уже отправлен, подождите перед повторной отправкой']);
    exit;
}

[$sent, $error] = getSmsRuClient()->sendMessage($normalizedPhone, "Код подтверждения: {$code}");

if (!$sent) {
    error_log('smsru_send_code: отправка не удалась (' . $normalizedPhone . '): ' . $error);
    echo json_encode(['success' => false, 'message' => 'Не удалось отправить SMS, попробуйте позже']);
    exit;
}

echo json_encode(['success' => true]);
