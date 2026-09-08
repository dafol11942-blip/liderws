<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class AutopiterConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
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

            // Для оформления заказа (см. SupplierOrderable::placeOrder()) —
            // DetailUid однозначно определяет предложение и передаётся как есть
            // в ItemAddCartModel.DetailUid у MakeOrderByItems.
            $r->orderMeta = ['detail_uid' => $detailUid ?: ''];

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

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    public function placeOrder(array $items, bool $test = false): array
    {
        // Автопитер не документирует флаг тестового заказа для MakeOrderByItems —
        // как и у Иксоры/Авторуси/Росско, в тестовом режиме запрос не
        // отправляем вообще, безопаснее пропустить, чем случайно оформить
        // реальный заказ.
        if ($test) {
            $this->log('placeOrder: тестовый режим не поддерживается API Автопитера (флага тестового заказа нет) — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        if (!$this->ensureAuth()) {
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'auth_failed'];
        }

        $models = [];
        $queueByUid = [];
        $skipped = 0;
        foreach ($items as $item) {
            $detailUid = trim((string)($item['order_meta']['detail_uid'] ?? ''));
            $qty   = (int)($item['quantity'] ?? 0);
            $price = (float)($item['price_base'] ?? 0);
            if ($detailUid === '' || $qty <= 0 || $price <= 0) { $skipped++; continue; }

            $models[] = [
                'DetailUid' => $detailUid,
                'Comment'   => (string)($item['comment'] ?? ''),
                'SalePrice' => number_format($price, 2, '.', ''),
                'Quantity'  => $qty,
            ];
            $queueByUid[$detailUid][] = (int)($item['basket_item_id'] ?? 0);
        }

        if (empty($models)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет detail_uid в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $body = $this->buildMakeOrderByItemsXml($models);

        $this->log('placeOrder: request items=' . count($models) . ' skipped=' . $skipped . ' body=' . $body);

        $resp = $this->execSoap($body);

        $this->log('placeOrder: response body=' . substr((string)$resp, 0, 4000));

        if ($resp === null) {
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'http_error'];
        }

        $orderNumber = $this->xmlTag($resp, 'OrderNumber');

        if (!preg_match_all('/<ResponseCodeItemCart>(.*?)<\/ResponseCodeItemCart>/s', $resp, $blockMatches)) {
            // Не нашли ни одной позиции ответа в ожидаемом формате — не считаем
            // это успехом, даже если OrderNumber пришёл: без сопоставления
            // позиций с DetailUid мы не можем быть уверены, что именно
            // отправлено поставщику.
            return [
                'http_code' => 200,
                'success'   => false,
                'raw'       => ['_raw_text' => $resp, 'orderNumber' => $orderNumber],
                'error'     => 'unparsable_response',
            ];
        }

        $itemReferences = [];
        $itemsRaw = [];
        foreach ($blockMatches[1] as $block) {
            // Item — вложенная модель детали внутри ResponseCodeItemCart, если
            // её нет отдельным тегом (плоская структура), берём поля прямо из
            // блока целиком — оба варианта покрываются одним xmlTag() поиском.
            $inner = $block;
            if (preg_match('/<Item>(.*?)<\/Item>/s', $block, $im)) {
                $inner = $im[1];
            }
            $detailUid = $this->xmlTag($inner, 'DetailUid');
            if (!$detailUid) continue;

            // Code — список кодов результата (ArrayOfInt). Подтверждено вживую
            // (заказ №191): реальная обёртка — <Code><ResponseCode>0</ResponseCode></Code>,
            // может содержать несколько <ResponseCode> при нескольких кодах сразу —
            // берём все числа внутри блока <Code>, а не только сразу после тега.
            $codes = [];
            if (preg_match('/<Code>(.*?)<\/Code>/s', $block, $codeBlock)) {
                preg_match_all('/-?\d+/', $codeBlock[1], $codeMatches);
                $codes = array_map('intval', $codeMatches[0] ?? []);
            }
            $itemsRaw[] = ['detailUid' => $detailUid, 'codes' => $codes];

            // 0 — позиция удачно добавлена в корзину (см. общий словарь
            // ResponseCode в документации InsertToBasket/MakeOrderByItems).
            if (!in_array(0, $codes, true)) continue;

            if (!empty($queueByUid[$detailUid])) {
                $basketItemId = array_shift($queueByUid[$detailUid]);
                if ($basketItemId > 0) {
                    // OrderNumber общий на весь вызов, DetailUid однозначно
                    // определяет позицию внутри него (как orderNumber:positionId
                    // у Авторуси) — составной reference нужен для последующего
                    // GetFullInvoiceOrder(OrderNumber), который возвращает
                    // список позиций именно по DetailUid.
                    $itemReferences[$basketItemId] = $orderNumber . ':' . $detailUid;
                }
            }
        }

        // Успех — только если пришёл OrderNumber И хотя бы одна позиция
        // подтверждена кодом 0, а не сам факт HTTP 200 (см. общий принцип у
        // остальных коннекторов — top-level флаг не гарантирует реальный успех).
        $success = !empty($orderNumber) && !empty($itemReferences);

        return [
            'http_code'       => 200,
            'success'         => $success,
            'raw'             => ['orderNumber' => $orderNumber, 'items' => $itemsRaw],
            'error'           => $success ? null : 'order_rejected',
            'item_references' => $itemReferences,
        ];
    }

    private function buildMakeOrderByItemsXml(array $models): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $itemsXml = '';
        foreach ($models as $m) {
            $itemsXml .= '<ItemAddCartModel>'
                . '<DetailUid>' . $esc($m['DetailUid']) . '</DetailUid>'
                . '<Comment>' . $esc($m['Comment']) . '</Comment>'
                . '<SalePrice>' . $esc($m['SalePrice']) . '</SalePrice>'
                . '<Quantity>' . (int)$m['Quantity'] . '</Quantity>'
                . '</ItemAddCartModel>';
        }

        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<MakeOrderByItems xmlns="http://www.autopiter.ru/">'
            . '<Items>' . $itemsXml . '</Items>'
            . '</MakeOrderByItems>'
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference — составной "{OrderNumber}:{DetailUid}" (см.
     * placeOrder()::item_references) — GetFullInvoiceOrder запрашивается по
     * номеру счёта, DetailUid однозначно определяет конкретную позицию внутри
     * него (один OrderNumber может покрывать несколько наших позиций).
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderNumber, $detailUid] = explode(':', $reference, 2);
        $orderNumber = trim($orderNumber);
        $detailUid   = trim($detailUid);
        if ($orderNumber === '' || $detailUid === '') return [];

        if (!$this->ensureAuth()) return [];

        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<GetFullInvoiceOrder xmlns="http://www.autopiter.ru/">'
            . '<OrderNumber>' . htmlspecialchars($orderNumber, ENT_XML1) . '</OrderNumber>'
            . '</GetFullInvoiceOrder>'
            . '</soap:Body>'
            . '</soap:Envelope>';

        $resp = $this->execSoap($xml);

        $this->log("fetchOrderStatusByReference({$reference}): response body=" . substr((string)$resp, 0, 4000));

        if ($resp === null) return [];

        if (!preg_match_all('/<OrderInformationItemModel>(.*?)<\/OrderInformationItemModel>/s', $resp, $matches)) {
            return [];
        }

        $position = null;
        foreach ($matches[1] as $block) {
            if ($this->xmlTag($block, 'DetailUid') === $detailUid) { $position = $block; break; }
        }
        // Позицию по DetailUid не нашли — как и у Авторуси, безопаснее вернуть
        // первую позицию заказа, чем молчать.
        if ($position === null) $position = $matches[1][0] ?? null;
        if ($position === null) return [];

        $statusBlock = $position;
        if (preg_match('/<Status>(.*?)<\/Status>/s', $position, $sm)) {
            $statusBlock = $sm[1];
        }
        $statusId   = $this->xmlTag($statusBlock, 'Id');
        $statusText = $this->xmlTag($statusBlock, 'Name');
        $deliveryDate = $this->xmlTag($position, 'DeliveryDate');
        $comment      = $this->xmlTag($position, 'Comment');

        return [[
            'order_number'    => $orderNumber,
            'state_id'        => $statusId,
            'state_text'      => $statusText ?: ($statusId !== null ? (self::STATUS_LABELS[(int)$statusId] ?? null) : null),
            'stage'           => $this->normalizeStage($statusId !== null ? (int)$statusId : null),
            'expected_date'   => $deliveryDate,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $comment,
            'raw'             => ['statusId' => $statusId, 'statusText' => $statusText],
        ]];
    }

    // Основные статусы заказа (Status.Id) — из официальной документации
    // Автопитера (GetFullInvoiceOrder), список помечен как неполный ("Список
    // основных статусов"), поэтому для незадокументированных id — дефолт
    // 'ordered', а не догадка.
    private const STATUS_STAGE_MAP = [
        11   => 'ready',    // Выдано
        9    => 'refused',  // Отказ
        149  => 'refused',  // Возврат невозможен
        128  => 'ready',    // Зачтено клиенту (успешный возврат — заказ закрыт)
        677  => 'in_transit', // Отгружено([город])
        1309 => 'in_transit', // Отправлено в г.[город]
        1546 => 'in_transit', // Поступило в г.[город] (ещё не выдано получателю)
        1858 => 'ready',    // Выдан получателю
        1859 => 'refused',  // Возврат в Autopiter.ru (клиент не забрал)
        1919 => 'in_transit', // Транзит в г.[город]
    ];

    private const STATUS_LABELS = [
        11   => 'Выдано',
        9    => 'Отказ',
        149  => 'Возврат невозможен',
        128  => 'Зачтено клиенту',
        677  => 'Отгружено',
        1309 => 'Отправлено',
        1546 => 'Поступило',
        1858 => 'Выдан получателю',
        1859 => 'Возврат в Autopiter.ru',
        1919 => 'Транзит',
    ];

    private function normalizeStage(?int $statusId): string
    {
        if ($statusId === null) return 'ordered';
        return self::STATUS_STAGE_MAP[$statusId] ?? 'ordered';
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