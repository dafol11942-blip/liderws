<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Lider\Auth\PhoneNumberNormalizer;
use Lider\Auth\PhoneUserService;

// Скрипт не подключает epilog/header.php, поэтому куки и заголовки, поставленные
// через $USER->Authorize() (Bitrix\Main\HttpResponse), никогда не долетали бы до
// браузера без явного Application::end() — оборачиваем весь вывод в буфер и всегда
// завершаемся через него вместо голого exit.
ob_start();

header('Content-Type: application/json; charset=utf-8');

global $USER;

function mobileidRespond(array $data): void
{
    echo json_encode($data);
    \Bitrix\Main\Application::getInstance()->end();
}

// Уже авторизован (например, повторный вызов события verified) — не создаём
// второй аккаунт и не переавторизуем сессию, просто подтверждаем текущее состояние.
if ($USER->IsAuthorized()) {
    $arCurrentUser = \CUser::GetByID($USER->GetID())->Fetch();
    mobileidRespond([
        'success'    => true,
        'phone'      => $arCurrentUser['PERSONAL_PHONE'] ?? '',
        'authorized' => true,
        'is_new'     => false,
    ]);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (string)($input['session_id'] ?? '');
$verifyToken = (string)($input['verify_token'] ?? '');

if ($sessionId === '' || $verifyToken === '') {
    http_response_code(400);
    mobileidRespond(['success' => false, 'message' => 'session_id и verify_token обязательны']);
}

// Согласие на обработку персональных данных (152-ФЗ) — форма на /auth/
// блокирует виджет до отметки чекбокса, но не доверяем одной только JS-
// проверке, дублируем на сервере.
if (($input['agree_pd'] ?? '') !== 'Y') {
    http_response_code(200);
    mobileidRespond(['success' => false, 'message' => 'Подтвердите согласие на обработку персональных данных']);
}

[$status, $body] = getMobileIdClient()->siteVerify($sessionId, $verifyToken);

if ($status !== 200 || empty($body['success']) || ($body['status'] ?? '') !== 'verified') {
    http_response_code(200);
    mobileidRespond(['success' => false, 'message' => 'Верификация не подтверждена']);
}

// Телефону из ответа MobileID (server-to-server) доверяем; клиентский phone игнорируем.
$normalizedPhone = PhoneNumberNormalizer::normalize((string)($body['phone'] ?? ''));
if ($normalizedPhone === null) {
    http_response_code(200);
    mobileidRespond(['success' => false, 'message' => 'Некорректный номер телефона от сервиса верификации']);
}

try {
    [$userId, $isNew] = PhoneUserService::findOrCreateUserId($normalizedPhone);
} catch (\Throwable $e) {
    error_log('mobileid_siteverify: findOrCreateUserId упал: ' . $e->getMessage());
    http_response_code(200);
    mobileidRespond(['success' => false, 'message' => 'Не удалось создать пользователя']);
}

$USER->Authorize($userId, true);

mobileidRespond([
    'success'    => true,
    'phone'      => $normalizedPhone,
    'authorized' => true,
    'is_new'     => $isNew,
]);
