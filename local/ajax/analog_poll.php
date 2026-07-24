<?php
/**
 * Polling: проверка готовности Phase 2.
 * GET ?hash=xxx
 * Возвращает: {ready: true, p2_count: N} или {ready: false}
 */
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$hash = trim($_GET['hash'] ?? '');
if (empty($hash)) { echo json_encode(['ready'=>false]); exit; }

$p2File = '/var/www/u3564357/data/www/liderws.ru/upload/cache/search/p2/' . $hash . '.json';
if (!file_exists($p2File)) { echo json_encode(['ready'=>false]); exit; }

$data = json_decode(file_get_contents($p2File), true);
echo json_encode([
    'ready' => !empty($data['done']),
    'p2_count' => $data['p2_count'] ?? 0,
    'error' => $data['error'] ?? null,
]);