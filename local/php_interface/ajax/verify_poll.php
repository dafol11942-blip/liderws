<?php
/**
 * AJAX: Поллинг статуса верификации.
 * GET: ?task_hash=...
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

$taskHash = trim((string)($_GET['task_hash'] ?? ''));
if (empty($taskHash)) {
    echo json_encode(['ok' => false, 'error' => 'Missing task_hash']);
    exit;
}

$db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
$stmt = $db->prepare("SELECT status, result_json FROM b_search_verify_tasks WHERE task_hash = ?");
$stmt->bind_param('s', $taskHash);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$db->close();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Not found', 'status' => 'not_found']);
    exit;
}

echo json_encode([
    'ok'     => true,
    'status' => $row['status'],
    'result' => $row['result_json'] ? json_decode($row['result_json'], true) : null,
]);
