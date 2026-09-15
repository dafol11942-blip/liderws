<?php
/**
 * Батч-версия umapi_cross_info.php — карточки (фото/наименование/характеристики) сразу
 * для НЕСКОЛЬКИХ пар артикул+бренд за один запрос. Используется /search/ для аналогов
 * (loadAnalogCrossInfo() в search/index.php) — одна группа аналога = одна пара.
 * Один HTTP-запрос с фронтенда вместо N штук, кэш-хиты отдаются все разом, живые запросы
 * к UMAPI для кэш-промахов идут параллельно, см. UmapiCrossInfo::fetchMany().
 *
 * POST JSON: {"items":[{"id":"...","article":"...","brand":"..."}, ...]}
 * Ответ: {"<id>": {"success":true,"title":...,"img":...,"criterias":[...],"oem":[...],"superseded":{...}} | {"success":false}, ...}
 */
define('NOT_CHECK_PERMISSIONS', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Сессия Bitrix (файловая блокировка) держится, пока скрипт не закроет её сам — а этот
// эндпоинт дальше делает МЕДЛЕННЫЕ curl-запросы к UMAPI (десятки пар, до ~10с). Без явного
// закрытия сессия остаётся заблокированной на всё это время, и любой другой AJAX той же
// вкладки (в первую очередь search/ajax.php — поиск/докрутка) встаёт в очередь на её
// открытие — именно это превращало "второстепенную" докачку карточек аналогов в тормоза
// и таймауты у КРИТИЧНОЙ докрутки складов. Тот же приём уже используется в search/ajax.php.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/Common/MultiCurlExecutor.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/UmapiCrossInfo.php');

use Lider\Search\UmapiCrossInfo;

header('Content-Type: application/json; charset=utf-8');

// Потолок на кол-во пар в ОДНОМ запросе с фронтенда (не путать с MAX_LIVE_REQUESTS_PER_BATCH
// внутри UmapiCrossInfo — тот ограничивает живые походы к UMAPI среди кэш-промахов; этот —
// защита эндпоинта от произвольно большого тела запроса).
const MAX_ITEMS_PER_REQUEST = 150;

$body = json_decode(file_get_contents('php://input'), true);
$items = is_array($body['items'] ?? null) ? $body['items'] : [];

$clean = [];
foreach (array_slice($items, 0, MAX_ITEMS_PER_REQUEST) as $it) {
    if (!is_array($it)) continue;
    $id      = trim((string)($it['id'] ?? ''));
    $article = trim((string)($it['article'] ?? ''));
    $brand   = trim((string)($it['brand'] ?? ''));
    if ($id === '' || $article === '' || $brand === '') continue;
    $clean[] = ['id' => $id, 'article' => $article, 'brand' => $brand];
}

if (!$clean) {
    echo json_encode(new stdClass());
    exit;
}

try {
    // Дедлайн ниже дефолтного (8с) — карточки аналогов второстепенны и не должны своей
    // холодной загрузкой (десятки пар сразу) заметно задерживать остальные AJAX-запросы
    // страницы (докрутку аналогов, поллинг прогресса), см. UmapiCrossInfo::prefetchImages().
    echo json_encode(UmapiCrossInfo::fetchMany($clean, 6.0), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode(new stdClass());
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
