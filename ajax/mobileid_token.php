<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$fingerprintHash = (string)($input['fingerprint_hash'] ?? '');

if ($fingerprintHash === '') {
    http_response_code(400);
    echo json_encode(['error' => 'fingerprint_hash required']);
    exit;
}

[$status, $body] = getMobileIdClient()->getToken($fingerprintHash);

http_response_code($status);
echo json_encode($body);
