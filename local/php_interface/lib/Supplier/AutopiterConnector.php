<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class AutopiterConnector implements SupplierInterface
{
    private string $userId;
    private string $password;
    private string $baseUrl;
    private int $timeout;
    private ?string $authCookie = null;

    public function __construct(array $config = [])
    {
        $this->userId   = $config['USER_ID']  ?? '';
        $this->password = $config['PASSWORD'] ?? '';
        $this->baseUrl  = $config['BASE_URL'] ?? 'https://service.autopiter.ru/v2/price';
        $this->timeout  = $config['TIMEOUT']  ?? 10;
    }

    public function getCode(): string       { return 'autopiter'; }
    public function getName(): string       { return 'Автопитер'; }
    public function getWarehousePrefix(): string { return 'ap'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->userId) && !empty($this->password);
    }

    // ==================== АВТОРИЗАЦИЯ (SOAP Cookie) ====================

    private function ensureAuth(): bool
    {
        if ($this->authCookie !== null) return true;

        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<Authorization xmlns="http://www.autopiter.ru/">'
            . '<UserID>' . $this->userId . '</UserID>'
            . '<Password>' . $this->password . '</Password>'
            . '<Save>true</Save>'
            . '</Authorization>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $resp = $this->execSoap($xml, true);
        if ($resp === null) return false;

        if (preg_match('/AuthorizationResult>true</', $resp)) {
            return true;
        }
        return false;
    }

    // ==================== БРЕНДЫ ====================

    public function searchBrands(string $article): array
    {
        if (!$this->ensureAuth()) return [];    
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execSoap($req['body']);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) return null;

        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<FindCatalog xmlns="http://www.autopiter.ru/">'
            . '<Number>' . htmlspecialchars($article, ENT_XML1) . '</Number>'
            . '</FindCatalog>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $headers = ['Content-Type: text/xml; charset=utf-8'];
        if ($this->authCookie) {
            $headers[] = 'Cookie: ' . $this->authCookie;
        }

        return [
            'url'     => $this->baseUrl,
            'headers' => $headers,
            'method'  => 'POST',
            'body'    => $xml,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $article = trim($requestArticle);

        if (!preg_match_all('/<SearchCatalogModel>(.*?)<\/SearchCatalogModel>/s', $responseBody, $matches)) {
            return $brands;
        }

        foreach ($matches[1] as $block) {
            $artId    = $this->xmlTag($block, 'ArticleId');
            $catName  = $this->xmlTag($block, 'CatalogName');
            $name     = $this->xmlTag($block, 'Name');
            $number   = $this->xmlTag($block, 'Number');

            if (!$catName || !$number) continue;

            $key = mb_strtolower($catName) . '|' . mb_strtolower($number);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $catName,
                    'article'     => $article,
                    'article_nr'  => $number,
                    'article_id'  => $artId,
                    'description' => $name ?: '',
                ];
            }
        }
        return array_values($brands);
    }

    // ==================== ПРЕДЛОЖЕНИЯ ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        if (!$this->ensureAuth()) return [];    
        $req = $this->buildSearchRequest($brand, $article);
        if (!$req) return [];
        $resp = $this->execSoap($req['body']);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;

        $articleId = $this->resolveArticleId($brand, $article);
        if (!$articleId) return null;

        $searchCross = $withCrosses ? 1 : 0;

        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<GetPriceId xmlns="http://www.autopiter.ru/">'
            . '<ArticleId>' . $articleId . '</ArticleId>'
            . '<SearchCross>' . $searchCross . '</SearchCross>'
            . '</GetPriceId>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $headers = ['Content-Type: text/xml; charset=utf-8'];
        if ($this->authCookie) {
            $headers[] = 'Cookie: ' . $this->authCookie;
        }

        return [
            'url'     => $this->baseUrl,
            'headers' => $headers,
            'method'  => 'POST',
            'body'    => $xml,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $own = [];    // StoreType 0,2 — на складе Автопитера
        $other = [];  // StoreType 1,3,4,9 — чужие

        if (!preg_match_all('/<PriceSearchModel>(.*?)<\/PriceSearchModel>/s', $responseBody, $matches)) {
            return [];
        }

        foreach ($matches[1] as $block) {
            $detailUid    = $this->xmlTag($block, 'DetailUid');
            $sellerId     = $this->xmlTag($block, 'SellerId');
            $numAvail     = $this->xmlTag($block, 'NumberOfAvailable');
            $minSales     = $this->xmlTag($block, 'MinNumberOfSales');
            $salePrice    = $this->xmlTag($block, 'SalePrice');
            $daysSupply   = $this->xmlTag($block, 'NumberOfDaysSupply');
            $deliveryDate = $this->xmlTag($block, 'DeliveryDate');
            $region       = $this->xmlTag($block, 'Region');
            $storeType    = (int)($this->xmlTag($block, 'StoreType') ?: 3);
            $nameStatus   = $this->xmlTag($block, 'NameStatus');
            $numberChange = $this->xmlTag($block, 'NumberChange');
            $isDimension  = $this->xmlTag($block, 'IsDimension');
            $typeRefusal  = $this->xmlTag($block, 'TypeRefusal');
            $xmlName      = $this->xmlTag($block, 'Name');

            // ═══ ИЗВЛЕКАЕМ РЕАЛЬНЫЙ БРЕНД И АРТИКУЛ ИЗ XML ═══
            $xmlBrand   = $this->xmlTag($block, 'CatalogName');
            $xmlArticle = $this->xmlTag($block, 'Number');

            if (empty($salePrice) || (float)$salePrice <= 0) continue;
            // NumberChange непустой → по документации заказ по этой позиции невозможен
            // (нужно искать по номеру замены отдельным FindCatalog) — не показываем.
            if (!empty($numberChange)) continue;
            // IsDimension=true → "крупногабаритный товар, будет отказ по детали".
            if ($isDimension === 'true' || $isDimension === '1') continue;

            $price = (float)$salePrice;
            $avail = ($numAvail !== null && $numAvail !== '') ? (int)$numAvail : -1;
            $minSalesVal = max(1, (int)($minSales ?: 1));
            $daysVal = (int)(($daysSupply !== null && $daysSupply !== '') ? $daysSupply : 0);
            $isToday   = $this->xmlTag($block, 'IsToday');
            $isExpress = $this->xmlTag($block, 'IsExpress');
            $expressText = $this->xmlTag($block, 'ExpressDeliveryHoursText');

            if ($avail <= 0 && $avail > -10) {
                // -1,-2,-3 — неточное наличие, пропускаем
            }
            if ($avail === 0 || $avail === -10) continue;

            [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($deliveryDate, $daysVal);

            $r = new SearchResultItem();
            $r->source            = $this->getCode();
            // Используем реальные бренд/артикул из XML вместо переданных в запросе
            $r->article           = $xmlArticle ?: $article;
            $r->brand             = $xmlBrand ?: $brand;
            $r->name              = $xmlName ?: trim($r->brand . ' ' . $r->article);
            $r->price              = $price;
            $r->quantity            = max(0, $avail);
            $r->warehouse          = ($region ?: 'Склад') . ($sellerId ? ' (' . $sellerId . ')' : '');
            $r->stockId            = $detailUid ?: '';
            $r->supplierName       = $this->getName();
            $r->isSched            = false;
            $r->multiplicity       = $minSalesVal;
            $r->unit               = 'шт.';
            // TypeRefusal 3 и 4 — возврат невозможен, иначе (в т.ч. если поле отсутствует) возможен.
            $r->returnable         = !in_array((int)$typeRefusal, [3, 4], true);
            $r->deliveryDays       = $deliveryDays;
            $r->deliveryPeriod     = $deliveryPeriod;
            $r->deliveryLabel      = $deliveryLabel;
            $r->deliveryTimeLabel  = $deliveryTimeLabel;
            $r->deliveryToday      = $deliveryToday;
            $r->deliveryDeadline   = $deliveryDeadline;

            $r->raw = [
                'storeType'    => $storeType,
                'sellerId'     => $sellerId,
                'nameStatus'   => $nameStatus,
                'deliveryDays' => $daysVal,
                'deliveryDate' => $deliveryDate,
                'isToday'      => $isToday,
                'isExpress'    => $isExpress,
                'expressText'  => $expressText,
            ];

            if ($storeType === 0 || $storeType === 2) {
                $own[] = $r;
            } else {
                $other[] = $r;
            }
        }

        usort($own, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

        usort($other, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });
        $other = array_slice($other, 0, 10);

        return array_merge($own, $other);
    }

    /**
     * Срок доставки Автопитер. Поверх реальных данных API — осознанный запас
     * под логистику Автопитера (подтверждено): +2 дня к NumberOfDaysSupply
     * (0 или отсутствует -> ровно 2 дня). IsToday/IsExpress/DeliveryDate
     * появились только в новой версии API и намеренно НЕ используются для
     * обхода этого запаса — даже IsToday=true всё равно получает +2 дня,
     * т.к. по договорённости с сайтом реальная доставка клиенту всегда
     * дольше, чем заявляет поставщик.
     *
     * DeliveryDate, если пришла, всё же даёт точное время суток — берём её,
     * сдвигаем на те же 2 дня буфера и получаем "Сегодня/Завтра/дата HH:MM"
     * вместо голого дня; без неё — только день, без времени.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(?string $deliveryDateRaw, int $daysVal): array
    {
        $bufferDays = 2;

        if (!empty($deliveryDateRaw)) {
            $rawTs = strtotime($deliveryDateRaw);
            if ($rawTs) {
                $now           = time();
                $todayStart    = strtotime('today');
                $tomorrowStart = strtotime('tomorrow');
                $ts            = $rawTs + $bufferDays * 86400;

                $tsDay     = strtotime(date('Y-m-d', $ts));
                $days      = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
                $dayLabel  = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $ts));
                $timeLabel = date('H:i', $ts);
                $hours     = max(0, (int)ceil(($ts - $now) / 3600));
                return [$days, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart, null];
            }
        }

        $days     = max(0, $daysVal) + $bufferDays;
        $dayLabel = $days === 0 ? 'Сегодня' : ($days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days")));
        return [$days, $days * 24, $dayLabel, null, $days === 0, null];
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
        if (!$this->ensureAuth()) return $results;
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $brands = $this->searchBrands($query);
        $brands = array_slice($brands, 0, 10);

        foreach ($brands as $br) {
            try {
                $items = $this->searchByBrandArticle($br['brand'], $br['article_nr']);
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

    private function resolveArticleId(string $brand, string $article): ?string
    {
        $brands = $this->searchBrands($article);
        $norm = BrandNormalizer::normalize($brand);

        foreach ($brands as $br) {
            if (BrandNormalizer::normalize($br['brand']) === $norm) {
                return $br['article_id'] ?? null;
            }
        }

        $raw = mb_strtolower(trim($brand));
        foreach ($brands as $br) {
            $b = mb_strtolower(trim($br['brand']));
            if ($b === $raw || mb_stripos($b, $raw) !== false || mb_stripos($raw, $b) !== false) {
                return $br['article_id'] ?? null;
            }
        }

        if (!empty($brands[0]['article_id'])) {
            return $brands[0]['article_id'];
        }

        return null;
    }

    private function execSoap(string $xml, bool $isAuth = false): ?string
    {
        $ch = curl_init($this->baseUrl);
        $headers = ['Content-Type: text/xml; charset=utf-8'];

        if (!$isAuth && $this->authCookie) {
            $headers[] = 'Cookie: ' . $this->authCookie;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) {
                if (stripos($headerLine, 'Set-Cookie:') === 0) {
                    $cookie = trim(substr($headerLine, 12));
                    $cookie = explode(';', $cookie)[0];
                    $this->authCookie = $cookie;
                }
                return strlen($headerLine);
            },
        ]);

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

    private function xmlTag(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/', $xml, $m)) {
            $val = trim($m[1]);
            return $val !== '' ? $val : null;
        }
        return null;
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
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/autopiter_' . date('Y-m-d') . '.log',
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