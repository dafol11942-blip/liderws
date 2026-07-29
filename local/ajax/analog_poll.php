<?php
/**
 * Polling для Phase 2
 * 1. Если задача в очереди есть → читаем статус
 * 2. Если нет → создаём pending и запускаем p2_worker.php через exec
 */
ob_start();
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$hash = trim($_GET['hash'] ?? '');
if (empty($hash)) { echo json_encode(['ready'=>false]); exit; }

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$p2File = $docRoot . '/upload/cache/search/p2/' . $hash . '.json';

$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
if ($db->connect_error) {
    ob_clean();
    echo json_encode(['ready'=>false,'error'=>'db_connect']);
    exit;
}
$db->set_charset('utf8mb4');

// Проверяем статус
$stmt = $db->prepare("SELECT status, result_count FROM b_p2_queue WHERE hash=? LIMIT 1");
$stmt->bind_param('s', $hash);
$stmt->execute();
$qRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($qRow) {
    $db->close();
    ob_clean();
    if ($qRow['status'] === 'done') {
        $p2Data = file_exists($p2File) ? json_decode(file_get_contents($p2File), true) : [];
        echo json_encode([
            'ready'    => true,
            'p2_count' => (int)$qRow['result_count'],
            'p2_results' => $p2Data['p2_results'] ?? [],
        ]);
    } elseif ($qRow['status'] === 'error') {
        echo json_encode(['ready'=>false,'error'=>'p2_failed']);
    } else {
        echo json_encode(['ready'=>false,'status'=>$qRow['status']]);
    }
    exit;
}

// Записи нет — создаём и запускаем воркер
if (!file_exists($p2File)) {
    $db->close();
    ob_clean();
    echo json_encode(['ready'=>false,'error'=>'no_p2_file']);
    exit;
}

$data = json_decode(file_get_contents($p2File), true);
$article = $data['article'] ?? ($data['umapiAnalogs'][0]['article'] ?? $hash);
$brand   = $data['brand']   ?? ($data['umapiAnalogs'][0]['brand']   ?? '');

$stmt = $db->prepare(
    "INSERT IGNORE INTO b_p2_queue (hash, article, brand, status, created_at) VALUES (?,?,?,'pending', NOW())"
);
$stmt->bind_param('sss', $hash, $article, $brand);
$stmt->execute();
$inserted = $stmt->affected_rows > 0;
$stmt->close();
$db->close();

// Если новая запись — запускаем воркер немедленно в фоне
if ($inserted) {
    $phpBin = '/usr/bin/php';
    $workerScript = $docRoot . '/local/php_interface/cron/p2_worker.php';
    $cmd = sprintf('%s %s %s > /dev/null 2>&1 &', $phpBin, $workerScript, escapeshellarg($hash));
    exec($cmd);
}

ob_clean();
echo json_encode(['ready'=>false,'status'=>'pending']);