<?php
/**
 * Крон: опрос реальных статусов заказов у поставщиков по нашим reference
 * (b_supplier_order_item.REFERENCE), обновляет STATE_ID/STATE_TEXT/... в той же
 * таблице. Запуск: раз в 15-30 минут через crontab (интервал согласовать при
 * выкладке).
 *
 * Не пытаемся угадывать "терминальность" статуса по незнакомому словарю
 * state/stateTxt — просто ограничиваем окно опроса возрастом строки (иначе
 * очередь на опрос росла бы бесконечно).
 */

$docRoot = '/var/www/u3564357/data/www/liderws.ru';
// Голый CLI-запуск не заполняет $_SERVER['DOCUMENT_ROOT'] сам — коннекторам
// (см. MoskvorechieConnector::log()/PartKomConnector::log()) он нужен для
// своих собственных логов supplier-specific запросов/ответов.
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$logFile = $docRoot . '/upload/logs/supplier_order_status_poll_' . date('Y-m-d') . '.log';

function clog(string $msg): void {
    global $logFile;
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

const RECHECK_AFTER_MINUTES = 30;
const MAX_AGE_DAYS          = 30;
const BATCH_LIMIT           = 200;

// Ручной запуск с --force (или -f) игнорирует 30-минутное окно — удобно при
// тестировании, чтобы не ждать. Обычный крон-запуск по расписанию его не передаёт.
$forceRecheck = in_array('--force', $argv ?? [], true) || in_array('-f', $argv ?? [], true);

$db = new mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
if ($db->connect_error) {
    clog('DB connect failed: ' . $db->connect_error);
    exit(1);
}
$db->set_charset('utf8mb4');

clog('=== supplier_order_status_poll START ===');

// Коннекторы не зависят от ядра Bitrix — только от Lider\Search\*, которые в том
// же автозагрузчике. Полный bitrix/header.php тут не нужен.
require_once $docRoot . '/local/php_interface/lib/autoload.php';

$connectorsByCode = [
    'partkom' => new \Lider\Supplier\PartKomConnector([
        'LOGIN'    => 'lider16',
        'PASSWORD' => 'LidGates16',
    ]),
    'moskvorechie' => new \Lider\Supplier\MoskvorechieConnector([
        'API_KEY' => 'hRohAwdf9nEy:qb9WatcqtLCdxunJ6klPootnydulyYMZ',
    ]),
    'autoeuro' => new \Lider\Supplier\AutoeuroConnector([
        'API_KEY' => 'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX',
        'DELIVERY_KEY' => 'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA',
    ]),
    'berg' => new \Lider\Supplier\BergConnector([
        'API_KEY' => '9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36',
        'ADDRESS_ID' => 31173,
    ]),
    'rossko' => new \Lider\Supplier\RosskoConnector([
        'KEY1' => 'd6907f0f857524815255b74cda86fe9b',
        'KEY2' => 'a514b4c11299686d7cfe8fd3563d1c58',
    ]),
    'autoruss' => new \Lider\Supplier\AutorussConnector([
        'LOGIN' => 'Lider-16@bk.ru',
        'PASSWORD_MD5' => '00fd3781d2cfdf0d971b57fa7397cfac',
    ]),
    'ixora' => new \Lider\Supplier\IxoraConnector([
        'AUTH_CODE' => '460880B0988C8C204B2DD392EC81611D',
        'TIMEOUT' => 8,
    ]),
    'autopiter' => new \Lider\Supplier\AutopiterConnector([
        'USER_ID' => '165286',
        'PASSWORD' => 'LidGates16',
    ]),
    'armtek' => new \Lider\Supplier\ArmtekConnector([
        'LOGIN' => 'lider-16@bk.ru',
        'PASSWORD' => 'LidGates166',
        'VKORG' => '4220',
        'KUNRG' => '40039944',
        'KUNWE' => '43039417',
        'KUNZA' => '48022996',
        'VBELN' => '40359920',
    ]),
    'tatparts' => new \Lider\Supplier\TatpartsConnector([
        'LOGIN' => 'lider-16@bk.ru',
        'PASSWORD' => "'8dTpDU8}Myr)*&",
        'TIMEOUT' => 10,
    ]),
    // Другие поставщики добавятся сюда по мере реализации у них
    // SupplierOrderStatusProvider — остальной скрипт их не касается.
];

$recheckCond = $forceRecheck
    ? '1=1'
    : "(i.LAST_CHECKED_AT IS NULL OR i.LAST_CHECKED_AT < NOW() - INTERVAL " . RECHECK_AFTER_MINUTES . " MINUTE)";

$sql = "SELECT i.ID, i.REFERENCE, o.SUPPLIER_CODE
        FROM b_supplier_order_item i
        JOIN b_supplier_order o ON o.ID = i.SUPPLIER_ORDER_ID
        WHERE i.CREATED_AT > NOW() - INTERVAL " . MAX_AGE_DAYS . " DAY
          AND {$recheckCond}
        ORDER BY i.LAST_CHECKED_AT IS NULL DESC, i.LAST_CHECKED_AT ASC
        LIMIT " . BATCH_LIMIT;

if ($forceRecheck) clog('--force: игнорирую 30-минутное окно между проверками');

$rows = $db->query($sql);
if (!$rows) {
    clog('SELECT failed: ' . $db->error);
    exit(1);
}

$checked = 0;
$updated = 0;
$skippedNoConnector = 0;

while ($row = $rows->fetch_assoc()) {
    $connector = $connectorsByCode[$row['SUPPLIER_CODE']] ?? null;
    if (!$connector instanceof \Lider\Supplier\SupplierOrderStatusProvider) {
        $skippedNoConnector++;
        continue;
    }

    $checked++;
    try {
        $statuses = $connector->fetchOrderStatusByReference($row['REFERENCE']);
    } catch (\Throwable $e) {
        clog("reference={$row['REFERENCE']}: исключение — " . $e->getMessage());
        continue;
    }

    // Опрос всегда отмечаем (LAST_CHECKED_AT), даже если ответ пуст — иначе
    // пустая строка будет опрашиваться на каждом прогоне без пользы.
    $itemId = (int)$row['ID'];

    if (empty($statuses)) {
        $db->query("UPDATE b_supplier_order_item SET LAST_CHECKED_AT = NOW() WHERE ID = {$itemId}");
        continue;
    }

    // Если записей несколько — берём последнюю по orderDate из raw, иначе последнюю в списке.
    $last = end($statuses);

    // Формат дат от API не документирован — если strtotime не смог разобрать
    // строку, пишем NULL в структурированное поле, а не роняем весь UPDATE
    // (сырой ответ всё равно цел в LAST_STATUS_JSON).
    $toSqlDate = function ($value) {
        if (empty($value)) return null;
        $ts = strtotime((string)$value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    };
    $expectedDateSql   = $toSqlDate($last['expected_date'] ?? null);
    $guaranteedDateSql = $toSqlDate($last['guaranteed_date'] ?? null);

    // Общий, поставщико-независимый этап — коннектор сам его посчитал из своего
    // словаря (см. PartKomConnector::normalizeStage()); дефолт 'ordered', если
    // коннектор почему-то его не вернул (более старая реализация и т.п.).
    $stage = in_array($last['stage'] ?? null, ['ordered', 'in_transit', 'ready', 'refused'], true)
        ? $last['stage'] : 'ordered';

    $updateOk = $db->query(sprintf(
        "UPDATE b_supplier_order_item SET
            SUPPLIER_ORDER_NUMBER = '%s',
            STATE_ID = %s,
            STATE_TEXT = '%s',
            STAGE = '%s',
            EXPECTED_DATE = %s,
            GUARANTEED_DATE = %s,
            STORE_COUNT = %s,
            RELEASE_COUNT = %s,
            REFUSAL_COUNT = %s,
            LAST_STATUS_JSON = '%s',
            LAST_CHECKED_AT = NOW()
         WHERE ID = %d",
        $db->real_escape_string((string)($last['order_number'] ?? '')),
        $last['state_id'] !== null ? "'" . $db->real_escape_string((string)$last['state_id']) . "'" : 'NULL',
        $db->real_escape_string((string)($last['state_text'] ?? '')),
        $db->real_escape_string($stage),
        $expectedDateSql ? "'" . $expectedDateSql . "'" : 'NULL',
        $guaranteedDateSql ? "'" . $guaranteedDateSql . "'" : 'NULL',
        $last['store_count'] !== null ? (int)$last['store_count'] : 'NULL',
        $last['release_count'] !== null ? (int)$last['release_count'] : 'NULL',
        $last['refusal_count'] !== null ? (int)$last['refusal_count'] : 'NULL',
        $db->real_escape_string(json_encode($statuses, JSON_UNESCAPED_UNICODE)),
        $itemId
    ));

    if ($updateOk) {
        $updated++;
    } else {
        clog("reference={$row['REFERENCE']}: UPDATE failed — " . $db->error);
    }
}

clog("Проверено: {$checked}, обновлено: {$updated}, без коннектора статуса: {$skippedNoConnector}");
clog('=== supplier_order_status_poll DONE ===');

$db->close();
