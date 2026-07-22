<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class RosskoConnector implements SupplierInterface
{
    private string $key1;
    private string $key2;
    private string $deliveryId;
    private ?string $addressId;
    private int $timeout;

    public function __construct(array $config = [])
    {
        $this->key1       = $config['KEY1']        ?? '';
        $this->key2       = $config['KEY2']        ?? '';
        $this->deliveryId = $config['DELIVERY_ID'] ?? '000000002';
        $this->addressId  = $config['ADDRESS_ID']  ?? '71520';
        $this->timeout    = $config['TIMEOUT']     ?? 8;
    }

    public function getCode(): string       { return 'rossko'; }
    public function getName(): string       { return 'ROSSKO'; }
    public function getWarehousePrefix(): string { return 'rsk'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->key1) && !empty($this->key2);
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
        $body = $this->soapEnvelope('GetSearch', [
            'KEY1' => $this->key1, 'KEY2' => $this->key2,
            'text' => $article, 'delivery_id' => $this->deliveryId, 'address_id' => $this->addressId,
        ]);
        return [
            'url'     => 'https://api.rossko.ru/service/v2.1/GetSearch',
            'headers' => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: https://api.rossko.ru/GetSearch'],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $xml = simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) return $brands;

        $sn = $xml->xpath('//*[local-name()="success"]');
        if (!$sn || (string)$sn[0] !== 'true') return $brands;

        $pl = $xml->xpath('//*[local-name()="PartsList"]');
        if (!$pl) return $brands;
        $pn = $pl[0]->xpath('*[local-name()="Part"]');
        if (!$pn) return $brands;

        foreach ($pn as $part) {
            $b  = trim((string)($part->xpath('*[local-name()="brand"]')[0] ?? ''));
            $n  = trim((string)($part->xpath('*[local-name()="partnumber"]')[0] ?? ''));
            $nm = trim((string)($part->xpath('*[local-name()="name"]')[0] ?? ''));
            if (!$b || !$n) continue;
            $key = $b . '|' . $n;
            if (!isset($brands[$key])) {
                $brands[$key] = ['brand' => $b, 'article' => $n, 'article_fix' => $n, 'description' => $nm];
            }
            $crosses = $part->xpath('*[local-name()="crosses"]/*[local-name()="Part"]');
            if ($crosses) {
                foreach ($crosses as $cross) {
                    $cb = trim((string)($cross->xpath('*[local-name()="brand"]')[0] ?? ''));
                    $cn = trim((string)($cross->xpath('*[local-name()="partnumber"]')[0] ?? ''));
                    $cnm = trim((string)($cross->xpath('*[local-name()="name"]')[0] ?? ''));
                    if (!$cb || !$cn) continue;
                    $ckey = $cb . '|' . $cn;
                    if (!isset($brands[$ckey])) {
                        $brands[$ckey] = ['brand' => $cb, 'article' => $cn, 'article_fix' => $cn, 'description' => $cnm];
                    }
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
        // Rossko всегда отдаёт кроссы в ответе — параметр $withCrosses не меняет запрос
        $body = $this->soapEnvelope('GetSearch', [
            'KEY1' => $this->key1, 'KEY2' => $this->key2,
            'text' => $article . ' ' . $brand, 'delivery_id' => $this->deliveryId, 'address_id' => $this->addressId,
        ]);
        return [
            'url'     => 'https://api.rossko.ru/service/v2.1/GetSearch',
            'headers' => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: https://api.rossko.ru/GetSearch'],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    /**
     * Парсит ответ GetSearch.
     * Собирает ВСЕ склады: из основного Part и из <crosses>.
     * Без фильтрации по семейству — разделение exact/analog делает Stage2.
     */
    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $xml = simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) return $results;

        $sn = $xml->xpath('//*[local-name()="success"]');
        if (!$sn || (string)$sn[0] !== 'true') return $results;

        $pl = $xml->xpath('//*[local-name()="PartsList"]');
        if (!$pl) return $results;

        $pn = $pl[0]->xpath('*[local-name()="Part"]');
        if (!$pn) return $results;

        // Собираем все Part: и основные, и кроссы
        $allParts = [];
        foreach ($pn as $part) {
            $allParts[] = $part;
            $crosses = $part->xpath('*[local-name()="crosses"]/*[local-name()="Part"]');
            if ($crosses) {
                foreach ($crosses as $c) {
                    $allParts[] = $c;
                }
            }
        }

        foreach ($allParts as $part) {
            $ds = $part->xpath('*[local-name()="stocks"]/*[local-name()="stock"]');
            if ($ds) {
                foreach ($ds as $s) {
                    $results[] = $this->parseStock($s, $part);
                }
            }
        }

        // Дедупликация
        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $key = !empty($item->stockId) ? $item->stockId
                : md5(($item->warehouse ?? '') . '|' . $item->price . '|' . $item->brand . '|' . $item->article);
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

        return array_slice($unique, 0, 180);
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
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $xml = $this->soapCall('GetSearch', [
            'KEY1' => $this->key1, 'KEY2' => $this->key2,
            'text' => $query, 'delivery_id' => $this->deliveryId, 'address_id' => $this->addressId,
        ]);
        if ($xml === null) return $results;
        $sn = $xml->xpath('//*[local-name()="success"]');
        if (!$sn || (string)$sn[0] !== 'true') return $results;
        $pl = $xml->xpath('//*[local-name()="PartsList"]');
        if (!$pl) return $results;
        $pn = $pl[0]->xpath('*[local-name()="Part"]');
        if (!$pn) return $results;

        $seen = [];
        foreach ($pn as $part) {
            $ds = $part->xpath('*[local-name()="stocks"]/*[local-name()="stock"]');
            if ($ds) { foreach ($ds as $s) { $item = $this->parseStock($s, $part); $key = $item->stockId ?: $item->getDedupeKey(); if (!isset($seen[$key])) { $seen[$key] = true; $results[] = $item; } } }
            $cp = $part->xpath('*[local-name()="crosses"]/*[local-name()="Part"]');
            if ($cp) { foreach ($cp as $c) { $cs = $c->xpath('*[local-name()="stocks"]/*[local-name()="stock"]'); if ($cs) { foreach ($cs as $s) { $item = $this->parseStock($s, $c); $key = $item->stockId ?: $item->getDedupeKey(); if (!isset($seen[$key])) { $seen[$key] = true; $results[] = $item; } } } } }
        }

        usort($results, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return array_slice($results, 0, 30);
    }

    // ==================== НОВЫЕ МЕТОДЫ ====================

    public function supportsCrossSearch(): bool
    {
        // Rossko отдаёт кроссы внутри того же ответа GetSearch — отдельный запрос не нужен
        return false;
    }

    public function getSearchTimeout(): int
    {
        return $this->timeout;
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function parseStock(\SimpleXMLElement $stock, \SimpleXMLElement $part): SearchResultItem
    {
        $qty = (int)($stock->xpath('*[local-name()="count"]')[0] ?? 0);
        $del = (int)($stock->xpath('*[local-name()="delivery"]')[0] ?? 0);
        $sid = trim((string)($stock->xpath('*[local-name()="id"]')[0] ?? ''));

        $r = new SearchResultItem();
        $r->source         = $this->getCode();
        $r->article        = trim((string)($part->xpath('*[local-name()="partnumber"]')[0] ?? ''));
        $r->brand          = trim((string)($part->xpath('*[local-name()="brand"]')[0] ?? ''));
        $r->name           = trim((string)($part->xpath('*[local-name()="name"]')[0] ?? ''));
        $r->price          = (float)($stock->xpath('*[local-name()="price"]')[0] ?? 0);
        $r->quantity       = $qty;
        $r->deliveryDays   = $del;
        $r->deliveryPeriod = $del > 0 ? $del * 24 : null;
        $r->warehouse      = trim((string)($stock->xpath('*[local-name()="description"]')[0] ?? ''));
        $r->stockId        = $sid;
        $r->supplierName   = $this->getName();
        $r->isSched        = $qty <= 0;
        $r->multiplicity   = max(1, (int)($stock->xpath('*[local-name()="multiplicity"]')[0] ?? 1));
        $r->unit           = 'шт.';
        $r->returnable     = true;

        $this->addDeliveryDetails($r, $del);
        return $r;
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $req['body'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || empty($resp)) return null;
        return $resp;
    }

    private function soapCall(string $method, array $params): ?\SimpleXMLElement
    {
        $body = $this->soapEnvelope($method, $params);
        $ch = curl_init('https://api.rossko.ru/service/v2.1/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: https://api.rossko.ru/' . $method],
            CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || empty($response)) return null;
        $xml = simplexml_load_string($response);
        return ($xml === false || $xml === null) ? null : $xml;
    }

    private function soapEnvelope(string $method, array $params): string
    {
        $body = '<ns1:' . $method . ' xmlns:ns1="https://api.rossko.ru/">';
        foreach ($params as $k => $v) {
            if ($v === null) continue;
            $body .= '<ns1:' . $k . '>' . htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</ns1:' . $k . '>';
        }
        $body .= '</ns1:' . $method . '>';
        return '<?xml version="1.0" encoding="utf-8"?>'
             . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soap:Body>' . $body . '</soap:Body>'
             . '</soap:Envelope>';
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
            '/var/www/u3564357/data/www/liderws.ru/upload/logs/rossko_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    private ?array $wavesCache = null;

    private function getWaves(): array
    {
        if ($this->wavesCache !== null) return $this->wavesCache;

        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/rossko_waves.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $this->wavesCache = json_decode(file_get_contents($cacheFile), true) ?: [];
            if (!empty($this->wavesCache)) {
                foreach ($this->wavesCache as $date => $ws) {
                    if (!empty($ws)) return $this->wavesCache;
                }
            }
        }

        $waves = [];
        for ($d = 0; $d <= 3; $d++) {
            $date = date('Y-m-d', strtotime("+{$d} days"));
            if (!isset($waves[$date])) $waves[$date] = [];

            $xml = $this->soapCall('GetDeliveryDetails', [
                'KEY1' => $this->key1, 'KEY2' => $this->key2,
                'date' => $date, 'address_id' => $this->addressId,
            ]);
            if (!$xml) continue;

            $dels = $xml->xpath('//*[local-name()="Delivery"]');
            foreach ($dels as $delivery) {
                $ws = $delivery->xpath('*[local-name()="Wave"]');
                foreach ($ws as $w) {
                    $waves[$date][] = [
                        'from'     => (string)($w->xpath('*[local-name()="deliveryStart"]')[0] ?? ''),
                        'to'       => (string)($w->xpath('*[local-name()="deliveryEnd"]')[0] ?? ''),
                        'deadline' => (string)($w->xpath('*[local-name()="timeLimit"]')[0] ?? ''),
                    ];
                }
            }
        }

        $this->wavesCache = $waves;
        @mkdir(dirname($cacheFile), 0755, true);
        if (!empty($waves)) { @file_put_contents($cacheFile, json_encode($waves)); }
        return $waves;
    }

    private function addDeliveryDetails(SearchResultItem $r, int $deliveryDays): void
    {
        $waves = $this->getWaves();
        $now = time();
        $targetDate = date('Y-m-d', strtotime("+{$deliveryDays} days"));

        $dayWaves = $waves[$targetDate] ?? [];

        foreach ($dayWaves as $w) {
            $deadlineTs = strtotime($w['deadline']);
            if ($deadlineTs > $now) {
                $r->raw['deliveryDateFrom'] = $w['from'];
                $r->raw['deliveryDateTo']   = $w['to'];
                $r->raw['deliveryCheckout'] = $w['deadline'];
                return;
            }
        }

        $nextDate = date('Y-m-d', strtotime("+1 day", strtotime($targetDate)));
        $nextWaves = $waves[$nextDate] ?? [];
        if (!empty($nextWaves)) {
            $r->raw['deliveryDateFrom'] = $nextWaves[0]['from'];
            $r->raw['deliveryDateTo']   = $nextWaves[0]['to'];
            $r->raw['deliveryCheckout'] = $nextWaves[0]['deadline'];
            $r->deliveryDays = $deliveryDays + 1;
        }
    }
}
