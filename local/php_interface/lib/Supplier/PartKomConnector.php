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
    private bool   $makerIdUsed        = false;
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
            // store=1 — ТОЛЬКО собственный склад ПартКома, отфильтровывает всех
            // остальных поставщиков, агрегируемых через ПартКом (providerId/
            // providerDescription в ответе — это именно сторонние поставщики).
            // По документации 0 — "все предложения поставщиков", это и нужно.
            'store'            => 0,
        ];

        if ($brand !== '') {
            $makerId = $this->resolveMakerId($brand);
            if ($makerId) {
                $params['maker_id']     = $makerId;
                $this->makerIdUsed      = true;
            }
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

        if ($this->makerIdUsed) {
            $matchBrand = ($this->resolvedBrandName !== '') ? $this->resolvedBrandName : $brand;
            $normBrand  = BrandNormalizer::normalize($matchBrand);
        } else {
            $normBrand = '';
        }
        $normArt = BrandNormalizer::normalizeArticle($article);

        foreach ($data as $item) {
            if (!is_array($item)) continue;
            $itemBrand  = trim((string)($item['maker'] ?? ''));
            $itemNumber = (string)($item['number'] ?? '');
            $itemName   = (string)($item['description'] ?? '');

            if (!$withCrosses) {
                if ($normArt !== '' && $itemNumber !== ''
                    && BrandNormalizer::normalizeArticle($itemNumber) !== $normArt) continue;
                if ($normBrand !== '' && $itemBrand !== ''
                    && BrandNormalizer::normalize($itemBrand) !== $normBrand) continue;
            } else {
                if ($normArt !== '' && $itemNumber !== ''
                    && BrandNormalizer::normalizeArticle($itemNumber) === $normArt
                    && $this->makerIdUsed && $normBrand !== ''
                    && BrandNormalizer::normalize($itemBrand) !== $normBrand) continue;

                $fam = $this->detectFamily($itemName . ' ' . $itemBrand);
                if ($fam !== '' && in_array($fam, ['stab','filter','pan','spring','tie'], true)) {
                    $tb = BrandNormalizer::normalize($brand);
                    if (in_array($tb, ['sangsin','hiq','hi-q','lynxauto','trw','brembo','ferodo'], true)
                        || preg_match('/^sp\d+/i', trim($article))) {
                        if ($fam !== 'pad' && $fam !== 'brake') continue;
                    }
                }
            }

            $qty     = _parseQty($item['quantity'] ?? 0);
            $isStock = !empty($item['isStock']);
            if (!$isStock || $qty <= 0) continue;

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

            [$r->deliveryDays, $r->deliveryPeriod, $r->deliveryLabel, $r->deliveryToday, $r->deliveryDeadline] = $this->resolveDelivery($item);

            $r->raw = [
                'deliveryWavesActive'   => $item['deliveryWavesActive'] ?? null,
                'deliveryCheckout'      => $item['deliveryCheckout'] ?? null,
                'deliveryDateFrom'      => $item['deliveryDateFrom'] ?? null,
                'deliveryDateTo'        => $item['deliveryDateTo'] ?? null,
                'expectedDate'          => $item['expectedDate'] ?? null,
                'guaranteedDate'        => $item['guaranteedDate'] ?? null,
                'expectedHours'         => $item['expectedHours'] ?? null,
                'guaranteedHours'       => $item['guaranteedHours'] ?? null,
                'expectedDays'          => $item['expectedDays'] ?? null,
                'guaranteedDays'        => $item['guaranteedDays'] ?? null,
                'isStock'               => $item['isStock'] ?? null,
                'storehouse'            => $item['storehouse'] ?? null,
                'flagReturnImpossible'  => $item['flagReturnImpossible'] ?? null,
            ];

            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
            if (count($results) >= 160) break;
        }

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

    /**
     * Срок доставки по ответу ПартКом — окно "от–до" в духе "Сегодня 13:15 - 16:00" /
     * "Завтра 07:45 - 15:00" / "03.09 07:45 - 15:00". Приоритет источников (от точного
     * к грубому):
     *  1. Волна доставки (deliveryWavesActive+deliveryDateTo, начало окна — deliveryDateFrom)
     *     — реальное "закажи до deliveryCheckout, получишь в это окно", самый точный источник.
     *  2. guaranteedDate ("до") + expectedDate ("от", если раньше guaranteedDate) — пара
     *     границ срока поставки.
     *  3. guaranteedHours / expectedHours — то же самое, но точность в часах, а не в дате.
     *  4. guaranteedDays / expectedDays — только точность в днях, без времени.
     * Намеренно НЕ используем deliveryDateFrom как самостоятельный срок поставки —
     * это лишь начало окна волны, а не время готовности детали.
     *
     * @return array{0:?int,1:?int,2:?string,3:bool,4:?string} [deliveryDays, deliveryPeriod(часы), label, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $item): array
    {
        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');

        $fromTs     = null;
        $toTs       = null;
        $deadlineTs = null;

        // 1. Волна доставки — готовая пара границ окна.
        if (!empty($item['deliveryWavesActive']) && !empty($item['deliveryDateTo'])) {
            $wTo = strtotime($item['deliveryDateTo']);
            if ($wTo && $wTo > $now) {
                $toTs = $wTo;
                $wFrom = !empty($item['deliveryDateFrom']) ? strtotime($item['deliveryDateFrom']) : null;
                if ($wFrom && $wFrom < $toTs) $fromTs = $wFrom;
                if (!empty($item['deliveryCheckout'])) $deadlineTs = strtotime($item['deliveryCheckout']);
            }
        }

        // 2. guaranteedDate ("до") + expectedDate ("от") — пара границ срока поставки.
        if ($toTs === null) {
            $g = !empty($item['guaranteedDate']) ? strtotime($item['guaranteedDate']) : null;
            $e = !empty($item['expectedDate']) ? strtotime($item['expectedDate']) : null;
            if ($g && $g > $now) {
                $toTs   = $g;
                $fromTs = ($e && $e > $now && $e < $g) ? $e : null;
            } elseif ($e && $e > $now) {
                $toTs = $e;
            }
        }

        if ($toTs !== null) {
            $hours = max(0, (int)ceil(($toTs - $now) / 3600));
            $tsDay = strtotime(date('Y-m-d', $toTs));
            $days  = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
            $label = $this->formatDeliveryLabel($tsDay, $todayStart, $tomorrowStart, $fromTs, $toTs);
            return [$days, $hours, $label, $tsDay <= $todayStart, $deadlineTs ? date('H:i', $deadlineTs) : null];
        }

        // 3. Только часы — тот же принцип "от"/"до", если обе границы заданы.
        $hTo = null;
        if (isset($item['guaranteedHours']) && $item['guaranteedHours'] !== '') {
            $hTo = (int)$item['guaranteedHours'];
        } elseif (isset($item['expectedHours']) && $item['expectedHours'] !== '') {
            $hTo = (int)$item['expectedHours'];
        }
        if ($hTo !== null && $hTo >= 0) {
            $hFrom = null;
            if (isset($item['expectedHours']) && $item['expectedHours'] !== '' && (int)$item['expectedHours'] < $hTo) {
                $hFrom = (int)$item['expectedHours'];
            }
            $toTs2   = $now + $hTo * 3600;
            $fromTs2 = $hFrom !== null ? $now + $hFrom * 3600 : null;
            $tsDay   = strtotime(date('Y-m-d', $toTs2));
            $days    = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
            $label   = $this->formatDeliveryLabel($tsDay, $todayStart, $tomorrowStart, $fromTs2, $toTs2);
            return [$days, $hTo, $label, $tsDay <= $todayStart, null];
        }

        // 4. Только дни — без точности во времени.
        $d = null;
        if (isset($item['guaranteedDays']) && $item['guaranteedDays'] !== '') {
            $d = (int)$item['guaranteedDays'];
        } elseif (isset($item['expectedDays']) && $item['expectedDays'] !== '') {
            $d = (int)$item['expectedDays'];
        }
        if ($d !== null) {
            $d     = max(0, $d);
            $label = $d === 0 ? 'Сегодня' : ($d === 1 ? 'Завтра' : date('d.m', strtotime("+{$d} days")));
            return [$d, null, $label, $d === 0, null];
        }

        return [null, null, null, false, null];
    }

    private function formatDeliveryLabel(int $tsDay, int $todayStart, int $tomorrowStart, ?int $fromTs, int $toTs): string
    {
        $dayLabel = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $toTs));
        $timeLabel = $fromTs ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $toTs);
        return $dayLabel . ' ' . $timeLabel;
    }

    // ── MAKER ID (v5: без substring, только точные совпадения) ──
    private function resolveMakerId(string $brand): ?int
    {
        if ($brand === '') return null;

        $this->loadBrands();
        if (empty($this->brandsCache)) return null;

        $norm  = BrandNormalizer::normalize($brand);
        $lower = mb_strtolower(trim($brand));

        // 1. Точное совпадение нормализованных имён
        foreach ($this->brandsCache as $id => $name) {
            if (BrandNormalizer::normalize((string)$name) === $norm) {
                // Санитарная проверка: не должны совпадать слова разной длины
                // MANN-FILTER и MANNOL могут иметь одинаковую normal-форму
                $nameLower = mb_strtolower(trim((string)$name));
                // Проверяем, что одно содержит другое (MANN-FILTER содержит MANN, но не MANNOL)
                if (mb_strlen($lower) >= 3 && mb_strlen($nameLower) >= 3) {
                    $shorter = mb_strlen($lower) < mb_strlen($nameLower) ? $lower : $nameLower;
                    $longer  = mb_strlen($lower) < mb_strlen($nameLower) ? $nameLower : $lower;
                    // Если короткое слово содержится в длинном — это ОК (MANN в MANN-FILTER)
                    // Если нет (MANNOL vs MANN-FILTER) — пропускаем
                    if (mb_strpos($longer, $shorter) === false && $shorter !== $longer) {
                        continue; // MANNOL не содержится в MANN-FILTER и наоборот
                    }
                }
                $this->resolvedBrandName = (string)$name;
                $this->log("resolveMakerId: exact '{$brand}' → id={$id} '{$name}'");
                return (int)$id;
            }
        }

        // 2. Точное регистронезависимое совпадение (без normalizer)
        foreach ($this->brandsCache as $id => $name) {
            if (mb_strtolower(trim((string)$name)) === $lower) {
                $this->resolvedBrandName = (string)$name;
                $this->log("resolveMakerId: exact-lower '{$brand}' → id={$id} '{$name}'");
                return (int)$id;
            }
        }

        // 3. Stripped suffix: MANN-FILTER → MANN
        $stripped = preg_replace('/[-_\s].*$/u', '', $brand);
        if ($stripped !== $brand && mb_strlen($stripped) >= 3) {
            $sn    = BrandNormalizer::normalize($stripped);
            $slower = mb_strtolower($stripped);

            foreach ($this->brandsCache as $id => $name) {
                $n = mb_strtolower(trim((string)$name));
                // Точное совпадение stripped
                if ($n === $slower || BrandNormalizer::normalize((string)$name) === $sn) {
                    $this->resolvedBrandName = (string)$name;
                    $this->log("resolveMakerId: stripped '{$brand}'→'{$stripped}' → id={$id} '{$name}'");
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
                return;
            }
        }

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

        $this->brandsCache = [];
        $json = json_decode($resp, true);
        $items = $json['data'] ?? $json['brands'] ?? $json['items'] ?? $json ?? [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item) && isset($item['name'])) {
                    $id = $item['id'] ?? $item['maker_id'] ?? $item['code'] ?? crc32($item['name']);
                    $this->brandsCache[(int)$id] = $item['name'];
                } elseif (is_string($item) && $item !== '') {
                    $this->brandsCache[crc32($item)] = $item;
                }
            }
        }

        $this->log("loadBrands: " . count($this->brandsCache) . " brands");

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