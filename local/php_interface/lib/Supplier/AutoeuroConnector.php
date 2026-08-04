<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

class AutoeuroConnector implements SupplierInterface
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private ?string $deliveryKey = null;

    public function __construct(array $config = [])
    {
        $this->apiKey     = $config['API_KEY']      ?? '';
        $this->baseUrl    = $config['BASE_URL']     ?? 'https://api.autoeuro.ru/api/v2/json';
        $this->timeout    = $config['TIMEOUT']      ?? 10;
        $this->deliveryKey = $config['DELIVERY_KEY'] ?? null;
    }

    public function getCode(): string       { return 'autoeuro'; }
    public function getName(): string       { return 'Авто-Евро'; }
    public function getWarehousePrefix(): string { return 'ae'; }

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
        $url = $this->baseUrl . '/search_brands?' . http_build_query(['code' => $article]);
        return [
            'url'     => $url,
            'headers' => ['key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data['DATA'])) return $brands;

        foreach ($data['DATA'] as $item) {
            $b  = $item['brand'] ?? '';
            $n  = $item['code']  ?? '';
            $nm = $item['name']  ?? '';
            if (!$b || !$n) continue;

            $key = $b . '|' . $n;
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $b,
                    'article'     => $n,
                    'article_fix' => $n,
                    'description' => $nm,
                ];
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

        $deliveryKey = $this->getDeliveryKey();
        if (!$deliveryKey) return null;

        $body = json_encode([
            'brand'        => $brand,
            'code'         => $article,
            'delivery_key' => $deliveryKey,
            'with_crosses' => ($withCrosses ? 1 : 0),
            'with_offers'  => 1,
        ]);

        return [
            'url'     => $this->baseUrl . '/search_items',
            'headers' => ['key: ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $own = [];    // stock=1 — свой склад
        $other = [];  // stock=0 — партнёр
        // Защита от OOM: огромные cross-ответы
        $len = strlen($responseBody);
        if ($len > 2500000) {
            $this->log("parseSearchResponse: body too large ({$len} bytes), skip");
            return [];
        }

        $data = json_decode($responseBody, true);
        unset($responseBody);

        if (empty($data['DATA']) || !is_array($data['DATA'])) {
            return [];
        }

        $maxItems = 150;
        $n = 0;
        foreach ($data['DATA'] as $item) {
            if (!is_array($item)) continue;
            $r = $this->buildResultItem($item, $brand, $article);
            if (is_array($r->raw) && count($r->raw) > 0) {
                $r->raw = $this->lightRaw($r->raw);
            }
            if ($r->price <= 0 && $r->quantity <= 0) continue;
            if ($r->isSched) continue;

            // Свои: stock=1, чужие: stock=0
            if (!empty($item['stock'])) {
                $own[] = $r;
            } else {
                $other[] = $r;
            }
            $n++;
            if ($n >= $maxItems) break;
        }
        unset($data);

        // Свои — сортировка по срокам+цене, все
        usort($own, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

        // Чужие — сортировка + лимит 10
        usort($other, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });
        $other = array_slice($other, 0, 10);

        return array_merge($own, $other);
    }

    private function lightRaw(array $item): array
    {
        $keep = [
            'delivery_time', 'delivery_time_max', 'order_before',
            'deliveryDateFrom', 'deliveryDateTo', 'deliveryCheckout',
            'warehouse_name', 'offer_key', 'stock', 'return', 'packing', 'unit',
        ];
        $out = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $item)) {
                $out[$k] = $item[$k];
            }
        }
        // стандартные ключи для calcDelivery
        if (!empty($item['delivery_time'])) {
            $out['deliveryDateFrom'] = $item['delivery_time'];
        }
        if (!empty($item['delivery_time_max'])) {
            $out['deliveryDateTo'] = $item['delivery_time_max'];
        }
        if (!empty($item['order_before'])) {
            $out['deliveryCheckout'] = $item['order_before'];
        }
        return $out;
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

        $brands = $this->searchBrands($query);
        $brands = array_slice($brands, 0, 10);

        foreach ($brands as $br) {
            try {
                $items = $this->searchByBrandArticle($br['brand'], $br['article_fix']);
                $results = array_merge($results, array_slice($items, 0, 3));
            } catch (\Throwable $e) {
                $this->log("Brand {$br['brand']} error: " . $e->getMessage());
            }
        }

        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey();
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $item; }
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });

        return array_slice($unique, 0, 30);
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function getDeliveryKey(): ?string
    {
        if ($this->deliveryKey) return $this->deliveryKey;
        if (!$this->isAvailable()) return null;

        $url  = $this->baseUrl . '/get_deliveries';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        $deliveries = $data['DATA'] ?? [];

        if (empty($deliveries)) {
            $this->log('No deliveries found');
            $this->deliveryKey = '';
            return null;
        }

        $this->deliveryKey = $deliveries[0]['delivery_key'] ?? '';
        return $this->deliveryKey ?: null;
    }

    private function buildResultItem(array $item, string $defaultBrand, string $defaultArticle): SearchResultItem
    {
        $stock   = !empty($item['stock']);
        $amount  = (int)($item['amount'] ?? 0);
        $isSched = ($amount <= 0);

        // Срок доставки: delivery_time = точное время прибытия
        // Доставка всегда минимум завтра (день в день не возят)
        $deliveryDays = null;
        $deliveryPeriod = null;
        $now = time();

        if (!empty($item['delivery_time'])) {
            $delTs = strtotime($item['delivery_time']);
            if ($delTs > $now) {
                $deliveryPeriod = max(0, (int)(($delTs - $now) / 3600));
                // Календарные дни, минимум 1
                if (date('Y-m-d', $delTs) === date('Y-m-d', $now)) {
                    $deliveryDays = 1;
                } else {
                    $deliveryDays = max(1, (int)ceil(($delTs - strtotime('today', $now)) / 86400));
                }
            }
        }

        $r = new SearchResultItem();
        $r->source         = $this->getCode();
        $r->article        = (string)($item['code'] ?? $defaultArticle);
        $r->brand          = (string)($item['brand'] ?? $defaultBrand);
        $r->name           = (string)($item['name'] ?? '');
        $r->price          = (float)($item['price'] ?? 0);
        $r->quantity       = $amount;
        $r->deliveryDays   = $deliveryDays;
        $r->deliveryPeriod = $deliveryPeriod;
        $r->warehouse      = $stock ? ((string)($item['warehouse_name'] ?? 'Склад')) : 'Под заказ';
        $r->stockId        = (string)($item['offer_key'] ?? '');
        $r->supplierName   = $this->getName();
        $r->isSched        = $isSched;
        $r->multiplicity   = max(1, (int)($item['packing'] ?? 1));
        $r->unit           = !empty($item['unit']) ? (string)$item['unit'] : 'шт.';
        $r->returnable     = !empty($item['return']);
        $r->raw            = $this->lightRaw($item);

        // Стандартные ключи для calcDelivery
        if (!empty($item['delivery_time']))     $r->raw['deliveryDateFrom'] = $item['delivery_time'];
        if (!empty($item['delivery_time_max'])) $r->raw['deliveryDateTo']   = $item['delivery_time_max'];
        if (!empty($item['order_before']))      $r->raw['deliveryCheckout'] = $item['order_before'];

        return $r;
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($req['method'] === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($req['body']) $opts[CURLOPT_POSTFIELDS] = $req['body'];
        }
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            $this->log("HTTP {$httpCode} err={$err}");
            return null;
        }
        return $resp;
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
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/autoeuro_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    public function supportsCrossSearch(): bool
    {
        return true;
    }

    public function getSearchTimeout(): int
    {
        return 10;
    }
}
