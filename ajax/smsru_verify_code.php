<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Lider\Auth\PhoneAuthCodeService;
use Lider\Auth\PhoneNumberNormalizer;
use Lider\Auth\PhoneUserService;

// Скрипт не подключает epilog/header.php, поэтому куки и заголовки, поставленные
// через $USER->Authorize() (Bitrix\Main\HttpResponse), никогда не долетали бы до
// браузера без явного Application::end() — оборачиваем весь вывод в буфер и всегда
// завершаемся через него вместо голого exit.
ob_start();

header('Content-Type: application/json; charset=utf-8');

global $USER;

function smsruRespond(array $data): void
{
    echo json_encode($data);
    \Bitrix\Main\Application::getInstance()->end();
}

// Уже авторизован (например, повторный вызов) — не создаём второй аккаунт и
// не переавторизуем сессию, просто подтверждаем текущее состояние.
if ($USER->IsAuthorized()) {
    $arCurrentUser = \CUser::GetByID($USER->GetID())->Fetch();
    smsruRespond([
        'success'    => true,
        'phone'      => $arCurrentUser['PERSONAL_PHONE'] ?? '',
        'authorized' => true,
        'is_new'     => false,
    ]);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$normalizedPhone = PhoneNumberNormalizer::normalize((string)($input['phone'] ?? ''));
$code = trim((string)($input['code'] ?? ''));

if ($normalizedPhone === null || $code === '') {
    http_response_code(400);
    smsruRespond(['success' => false, 'message' => 'Телефон и код обязательны']);
}

// Согласие на обработку персональных данных (152-ФЗ) — форма на /auth/
// блокирует форму до отметки чекбокса, но не доверяем одной только JS-
// проверке, дублируем на сервере.
if (($input['agree_pd'] ?? '') !== 'Y') {
    http_response_code(200);
    smsruRespond(['success' => false, 'message' => 'Подтвердите согласие на обработку персональных данных']);
}

$verifyResult = PhoneAuthCodeService::verifyCode($normalizedPhone, $code);
if ($verifyResult !== true) {
    http_response_code(200);
    smsruRespond(['success' => false, 'message' => $verifyResult]);
}

try {
    [$userId, $isNew] = PhoneUserService::findOrCreateUserId($normalizedPhone);
} catch (\Throwable $e) {
    error_log('smsru_verify_code: findOrCreateUserId упал: ' . $e->getMessage());
    http_response_code(200);
    smsruRespond(['success' => false, 'message' => 'Не удалось создать пользователя']);
}

$USER->Authorize($userId, true);

smsruRespond([
    'success'    => true,
    'phone'      => $normalizedPhone,
    'authorized' => true,
    'is_new'     => $isNew,
]);
