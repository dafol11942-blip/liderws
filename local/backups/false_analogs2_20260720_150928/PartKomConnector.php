<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

function _parseQty($val): int
{
    $val = trim((string)$val);
    if ($val === '' || $val === '0') return 0;
    if ($val[0] === '>') return max(1, (int)substr($val, 1));
    return max(0, (int)$val);
}

class PartKomConnector implements SupplierInterface
{
    private string $login;
    private string $password;
    private string $baseUrl;
    private int $timeout;
    private ?array $brandsCache = null;

    public function __construct(array $config = [])
    {
        $this->login    = $config['LOGIN']    ?? 'lider16';
        $this->password = $config['PASSWORD'] ?? 'LidGates16';
        $this->baseUrl  = $config['BASE_URL'] ?? 'https://ws.part-kom.ru/v4';
        $this->timeout  = $config['TIMEOUT']  ?? 8;
    }

    public function getCode(): string { return 'partkom'; }
    public function getName(): string { return 'ПартКом'; }
    public function getWarehousePrefix(): string { return 'pk'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->login) && !empty($this->password);
    }

    private function getAuthHeader(): string
    {
        return 'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password);
    }

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) return null;
        $url = $this->baseUrl . '/search/articule-brands?' . http_build_query(['number' => $article]);
        return [
            'url'     => $url,
            'headers' => [$this->getAuthHeader(), 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $article = trim($requestArticle);
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $brands;

        foreach ($data as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') continue;
            $key = mb_strtolower($name) . '|' . mb_strtolower($article);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $name,
                    'article'     => $article,
                    'article_nr'  => $article,
                    'description' => '',
                ];
            }
        }
        return array_values($brands);
    }

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

        $params = ['number' => $article, 'find_substitutes' => ($withCrosses ? 1 : 0)];
        if ($brand !== '') {
            $makerId = $this->resolveMakerId($brand);
            if ($makerId) {
                $params['maker_id'] = $makerId;
            }
        }

        $url = $this->baseUrl . '/search/offers?' . http_build_query($params);
        return [
            'url'     => $url,
            'headers' => [$this->getAuthHeader(), 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $results;

        // НЕ фильтруем по brand/article здесь:
        // при find_substitutes=1 API отдаёт аналоги (SAKURA, AMD, ...).
        // Разделение exact/analog делает stage2 по groupKey.
        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);

        foreach ($data as $item) {
            $itemBrand  = trim((string)($item['maker'] ?? ''));
            $itemNumber = (string)($item['number'] ?? '');

            $qty     = _parseQty($item['quantity'] ?? 0);
            $isStock = !empty($item['isStock']);
            $isSched = !$isStock || $qty <= 0;

            $r = new SearchResultItem();
            $r->source       = $this->getCode();
            $r->article      = $itemNumber !== '' ? $itemNumber : $article;
            $r->brand        = $itemBrand !== '' ? $itemBrand : $brand;
            $r->name         = (string)($item['description'] ?? '');
            $r->price        = (float)($item['price'] ?? 0);
            $r->quantity     = $qty;
            $isStorehouse    = !empty($item['storehouse']);
            if ($isStorehouse) {
                $r->warehouse = 'ПартКом: ' . ($item['placement'] ?? 'Склад');
            } else {
                $r->warehouse = ($item['providerDescription'] ?? '—') . ': ' . ($item['placement'] ?? '');
            }
            $r->stockId      = (string)($item['placementId'] ?? $item['providerId'] ?? '');
            $r->supplierName = $this->getName();
            $r->isSched      = $isSched;
            $r->multiplicity = max(1, (int)($item['minQuantity'] ?? 1));
            $r->unit         = 'шт.';
            $r->returnable   = empty($item['flagReturnImpossible']);

            $now = time();
            $deliveryTs = null;
            if (!empty($item['deliveryDateFrom'])) {
                $deliveryTs = strtotime($item['deliveryDateFrom']);
            } elseif (!empty($item['expectedDate'])) {
                $deliveryTs = strtotime($item['expectedDate']);
            }

            if ($deliveryTs && $deliveryTs > $now) {
                $diffSeconds = $deliveryTs - $now;
                $r->deliveryPeriod = max(0, (int)($diffSeconds / 3600));
                if (date('Y-m-d', $deliveryTs) === date('Y-m-d', $now)) {
                    $r->deliveryDays = 0;
                } else {
                    $r->deliveryDays = max(1, (int)ceil($diffSeconds / 86400));
                }
            } elseif (!empty($item['expectedHours'])) {
                $r->deliveryPeriod = (int)$item['expectedHours'];
                $r->deliveryDays   = (int)ceil($item['expectedHours'] / 24);
            }

            $r->raw = [
                'deliveryDateFrom' => $item['deliveryDateFrom'] ?? null,
                'deliveryDateTo'   => $item['expectedDate'] ?? ($item['deliveryDateTo'] ?? null),
                'expectedHours'    => $item['expectedHours'] ?? null,
                'isStock'          => $item['isStock'] ?? null,
                'storehouse'       => $item['storehouse'] ?? null,
                'flagReturnImpossible' => $item['flagReturnImpossible'] ?? null,
            ];
            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
            if (count($results) >= 160) { break; }
        }

        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $dk = ($item->stockId ?: '') . '|' . $item->price;
            if (!isset($seen[$dk])) {
                $seen[$dk] = true;
                $unique[] = $item;
            }
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });

        return array_slice($unique, 0, 120);
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched && $item->price > 0) return $item;
        }
        return $items[0] ?? null;
    }

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) return $results;
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $url = $this->baseUrl . '/search/offers?' . http_build_query(['number' => $query, 'find_substitutes' => 0]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [$this->getAuthHeader(), 'Accept: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || empty($resp)) return $results;

        return $this->parseSearchResponse($resp, '', $query);
    }

    private function resolveMakerId(string $brand): ?int
    {
        if ($brand === '') return null;
        $this->loadBrands();
        if (!$this->brandsCache) return null;

        $norm = BrandNormalizer::normalize($brand);
        // exact normalize match
        foreach ($this->brandsCache as $id => $name) {
            if (BrandNormalizer::normalize((string)$name) === $norm) {
                return (int)$id;
            }
        }
        // substring fallback
        $raw = mb_strtolower(trim($brand));
        foreach ($this->brandsCache as $id => $name) {
            $n = mb_strtolower(trim((string)$name));
            if ($n === $raw || mb_stripos($n, $raw) !== false || mb_stripos($raw, $n) !== false) {
                return (int)$id;
            }
        }
        return null;
    }

    private function loadBrands(): void
    {
        if ($this->brandsCache !== null) return;

        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/partkom_brands.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $this->brandsCache = json_decode((string)file_get_contents($cacheFile), true) ?: [];
            if (!empty($this->brandsCache)) return;
        }

        $url = $this->baseUrl . '/search/brands';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [$this->getAuthHeader(), 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $this->brandsCache = [];
        $data = json_decode((string)$resp, true);
        if (is_array($data)) {
            foreach ($data as $item) {
                $this->brandsCache[(int)$item['id']] = $item['name'];
            }
        }
        @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, json_encode($this->brandsCache, JSON_UNESCAPED_UNICODE));
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
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
        if ($err || $httpCode !== 200) {
            $this->log("HTTP {$httpCode} err={$err} url={$req['url']}");
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
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/partkom_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}
