<?php
ob_start();
/**
 * Polling: проверка готовности Phase 2.
 * Только проверяет статус + запускает exec в фоне (НЕ выполняет P2 синхронно).
 */
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$hash = trim($_GET['hash'] ?? '');
if (empty($hash)) { echo json_encode(['ready'=>false]); exit; }

$p2File = '/var/www/u3564357/data/www/liderws.ru/upload/cache/search/p2/' . $hash . '.json';
if (!file_exists($p2File)) { echo json_encode(['ready'=>false]); exit; }

$data = json_decode(file_get_contents($p2File), true);

// Сброс зависшего running (старше 90с)
$isStale = !empty($data['running'])
    && !empty($data['created'])
    && (time() - $data['created']) > 90;

if (empty($data['done']) && (empty($data['running']) || $isStale)) {
    if ($isStale) {
        $data['running'] = false;
        file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // Запускаем P2 только через exec в фоне
    $lockFile = $p2File . '.lock';
    $fp = @fopen($lockFile, 'w');
    if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
        $data['running'] = true;
        file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);

        $article = escapeshellarg($data['article'] ?? '');
        $brand   = escapeshellarg($data['brand'] ?? '');
        exec('/usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/ajax/analog_p2_exec.php '
            . $article . ' ' . $brand . ' > /dev/null 2>&1 &');
    } elseif ($fp) {
        fclose($fp);
    }
    $data = json_decode(file_get_contents($p2File), true);
}

ob_clean();
echo json_encode([
    'ready'    => !empty($data['done']),
    'p2_count' => $data['p2_count'] ?? 0,
    'error'    => $data['error'] ?? null,
]);