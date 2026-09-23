<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

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

$sessionId = (string)($input['session_id'] ?? '');
$verifyToken = (string)($input['verify_token'] ?? '');

if ($sessionId === '' || $verifyToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'session_id и verify_token обязательны']);
    exit;
}

[$status, $body] = getMobileIdClient()->siteVerify($sessionId, $verifyToken);

if ($status !== 200 || empty($body['success']) || ($body['status'] ?? '') !== 'verified') {
    echo json_encode(['success' => false, 'message' => 'Верификация не подтверждена']);
    exit;
}

$normalizedPhone = PhoneNumberNormalizer::normalize((string)($body['phone'] ?? ''));
if ($normalizedPhone === null) {
    echo json_encode(['success' => false, 'message' => 'Некорректный номер телефона от сервиса верификации']);
    exit;
}

$result = PhoneUserService::updateUserPhone((int)$USER->GetID(), $normalizedPhone);

if ($result !== true) {
    echo json_encode(['success' => false, 'message' => $result]);
    exit;
}

echo json_encode(['success' => true, 'phone' => $normalizedPhone]);
