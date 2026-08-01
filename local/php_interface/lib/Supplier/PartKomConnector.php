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
    private int    $timeout;
    private bool   $lastWithCrosses    = false;
    private string $resolvedBrandName  = '';
    private bool   $makerIdUsed        = false;   // ← новый флаг
    private ?array $brandsCache        = null;

    public function __construct(array $config = [])
    {
        $this->login    = $config['LOGIN']     ?? 'lider16';
        $this->password = $config['PASSWORD']  ?? 'LidGates16';
        $this->baseUrl  = $config['BASE_URL']  ?? 'https://ws.part-kom.ru/v4';
        $this->timeout  = $config['TIMEOUT']   ?? 8;
    }

    public function getCode(): string           { return 'partkom'; }
    public function getName(): string           { return 'ПартКом'; }
    public function getWarehousePrefix(): string { return 'pk'; }
    public function isAvailable(): bool         { return !empty($this->login) && !empty($this->password); }
    public function supportsCrossSearch(): bool { return true; }
    public function getSearchTimeout(): int     { return 8; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    private function authHeader(): string
    {
        return 'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password);
    }

    // ── BRANDS ────────────────────────────────────────────
    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) return null;
        return [
            'url'     => $this->baseUrl . '/search/articule-brands?' . http_build_query(['number' => $article]),
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $body, string $requestArticle = ''): array
    {
        $brands  = [];
        $data    = json_decode($body, true);
        if (!is_array($data)) return $brands;

        $article = trim($requestArticle);
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

    // ── SEARCH ────────────────────────────────────────────
    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;

        $this->lastWithCrosses   = $withCrosses;
        $this->resolvedBrandName = '';
        $this->makerIdUsed       = false;

        $params = [
            'number'           => $article,
            'find_substitutes' => $withCrosses ? 1 : 0,
            'store'            => 1,
        ];

        if ($brand !== '') {
            $makerId = $this->resolveMakerId($brand);
            if ($makerId) {
                $params['maker_id']     = $makerId;
                $this->makerIdUsed      = true;
            }
            // если maker_id не найден — ищем без него, PartKom вернёт все бренды
        }

        return [
            'url'     => $this->baseUrl . '/search/offers?' . http_build_query($params),
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $body, string $brand, string $article): array
    {
        $results = [];
        $data    = json_decode($body, true);
        if (!is_array($data)) return $results;

        $withCrosses = $this->lastWithCrosses;

        // Если maker_id использовался — бренд уже отфильтрован API, сверяем для надёжности
        // Если нет — PartKom вернул ВСЕ бренды, фильтруем по артикулу, бренд НЕ фильтруем
        if ($this->makerIdUsed) {
            $matchBrand = ($this->resolvedBrandName !== '') ? $this->resolvedBrandName : $brand;
            $normBrand  = BrandNormalizer::normalize($matchBrand);
        } else {
            $normBrand = ''; // без maker_id — не фильтруем по бренду
        }
        $normArt = BrandNormalizer::normalizeArticle($article);

        foreach ($data as $item) {
            if (!is_array($item)) continue;

            $itemBrand  = trim((string)($item['maker'] ?? ''));
            $itemNumber = (string)($item['number'] ?? '');
            $itemName   = (string)($item['description'] ?? '');

            // ── Фильтр: точное совпадение ──
            if (!$withCrosses) {
                // Артикул — всегда
                if ($normArt !== '' && $itemNumber !== ''
                    && BrandNormalizer::normalizeArticle($itemNumber) !== $normArt) {
                    continue;
                }
                // Бренд — только если maker_id был (API уже отфильтровал или мы сверяем)
                if ($normBrand !== '' && $itemBrand !== ''
                    && BrandNormalizer::normalize($itemBrand) !== $normBrand) {
                    continue;
                }
            } else {
                // ── Фильтр: кроссы ──
                // Выкидываем «однофамильцев»: тот же артикул другой бренд
                if ($normArt !== '' && $itemNumber !== ''
                    && BrandNormalizer::normalizeArticle($itemNumber) === $normArt
                    && $this->makerIdUsed
                    && $normBrand !== ''
                    && BrandNormalizer::normalize($itemBrand) !== $normBrand
                ) {
                    continue;
                }

                // Семейный фильтр
                $fam = $this->detectFamily($itemName . ' ' . $itemBrand);
                if ($fam !== '' && in_array($fam, ['stab','filter','pan','spring','tie'], true)) {
                    $tb = BrandNormalizer::normalize($brand);
                    if (in_array($tb, ['sangsin','hiq','hi-q','lynxauto','trw','brembo','ferodo'], true)
                        || preg_match('/^sp\d+/i', trim($article))) {
                        if ($fam !== 'pad' && $fam !== 'brake') continue;
                    }
                }
            }

            // ── Только со склада ──
            $qty     = _parseQty($item['quantity'] ?? 0);
            $isStock = !empty($item['isStock']);
            if (!$isStock || $qty <= 0) continue;

            // ── SearchResultItem ──
            $r                = new SearchResultItem();
            $r->source        = $this->getCode();
            $r->article       = $itemNumber !== '' ? $itemNumber : $article;
            $r->brand         = $itemBrand !== '' ? $itemBrand : $brand;
            $r->name          = $itemName;
            $r->price         = (float)($item['price'] ?? 0);
            $r->quantity      = $qty;
            $r->unit          = 'шт.';
            $r->returnable    = empty($item['flagReturnImpossible']);
            $r->multiplicity  = max(1, (int)($item['minQuantity'] ?? 1));
            $r->isSched       = false;
            $r->supplierName  = $this->getName();

            if (!empty($item['storehouse'])) {
                $r->warehouse = 'ПартКом: ' . ($item['placement'] ?? 'Склад');
            } else {
                $r->warehouse = ($item['providerDescription'] ?? '—') . ': ' . ($item['placement'] ?? '');
            }
            $r->stockId = (string)($item['placementId'] ?? $item['providerId'] ?? '');

            // Сроки
            $now = time();
            $ts  = null;
            if (!empty($item['deliveryDateFrom'])) {
                $ts = strtotime($item['deliveryDateFrom']);
            } elseif (!empty($item['expectedDate'])) {
                $ts = strtotime($item['expectedDate']);
            }
            if ($ts && $ts > $now) {
                $r->deliveryPeriod = max(0, (int)(($ts - $now) / 3600));
                $r->deliveryDays   = (date('Y-m-d', $ts) === date('Y-m-d', $now))
                    ? 0 : max(1, (int)ceil(($ts - $now) / 86400));
            } elseif (!empty($item['expectedHours'])) {
                $r->deliveryPeriod = (int)$item['expectedHours'];
                $r->deliveryDays   = (int)ceil($item['expectedHours'] / 24);
            }

            $r->raw = [
                'deliveryDateFrom'      => $item['deliveryDateFrom'] ?? null,
                'deliveryDateTo'        => $item['expectedDate'] ?? ($item['deliveryDateTo'] ?? null),
                'expectedHours'         => $item['expectedHours'] ?? null,
                'isStock'               => $item['isStock'] ?? null,
                'storehouse'            => $item['storehouse'] ?? null,
                'flagReturnImpossible'  => $item['flagReturnImpossible'] ?? null,
            ];

            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
            if (count($results) >= 160) break;
        }

        // Дедупликация
        $seen   = [];
        $unique = [];
        foreach ($results as $it) {
            $dk = ($it->stockId ?: '') . '|' . $it->price;
            if (!isset($seen[$dk])) { $seen[$dk] = true; $unique[] = $it; }
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

        return $unique;
    }

    // ── MAKER ID ──────────────────────────────────────────
    private function resolveMakerId(string $brand): ?int
    {
        if ($brand === '') return null;

        $this->loadBrands();
        if (empty($this->brandsCache)) return null;

        $norm = BrandNormalizer::normalize($brand);

        // 1. Точное совпадение
        foreach ($this->brandsCache as $id => $name) {
            if (BrandNormalizer::normalize((string)$name) === $norm) {
                $this->resolvedBrandName = (string)$name;
                $this->log("resolveMakerId: exact '{$brand}' → id={$id} '{$name}'");
                return (int)$id;
            }
        }

        // 2. Substring
        $raw = mb_strtolower(trim($brand));
        foreach ($this->brandsCache as $id => $name) {
            $n = mb_strtolower(trim((string)$name));
            if ($n === $raw || mb_strpos($n, $raw) !== false || mb_strpos($raw, $n) !== false) {
                $this->resolvedBrandName = (string)$name;
                $this->log("resolveMakerId: substring '{$brand}' → id={$id} '{$name}'");
                return (int)$id;
            }
        }

        // 3. Stripped suffix
        $stripped = preg_replace('/[-_\s].*$/u', '', $brand);
        if ($stripped !== $brand && $stripped !== '') {
            $sn = BrandNormalizer::normalize($stripped);
            foreach ($this->brandsCache as $id => $name) {
                if (BrandNormalizer::normalize((string)$name) === $sn) {
                    $this->resolvedBrandName = (string)$name;
                    $this->log("resolveMakerId: stripped '{$brand}'→'{$stripped}' → id={$id} '{$name}'");
                    return (int)$id;
                }
            }
            $sl = mb_strtolower($stripped);
            foreach ($this->brandsCache as $id => $name) {
                $n = mb_strtolower(trim((string)$name));
                if ($n === $sl || mb_strpos($n, $sl) !== false || mb_strpos($sl, $n) !== false) {
                    $this->resolvedBrandName = (string)$name;
                    $this->log("resolveMakerId: stripped substring '{$brand}'→'{$stripped}' → id={$id} '{$name}'");
                    return (int)$id;
                }
            }
        }

        return null;
    }

    // ── LOAD BRANDS ───────────────────────────────────────
    private function loadBrands(): void
    {
        if ($this->brandsCache !== null && !empty($this->brandsCache)) return;

        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/partkom_brands.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                $this->brandsCache = $cached;
                $this->log("loadBrands: cache (" . count($this->brandsCache) . " brands)");
                return;
            }
        }

        $this->log("loadBrands: API call...");
        $ch = curl_init($this->baseUrl . '/search/brands');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [$this->authHeader(), 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200 || empty($resp)) {
            $this->log("loadBrands: FAIL HTTP={$httpCode} err={$err}");
            $this->brandsCache = null;
            return;
        }

        // Отладка: сохраняем сырой ответ
        $this->log("loadBrands: raw=" . substr($resp, 0, 200));

        $this->brandsCache = [];
        $data = json_decode($resp, true);

        if (is_array($data)) {
            // Формат 1: [{"id":1,"name":"MANN"}, ...]
            // Формат 2: ["MANN","BOSCH",...]
            // Формат 3: {"brands": [...]}
            // Формат 4: {"data": [...]}

            // Проверяем вложенность
            $items = $data;
            if (isset($data['brands']) && is_array($data['brands'])) $items = $data['brands'];
            elseif (isset($data['data']) && is_array($data['data'])) $items = $data['data'];
            elseif (isset($data['items']) && is_array($data['items'])) $items = $data['items'];

            foreach ($items as $item) {
                if (is_array($item) && isset($item['id'], $item['name'])) {
                    $this->brandsCache[(int)$item['id']] = $item['name'];
                } elseif (is_array($item) && isset($item['name'])) {
                    // id может быть maker_id, code, etc
                    $id = $item['id'] ?? $item['maker_id'] ?? $item['code'] ?? crc32($item['name']);
                    $this->brandsCache[(int)$id] = $item['name'];
                } elseif (is_string($item) && $item !== '') {
                    $this->brandsCache[crc32($item)] = $item;
                }
            }
        }

        $this->log("loadBrands: result=" . count($this->brandsCache) . " brands");

        if (!empty($this->brandsCache)) {
            @mkdir(dirname($cacheFile), 0755, true);
            @file_put_contents($cacheFile, json_encode($this->brandsCache, JSON_UNESCAPED_UNICODE));
        } else {
            $this->brandsCache = null;
        }
    }

    // ── HELPERS ───────────────────────────────────────────
    private function detectFamily(string $text): string
    {
        $t = mb_strtolower($text);
        $map = [
            'pad'    => ['колодк', 'brake pad', 'disc pad'],
            'stab'   => ['стабил', 'stabilizer', 'sway', 'тяга стаб', 'стойка стаб', 'anti-roll'],
            'filter' => ['фильтр', 'filter'],
            'spring' => ['пружин', 'spring'],
            'tie'    => ['наконечник', 'tie rod', 'рулев'],
            'pan'    => ['поддон', 'oil pan'],
        ];
        foreach ($map as $fam => $words) {
            foreach ($words as $w) {
                if (mb_strpos($t, $w) !== false) return $fam;
            }
        }
        if (mb_strpos($t, 'тормоз') !== false) return 'brake';
        return '';
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
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        if ($err || $httpCode !== 200) {
            $this->log("HTTP {$httpCode} err={$err} url={$req['url']}");
            return null;
        }
        return $resp;
    }

    private function log(string $message): void
    {
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/partkom_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
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
            CURLOPT_HTTPHEADER     => [$this->authHeader(), 'Accept: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || empty($resp)) return $results;

        return $this->parseSearchResponse($resp, '', $query);
    }
}