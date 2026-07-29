<?php
/**
 * Крон: выполнение Phase 2 поиска аналогов
 * Запуск: каждые 15 секунд
 * НЕ использует Битрикс — прямое подключение к mysqli
 * Чанкинг: по 50 аналогов за запуск (~20-25с)
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
$logFile = $docRoot . '/upload/logs/p2_worker_' . date('Y-m-d') . '.log';

function wlog(string $msg): void {
    global $logFile;
    file_put_contents($logFile, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// Блокировка: только один воркер одновременно
$lockFile = $docRoot . '/upload/cache/search/p2/.worker.lock';
$lockFp = fopen($lockFile, 'w');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
$db->set_charset('utf8mb4');

// Сбрасываем зависшие running задания (>120 секунд)
$db->query("UPDATE b_p2_queue SET status='pending', started_at=NULL
            WHERE status='running' AND started_at < NOW() - INTERVAL 120 SECOND");

// Берём одно pending задание (FIFO)
$taskRow = $db->query(
    "SELECT * FROM b_p2_queue WHERE status='pending' ORDER BY created_at ASC LIMIT 1"
)->fetch_assoc();

if (!$taskRow) {
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

$id   = (int)$taskRow['id'];
$hash = $taskRow['hash'];

// Атомарно захватываем задание
$db->query("UPDATE b_p2_queue SET status='running', started_at=NOW()
            WHERE id=$id AND status='pending'");
if ($db->affected_rows !== 1) {
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(0);
}

wlog("START id=$id hash=$hash article={$taskRow['article']} brand={$taskRow['brand']}");

$p2File = $docRoot . '/upload/cache/search/p2/' . $hash . '.json';

if (!file_exists($p2File)) {
    $db->query("UPDATE b_p2_queue SET status='error' WHERE id=$id");
    wlog("ERROR: p2 file not found: $p2File");
    $db->close();
    flock($lockFp, LOCK_UN);
    exit(1);
}

$data = json_decode(file_get_contents($p2File), true);

// Первый запуск: инициализируем p2_results
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

// --- ЧАНКИНГ: берём первые 50 аналогов ---
$chunkSize = 50;
$chunk = array_slice($allAnalogs, 0, $chunkSize);
$remaining = array_slice($allAnalogs, $chunkSize);

wlog("Chunk: " . count($chunk) . " аналогов, осталось: " . count($remaining));

// Загружаем библиотеки БЕЗ Битрикс
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
    $f->register(new \Lider\Supplier\IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81611D','TIMEOUT'=>8]));
    $f->register(new \Lider\Supplier\TatpartsConnector());
    $f->register(new \Lider\Supplier\AutorussConnector(['LOGIN'=>'Lider-16@bk.ru','PASSWORD_MD5'=>'00fd3781d2cfdf0d971b57fa7397cfac']));
    $f->register(new \Lider\Supplier\AutopiterConnector(['USER_ID'=>'165286','PASSWORD'=>'LidGates16']));

    $launcher = new \Lider\Search\Stage2\FullSearchLauncher($f);

    // 25 секунд на чанк из 50 аналогов
    $p2Results = $launcher->executePhase2($chunk, 25.0);

    $chunkCount = count($p2Results);
    wlog("executePhase2 вернул $chunkCount результатов для чанка");

    // --- Сохраняем в b_supplier_stock (прямой SQL) ---
    $savedCount = 0;
    $stmt = $db->prepare(
        "INSERT INTO b_supplier_stock (supplier_code, stock_id, article, brand, name, price, quantity, warehouse, stockId, supplierName, isSched, deliveryDays, deliveryPeriod, multiplicity, unit, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE
            article = VALUES(article),
            brand = VALUES(brand),
            name = VALUES(name),
            price = VALUES(price),
            quantity = VALUES(quantity),
            warehouse = VALUES(warehouse),
            supplierName = VALUES(supplierName),
            isSched = VALUES(isSched),
            deliveryDays = VALUES(deliveryDays),
            deliveryPeriod = VALUES(deliveryPeriod),
            multiplicity = VALUES(multiplicity),
            unit = VALUES(unit),
            is_active = 1,
            updated_at = NOW()"
    );

    foreach ($p2Results as $item) {
        $source    = $item->source;
        $article   = $item->article;
        $brand     = $item->brand;
        $warehouse = $item->warehouse ?? '';
        $price     = (float)($item->price ?? 0);
        $stockId   = $item->stockId ?? '';
        $stock_id  = md5($source . '|' . $article . '|' . $brand . '|' . $warehouse . '|' . $price . '|' . $stockId);

        $stmt->bind_param('sssssdissiiisss',
            $source,
            $stock_id,
            $article,
            $brand,
            $item->name,
            $price,
            $item->quantity,
            $warehouse,
            $stockId,
            $item->supplierName,
            $item->isSched,
            $item->deliveryDays,
            $item->deliveryPeriod ?? 0,
            $item->multiplicity ?? 1,
            $item->unit ?? 'шт.'
        );
        $stmt->execute();
        $savedCount++;
    }
    $stmt->close();
    wlog("Сохранено в b_supplier_stock: $savedCount строк");

    // Добавляем результаты к накопленным
    $data['p2_results'] = array_merge($data['p2_results'], array_map(function($item) {
        return [
            'source'       => $item->source,
            'article'      => $item->article,
            'brand'        => $item->brand,
            'name'         => $item->name,
            'price'        => $item->price,
            'quantity'     => $item->quantity,
            'warehouse'    => $item->warehouse,
            'stockId'      => $item->stockId,
            'supplierName' => $item->supplierName,
            'isSched'      => $item->isSched,
            'deliveryDays' => $item->deliveryDays,
            'deliveryPeriod' => $item->deliveryPeriod ?? 0,
            'multiplicity' => $item->multiplicity ?? 1,
            'unit'         => $item->unit ?? 'шт.',
        ];
    }, $p2Results));

    $totalCount = count($data['p2_results']);

    if (empty($remaining)) {
        // Все аналоги обработаны
        $data['umapiAnalogs'] = [];
        $data['done']    = true;
        $data['p2_count'] = $totalCount;
        $data['running'] = false;
        $db->query("UPDATE b_p2_queue SET status='done', result_count=$totalCount, done_at=NOW() WHERE id=$id");
        wlog("DONE (все чанки) id=$id total=$totalCount");
    } else {
        // Остались ещё аналоги — возвращаем в pending
        $data['umapiAnalogs'] = $remaining;
        $data['done']    = false;
        $data['p2_count'] = $totalCount;
        $data['running'] = false;
        $db->query("UPDATE b_p2_queue SET status='pending', started_at=NULL WHERE id=$id");
        wlog("CHUNK DONE id=$id chunkCount=$chunkCount total=$totalCount remaining=" . count($remaining));
    }

    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));

} catch (\Throwable $e) {
    wlog("EXCEPTION: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $db->query("UPDATE b_p2_queue SET status='error' WHERE id=$id");
    $data['error']   = $e->getMessage();
    $data['running'] = false;
    file_put_contents($p2File, json_encode($data, JSON_UNESCAPED_UNICODE));
}

$db->close();
flock($lockFp, LOCK_UN);