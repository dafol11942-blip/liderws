<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

class MoskvorechieConnector implements SupplierInterface
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private string $agreementId;
    private string $filialId;

    public function __construct(array $config = [])
    {
        $this->apiUrl      = $config['API_URL']      ?? 'https://api.moskvorechie.ru/v1/';
        $this->apiKey      = $config['API_KEY']      ?? '';
        $this->timeout     = $config['TIMEOUT']      ?? 6;
        $this->agreementId = $config['AGREEMENT_ID'] ?? '';
        $this->filialId    = $config['FILIAL_ID']    ?? '';
    }

    public function getCode(): string       { return 'moskvorechie'; }
    public function getName(): string       { return 'Москворечье'; }
    public function getWarehousePrefix(): string { return 'msk'; }

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
        $url = rtrim($this->apiUrl, '/') . '/search/brands?'
             . http_build_query(['number' => $article, 'search_oe' => 1, 'search_ref' => 1, 'search_trade' => 1, 'search_ean' => 1, 'avail' => 1]);
        return ['url' => $url, 'headers' => $this->buildHeaders(), 'method' => 'GET', 'body' => null];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data['data'])) return $brands;

        foreach ($data['data'] as $entry) {
            foreach ($entry['positions'] ?? [] as $pos) {
                $b  = trim((string)($pos['brand'] ?? ''));
                $n  = trim((string)($pos['number'] ?? ''));
                $nf = trim((string)($pos['number_fix'] ?? ''));
                if ($nf === '') $nf = $n;
                $d  = (string)($pos['description'] ?? '');
                if ($b === '' || $nf === '') continue;
                $key = $b . '|' . $nf;
                if (!isset($brands[$key])) {
                    $brands[$key] = ['brand' => $b, 'article' => $n, 'article_fix' => $nf, 'description' => $d];
                }
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
        $url = rtrim($this->apiUrl, '/') . '/search/articles?'
             . http_build_query(['brand' => $brand, 'number' => $article, 'avail' => 1]);
        return ['url' => $url, 'headers' => $this->buildHeaders(), 'method' => 'GET', 'body' => null];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        $srcItems   = $data['data']['src']   ?? [];
        $trustItems = $data['data']['trust'] ?? [];

        // Дедупликация по stock_id — src и trust могут содержать одинаковые склады
        $seen = [];
        foreach (array_merge($srcItems, $trustItems) as $item) {
            $sid = (string)($item['stock_id'] ?? '');
            if ($sid !== '' && isset($seen[$sid])) continue;
            $seen[$sid] = true;
            $r = $this->buildResultItem($item, $brand, $article);
            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
        }
        return $results;
    }

    // ==================== ДЕТАЛЬНАЯ ИНФОРМАЦИЯ ====================

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched) return $item;
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

        $seen = [];
        $unique = [];
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

    private function buildHeaders(): array
    {
        $h = ['X-API-Key: ' . $this->apiKey, 'Accept: application/json', 'Accept-Encoding: gzip'];
        if (!empty($this->agreementId)) $h[] = 'X-Agreement-ID: ' . $this->agreementId;
        if (!empty($this->filialId))    $h[] = 'X-Filial-ID: ' . $this->filialId;
        return $h;
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_ENCODING       => 'gzip',
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

        if ($err || $httpCode !== 200) {
            $this->log("execCurl: HTTP {$httpCode} err={$err}");
            return null;
        }
        return $resp;
    }

    private function buildResultItem(array $item, string $defaultBrand, string $defaultArticle): SearchResultItem
    {
        $flags      = (array)($item['flags'] ?? []);
        $isSched    = !empty($item['is_sched']);
        $returnable = !in_array('noreturn', $flags);

        $r = new SearchResultItem();
        $r->source         = $this->getCode();
        $r->article        = (string)($item['number_fix'] ?? $item['number'] ?? $defaultArticle);
        $r->brand          = (string)($item['brand'] ?? $defaultBrand);
        $r->name           = (string)($item['description'] ?? '');
        $r->price          = (float)($item['price'] ?? 0);
        $r->quantity       = (int)($item['availability'] ?? 0);
        $r->deliveryPeriod = isset($item['delivery_period']) ? (int)$item['delivery_period'] : null;
        $r->deliveryDays   = $r->deliveryPeriod !== null ? (int)ceil($r->deliveryPeriod / 24) : null;
        $r->warehouse      = (string)($item['stock_name'] ?? '');
        $r->stockId        = (string)($item['stock_id'] ?? '');
        $r->supplierName   = $this->getName();
        $r->multiplicity   = max(1, (int)($item['packing'] ?? 1));
        $r->unit           = !empty($item['unit']) ? (string)$item['unit'] : 'шт.';
        $r->isSched        = $isSched;
        $r->returnable     = $returnable;
        $r->raw            = $item;
        return $r;
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
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/moskvorechie_' . date('Y-m-d') . '.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    public function supportsCrossSearch(): bool
    {
        return false;
    }

    public function getSearchTimeout(): int
    {
        return 6;
    }
}
