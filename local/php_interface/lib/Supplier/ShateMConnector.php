<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class ShateMConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $apiUrl;
    private string $apiKey;
    private string $agreementCode;
    private string $deliveryAddressCode;
    private int    $timeout;
    private ?string $token = null;
    private ?int   $tokenExpires = null;
    private array  $locationNames = [];

    public function __construct(array $config = [])
    {
        // Домен .ru (не .by) — подтверждено вживую с ключом заказчика.
        $this->apiUrl             = $config['API_URL']  ?? 'https://api.shate-m.ru/api/v1/';
        $this->apiKey              = $config['API_KEY'] ?? '';
        // Единственный активный договор клиента (GET /customer/agreements,
        // снято вживую) — locationCode SHATE-KAX, поддерживает и доставку, и
        // самовывоз. AGREEMENT_CODE обязателен для оформления заказа
        // (OrderCreateByPrices.agreementCode), у поиска — необязателен.
        $this->agreementCode       = $config['AGREEMENT_CODE'] ?? 'RSAGR56329';
        // Единственный адрес доставки клиента (GET /delivery/addresses,
        // снято вживую) — Елабуга. Если не задан — заказ уйдёт на самовывоз
        // (так документирован API при пустом deliveryInfo).
        $this->deliveryAddressCode = $config['DELIVERY_ADDRESS_CODE'] ?? 'Д1';
        $this->timeout             = $config['TIMEOUT']  ?? 12;
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

    // ==================== АВТОРИЗАЦИЯ ====================

    private function ensureToken(): string
    {
        if ($this->token && $this->tokenExpires && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            // Подтверждено вживую: поле называется "ApiKey" (с заглавной),
            // а не "apiKey" — так задано в OpenAPI-схеме auth/loginbyapikey.
            CURLOPT_URL            => $this->apiUrl . 'auth/loginbyapikey',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['ApiKey' => $this->apiKey]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $this->log('Auth failed: HTTP ' . $httpCode);
            $this->token = '';
            return '';
        }
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
        return [
            'url'     => $this->apiUrl . 'articles/search?' . http_build_query(['searchString' => $article]),
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
        if (isset($data['article'])) $data = [$data];
        foreach ($data as $row) {
            $art = $row['article'] ?? $row;
            $b  = $art['tradeMarkName'] ?? '';
            $n  = $art['code'] ?? '';
            $nm = $art['name'] ?? '';
            if (!$b || !$n) continue;
            $key = mb_strtolower($b) . '|' . mb_strtolower($n);
            if (!isset($brands[$key])) {
                $brands[$key] = ['brand' => $b, 'article' => $n, 'article_nr' => $n, 'description' => $nm];
            }
        }
        return array_values($brands);
    }

    // ==================== ЭТАП 2: ПРЕДЛОЖЕНИЯ ====================

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        $token = $this->ensureToken();
        if (!$token) return null;
        $params = ['searchString' => $article];
        if ($brand !== '') {
            $params['tradeMarkNames'] = $brand;
        }
        return [
            'url'     => $this->apiUrl . 'articles/search?' . http_build_query($params),
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $token = $this->ensureToken();
        if (!$token) return $results;

        $data = json_decode($responseBody, true);
        if (empty($data)) return $results;
        if (isset($data['article'])) $data = [$data];

        $normBrand = BrandNormalizer::normalize($brand);
        $articleIds = [];
        $articleInfo = [];
        foreach ($data as $row) {
            $art = $row['article'] ?? $row;
            $artBrand = $art['tradeMarkName'] ?? '';
            if ($artBrand === '') continue;
            if ($normBrand !== '' && BrandNormalizer::normalize($artBrand) !== $normBrand) continue;
            $id = $art['id'] ?? 0;
            if ($id > 0) {
                $articleIds[] = $id;
                $articleInfo[$id] = $art;
            }
        }
        if (empty($articleIds)) return $results;

        $pricesData = $this->getPrices($articleIds, $token);
        foreach ($pricesData as $pe) {
            $art         = $pe['article'] ?? [];
            $prices      = $pe['prices'] ?? [];
            $artId       = $art['id'] ?? 0;
            $info        = $articleInfo[$artId] ?? $art;
            $unitMeasure = $info['unitOfMeasure'] ?? $art['unitOfMeasure'] ?? 'шт.';

            foreach ($prices as $p) {
                $result = $this->buildSearchResultItem($p, $info, $art, $brand, $article, $token, $unitMeasure);
                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        return $this->deduplicateAndSort($results);
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

    // ==================== ПОЛНЫЙ ПОИСК ====================

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) return $results;

        $token = $this->ensureToken();
        if (!$token) return $results;

        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;

        $req = $this->buildBrandsRequest($query);
        if (!$req) return $results;
        $resp = $this->execCurl($req);
        if ($resp === null) return $results;

        $articlesData = json_decode($resp, true);
        if (empty($articlesData)) return $results;
        if (isset($articlesData['article'])) $articlesData = [$articlesData];
        $articlesData = array_slice($articlesData, 0, 15);

        $articleIds = [];
        $articleInfo = [];
        foreach ($articlesData as $row) {
            $art = $row['article'] ?? $row;
            $id = $art['id'] ?? 0;
            if ($id > 0) {
                $articleIds[] = $id;
                $articleInfo[$id] = $art;
            }
        }
        if (empty($articleIds)) return $results;

        foreach (array_chunk($articleIds, 10) as $batch) {
            foreach ($this->getPrices($batch, $token) as $pe) {
                $art         = $pe['article'] ?? [];
                $prices      = $pe['prices'] ?? [];
                $artId       = $art['id'] ?? 0;
                $info        = $articleInfo[$artId] ?? $art;
                $unitMeasure = $info['unitOfMeasure'] ?? $art['unitOfMeasure'] ?? 'шт.';

                foreach ($prices as $price) {
                    $result = $this->buildSearchResultItem($price, $info, $art, '', '', $token, $unitMeasure);
                    if ($result !== null) {
                        $results[] = $result;
                    }
                }
            }
        }

        return array_slice($this->deduplicateAndSort($results), 0, 30);
    }

    // ==================== ПОСТРОЕНИЕ SearchResultItem ====================

    private function buildSearchResultItem(
        array $priceData,
        array $articleInfo,
        array $articleData,
        string $brand,
        string $article,
        string $token,
        string $unitMeasure
    ): ?SearchResultItem {
        $locCode = $priceData['locationCode'] ?? '';
        $locName = $this->getLocationName($locCode, $token);

        // --- ЦЕНА ---
        $priceValue = (float)($priceData['price']['value'] ?? 0);
        $currency   = (string)($priceData['price']['currencyCode'] ?? 'RUB');

        // --- КОЛИЧЕСТВО + КРАТНОСТЬ ---
        $qtyAvailable = (int)($priceData['quantity']['available'] ?? 0);
        $multiplicity = max(1, (int)($priceData['quantity']['multiplicity'] ?? 1));
        $minQty       = max(1, (int)($priceData['quantity']['minimum'] ?? 1));
        $maxQty       = isset($priceData['quantity']['maximum']) && $priceData['quantity']['maximum'] !== null
            ? (int)$priceData['quantity']['maximum'] : null;

        // --- ВОЗВРАТ ---
        $addInfo      = $priceData['addInfo'] ?? [];
        $isReturnable = (bool)($addInfo['isReturnAllowed'] ?? true);
        $warningText  = (string)($addInfo['warningText'] ?? '');
        $isSale       = (bool)($addInfo['isSale'] ?? false);

        // --- СРОК ДОСТАВКИ (только deliveryDateTime, без самовывоза) ---
        $deliveryDT   = $priceData['deliveryDateTimes'][0]['deliveryDateTime'] ?? null;

        // --- СТАТИСТИКА ---
        $supplyRatingRaw = $priceData['supplyProbability']['rating'] ?? null;
        $supplyRating = is_numeric($supplyRatingRaw) ? max(0, min(100, (int)round((float)$supplyRatingRaw))) : null;

        // --- ИДЕНТИФИКАТОРЫ ДЛЯ КОРЗИНЫ/ЗАКАЗА ---
        $priceId   = (string)($priceData['id'] ?? '');
        $hash      = (int)($priceData['hash'] ?? 0);
        $articleId = (int)($priceData['articleId'] ?? $articleInfo['id'] ?? $articleData['id'] ?? 0);

        $r = new SearchResultItem();
        $r->source       = $this->getCode();
        $r->article      = (string)($articleInfo['code'] ?? $articleData['code'] ?? $article);
        $r->brand        = (string)($articleInfo['tradeMarkName'] ?? $articleData['tradeMarkName'] ?? $brand);
        $r->name         = (string)($articleInfo['name'] ?? $articleData['name'] ?? '');
        $r->price        = $priceValue;
        $r->currency     = $currency;
        $r->quantity     = $qtyAvailable;
        $r->multiplicity = $multiplicity;
        $r->unit         = $unitMeasure;
        $r->warehouse    = $locName;
        $r->stockId      = $locCode;
        $r->supplierName = $this->getName();
        $r->isSched      = ($qtyAvailable <= 0);
        $r->returnable   = $isReturnable;
        $r->reliabilityPercent = $supplyRating;

        // --- СРОКИ ---
        [$r->deliveryDays, $r->deliveryPeriod, $r->deliveryLabel, $r->deliveryTimeLabel, $r->deliveryToday] = $this->resolveDelivery($deliveryDT);

        $r->raw = [
            'priceId'           => $priceId,
            'hash'              => $hash,
            'articleId'         => $articleId,
            'locationCode'      => $locCode,
            'locationCodeReal'  => $priceData['locationCodeReal'] ?? '',
            'agreementCode'     => $priceData['agreementCode'] ?? '',
            'type'              => $priceData['type'] ?? '',
            'isRepaired'        => (bool)($priceData['isRepaired'] ?? false),
            'currencyCode'      => $currency,
            'importAllowance'   => (int)($priceData['price']['importAllowance'] ?? 0),
            'priceMax'          => (float)($priceData['price']['priceMax'] ?? 0),
            'valueForCart'      => (float)($priceData['price']['valueForCart'] ?? 0),
            'valueWithMarginForCart' => (float)($priceData['price']['valueWithMarginForCart'] ?? 0),
            'availableType'     => $priceData['quantity']['availableType'] ?? 'Equal',
            'minQty'            => $minQty,
            'maxQty'            => $maxQty,
            'supplyRating'      => $supplyRating,
            'isReturnAllowed'   => $isReturnable,
            'warningText'       => $warningText,
            'isSale'            => $isSale,
            'deliveryDateTime'  => $deliveryDT,
            'isImport'          => (bool)($priceData['isImport'] ?? false),
            'isFree'            => (bool)($priceData['isFree'] ?? false),
            'priority'          => (int)($priceData['priority'] ?? 0),
        ];

        // Для оформления заказа (см. SupplierOrderable::placeOrder()) —
        // priceId нужен для POST /orders/bypriceitems, locationCode — т.к.
        // "Все строки заказа должны быть из одного locationCode, иначе заказ
        // не будет оформлен" (документация), articleId — для сопоставления
        // строк ответа с basket_item_id (ответ не эхует обратно priceId).
        $r->orderMeta = [
            'price_id'      => $priceId,
            'article_id'    => $articleId,
            'location_code' => $locCode,
        ];

        if ($r->price <= 0 && $r->quantity <= 0) {
            return null;
        }

        return $r;
    }

    /**
     * Срок доставки ШАТЕ-М. API отдаёт одну конкретную дату-время (UTC,
     * ISO 8601, напр. "2026-09-12T13:00:00Z"), а не диапазон "от-до" — тот
     * же случай, что у Армтека (DLVDT), формат вывода единый по всему сайту:
     * день ("Сегодня"/"Завтра"/"дд.мм") + время "ЧЧ:ММ" отдельным бейджем
     * (см. search/index.php::dRange() — без deliveryLabel фронт падает в
     * уродливый фолбэк "N дн.", что и было причиной этой правки).
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool}
     */
    private function resolveDelivery(?string $deliveryDT): array
    {
        if (!$deliveryDT) return [null, null, null, null, false];
        $ts = strtotime($deliveryDT);
        if (!$ts) return [null, null, null, null, false];

        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $tsDay         = strtotime(date('Y-m-d', $ts));
        $days          = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
        $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $ts));
        $timeLabel     = date('H:i', $ts);
        $hours         = max(0, (int)ceil(($ts - $now) / 3600));

        return [$days, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart];
    }

    private function deduplicateAndSort(array $results): array
    {
        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $key = $item->getDedupeKey() . '|' . $item->warehouse;
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
        return $unique;
    }

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    public function placeOrder(array $items, bool $test = false): array
    {
        $token = $this->ensureToken();
        if (!$token) {
            $this->log('placeOrder: не удалось получить токен');
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'auth_failed'];
        }

        // "Все строки заказа должны быть из одного locationCode, иначе заказ
        // не будет оформлен" — группируем позиции по locationCode и делаем
        // отдельный вызов /orders/bypriceitems на каждую группу.
        $groups = [];
        $skipped = 0;
        foreach ($items as $item) {
            $priceId  = trim((string)($item['order_meta']['price_id'] ?? ''));
            $artId    = (int)($item['order_meta']['article_id'] ?? 0);
            $locCode  = trim((string)($item['order_meta']['location_code'] ?? ''));
            $qty      = (int)($item['quantity'] ?? 0);
            $basketItemId = (int)($item['basket_item_id'] ?? 0);
            if ($priceId === '' || $locCode === '' || $qty <= 0 || $basketItemId <= 0) { $skipped++; continue; }

            $groups[$locCode]['items'][] = [
                'priceId' => $priceId,
                'quantity' => $qty,
                'comment' => mb_substr((string)($item['comment'] ?? ''), 0, 250),
            ];
            $groups[$locCode]['queue'][$artId][] = $basketItemId;
            $groups[$locCode]['comment'] = mb_substr((string)($item['comment'] ?? ''), 0, 250);
        }

        if (empty($groups)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет price_id/location_code в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $itemReferences = [];
        $rawResponses = [];
        $anyHttpCode = null;
        $anySuccess = false;
        $errors = [];

        foreach ($groups as $locCode => $group) {
            $body = json_encode([
                'agreementCode' => $this->agreementCode,
                'comment'       => $group['comment'] ?? '',
                'deliveryInfo'  => $this->deliveryAddressCode !== '' ? [
                    'deliveryAddressCode' => $this->deliveryAddressCode,
                ] : null,
                // Обязательные флаги согласия у API — это B2B-интеграция по
                // уже действующему договору с ШАТЕ-М, а не форма для
                // конечного покупателя, поэтому подтверждаем программно.
                'agreeWithTermsOfDelivery' => true,
                'agreeWithPersonalDataProcessingPolicyAndUserAgreement' => true,
                'priceItems' => $group['items'],
            ], JSON_UNESCAPED_UNICODE);

            $this->log("placeOrder: location={$locCode} items=" . count($group['items']) . " body={$body}");

            $ch = curl_init($this->apiUrl . 'orders/bypriceitems');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
            ]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err      = curl_error($ch);
            curl_close($ch);

            $this->log("placeOrder: location={$locCode} response http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));
            $anyHttpCode = $httpCode ?: $anyHttpCode;

            $decoded = json_decode((string)$resp, true);
            $rawResponses[$locCode] = $decoded;

            if ($err || $httpCode !== 200 || !is_array($decoded)) {
                $errors[] = is_array($decoded) && !empty($decoded['errors'])
                    ? implode(',', $decoded['errors']) : ($err ?: 'http_' . $httpCode);
                continue;
            }

            // Сопоставление строк ответа с basket_item_id по articleId (ответ
            // не эхует priceId — только article.id/code/tradeMarkName) —
            // FIFO-очередь по articleId, тот же принцип, что и у других
            // коннекторов (Росско/Авторусь/Иксора/Автопитер).
            foreach ((array)($decoded['orderItems'] ?? []) as $oi) {
                $lineArtId = (int)($oi['article']['id'] ?? 0);
                $lineId    = (int)($oi['id'] ?? 0);
                if ($lineId <= 0 || empty($group['queue'][$lineArtId])) continue;
                $basketItemId = array_shift($group['queue'][$lineArtId]);
                if ($basketItemId > 0) {
                    $itemReferences[$basketItemId] = (string)$lineId;
                    $anySuccess = true;
                }
            }
        }

        return [
            'http_code'       => $anyHttpCode,
            'success'         => $anySuccess,
            'raw'             => $rawResponses,
            'error'           => $anySuccess ? null : (implode('; ', $errors) ?: 'order_rejected'),
            'item_references' => $itemReferences,
        ];
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /** $reference — orderItem.id (полученный из placeOrder()::item_references). */
    public function fetchOrderStatusByReference(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '' || !ctype_digit($reference)) return [];

        $token = $this->ensureToken();
        if (!$token) return [];

        $resp = $this->execCurl([
            'url'     => $this->apiUrl . "orderitems/{$reference}/statuseshistory",
            'headers' => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ]);
        if ($resp === null) return [];

        $rows = json_decode($resp, true);
        if (!is_array($rows) || empty($rows)) return [];

        // Берём последнюю по дате запись истории (текущий статус).
        usort($rows, fn($a, $b) => strtotime((string)($a['dateTime'] ?? '')) <=> strtotime((string)($b['dateTime'] ?? '')));
        $last = end($rows);
        $statusCode = (int)($last['statusCode'] ?? -1);
        $info = self::STATUS_CODES[$statusCode] ?? null;

        return [[
            'order_number'    => null,
            'state_id'        => (string)$statusCode,
            'state_text'      => $info['name'] ?? ('code ' . $statusCode),
            'stage'           => $info['stage'] ?? 'ordered',
            'expected_date'   => null,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $info['description'] ?? null,
            'raw'             => $last,
        ]];
    }

    /**
     * Полный официальный словарь statusCode (GET /orderitemstatuscodes,
     * снят вживую 2026-09-11) — name/isFinal как есть от ШАТЕ-М, stage —
     * наша классификация под общий словарь (ordered/in_transit/ready/refused).
     */
    private const STATUS_CODES = [
        0   => ['name' => 'Создано',                              'stage' => 'ordered'],
        1   => ['name' => 'Ожидает обработки',                    'stage' => 'ordered'],
        20  => ['name' => 'Ожидает предоплаты',                   'stage' => 'ordered'],
        21  => ['name' => 'Заказ ожидает завершения транзакции',  'stage' => 'ordered'],
        25  => ['name' => 'Ожидает оплаты (Эквайринг)',           'stage' => 'ordered'],
        26  => ['name' => 'Оплата подтверждена',                  'stage' => 'ordered'],
        27  => ['name' => 'Оплата не подтверждена (Эквайринг)',   'stage' => 'refused'],
        30  => ['name' => 'В работе',                             'stage' => 'ordered'],
        35  => ['name' => 'В перемещении',                        'stage' => 'in_transit'],
        40  => ['name' => 'Заказ у поставщика',                   'stage' => 'ordered'],
        45  => ['name' => 'Частично подтвержден поставщиком',     'stage' => 'in_transit'],
        50  => ['name' => 'Подтвержден поставщиком',              'stage' => 'in_transit'],
        60  => ['name' => 'Отказ поставщика',                     'stage' => 'refused'],
        70  => ['name' => 'Отправлено на центральный склад ШМ+',  'stage' => 'in_transit'],
        80  => ['name' => 'Не удалось зарезервировать позицию',   'stage' => 'ordered'],
        90  => ['name' => 'Частичное резервирование',             'stage' => 'in_transit'],
        100 => ['name' => 'Готов к отгрузке',                     'stage' => 'in_transit'],
        110 => ['name' => 'Минимальная сумма доставки',           'stage' => 'ordered'],
        120 => ['name' => 'Собран',                                'stage' => 'in_transit'],
        125 => ['name' => 'Отгружено',                             'stage' => 'in_transit'],
        130 => ['name' => 'В пути',                                'stage' => 'in_transit'],
        140 => ['name' => 'Доставлено',                            'stage' => 'ready'],
        150 => ['name' => 'Выдан',                                 'stage' => 'ready'],
        160 => ['name' => 'Удален',                                'stage' => 'refused'],
        170 => ['name' => 'Ошибка',                                'stage' => 'ordered'],
        180 => ['name' => 'Отменён',                               'stage' => 'refused'],
    ];

    // ==================== HTTP ====================

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
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
        if ($httpCode !== 200 || $resp === false) {
            $this->log("HTTP {$httpCode} err={$err}");
            return null;
        }
        return $resp;
    }

    private function getPrices(array $articleIds, string $token): array
    {
        if (empty($articleIds)) return [];
        $body = json_encode(array_map(fn($id) => ['articleId' => $id], $articleIds));
        // Подтверждено вживую: без agreementCode/deliveryAddressCode этот
        // эндпоинт отдаёт пустой массив для данного аккаунта, даже по ходовым
        // артикулам с реальным наличием — необходимые query-параметры, а не
        // опциональные, как можно было понять из документации.
        $query = http_build_query([
            'agreementCode' => $this->agreementCode,
            'deliveryAddressCode' => $this->deliveryAddressCode,
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl . 'prices/search/with_article_info?' . $query,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            $this->log("getPrices HTTP {$httpCode}");
            return [];
        }
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
                CURLOPT_URL            => $this->apiUrl . 'locations',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
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

    public function supportsCrossSearch(): bool { return false; }
    public function getSearchTimeout(): int { return 8; }

    // ==================== УТИЛИТЫ ====================

    private function generateWarehouseCode(string $name): string
    {
        static $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu',
            'я'=>'ya',' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>'',
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
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $dir = $root . '/upload/logs';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents(
            $dir . '/shatem_' . date('Y-m-d') . '.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}
