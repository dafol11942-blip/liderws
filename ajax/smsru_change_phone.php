<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Lider\Auth\PhoneAuthCodeService;
use Lider\Auth\PhoneNumberNormalizer;
use Lider\Auth\PhoneUserService;

header('Content-Type: application/json; charset=utf-8');

global $USER;

if (!$USER->IsAuthorized()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!hash_equals(bitrix_sessid(), (string)($input['sessid'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Некорректный sessid']);
    exit;
}

$normalizedPhone = PhoneNumberNormalizer::normalize((string)($input['phone'] ?? ''));
$code = trim((string)($input['code'] ?? ''));

if ($normalizedPhone === null || $code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Телефон и код обязательны']);
    exit;
}

$verifyResult = PhoneAuthCodeService::verifyCode($normalizedPhone, $code);
if ($verifyResult !== true) {
    echo json_encode(['success' => false, 'message' => $verifyResult]);
    exit;
}

$result = PhoneUserService::updateUserPhone((int)$USER->GetID(), $normalizedPhone);

if ($result !== true) {
    echo json_encode(['success' => false, 'message' => $result]);
    exit;
}

echo json_encode(['success' => true, 'phone' => $normalizedPhone]);
