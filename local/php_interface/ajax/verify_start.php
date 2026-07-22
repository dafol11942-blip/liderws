<?php
/**
 * AJAX: Запуск live-верификации (синхронно, без fastcgi_finish_request).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';

use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\InstantSearcher;

header('Content-Type: application/json; charset=utf-8');
ignore_user_abort(true);
set_time_limit(30);

$taskHash   = trim((string)($_POST['task_hash'] ?? ''));
$article    = trim((string)($_POST['article'] ?? ''));
$brand      = trim((string)($_POST['brand'] ?? ''));
$brandMap   = json_decode((string)($_POST['brandMap'] ?? '{}'), true) ?: [];
$exactKey   = trim((string)($_POST['exactKey'] ?? ''));
$targetEntry = json_decode((string)($_POST['targetEntry'] ?? 'null'), true);

if (empty($taskHash) || empty($article) || empty($brand)) {
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

// Помечаем running
$db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
$db->query("UPDATE b_search_verify_tasks SET status = 'running', updated_at = NOW() WHERE task_hash = '{$db->real_escape_string($taskHash)}'");
$db->close();

try {
    $launcher = new FullSearchLauncher(getSupplierFactory());
    $allResults = $launcher->launch($brand, $article, $brandMap, $exactKey, $targetEntry, 15.0);

    // Кэшируем
    if (!empty($allResults)) {
        $cache = new InstantSearcher();
        $saved = $cache->saveResults($allResults);
    } else {
        $saved = 0;
    }

    // Статус done
    $db2 = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
    $json = json_encode(['total' => count($allResults), 'saved' => $saved], JSON_UNESCAPED_UNICODE);
    $db2->query("UPDATE b_search_verify_tasks SET status = 'done', result_json = '{$db2->real_escape_string($json)}', updated_at = NOW() WHERE task_hash = '{$db2->real_escape_string($taskHash)}'");
    $db2->close();

    echo json_encode(['ok' => true, 'total' => count($allResults), 'saved' => $saved]);

} catch (\Throwable $e) {
    $db2 = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
    $db2->query("UPDATE b_search_verify_tasks SET status = 'done', result_json = '{\"error\":\"".$db2->real_escape_string($e->getMessage())."\"}' WHERE task_hash = '{$db2->real_escape_string($taskHash)}'");
    $db2->close();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
