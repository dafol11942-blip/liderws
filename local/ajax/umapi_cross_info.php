<?php
/**
 * Карточка товара для /search/ — фото/описание/характеристики/замены по данным UMAPI
 * getCrossInfo (отдельный эндпоинт от Analogs/pro, используемого в analog_search.php —
 * свой ключ, см. b_umapi_cross_info_table.sql). Вызывается асинхронно с фронтенда
 * (search/index.php, loadCrossInfo()), параллельно с основным поиском предложений —
 * НЕ блокирует и не замедляет страницу, если UMAPI медленная/недоступна.
 *
 * Логика кэша/запроса к UMAPI — в Lider\Search\UmapiCrossInfo (общая с батч-версией
 * для аналогов, см. umapi_cross_info_batch.php).
 *
 * GET article, brand
 */
define('NOT_CHECK_PERMISSIONS', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/Common/MultiCurlExecutor.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/UmapiCrossInfo.php');

use Lider\Search\UmapiCrossInfo;

header('Content-Type: application/json; charset=utf-8');

$article = trim((string)($_GET['article'] ?? ''));
$brand   = trim((string)($_GET['brand'] ?? ''));

if ($article === '' || $brand === '') {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $result = UmapiCrossInfo::fetchMany([[
        'id'      => 'x',
        'article' => $article,
        'brand'   => $brand,
    ]]);
    echo json_encode($result['x'] ?? ['success' => false], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode(['success' => false]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
