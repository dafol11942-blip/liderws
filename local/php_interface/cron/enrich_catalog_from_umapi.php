<?php
/**
 * Разовый/периодический бэкофилл характеристик и фото товаров своего склада (IBLOCK_ID 42,
 * "в наличии" — сумма остатков по складам > 0) через UMAPI getCrossInfo — тот же эндпоинт
 * и кеш-таблицу (b_umapi_cross_info), что уже использует карточка товара в /search/
 * (см. local/ajax/umapi_cross_info.php). Ничего не перезаписывает — только дозаполняет
 * отсутствующие свойства и отсутствующее фото.
 *
 * Запуск: php local/php_interface/cron/enrich_catalog_from_umapi.php
 * Throttle/логирование — по образцу build_cross_index.php (тот же проверенный в проде
 * паттерн ночного крона к UMAPI).
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

$_SERVER['DOCUMENT_ROOT'] = '/var/www/u3564357/data/www/liderws.ru';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';

use Lider\Search\BrandNormalizer;

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

const IBLOCK_ID          = 42;
const UMAPI_CROSSINFO_URL = 'https://api.umapi.ru/v2/cross/parts/Analogs/getCrossInfo';
const UMAPI_CROSSINFO_KEY = '7aa16ec6-c790-45cb-a184-1c11677b78a1';
const TTL_FOUND_SECONDS  = 30 * 86400;
const TTL_EMPTY_SECONDS  = 1 * 86400;
const DELAY_US           = 200000; // throttle между реальными (не кэш) обращениями к UMAPI
const LOG_DIR            = '/var/www/u3564357/data/www/liderws.ru/upload/logs/';

$logFile = LOG_DIR . 'enrich_catalog_from_umapi_' . date('Y-m-d_H-i-s') . '.log';
@mkdir(LOG_DIR, 0755, true);

function logger(string $msg): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

// ─── UMAPI: кэш-таблица (та же, что local/ajax/umapi_cross_info.php) ─────────
function fetchCrossInfoCached(string $article, string $brand, $db, $helper, array &$stats): ?array
{
    $articleNorm = BrandNormalizer::normalizeArticle($article);
    $brandNorm   = BrandNormalizer::normalize($brand);
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

    if ($rawJson === null) {
        $stats['api_calls']++;
        $url = UMAPI_CROSSINFO_URL . '?' . http_build_query([
            'article' => $article, 'brand' => $brand,
            'Products' => 'false', 'Info' => 'true', 'LaCriterias' => 'false',
            'Superseded' => 'true', 'PartsList' => 'false', 'OEM' => 'true',
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['accept: application/json', 'X-App-Key: ' . UMAPI_CROSSINFO_KEY],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        usleep(DELAY_US);

        if ($httpCode !== 200 || $response === false || $curlErr) {
            $stats['errors']++;
            return null; // транзиентная ошибка — в кэш не пишем, просто пропускаем на этом прогоне
        }

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
    } else {
        $stats['cache_hits']++;
    }

    if ($rawJson === '') return null;
    $data = json_decode($rawJson, true);
    return is_array($data) ? $data : null;
}

// ─── Характеристики: та же логика форматирования, что в umapi_cross_info.php ──
function extractCriterias(array $td): array
{
    $out = [];
    foreach ((array)($td['CRITERIAS'] ?? []) as $c) {
        $criId = (int)($c['CRI_ID'] ?? 0);
        if ($criId <= 0) continue;
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
        $out[] = ['cri_id' => $criId, 'label' => $label, 'value' => $value];
    }
    return $out;
}

// ─── Свойство инфоблока: найти по коду или создать ────────────────────────────
$propCache = []; // CODE => ['ID'=>..] — чтобы не дёргать CIBlockProperty::GetList на каждый товар повторно
function ensureProperty(int $criId, string $label, array &$propCache, array &$stats): string
{
    $code = 'UMAPI_CRI_' . $criId;
    if (isset($propCache[$code])) return $code;

    $exist = CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code])->Fetch();
    if ($exist) {
        $propCache[$code] = true;
        return $code;
    }

    $ibp = new CIBlockProperty;
    $newId = $ibp->Add([
        'IBLOCK_ID'      => IBLOCK_ID,
        'NAME'           => $label,
        'ACTIVE'         => 'Y',
        'SORT'           => 500,
        'CODE'           => $code,
        'PROPERTY_TYPE'  => 'S',
        'ROW_COUNT'      => 1,
        'COL_COUNT'      => 30,
        'MULTIPLE'       => 'N',
        'IS_SEARCHABLE'  => 'N',
        'FILTRABLE'      => 'N',
    ]);
    if ($newId) {
        $stats['properties_created']++;
        $propCache[$code] = true;
    } else {
        logger('  ⚠️ Не удалось создать свойство ' . $code . ' (' . $label . '): ' . $ibp->LAST_ERROR);
    }
    return $code;
}

// ─── Фото: скачать во временный файл, вернуть локальный путь или null ─────────
function downloadTempImage(string $remoteUrl): ?string
{
    $ext = strtolower(pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
    $tmpPath = sys_get_temp_dir() . '/umapi_img_' . uniqid() . '.' . $ext;

    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $bytes = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($bytes)) return null;
    file_put_contents($tmpPath, $bytes);
    return is_file($tmpPath) ? $tmpPath : null;
}

// ─── ОСНОВНОЙ ПОТОК ─────────────────────────────────────────────────────────
$db     = \Bitrix\Main\Application::getConnection();
$helper = $db->getSqlHelper();

$stats = [
    'processed' => 0, 'skipped_no_article' => 0, 'skipped_not_in_stock' => 0,
    'properties_created' => 0, 'properties_filled' => 0, 'images_set' => 0,
    'cache_hits' => 0, 'api_calls' => 0, 'errors' => 0,
];

logger('Старт: выборка товаров IBLOCK_ID=' . IBLOCK_ID . ', ACTIVE=Y');

$res = CIBlockElement::GetList(
    [], ['IBLOCK_ID' => IBLOCK_ID, 'ACTIVE' => 'Y'], false, false,
    ['ID', 'NAME', 'DETAIL_PICTURE', 'PREVIEW_PICTURE']
);

$total = 0;
$rows = [];
while ($el = $res->GetNext()) {
    $rows[] = $el;
}
$total = count($rows);
logger('Товаров ACTIVE=Y: ' . $total);

foreach ($rows as $i => $el) {
    $productId = (int)$el['ID'];

    // "В наличии" — та же сумма по складам, что уже используется на странице товара
    // и в корзине (см. catalog.element/lider_style/template.php, cart template).
    $totalAmount = 0;
    $dbStore = CCatalogStoreProduct::GetList([], ['PRODUCT_ID' => $productId], false, false, ['AMOUNT']);
    while ($arStore = $dbStore->Fetch()) $totalAmount += (int)$arStore['AMOUNT'];
    if ($totalAmount <= 0) {
        $stats['skipped_not_in_stock']++;
        continue;
    }

    $article = '';
    $artRes = CIBlockElement::GetProperty(IBLOCK_ID, $productId, [], ['CODE' => 'CML2_ARTICLE']);
    if ($artRow = $artRes->Fetch()) $article = trim((string)($artRow['VALUE'] ?? ''));

    $brand = '';
    $brandRes = CIBlockElement::GetProperty(IBLOCK_ID, $productId, [], ['CODE' => 'CML2_MANUFACTURER']);
    if ($brandRow = $brandRes->Fetch()) $brand = trim((string)($brandRow['VALUE_ENUM'] ?? $brandRow['VALUE'] ?? ''));
    if ($brand === '') {
        $propsRes = CIBlockElement::GetProperty(IBLOCK_ID, $productId, [], []);
        $allProps = [];
        while ($p = $propsRes->Fetch()) $allProps[] = ['NAME' => $p['NAME'], 'VALUE' => $p['VALUE_ENUM'] ?? $p['VALUE']];
        $brand = resolveBrandFromProperties($allProps);
    }

    if ($article === '' || $brand === '') {
        $stats['skipped_no_article']++;
        continue;
    }

    $data = fetchCrossInfoCached($article, $brand, $db, $helper, $stats);
    $stats['processed']++;

    if ($data) {
        $td = (array)($data['td'] ?? []);

        foreach (extractCriterias($td) as $c) {
            $code = ensureProperty($c['cri_id'], $c['label'], $propCache, $stats);

            $curRes = CIBlockElement::GetProperty(IBLOCK_ID, $productId, [], ['CODE' => $code]);
            $curVal = ($curRow = $curRes->Fetch()) ? trim((string)($curRow['VALUE'] ?? '')) : '';
            if ($curVal !== '') continue; // уже заполнено — не перезаписываем

            CIBlockElement::SetPropertyValuesEx($productId, IBLOCK_ID, [$code => $c['value']]);
            $stats['properties_filled']++;
        }

        $hasImage = !empty($el['DETAIL_PICTURE']) || !empty($el['PREVIEW_PICTURE']);
        if (!$hasImage && !empty($data['img']) && preg_match('~^(/[A-Za-z0-9_\-]+)+\.[A-Za-z0-9]+$~', $data['img'])) {
            $tmpPath = downloadTempImage('https://image.umapi.ru/IMAGE' . $data['img']);
            if ($tmpPath) {
                $fileArrayDetail  = CFile::MakeFileArray($tmpPath);
                $fileArrayPreview = CFile::MakeFileArray($tmpPath);
                if ($fileArrayDetail && $fileArrayPreview) {
                    $element = new CIBlockElement();
                    $ok = $element->Update($productId, [
                        'DETAIL_PICTURE'  => $fileArrayDetail,
                        'PREVIEW_PICTURE' => $fileArrayPreview,
                    ]);
                    if ($ok) {
                        $stats['images_set']++;
                    } else {
                        logger('  ⚠️ Не удалось обновить фото товара ID ' . $productId . ': ' . $element->LAST_ERROR);
                    }
                }
                @unlink($tmpPath);
            }
        }
    }

    if (($i + 1) % 50 === 0) {
        logger("  Прогресс: " . ($i + 1) . "/$total, обработано {$stats['processed']}, "
            . "свойств создано {$stats['properties_created']}, заполнено {$stats['properties_filled']}, "
            . "фото добавлено {$stats['images_set']}, кэш-хитов {$stats['cache_hits']}, "
            . "запросов к UMAPI {$stats['api_calls']}, ошибок {$stats['errors']}");
    }
}

logger("========================================");
logger("ГОТОВО.");
logger("Всего товаров ACTIVE=Y:      $total");
logger("Обработано (в наличии):      {$stats['processed']}");
logger("Пропущено (нет в наличии):   {$stats['skipped_not_in_stock']}");
logger("Пропущено (нет артикула/бренда): {$stats['skipped_no_article']}");
logger("Свойств создано:             {$stats['properties_created']}");
logger("Свойств заполнено:           {$stats['properties_filled']}");
logger("Фото добавлено:              {$stats['images_set']}");
logger("Кэш-хитов:                   {$stats['cache_hits']}");
logger("Запросов к UMAPI:            {$stats['api_calls']}");
logger("Ошибок:                      {$stats['errors']}");
logger("Лог: $logFile");
