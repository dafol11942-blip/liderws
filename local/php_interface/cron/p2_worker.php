<?php
/**
 * P2 Worker — выполнение Phase 2 (без Битрикс)
 * Запуск: php p2_worker.php [hash]  — с хешем = немедленно, без хеша = взять pending из очереди
 * Внутренний цикл: чанки по 50, но задача не возвращается в pending
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/p2_worker_' . date('Y-m-d') . '.log';

function wlog(string $msg): void {
    global $logFile;
    file_put_contents($logFile, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// === Аргументы ===
$cliHash = $argv[1] ?? '';

// === Блокировка ===
$lockFile = $docRoot . '/upload/cache/search/p2/.worker.lock';
$lockFp = fopen($lockFile, 'w');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    wlog("SKIP: другой воркер уже работает");
    exit(0);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
$db->set_charset('utf8mb4');

// Сбрасываем зависшие running (>120с)
$db->query("UPDATE b_p2_queue SET status='pending', started_at=NULL
            WHERE status='running' AND started_at < NOW() - INTERVAL 120 SECOND");

// === Выбор задачи ===
if ($cliHash !== '') {
    $stmt = $db->prepare("SELECT * FROM b_p2_queue WHERE hash=? AND status IN ('pending','running') LIMIT 1");
    $stmt->bind_param('s', $cliHash);
    $stmt->execute();
    $taskRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $taskRow = $db->query(
        "SELECT * FROM b_p2_queue WHERE status='pending' ORDER BY created_at ASC LIMIT 1"
    )->fetch_assoc();
}

if (!$taskRow) {
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

$id   = (int)$taskRow['id'];
$hash = $taskRow['hash'];

// Не перезапускаем done
if ($taskRow['status'] === 'done') {
    wlog("SKIP id=$id: уже done");
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

// Атомарно захватываем (только если pending)
if ($taskRow['status'] === 'pending') {
    $db->query("UPDATE b_p2_queue SET status='running', started_at=NOW()
                WHERE id=$id AND status='pending'");
    if ($db->affected_rows !== 1) {
        $db->close();
        flock($lockFp, LOCK_UN);
        exit(0);
    }
}

wlog("START id=$id hash=$hash article={$taskRow['article']} brand={$taskRow['brand']}");

$p2File = $docRoot . '/upload/cache/search/p2/' . $hash . '.json';

if (!file_exists($p2File)) {
    $db->query("UPDATE b_p2_queue SET status='error', done_at=NOW() WHERE id=$id");
    wlog("ERROR: p2 file not found: $p2File");
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(1);
}

$data = json_decode(file_get_contents($p2File), true);

// Уже done — не перезапускаем
if (!empty($data['done'])) {
    wlog("SKIP id=$id: JSON already done");
    $db->query("UPDATE b_p2_queue SET status='done', done_at=NOW() WHERE id=$id");
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

if (!isset($data['p2_results'])) {
    $data['p2_results'] = [];
}

$allAnalogs = $data['umapiAnalogs'] ?? [];

if (empty($allAnalogs)) {
    $db->query("UPDATE b_p2_queue SET status='done', result_count=0, done_at=NOW() WHERE id=$id");
    $data['done'] = true;
    $data['p2_count'] = 0;
    $data['running'] = false;
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
    wlog("DONE: нет umapiAnalogs, count=0");
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

wlog("umapiAnalogs: " . count($allAnalogs) . " аналогов");

// === Загружаем библиотеки (без Битрикс) ===
require_once $docRoot . '/local/php_interface/lib/Search/BrandNormalizer.php';
require_once $docRoot . '/local/php_interface/lib/Search/SearchResultItem.php';
require_once $docRoot . '/local/php_interface/lib/Search/Stage2/FullSearchLauncher.php';
require_once $docRoot . '/local/php_interface/lib/Search/Common/MultiCurlExecutor.php';
require_once $docRoot . '/local/php_interface/lib/Supplier/SupplierInterface.php';
require_once $docRoot . '/local/php_interface/lib/Supplier/SupplierFactory.php';
foreach (['Moskvorechie','Rossko','PartKom','Autoeuro','Berg','Ixora','ShateM','Tatparts','Autoruss','Autopiter'] as $c) {
    require_once $docRoot . '/local/php_interface/lib/Supplier/' . $c . 'Connector.php';
}

try {
    $f = new \Lider\Supplier\SupplierFactory();
    $f->register(new \Lider\Supplier\MoskvorechieConnector(['API_KEY'=>'2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf']));
    $f->register(new \Lider\Supplier\RosskoConnector(['KEY1'=>'d6907f0f857524815255b74cda86fe9b','KEY2'=>'a514b4c11299686d7cfe8fd3563d1c58','DELIVERY_ID'=>'000000002','ADDRESS_ID'=>'71520']));
    $f->register(new \Lider\Supplier\BergConnector(['API_KEY'=>'9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36','ADDRESS_ID'=>31173]));
    $f->register(new \Lider\Supplier\AutoeuroConnector(['API_KEY'=>'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX','DELIVERY_KEY'=>'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA']));
    $f->register(new \Lider\Supplier\PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']));
    $f->register(new \Lider\Supplier\IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81116D','TIMEOUT'=>8]));
    $f->register(new \Lider\Supplier\TatpartsConnector());
    $f->register(new \Lider\Supplier\AutorussConnector(['LOGIN'=>'Lider-16@bk.ru','PASSWORD_MD5'=>'00fd3781d2cfdf0d971b57fa7397cfac']));
    $f->register(new \Lider\Supplier\AutopiterConnector(['USER_ID'=>'165286','PASSWORD'=>'LidGates16']));

    $launcher = new \Lider\Search\Stage2\FullSearchLauncher($f);

    $chunkSize = 50;
    $totalSaved = 0;

    // === Основной цикл: обрабатываем все чанки за один запуск ===
    while (!empty($allAnalogs)) {
        $chunk = array_slice($allAnalogs, 0, $chunkSize);
        $allAnalogs = array_slice($allAnalogs, $chunkSize);
        $remaining = count($allAnalogs);

        wlog("Chunk: " . count($chunk) . " аналогов, осталось: $remaining");

        $p2Results = $launcher->executePhase2($chunk, 25.0);
        $chunkCount = count($p2Results);
        wlog("executePhase2 вернул $chunkCount результатов");

        if ($chunkCount === 0) {
            continue; // пустой чанк — пропускаем
        }

        // Сохраняем в b_supplier_stock
        $stmt = $db->prepare(
            "INSERT INTO b_supplier_stock (supplier_code, stock_id, article, brand, brand_normalized, name, price, quantity, warehouse_name, warehouse_code, delivery_days, is_sched, multiplicity, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                article = VALUES(article),
                brand = VALUES(brand),
                brand_normalized = VALUES(brand_normalized),
                name = VALUES(name),
                price = VALUES(price),
                quantity = VALUES(quantity),
                warehouse_name = VALUES(warehouse_name),
                warehouse_code = VALUES(warehouse_code),
                delivery_days = VALUES(delivery_days),
                is_sched = VALUES(is_sched),
                multiplicity = VALUES(multiplicity),
                is_active = 1"
        );

        $savedCount = 0;
        foreach ($p2Results as $item) {
            // Копируем в локальные переменные (исправление bind_param)
            $s_source    = (string)$item->source;
            $s_article   = (string)$item->article;
            $s_brand     = (string)$item->brand;
            $s_name      = (string)($item->name ?? '');
            $s_warehouse = (string)($item->warehouse ?? '');
            $s_stockId   = (string)($item->stockId ?? '');
            $d_price     = (float)($item->price ?? 0);
            $i_quantity  = (int)($item->quantity ?? 0);
            $i_delivery  = (int)($item->deliveryDays ?? 0);
            $i_isSched   = (int)($item->isSched ?? 0);
            $i_mult      = (int)($item->multiplicity ?? 1);

            $s_stock_id  = md5($s_source . '|' . $s_article . '|' . $s_brand . '|' . $s_warehouse . '|' . $d_price . '|' . $s_stockId);

            $stmt->bind_param('ssssssdissiii',
                $s_source,
                $s_stock_id,
                $s_article,
                $s_brand,
                $s_brand,
                $s_name,
                $d_price,
                $i_quantity,
                $s_warehouse,
                $s_stockId,
                $i_delivery,
                $i_isSched,
                $i_mult
            );
            $stmt->execute();
            $savedCount++;
        }
        $stmt->close();
        $totalSaved += $savedCount;
        wlog("Сохранено в b_supplier_stock: $savedCount строк");

        // Обновляем JSON
        $data['p2_results'] = array_merge($data['p2_results'], array_map(function($item) {
            return [
                'source'       => $item->source,
                'article'      => $item->article,
                'brand'        => $item->brand,
                'name'         => $item->name ?? '',
                'price'        => $item->price ?? 0,
                'quantity'     => $item->quantity ?? 0,
                'warehouse'    => $item->warehouse ?? '',
                'stockId'      => $item->stockId ?? '',
                'supplierName' => $item->supplierName ?? '',
                'isSched'      => $item->isSched ?? 0,
                'deliveryDays' => $item->deliveryDays ?? 0,
                'deliveryPeriod' => $item->deliveryPeriod ?? 0,
                'multiplicity' => $item->multiplicity ?? 1,
                'unit'         => $item->unit ?? 'шт.',
            ];
        }, $p2Results));

        $totalCount = count($data['p2_results']);
        $data['umapiAnalogs'] = $allAnalogs;
        $data['p2_count'] = $totalCount;
        $data['running'] = true;
        $data['done'] = empty($allAnalogs);

        file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // Всё обработано
    $data['done'] = true;
    $data['running'] = false;
    $data['umapiAnalogs'] = [];
    $totalCount = count($data['p2_results']);
    $data['p2_count'] = $totalCount;
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));

    $db->query("UPDATE b_p2_queue SET status='done', result_count=$totalCount, done_at=NOW() WHERE id=$id");
    wlog("DONE id=$id total=$totalCount saved=$totalSaved");

} catch (\Throwable $e) {
    wlog("EXCEPTION: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $db->query("UPDATE b_p2_queue SET status='error', done_at=NOW() WHERE id=$id");
    $data['error']   = $e->getMessage();
    $data['running'] = false;
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
}

$db->close();
flock($lockFp, LOCK_UN);