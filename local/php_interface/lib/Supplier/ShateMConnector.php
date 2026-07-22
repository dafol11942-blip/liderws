<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class ShateMConnector implements SupplierInterface
{
    private string $apiUrl;
    private string $apiKey;
    private int    $timeout;
    private ?string $token = null;
    private ?int   $tokenExpires = null;
    private array  $locationNames = [];

    public function __construct(array $config = [])
    {
        $this->apiUrl  = $config['API_URL']  ?? 'https://api.shate-m.by/api/v1/';
        $this->apiKey  = $config['API_KEY']  ?? '';
        $this->timeout = $config['TIMEOUT']  ?? 12;
    }

    public function getCode(): string       { return 'shatem'; }
    public function getName(): string       { return 'ШАТЕ-М'; }
    public function getWarehousePrefix(): string { return 'shtm'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    // ==================== АВТОРИЗАЦИЯ ====================

    private function ensureToken(): string
    {
        if ($this->token && $this->tokenExpires && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl . 'auth/loginByapiKey',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['apiKey' => $this->apiKey]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $this->log('Auth failed: HTTP ' . $httpCode);
            $this->token = '';
            return '';
        }
        $data = json_decode($resp, true);
        $this->token = $data['access_token'] ?? '';
        $this->tokenExpires = time() + (int)($data['expires_in'] ?? 3600);
        return $this->token;
    }

    // ==================== ЭТАП 1: БРЕНДЫ ====================

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        $token = $this->ensureToken();
        if (!$token) return null;
        return [
            'url'     => $this->apiUrl . 'articles/search?' . http_build_query(['searchString' => $article]),
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data)) return $brands;
        if (isset($data['id'])) $data = [$data];
        foreach ($data as $art) {
            $b  = $art['tradeMarkName'] ?? '';
            $n  = $art['code'] ?? '';
            $nm = $art['name'] ?? '';
            if (!$b || !$n) continue;
            $key = mb_strtolower($b) . '|' . mb_strtolower($n);
            if (!isset($brands[$key])) {
                $brands[$key] = ['brand' => $b, 'article' => $n, 'article_nr' => $n, 'description' => $nm];
            }
        }
        return array_values($brands);
    }

    // ==================== ЭТАП 2: ПРЕДЛОЖЕНИЯ ====================

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        $token = $this->ensureToken();
        if (!$token) return null;
        $params = ['searchString' => $article];
        if ($brand !== '') {
            $params['tradeMarkNames'] = $brand;
        }
        return [
            'url'     => $this->apiUrl . 'articles/search?' . http_build_query($params),
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $token = $this->ensureToken();
        if (!$token) return $results;

        $data = json_decode($responseBody, true);
        if (empty($data)) return $results;
        if (isset($data['id'])) $data = [$data];

        $normBrand = BrandNormalizer::normalize($brand);
        $articleIds = [];
        $articleInfo = [];
        foreach ($data as $art) {
            $artBrand = $art['tradeMarkName'] ?? '';
            if ($artBrand === '') continue;
            if ($normBrand !== '' && BrandNormalizer::normalize($artBrand) !== $normBrand) continue;
            $id = $art['id'] ?? 0;
            if ($id > 0) {
                $articleIds[] = $id;
                $articleInfo[$id] = $art;
            }
        }
        if (empty($articleIds)) return $results;

        $pricesData = $this->getPrices($articleIds, $token);
        foreach ($pricesData as $pe) {
            $art         = $pe['article'] ?? [];
            $prices      = $pe['prices'] ?? [];
            $artId       = $art['id'] ?? 0;
            $info        = $articleInfo[$artId] ?? $art;
            $unitMeasure = $info['unitOfMeasure'] ?? $art['unitOfMeasure'] ?? 'шт.';

            foreach ($prices as $p) {
                $result = $this->buildSearchResultItem($p, $info, $art, $brand, $article, $token, $unitMeasure);
                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        return $this->deduplicateAndSort($results);
    }

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched && $item->price > 0) return $item;
        }
        return $items[0] ?? null;
    }

    // ==================== ПОЛНЫЙ ПОИСК ====================

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) return $results;

        $token = $this->ensureToken();
        if (!$token) return $results;

        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $req = $this->buildBrandsRequest($query);
        if (!$req) return $results;
        $resp = $this->execCurl($req);
        if ($resp === null) return $results;

        $articlesData = json_decode($resp, true);
        if (empty($articlesData)) return $results;
        if (isset($articlesData['id'])) $articlesData = [$articlesData];
        $articlesData = array_slice($articlesData, 0, 15);

        $articleIds = [];
        $articleInfo = [];
        foreach ($articlesData as $art) {
            $id = $art['id'] ?? 0;
            if ($id > 0) {
                $articleIds[] = $id;
                $articleInfo[$id] = $art;
            }
        }
        if (empty($articleIds)) return $results;

        foreach (array_chunk($articleIds, 10) as $batch) {
            foreach ($this->getPrices($batch, $token) as $pe) {
                $art         = $pe['article'] ?? [];
                $prices      = $pe['prices'] ?? [];
                $artId       = $art['id'] ?? 0;
                $info        = $articleInfo[$artId] ?? $art;
                $unitMeasure = $info['unitOfMeasure'] ?? $art['unitOfMeasure'] ?? 'шт.';

                foreach ($prices as $price) {
                    $result = $this->buildSearchResultItem($price, $info, $art, '', '', $token, $unitMeasure);
                    if ($result !== null) {
                        $results[] = $result;
                    }
                }
            }
        }

        return array_slice($this->deduplicateAndSort($results), 0, 30);
    }

    // ==================== ПОСТРОЕНИЕ SearchResultItem ====================

    private function buildSearchResultItem(
        array $priceData,
        array $articleInfo,
        array $articleData,
        string $brand,
        string $article,
        string $token,
        string $unitMeasure
    ): ?SearchResultItem {
        $locCode = $priceData['locationCode'] ?? '';
        $locName = $this->getLocationName($locCode, $token);

        // --- ЦЕНА ---
        $priceValue = (float)($priceData['price']['value'] ?? 0);
        $currency   = (string)($priceData['price']['currencyCode'] ?? 'BYN');

        // --- КОЛИЧЕСТВО + КРАТНОСТЬ ---
        $qtyAvailable = (int)($priceData['quantity']['available'] ?? 0);
        $multiplicity = max(1, (int)($priceData['quantity']['multiplicity'] ?? 1));
        $minQty       = max(1, (int)($priceData['quantity']['minimum'] ?? 1));
        $maxQty       = isset($priceData['quantity']['maximum']) && $priceData['quantity']['maximum'] !== null
            ? (int)$priceData['quantity']['maximum'] : null;

        // --- ВОЗВРАТ ---
        $addInfo      = $priceData['addInfo'] ?? [];
        $isReturnable = (bool)($addInfo['isReturnAllowed'] ?? true);
        $warningText  = (string)($addInfo['warningText'] ?? '');
        $isSale       = (bool)($addInfo['isSale'] ?? false);

        // --- СРОК ДОСТАВКИ (только deliveryDateTime, без самовывоза) ---
        $deliveryDT   = $priceData['deliveryDateTimes'][0]['deliveryDateTime'] ?? null;

        // --- СТАТИСТИКА ---
        $supplyRating = (int)($priceData['supplyProbability']['rating'] ?? 0);

        // --- ИДЕНТИФИКАТОРЫ ДЛЯ КОРЗИНЫ/ЗАКАЗА ---
        $priceId = (string)($priceData['id'] ?? '');
        $hash    = (int)($priceData['hash'] ?? 0);

        $r = new SearchResultItem();
        $r->source       = $this->getCode();
        $r->article      = (string)($articleInfo['code'] ?? $articleData['code'] ?? $article);
        $r->brand        = (string)($articleInfo['tradeMarkName'] ?? $articleData['tradeMarkName'] ?? $brand);
        $r->name         = (string)($articleInfo['name'] ?? $articleData['name'] ?? '');
        $r->price        = $priceValue;
        $r->currency     = $currency;
        $r->quantity     = $qtyAvailable;
        $r->multiplicity = $multiplicity;
        $r->unit         = $unitMeasure;
        $r->warehouse    = $locName;
        $r->stockId      = $locCode;
        $r->supplierName = $this->getName();
        $r->isSched      = ($qtyAvailable <= 0);
        $r->returnable   = $isReturnable;

        // --- СРОКИ ---
        if ($deliveryDT) {
            $now   = time();
            $delTs = strtotime($deliveryDT);
            $r->deliveryDays   = max(0, (int)ceil(($delTs - $now) / 86400));
            $r->deliveryPeriod = max(0, (int)ceil(($delTs - $now) / 3600));
        }

        // --- raw (для будущей корзины/заказа) ---
        $r->raw = [
            'priceId'           => $priceId,
            'hash'              => $hash,
            'locationCode'      => $locCode,
            'locationCodeReal'  => $priceData['locationCodeReal'] ?? '',
            'agreementCode'     => $priceData['agreementCode'] ?? '',
            'type'              => $priceData['type'] ?? '',
            'isRepaired'        => (bool)($priceData['isRepaired'] ?? false),
            'currencyCode'      => $currency,
            'importAllowance'   => (int)($priceData['price']['importAllowance'] ?? 0),
            'priceMax'          => (float)($priceData['price']['priceMax'] ?? 0),
            'valueForCart'      => (float)($priceData['price']['valueForCart'] ?? 0),
            'valueWithMarginForCart' => (float)($priceData['price']['valueWithMarginForCart'] ?? 0),
            'availableType'     => $priceData['quantity']['availableType'] ?? 'Equal',
            'minQty'            => $minQty,
            'maxQty'            => $maxQty,
            'supplyRating'      => $supplyRating,
            'isReturnAllowed'   => $isReturnable,
            'warningText'       => $warningText,
            'isSale'            => $isSale,
            'deliveryDateTime'  => $deliveryDT,
            'isImport'          => (bool)($priceData['isImport'] ?? false),
            'isFree'            => (bool)($priceData['isFree'] ?? false),
            'priority'          => (int)($priceData['priority'] ?? 0),
        ];

        if ($r->price <= 0 && $r->quantity <= 0) {
            return null;
        }

        return $r;
    }

    private function deduplicateAndSort(array $results): array
    {
        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey() . '|' . $item->warehouse;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return $unique;
    }

    // ==================== HTTP ====================

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if (($req['method'] ?? 'GET') === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($httpCode !== 200 || $resp === false) {
            $this->log("HTTP {$httpCode} err={$err}");
            return null;
        }
        return $resp;
    }

    private function getPrices(array $articleIds, string $token): array
    {
        if (empty($articleIds)) return [];
        $body = json_encode(array_map(fn($id) => ['articleId' => $id], $articleIds));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl . 'prices/search/with_article_info',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $this->log("getPrices HTTP {$httpCode}");
            return [];
        }
        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }

    private function getLocationName(string $code, string $token): string
    {
        if ($code === '') return '—';
        if (isset($this->locationNames[$code])) return $this->locationNames[$code];

        if (empty($this->locationNames)) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $this->apiUrl . 'locations',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $locs = json_decode($resp, true);
            if (is_array($locs)) {
                foreach ($locs as $loc) {
                    $this->locationNames[$loc['code']] = $loc['city'] ?? $loc['name'] ?? $loc['code'];
                }
            }
        }
        return $this->locationNames[$code] ?? $code;
    }

    // ==================== УТИЛИТЫ ====================

    private function generateWarehouseCode(string $name): string
    {
        static $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu',
            'я'=>'ya',' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>'',
        ];
        $lower = mb_strtolower(trim($name));
        $translit = '';
        foreach (mb_str_split($lower) as $char) {
            $translit .= $map[$char] ?? $char;
        }
        $clean = preg_replace('/[^a-z0-9]/', '', $translit);
        $abbr = substr($clean, 0, 3);
        while (strlen($abbr) < 3) $abbr .= 'x';
        return $this->getWarehousePrefix() . '_' . $abbr;
    }

    private function log(string $message): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $dir = $root . '/upload/logs';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents(
            $dir . '/shatem_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    public function supportsCrossSearch(): bool { return false; }
    public function getSearchTimeout(): int { return 8; }
}
