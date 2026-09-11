<?php
/**
 * Карточка товара для /search/ — фото/описание/характеристики/замены по данным UMAPI
 * getCrossInfo (отдельный эндпоинт от Analogs/pro, используемого в analog_search.php —
 * свой ключ, см. b_umapi_cross_info_table.sql). Вызывается асинхронно с фронтенда
 * (search/index.php, loadCrossInfo()), параллельно с основным поиском предложений —
 * НЕ блокирует и не замедляет страницу, если UMAPI медленная/недоступна.
 *
 * GET article, brand
 */
define('NOT_CHECK_PERMISSIONS', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php');

use Lider\Search\BrandNormalizer;

header('Content-Type: application/json; charset=utf-8');

const UMAPI_CROSSINFO_URL = 'https://api.umapi.ru/v2/cross/parts/Analogs/getCrossInfo';
const UMAPI_CROSSINFO_KEY = '7aa16ec6-c790-45cb-a184-1c11677b78a1';
const TTL_FOUND_SECONDS = 30 * 86400; // найденные данные почти не меняются
const TTL_EMPTY_SECONDS = 1 * 86400;  // подтверждённое "нет данных" — не долбить UMAPI на каждый повтор

// Скачивает картинку с UMAPI один раз и кеширует локально в /upload/umapi_img/<то же
// относительное имя, что дал UMAPI> — дальше отдаём собственным URL (same-origin), см.
// вызов ниже. relPath уже провалидирован regex'ом на вызывающей стороне (/\d+/файл.ext).
function cacheUmapiImage(string $remoteUrl, string $relPath): ?string
{
    $localRel  = '/upload/umapi_img' . $relPath;
    $localAbs  = $_SERVER['DOCUMENT_ROOT'] . $localRel;

    if (is_file($localAbs) && filesize($localAbs) > 0) {
        return $localRel;
    }

    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 6,
    ]);
    $bytes    = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($bytes)) {
        return null;
    }

    $dir = dirname($localAbs);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($localAbs, $bytes);

    return is_file($localAbs) ? $localRel : null;
}

$article = trim((string)($_GET['article'] ?? ''));
$brand   = trim((string)($_GET['brand'] ?? ''));

if ($article === '' || $brand === '') {
    echo json_encode(['success' => false]);
    exit;
}

$articleNorm = BrandNormalizer::normalizeArticle($article);
$brandNorm   = BrandNormalizer::normalize($brand);

try {
    $db     = \Bitrix\Main\Application::getConnection();
    $helper = $db->getSqlHelper();
    $artSql = $helper->forSql($articleNorm);
    $brSql  = $helper->forSql($brandNorm);

    $row = $db->query("SELECT RESPONSE_JSON, FETCHED_AT FROM b_umapi_cross_info WHERE ARTICLE_NORM = '{$artSql}' AND BRAND_NORM = '{$brSql}'")->fetch();

    $rawJson = null;
    if ($row) {
        $age = time() - strtotime($row['FETCHED_AT']);
        $isEmpty = ($row['RESPONSE_JSON'] === null || $row['RESPONSE_JSON'] === '');
        $ttl = $isEmpty ? TTL_EMPTY_SECONDS : TTL_FOUND_SECONDS;
        if ($age <= $ttl) {
            $rawJson = $isEmpty ? '' : $row['RESPONSE_JSON'];
        }
    }

    // Кэш-мисс или протух — идём за свежими данными к UMAPI. rawJson остаётся null
    // только когда реально нужен новый запрос (не путать с '' — это кэшированный "пусто").
    if ($rawJson === null) {
        $url = UMAPI_CROSSINFO_URL . '?' . http_build_query([
            'article'      => $article,
            'brand'        => $brand,
            'Products'     => 'false',
            'Info'         => 'true',
            'LaCriterias'  => 'false',
            'Superseded'   => 'true',
            'PartsList'    => 'false',
            'OEM'          => 'true',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['accept: application/json', 'X-App-Key: ' . UMAPI_CROSSINFO_KEY],
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false || $curlErr) {
            // Транзиентная ошибка (сеть/таймаут/5xx) — в кэш не пишем, просто отдаём "нет данных".
            echo json_encode(['success' => false]);
            exit;
        }

        // "td" может быть null у части позиций (характеристик/OEM/замен нет), но title/img
        // на верхнем уровне ответа при этом всё равно присутствуют — считаем данные полезными,
        // если есть ХОТЬ ЧТО-ТО, а не только когда заполнен td целиком.
        $decoded = json_decode($response, true);
        $hasData = is_array($decoded) && (
            !empty($decoded['td']) || !empty($decoded['title']) || !empty($decoded['img'])
        );

        $toStore = $hasData ? $response : '';
        $db->query(
            "INSERT INTO b_umapi_cross_info (ARTICLE_NORM, BRAND_NORM, RESPONSE_JSON, FETCHED_AT) VALUES ('{$artSql}', '{$brSql}', '" . $helper->forSql($toStore) . "', NOW())
             ON DUPLICATE KEY UPDATE RESPONSE_JSON = '" . $helper->forSql($toStore) . "', FETCHED_AT = NOW()"
        );

        $rawJson = $toStore;
    }

    if ($rawJson === '') {
        echo json_encode(['success' => false]);
        exit;
    }

    $data = json_decode($rawJson, true);
    // td бывает null (нет характеристик/OEM/замен для этой позиции) — это не повод скрывать
    // то, что ЕСТЬ (title/img на верхнем уровне), см. hasData выше. Приводим к массиву, чтобы
    // ?? [] на вложенных ключах ниже отрабатывал одинаково что для null, что для отсутствия.
    $td = (array)($data['td'] ?? []);

    // Картинку отдаём со своего домена, а не прямой ссылкой на image.umapi.ru — у части
    // посетителей блокировщики рекламы (в т.ч. встроенный в Opera) блокируют запросы к
    // незнакомым сторонним доменам с картинками (подтверждено: net::ERR_BLOCKED_BY_CLIENT
    // в DevTools). Скачиваем один раз на сервере и кешируем в /upload — дальше отдаём как
    // обычную картинку сайта, same-origin, блокировщикам нечего блокировать.
    // Путь у UMAPI бывает разного вида ("/4/hash.webp", "/PUBLIC/P2016/AG290.JPEG") —
    // проверяем только на безопасность (относительный путь, без ".." и посторонних символов),
    // не на конкретную форму.
    $img = null;
    if (!empty($data['img']) && preg_match('~^(/[A-Za-z0-9_\-]+)+\.[A-Za-z0-9]+$~', $data['img'])) {
        $img = cacheUmapiImage('https://image.umapi.ru/IMAGE' . $data['img'], $data['img']);
    }

    $criterias = [];
    foreach ((array)($td['CRITERIAS'] ?? []) as $c) {
        $label = trim((string)($c['CRI_SHORT_DES'] ?? $c['CRI_DES'] ?? ''));
        if ($label === '') continue;
        if (($c['CRI_TYPE'] ?? '') === 'KeyValue' && !empty($c['DES'])) {
            $value = (string)$c['DES'];
        } else {
            $value = trim((string)($c['VALUE'] ?? ''));
            $unit = trim((string)($c['CRI_UNIT_DES'] ?? ''));
            if ($unit !== '') $value .= ' ' . $unit;
        }
        $value = trim($value);
        if ($value === '') continue;
        $criterias[] = ['label' => $label, 'value' => $value];
    }

    $oem = [];
    foreach ((array)($td['OEM'] ?? $data['oem'] ?? []) as $o) {
        $num = is_array($o) ? (string)($o['NUMBER'] ?? $o['number'] ?? '') : (string)$o;
        $num = trim($num);
        if ($num !== '') $oem[] = $num;
    }

    $superseded = (array)($td['SUPERSEDED'] ?? []);
    $supersededNew = [];
    foreach ((array)($superseded['NEW'] ?? []) as $s) {
        $num = trim((string)($s['NUMBER'] ?? ''));
        if ($num !== '') $supersededNew[] = $num;
    }
    $supersededOld = [];
    foreach ((array)($superseded['OLD'] ?? []) as $s) {
        $num = trim((string)($s['NUMBER'] ?? ''));
        if ($num !== '') $supersededOld[] = $num;
    }

    $title = (string)($data['title'] ?? '');
    if ($title === '' && !$img && empty($criterias) && empty($oem) && empty($supersededNew) && empty($supersededOld)) {
        echo json_encode(['success' => false]);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'title'      => $title,
        'img'        => $img,
        'criterias'  => $criterias,
        'oem'        => $oem,
        'superseded' => ['new' => $supersededNew, 'old' => $supersededOld],
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['success' => false]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
