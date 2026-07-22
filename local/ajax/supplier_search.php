<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Сразу закрываем сессию, чтобы не блокировать параллельные AJAX-запросы
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'Слишком короткий запрос. Минимум 2 символа.',
    ]);
    die();
}

// Кеширование — файловый кеш, без блокировок
$cacheKey = 'livesearch_' . md5(mb_strtolower($query));
$cache = new \Lider\Search\SearchCacheManager('/search/livesearch', 120);
$cached = $cache->get($cacheKey);

if ($cached !== null) {
    echo json_encode([
        'success' => true,
        'query'   => $query,
        'total'   => count($cached),
        'items'   => $cached,
        'cached'  => true,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    die();
}

try {
    $searchService = getSearchService();
    $results = $searchService->search($query, true, true);

    $items = array_map(fn($item) => $item->toArray(), $results);

    // Кешируем на 2 минуты
    $cache->set($cacheKey, $items, 120);

    echo json_encode([
        'success' => true,
        'query'   => $query,
        'total'   => count($items),
        'items'   => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка поиска: ' . $e->getMessage(),
    ]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
