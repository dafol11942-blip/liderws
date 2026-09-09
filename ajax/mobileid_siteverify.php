<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Lider\Auth\PhoneNumberNormalizer;
use Lider\Auth\PhoneUserService;

header('Content-Type: application/json; charset=utf-8');

global $USER;

// Уже авторизован (например, повторный вызов события verified) — не создаём
// второй аккаунт и не переавторизуем сессию, просто подтверждаем текущее состояние.
if ($USER->IsAuthorized()) {
    $arCurrentUser = \CUser::GetByID($USER->GetID())->Fetch();
    echo json_encode([
        'success'    => true,
        'phone'      => $arCurrentUser['PERSONAL_PHONE'] ?? '',
        'authorized' => true,
        'is_new'     => false,
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (string)($input['session_id'] ?? '');
$verifyToken = (string)($input['verify_token'] ?? '');

if ($sessionId === '' || $verifyToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'session_id и verify_token обязательны']);
    exit;
}

// Согласие на обработку персональных данных (152-ФЗ) — форма на /auth/
// блокирует виджет до отметки чекбокса, но не доверяем одной только JS-
// проверке, дублируем на сервере.
if (($input['agree_pd'] ?? '') !== 'Y') {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Подтвердите согласие на обработку персональных данных']);
    exit;
}

[$status, $body] = getMobileIdClient()->siteVerify($sessionId, $verifyToken);

if ($status !== 200 || empty($body['success']) || ($body['status'] ?? '') !== 'verified') {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Верификация не подтверждена']);
    exit;
}

// Телефону из ответа MobileID (server-to-server) доверяем; клиентский phone игнорируем.
$normalizedPhone = PhoneNumberNormalizer::normalize((string)($body['phone'] ?? ''));
if ($normalizedPhone === null) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Некорректный номер телефона от сервиса верификации']);
    exit;
}

try {
    [$userId, $isNew] = PhoneUserService::findOrCreateUserId($normalizedPhone);
} catch (\Throwable $e) {
    error_log('mobileid_siteverify: findOrCreateUserId упал: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Не удалось создать пользователя']);
    exit;
}

$USER->Authorize($userId);

echo json_encode([
    'success'    => true,
    'phone'      => $normalizedPhone,
    'authorized' => true,
    'is_new'     => $isNew,
]);
