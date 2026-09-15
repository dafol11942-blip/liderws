<?php
namespace Lider\Search;

use Lider\Search\Common\MultiCurlExecutor;

/**
 * Карточка товара (фото/наименование/характеристики/OEM/замены) по данным UMAPI
 * getCrossInfo — общая логика для одиночного запроса (local/ajax/umapi_cross_info.php,
 * искомый номер в /search/) и батча (local/ajax/umapi_cross_info_batch.php, аналоги
 * в /search/). Кэш — таблица b_umapi_cross_info, см. её .sql за описанием TTL.
 */
class UmapiCrossInfo
{
    const API_URL = 'https://api.umapi.ru/v2/cross/parts/Analogs/getCrossInfo';
    const API_KEY = '7aa16ec6-c790-45cb-a184-1c11677b78a1';
    const TTL_FOUND_SECONDS = 30 * 86400; // найденные данные почти не меняются
    const TTL_EMPTY_SECONDS = 1 * 86400;  // подтверждённое "нет данных" — не долбить UMAPI на каждый повтор

    // Потолок на кол-во ЖИВЫХ запросов к UMAPI за один вызов fetchMany() (кэш-хиты сюда
    // не попадают, отдаются все). Аналогов на странице /search/ может быть 50-100+ —
    // без потолка холодная загрузка страницы дала бы всплеск в стольких же одновременных
    // соединений к внешнему API, см. историю блокировки IP за перегрузку внешних сервисов
    // (STAGES.md). Непопавшие в потолок пары просто не получают карточку в этот раз —
    // при следующей загрузке (или после того как кэш прогреется другим посетителем/кроном)
    // попробуют снова.
    const MAX_LIVE_REQUESTS_PER_BATCH = 40;

    /**
     * Скачивает картинку с UMAPI один раз и кеширует локально в /upload/umapi_img/<то же
     * относительное имя, что дал UMAPI> — дальше отдаём собственным URL (same-origin).
     * relPath уже провалидирован regex'ом в shapeRaw() (/относительный путь/файл.ext).
     */
    public static function cacheImage(string $remoteUrl, string $relPath): ?string
    {
        $localRel = '/upload/umapi_img' . $relPath;
        $localAbs = $_SERVER['DOCUMENT_ROOT'] . $localRel;

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

    private static function buildApiUrl(string $article, string $brand): string
    {
        return self::API_URL . '?' . http_build_query([
            'article'     => $article,
            'brand'       => $brand,
            'Products'    => 'false',
            'Info'        => 'true',
            'LaCriterias' => 'false',
            'Superseded'  => 'true',
            'PartsList'   => 'false',
            'OEM'         => 'true',
        ]);
    }

    private static function hasUsefulRawData(string $rawJson): bool
    {
        $decoded = json_decode($rawJson, true);
        return is_array($decoded) && (
            !empty($decoded['td']) || !empty($decoded['title']) || !empty($decoded['img'])
        );
    }

    /**
     * Разбирает сырой JSON-ответ UMAPI (как он хранится в RESPONSE_JSON) в форму,
     * готовую для фронтенда. Возвращает null, если полезных данных нет вообще
     * (title/img/характеристики/OEM/замены — всё пусто).
     */
    public static function shapeRaw(string $rawJson): ?array
    {
        if ($rawJson === '') return null;

        $data = json_decode($rawJson, true);
        if (!is_array($data)) return null;

        // td бывает null (нет характеристик/OEM/замен для этой позиции) — это не повод
        // скрывать то, что ЕСТЬ (title/img на верхнем уровне).
        $td = (array)($data['td'] ?? []);

        // Картинку отдаём со своего домена, а не прямой ссылкой на image.umapi.ru — у части
        // посетителей блокировщики рекламы блокируют запросы к незнакомым сторонним доменам
        // с картинками (подтверждено: net::ERR_BLOCKED_BY_CLIENT в DevTools). Путь у UMAPI
        // бывает разного вида ("/4/hash.webp", "/PUBLIC/P2016/AG290.JPEG") — проверяем только
        // на безопасность (относительный путь, без ".." и посторонних символов).
        $img = null;
        if (!empty($data['img']) && preg_match('~^(/[A-Za-z0-9_\-]+)+\.[A-Za-z0-9]+$~', $data['img'])) {
            $img = self::cacheImage('https://image.umapi.ru/IMAGE' . $data['img'], $data['img']);
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
            return null;
        }

        return [
            'success'    => true,
            'title'      => $title,
            'img'        => $img,
            'criterias'  => $criterias,
            'oem'        => $oem,
            'superseded' => ['new' => $supersededNew, 'old' => $supersededOld],
        ];
    }

    /**
     * Отдаёт карточки UMAPI для набора пар артикул+бренд одним проходом: сначала все
     * пары разом сверяются с кэшем (один SELECT), затем для промахов идут ЖИВЫЕ запросы
     * к UMAPI параллельно (см. MAX_LIVE_REQUESTS_PER_BATCH), результат кешируется обратно.
     *
     * @param array $items [['id'=>string, 'article'=>string, 'brand'=>string], ...] —
     *                      id произвольный, задаётся вызывающей стороной, используется
     *                      только чтобы вернуть результат под тем же ключом.
     * @return array [id => ['success'=>true,...] | ['success'=>false]]
     */
    public static function fetchMany(array $items, float $deadlineSeconds = 8.0): array
    {
        $result = [];
        foreach ($items as $item) {
            $id = (string)($item['id'] ?? '');
            if ($id !== '') $result[$id] = ['success' => false];
        }

        // normKey (brandNorm|articleNorm) -> список id'ов, которые на него ссылаются
        // (несколько разных исходных строк бренда/артикула могут нормализоваться в одно).
        $normKeyToIds = [];
        $normKeyToPair = [];
        foreach ($items as $item) {
            $id      = (string)($item['id'] ?? '');
            $article = trim((string)($item['article'] ?? ''));
            $brand   = trim((string)($item['brand'] ?? ''));
            if ($id === '' || $article === '' || $brand === '') continue;

            $articleNorm = BrandNormalizer::normalizeArticle($article);
            $brandNorm   = BrandNormalizer::normalize($brand);
            if ($articleNorm === '' || $brandNorm === '') continue;

            $normKey = $brandNorm . '|' . $articleNorm;
            $normKeyToIds[$normKey][] = $id;
            if (!isset($normKeyToPair[$normKey])) {
                $normKeyToPair[$normKey] = [
                    'article'     => $article,
                    'brand'       => $brand,
                    'articleNorm' => $articleNorm,
                    'brandNorm'   => $brandNorm,
                ];
            }
        }

        if (!$normKeyToPair) return $result;

        $db     = \Bitrix\Main\Application::getConnection();
        $helper = $db->getSqlHelper();

        $rawByNormKey = [];
        $conds = [];
        foreach ($normKeyToPair as $p) {
            $conds[] = "(ARTICLE_NORM = '" . $helper->forSql($p['articleNorm']) . "' AND BRAND_NORM = '" . $helper->forSql($p['brandNorm']) . "')";
        }
        $rows = $db->query('SELECT ARTICLE_NORM, BRAND_NORM, RESPONSE_JSON, FETCHED_AT FROM b_umapi_cross_info WHERE ' . implode(' OR ', $conds));
        while ($row = $rows->fetch()) {
            $normKey = $row['BRAND_NORM'] . '|' . $row['ARTICLE_NORM'];
            $age     = time() - strtotime($row['FETCHED_AT']);
            $isEmpty = ($row['RESPONSE_JSON'] === null || $row['RESPONSE_JSON'] === '');
            $ttl     = $isEmpty ? self::TTL_EMPTY_SECONDS : self::TTL_FOUND_SECONDS;
            if ($age <= $ttl) {
                $rawByNormKey[$normKey] = $isEmpty ? '' : $row['RESPONSE_JSON'];
            }
        }

        $missNormKeys = array_values(array_diff(array_keys($normKeyToPair), array_keys($rawByNormKey)));
        $missNormKeys = array_slice($missNormKeys, 0, self::MAX_LIVE_REQUESTS_PER_BATCH);

        if ($missNormKeys) {
            $requests = [];
            foreach ($missNormKeys as $normKey) {
                $p = $normKeyToPair[$normKey];
                $requests[] = [
                    'url'      => self::buildApiUrl($p['article'], $p['brand']),
                    'headers'  => ['accept: application/json', 'X-App-Key: ' . self::API_KEY],
                    '_timeout' => 6,
                    '_key'     => $normKey,
                ];
            }

            $executor  = new MultiCurlExecutor();
            $responses = $executor->executeAll($requests, $deadlineSeconds);

            foreach ($missNormKeys as $normKey) {
                $resp = $responses[$normKey] ?? null;
                // Транзиентная ошибка (сеть/таймаут/5xx) — в кэш не пишем, просто нет данных в этот раз.
                if (!$resp || $resp['body'] === null) continue;

                $toStore = self::hasUsefulRawData($resp['body']) ? $resp['body'] : '';

                $p      = $normKeyToPair[$normKey];
                $artSql = $helper->forSql($p['articleNorm']);
                $brSql  = $helper->forSql($p['brandNorm']);
                $valSql = $helper->forSql($toStore);
                $db->query(
                    "INSERT INTO b_umapi_cross_info (ARTICLE_NORM, BRAND_NORM, RESPONSE_JSON, FETCHED_AT) VALUES ('{$artSql}', '{$brSql}', '{$valSql}', NOW())
                     ON DUPLICATE KEY UPDATE RESPONSE_JSON = '{$valSql}', FETCHED_AT = NOW()"
                );

                $rawByNormKey[$normKey] = $toStore;
            }
        }

        foreach ($normKeyToIds as $normKey => $ids) {
            $raw    = $rawByNormKey[$normKey] ?? null;
            $shaped = ($raw !== null && $raw !== '') ? self::shapeRaw($raw) : null;
            foreach ($ids as $id) {
                $result[$id] = $shaped ?: ['success' => false];
            }
        }

        return $result;
    }
}
