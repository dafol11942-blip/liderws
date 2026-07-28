<?php
namespace Lider\Search;

class UmapiClient
{
    private const BASE_URL = 'https://api.umapi.ru/v2/cross/parts/';
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Уточнение бренда по артикулу. Всегда из API (лёгкий запрос, ~22 мс).
     * @return array[] [article, brand, title, img, brands[]]
     */
    public function refineBrandData(string $article): array
    {
        $url  = self::BASE_URL . 'refineBrandDataByArticle/' . urlencode($article);
        $data = $this->request($url);
        return is_array($data) ? $data : [];
    }

    /**
     * Все кросс-номера (аналоги). Сначала БД, потом API + сохраняем.
     * @return array[] [article, brand, title, img, brandSearchRoot, brandRoot]
     */
    public function getAnalogs(string $article, string $brand): array
    {
        $normArticle = BrandNormalizer::normalizeArticle($article);

        // 1. Пробуем локальную БД
        $fromDb = $this->getFromDb($normArticle);
        if (!empty($fromDb)) {
            return $fromDb;
        }

        // 2. Запрашиваем UMAPI
        $url = self::BASE_URL . 'Analogs/pro/'
            . urlencode($article) . '/'
            . urlencode($brand) . '/false';

        $data = $this->request($url);
        if (!is_array($data) || empty($data)) {
            return [];
        }

        // 3. Сохраняем в БД
        $this->saveToDb($article, $brand, $data);

        return $data;
    }

    /**
     * Информация об одном артикуле.
     */
    public function getCrossInfo(string $article, string $brand): ?array
    {
        $url = self::BASE_URL . 'Analogs/getCrossInfo'
            . '?article=' . urlencode($article)
            . '&brand=' . urlencode($brand)
            . '&Products=false&Info=true&LaCriterias=false&Superseded=false&PartsList=false&OEM=false';

        return $this->request($url);
    }

    // ----------------------------------------------------------------
    // Private
    // ----------------------------------------------------------------

    private function getFromDb(string $normArticle): array
    {
        $db = $this->db();
        $esc = $db->real_escape_string($normArticle);
        $res = $db->query(
            "SELECT cross_article, cross_brand, title, img 
             FROM b_umapi_crosses 
             WHERE article_normalized = '{$esc}' 
             ORDER BY id ASC"
        );

        $result = [];
        while ($row = $res->fetch_assoc()) {
            $result[] = [
                'article'         => $row['cross_article'],
                'brand'           => $row['cross_brand'],
                'brandSearchRoot' => BrandNormalizer::normalize($row['cross_brand']),
                'brandRoot'       => $row['cross_brand'],
                'title'           => $row['title'],
                'img'             => $row['img'],
                'isSearch'        => false,
            ];
        }
        $db->close();
        return $result;
    }

    private function saveToDb(string $article, string $brand, array $analogs): void
    {
        if (empty($analogs)) return;

        $normArticle = BrandNormalizer::normalizeArticle($article);
        $normBrand   = BrandNormalizer::normalize($brand);

        $db = $this->db();
        $db->query("START TRANSACTION");

        $stmt = $db->prepare(
            "INSERT IGNORE INTO b_umapi_crosses 
             (article, article_normalized, brand, brand_normalized, 
              cross_article, cross_article_normalized, cross_brand, cross_brand_normalized, title, img)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($analogs as $a) {
            $crossArticle = $a['article'] ?? '';
            $crossBrand   = $a['brand'] ?? '';
            $title        = $a['title'] ?? '';
            $img          = $a['img'] ?? '';
            $normCrossArt = BrandNormalizer::normalizeArticle($crossArticle);
            $normCrossBrd = BrandNormalizer::normalize($crossBrand);

            $stmt->bind_param(
                'ssssssssss',
                $article, $normArticle, $brand, $normBrand,
                $crossArticle, $normCrossArt, $crossBrand, $normCrossBrd,
                $title, $img
            );
            $stmt->execute();
        }

        $db->query("COMMIT");
        $stmt->close();
        $db->close();
    }

    private function request(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'X-App-Key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $error || empty($body)) {
            @file_put_contents(
                $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/umapi_' . date('Y-m-d') . '.log',
                '[' . date('H:i:s') . '] FAIL ' . $httpCode . ' ' . $error . ' ' . $url . "\n",
                FILE_APPEND
            );
            return null;
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private function db(): \mysqli
    {
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $db->set_charset('utf8mb4');
        return $db;
    }
}