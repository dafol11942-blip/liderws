<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class BergConnector implements SupplierInterface
{
    private string $apiKey;
    private int $timeout;
    private string $baseUrl;
    private ?int $addressId = null;

    public function __construct(array $config = [])
    {
        $this->apiKey  = $config['API_KEY'] ?? '';
        $this->timeout = $config['TIMEOUT'] ?? 7;
        $this->baseUrl   = $config['BASE_URL']   ?? 'https://api.berg.ru/v1.0';
        $this->addressId = (int)($config['ADDRESS_ID'] ?? 0) ?: null;
    }

    public function getCode(): string       { return 'berg'; }
    public function getName(): string       { return 'BERG'; }
    public function getWarehousePrefix(): string { return 'brg'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
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
        if (!$this->isAvailable()) return null;
        $url = rtrim($this->baseUrl, '/') . '/ordering/get_stock.json';
        $body = ['items' => [['resource_article' => $article]]];
        if ($this->addressId) $body['address_id'] = $this->addressId;
        $json = json_encode($body);
        return [
            'url'     => $url,
            'headers' => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $json,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data['resources'])) return $brands;

        foreach ($data['resources'] as $res) {
            $b  = $res['brand']['name'] ?? '';
            $n  = $res['article'] ?? '';
            $nm = $res['name'] ?? '';
            if (!$b || !$n) continue;
            $key = $b . '|' . $n;
            if (!isset($brands[$key])) {
                $brands[$key] = ['brand' => $b, 'article' => $n, 'article_fix' => $n, 'description' => $nm];
            }
        }
        return array_values($brands);
    }

    // ==================== ЭТАП 2: ПРЕДЛОЖЕНИЯ ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;
        $url = rtrim($this->baseUrl, '/') . '/ordering/get_stock.json';
        $body = [
            'items' => [[
                'resource_article' => $article,
                'brand_name'       => $brand,
            ]],
            'warehouse_types' => [1, 2],  // только свои склады БЕРГ (филиал + ЦС)
        ];
        if ($this->addressId) {
            $body['address_id'] = $this->addressId;
        }
        // Включаем аналоги, если запрошено
        if ($withCrosses) {
            $body['analogs'] = 1;
        }
        $json = json_encode($body);
        return [
            'url'     => $url,
            'headers' => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $json,
        ];
    }

    /**
     * Парсит ответ get_stock.
     * НЕ фильтрует по brand — при withCrosses (analogs=1) Berg вернёт разные бренды.
     * Разделение exact/analog делает Stage2 по groupKey.
     */
    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (empty($data['resources'])) return $results;

        foreach ($data['resources'] as $res) {
            foreach ($res['offers'] ?? [] as $offer) {
                $r = $this->buildResultItem($res, $offer);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }

        // Сортировка: сначала по срокам, потом по цене (все склады свои)
        usort($results, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });
        return $results;
    }

    // ==================== ДЕТАЛЬНАЯ ИНФОРМАЦИЯ ====================

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
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $data = $this->apiPost('ordering/get_stock', [['resource_article' => $query]]);
        if (empty($data['resources'])) return $results;

        foreach ($data['resources'] as $res) {
            foreach ($res['offers'] ?? [] as $offer) {
                $r = $this->buildResultItem($res, $offer);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }

        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->stockId ?: $item->getDedupeKey();
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $item; }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return array_slice($unique, 0, 30);
    }

    // ==================== НОВЫЕ МЕТОДЫ ====================

    public function supportsCrossSearch(): bool
    {
        // Berg API поддерживает "analogs":1
        return true;
    }

    public function getSearchTimeout(): int
    {
        return 7;
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        if ($req['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($req['body']) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err || $httpCode !== 200) return null;
        return $resp;
    }

    private function buildResultItem(array $resource, array $offer): SearchResultItem
    {
        $wh    = $offer['warehouse'] ?? [];
        $qty   = (int)($offer['quantity'] ?? 0);
        $transit = !empty($offer['is_transit']);

        $now = time();
        $deliveryDays = null;
        $deliveryPeriod = null;

        $ttAll = $offer['address_timetable'] ?? [];
        $tt = !empty($ttAll) ? $ttAll[0] : [];
        $dateFrom = $tt['delivery_from'] ?? $tt['pickup_from'] ?? null;
        $dateTo   = $tt['delivery_to']   ?? $tt['pickup_to']   ?? null;
        $buyUntil = $tt['buy_until'] ?? null;

        if (!empty($dateFrom)) {
            $delTs = strtotime($dateFrom);
            if ($delTs > $now) {
                $deliveryPeriod = max(0, (int)(($delTs - $now) / 3600));
                if (date('Y-m-d', $delTs) === date('Y-m-d', $now)) {
                    $deliveryDays = 0;
                } else {
                    $deliveryDays = max(1, (int)ceil(($delTs - strtotime('today', $now)) / 86400));
                }
            }
        } elseif (isset($offer['average_period'])) {
            $deliveryDays = (int)$offer['average_period'];
            $deliveryPeriod = $deliveryDays * 24;
        }

        $r = new SearchResultItem();
        $r->source    = $this->getCode();
        $r->article   = (string)($resource['article'] ?? '');
        $r->brand     = (string)($resource['brand']['name'] ?? '');
        $r->name      = (string)($resource['name'] ?? '');
        $r->price     = (float)($offer['price'] ?? 0);
        $r->quantity  = $qty;
        $r->deliveryDays   = $deliveryDays;
        $r->deliveryPeriod = $deliveryPeriod;
        $r->warehouse = (string)($wh['name'] ?? '');
        $r->stockId   = (string)($wh['id'] ?? '');
        $r->supplierName = $this->getName();
        $r->isSched   = ($qty <= 0) || $transit;
        $r->multiplicity   = max(1, (int)($offer['multiplication_factor'] ?? 1));
        $r->unit           = 'шт.';
        $r->returnable = true;
        $r->raw       = $offer;

        if (!empty($dateFrom)) $r->raw['deliveryDateFrom'] = $dateFrom;
        if (!empty($dateTo))   $r->raw['deliveryDateTo']   = $dateTo;
        if (!empty($buyUntil)) $r->raw['deliveryCheckout'] = $buyUntil;

        return $r;
    }

    private function apiPost(string $endpoint, array $items): array
    {
        $url  = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/') . '.json';
        $json = json_encode(['items' => $items]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resp, true);
        return is_array($result) ? $result : [];
    }

    private function generateWarehouseCode(string $name): string
    {
        static $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            ' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>'',
        ];
        $lower = mb_strtolower(trim($name));
        $translit = '';
        foreach (mb_str_split($lower) as $char) { $translit .= $map[$char] ?? $char; }
        $clean = preg_replace('/[^a-z0-9]/', '', $translit);
        $abbr = substr($clean, 0, 3);
        while (strlen($abbr) < 3) $abbr .= 'x';
        return $this->getWarehousePrefix() . '_' . $abbr;
    }
}
