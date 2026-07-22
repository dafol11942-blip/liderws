<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

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
        $this->timeout = $config['TIMEOUT']  ?? 15;
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

    private function ensureToken(): string
    {
        if ($this->token && $this->tokenExpires && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl . 'auth/loginByApiKey',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['apiKey' => $this->apiKey]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) { $this->token = ''; return ''; }
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
        $url = $this->apiUrl . 'articles/search?' . http_build_query(['searchString' => $article]);
        return [
            'url'     => $url,
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
        // ШАТЕ-М требует два запроса: поиск артикула → цены. Это остаётся последовательным.
        $token = $this->ensureToken();
        if (!$token) return [];

        $results = [];
        $data = $this->apiGet('articles/search', ['searchString' => $article, 'tradeMarkNames' => $brand], $token);
        if (empty($data)) return $results;
        if (isset($data['id'])) $data = [$data];

        $articleIds = [];
        $articleInfo = [];
        foreach ($data as $art) {
            $artBrand = $art['tradeMarkName'] ?? '';
            // Мягкое сравнение брендов
            $mapBrand = function($s) {
                $map = ['hi-q'=>'SANGSIN','hi q'=>'SANGSIN','hiq'=>'SANGSIN','sangsin'=>'SANGSIN','sang sin'=>'SANGSIN','mann'=>'MANN-FILTER','mann-filter'=>'MANN-FILTER','lynx'=>'LYNX','lynxauto'=>'LYNX','japanparts'=>'JAPANPARTS','japan parts'=>'JAPANPARTS','nipparts'=>'NIPPARTS','nip parts'=>'NIPPARTS','blue print'=>'BLUE PRINT','blueprint'=>'BLUE PRINT','febi'=>'FEBI','febi bilstein'=>'FEBI','magneti marelli'=>'MAGNETI MARELLI','magneti'=>'MAGNETI MARELLI','victor reinz'=>'VICTOR REINZ','victor'=>'VICTOR REINZ','jp group'=>'JP GROUP','borg & beck'=>'BORG & BECK','herth+buss'=>'HERTH+BUSS','quinton hazell'=>'QH','phc vale'=>'PHC VALE','hamburg technic'=>'HAMBURG TECHNIC','hans pries'=>'HANS PRIES','first line'=>'FIRST LINE','van wezel'=>'VAN WEZEL','ruhr'=>'RUHR AUTO','triple q'=>'TRIPLE Q'];
                $l = mb_strtolower(trim($s));
                if (isset($map[$l])) return $map[$l];
                foreach (['lynxauto','lynx'] as $v) { if (mb_stripos($s, $v) !== false) return 'LYNX'; }
                foreach (['mann-filter','mann'] as $v) { if (mb_stripos($s, $v) !== false) return 'MANN-FILTER'; }
                return $s;
            };
            $normBfn = function($s) use ($mapBrand) {
                $m = $mapBrand($s);
                return mb_strtolower(preg_replace('/^([^\s\-\._\/]+).*$/u', '$1', $m));
            };
            if ($normBfn($artBrand) !== $normBfn($brand)) continue;
            $id = $art['id'] ?? 0;
            if ($id > 0) { $articleIds[] = $id; $articleInfo[$id] = $art; }
        }
        if (empty($articleIds)) return $results;

        $pricesData = $this->getPrices($articleIds, $token);
        foreach ($pricesData as $pe) {
            $art = $pe['article'] ?? [];
            $prices = $pe['prices'] ?? [];
            $artId = $art['id'] ?? 0;
            $info = $articleInfo[$artId] ?? $art;

            foreach ($prices as $p) {
                $locCode = $p['locationCode'] ?? '';
                $locName = $this->getLocationName($locCode, $token);

                $r = new SearchResultItem();
                $r->source    = $this->getCode();
                $r->article   = (string)($info['code'] ?? $art['code'] ?? $article);
                $r->brand     = (string)($info['tradeMarkName'] ?? $art['tradeMarkName'] ?? $brand);
                $r->name      = (string)($info['name'] ?? $art['name'] ?? '');
                $r->price     = (float)($p['price']['value'] ?? 0);
                $r->quantity  = (int)($p['quantity']['available'] ?? 0);
                $r->warehouse = $locName;
                $r->stockId   = $locCode;
                $r->supplierName = $this->getName();
                $r->isSched   = ($p['quantity']['available'] ?? 0) <= 0;
                $r->returnable = true;
                $r->raw       = array_merge($p, ['_article_info' => $info]);

                $deliveryDT = $p['deliveryDateTimes'][0]['deliveryDateTime'] ?? $p['shippingDateTime'] ?? null;
                if ($deliveryDT) {
                    $now = time();
                    $delTs = strtotime($deliveryDT);
                    $r->deliveryDays = max(0, (int)ceil(($delTs - $now) / 86400));
                    $r->deliveryPeriod = max(0, (int)ceil(($delTs - $now) / 3600));
                }
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }

        // Дедупликация
        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey() . '|' . $item->warehouse;
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $item; }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return $unique;
    }

    public function buildSearchRequest(string $brand, string $article): ?array
    {
        // ШАТЕ-М не поддерживает одношаговый поиск — отдаём null, будет через searchByBrandArticle
        return null;
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        return [];
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
        $token = $this->ensureToken();
        if (!$token) return $results;
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $articlesData = $this->apiGet('articles/search', ['searchString' => $query], $token);
        if (empty($articlesData)) return $results;
        if (isset($articlesData['id'])) $articlesData = [$articlesData];
        $articlesData = array_slice($articlesData, 0, 15);

        $articleIds = []; $articleInfo = [];
        foreach ($articlesData as $art) {
            $id = $art['id'] ?? 0;
            if ($id > 0) { $articleIds[] = $id; $articleInfo[$id] = $art; }
        }
        if (empty($articleIds)) return $results;

        foreach (array_chunk($articleIds, 10) as $batch) {
            foreach ($this->getPrices($batch, $token) as $pe) {
                $art = $pe['article'] ?? []; $prices = $pe['prices'] ?? [];
                $artId = $art['id'] ?? 0; $info = $articleInfo[$artId] ?? $art;
                foreach ($prices as $price) {
                    $locCode = $price['locationCode'] ?? '';
                    $locName = $this->getLocationName($locCode, $token);
                    $r = new SearchResultItem();
                    $r->source    = $this->getCode();
                    $r->article   = (string)($info['code'] ?? $art['code'] ?? '');
                    $r->brand     = (string)($info['tradeMarkName'] ?? $art['tradeMarkName'] ?? '');
                    $r->name      = (string)($info['name'] ?? $art['name'] ?? '');
                    $r->price     = (float)($price['price']['value'] ?? 0);
                    $r->quantity  = (int)($price['quantity']['available'] ?? 0);
                    $r->warehouse = $locName;
                    $r->stockId   = $locCode;
                    $r->supplierName = $this->getName();
                    $r->isSched   = ($price['quantity']['available'] ?? 0) <= 0;
                    $r->returnable = true;
                    $r->raw       = array_merge($price, ['_article_info' => $info]);
                    $deliveryDT = $price['deliveryDateTimes'][0]['deliveryDateTime'] ?? $price['shippingDateTime'] ?? null;
                    if ($deliveryDT) {
                        $now = time(); $delTs = strtotime($deliveryDT);
                        $r->deliveryDays = max(0, (int)ceil(($delTs - $now) / 86400));
                        $r->deliveryPeriod = max(0, (int)ceil(($delTs - $now) / 3600));
                    }
                    if ($r->price <= 0 && $r->quantity <= 0) continue;
                    $results[] = $r;
                }
            }
        }

        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey() . '|' . $item->warehouse;
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

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ($req['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($req['body']) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
        }
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) return null;
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
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
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
                CURLOPT_URL => $this->apiUrl . 'locations', CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                CURLOPT_TIMEOUT => 10,
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

    private function apiGet(string $endpoint, array $params, string $token): array
    {
        $url = $this->apiUrl . $endpoint . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
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

    private function log(string $message): void
    {
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/shatem_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}
