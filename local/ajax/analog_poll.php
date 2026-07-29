<?php
/**
 * Polling для Phase 2 (v3 — JSON первичен)
 */
ob_start();
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$hash = trim($_GET['hash'] ?? '');
if (empty($hash)) { echo json_encode(['ready'=>false]); exit; }

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$p2File = $docRoot . '/upload/cache/search/p2/' . $hash . '.json';

// === ШАГ 1: JSON — главный источник правды ===
if (file_exists($p2File)) {
    $data = json_decode(file_get_contents($p2File), true);
    $p2Count = count($data['p2_results'] ?? []);
    
    if (!empty($data['done'])) {
        ob_clean();
        echo json_encode(['ready'=>true, 'p2_count'=>$p2Count ?: (int)($data['p2_count']??0)]);
        exit;
    }
    
    if ($p2Count > 0) {
        ob_clean();
        echo json_encode(['ready'=>true, 'p2_count'=>$p2Count, 'partial'=>true]);
        exit;
    }
}

// === ШАГ 2: БД — только для done/error ===
$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
if ($db->connect_error) {
    ob_clean();
    echo json_encode(['ready'=>false, 'error'=>'db_connect']);
    exit;
}
$db->set_charset('utf8mb4');

$stmt = $db->prepare("SELECT status, result_count FROM b_p2_queue WHERE hash=? LIMIT 1");
$stmt->bind_param('s', $hash);
$stmt->execute();
$qRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($qRow && $qRow['status'] === 'error') {
    $db->close();
    ob_clean();
    echo json_encode(['ready'=>false, 'error'=>'p2_failed']);
    exit;
}

// === ШАГ 3: Создать задачу и запустить воркер ===
if (!$qRow && file_exists($p2File)) {
    $data = json_decode(file_get_contents($p2File), true);
    $article = $data['article'] ?? $hash;
    $brand   = $data['brand']   ?? '';

    $stmt = $db->prepare(
        "INSERT IGNORE INTO b_p2_queue (hash, article, brand, status, created_at) VALUES (?,?,?,'pending', NOW())"
    );
    $stmt->bind_param('sss', $hash, $article, $brand);
    $stmt->execute();
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();

    if ($inserted) {
        $cmd = sprintf('/usr/bin/php %s %s > /dev/null 2>&1 &',
            $docRoot . '/local/php_interface/cron/p2_worker.php',
            escapeshellarg($hash)
        );
        exec($cmd);
    }
}

$db->close();
ob_clean();
echo json_encode(['ready'=>false, 'status'=>($qRow ? $qRow['status'] : 'pending')]);