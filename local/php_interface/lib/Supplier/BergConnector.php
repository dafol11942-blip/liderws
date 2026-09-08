<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class BergConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $apiKey;
    private int $timeout;
    private string $baseUrl;
    private ?int $addressId = null;

    public function __construct(array $config = [])
    {
        $this->apiKey  = $config['API_KEY'] ?? '';
        $this->timeout = $config['TIMEOUT'] ?? 7;
        $this->baseUrl   = $config['BASE_URL']   ?? 'https://api.berg.ru/v1.0';
        $this->addressId = (int)($config['ADDRESS_ID'] ?? 0) ?: null;
    }

    public function getCode(): string       { return 'berg'; }
    public function getName(): string       { return 'BERG'; }
    public function getWarehousePrefix(): string { return 'brg'; }

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
        $url = rtrim($this->baseUrl, '/') . '/ordering/get_stock.json';
        $body = ['items' => [['resource_article' => $article]]];
        if ($this->addressId) $body['address_id'] = $this->addressId;
        $json = json_encode($body);
        return [
            'url'     => $url,
            'headers' => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $json,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data['resources'])) return $brands;

        foreach ($data['resources'] as $res) {
            $b  = $res['brand']['name'] ?? '';
            $n  = $res['article'] ?? '';
            $nm = $res['name'] ?? '';
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
        $req = $this->buildSearchRequest($brand, $article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;
        $url = rtrim($this->baseUrl, '/') . '/ordering/get_stock.json';
        $body = [
            'items' => [[
                'resource_article' => $article,
                'brand_name'       => $brand,
            ]],
            'warehouse_types' => [1, 2, 3],  // все склады (свои + чужие), разделим в parseSearchResponse
        ];
        if ($this->addressId) {
            $body['address_id'] = $this->addressId;
        }
        // Включаем аналоги, если запрошено
        if ($withCrosses) {
            $body['analogs'] = 1;
        }
        $json = json_encode($body);
        return [
            'url'     => $url,
            'headers' => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $json,
        ];
    }

    /**
     * Парсит ответ get_stock.
     * НЕ фильтрует по brand — при withCrosses (analogs=1) Berg вернёт разные бренды.
     * Разделение exact/analog делает Stage2 по groupKey.
     */
    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $own = [];    // type=1,2 — свои склады БЕРГ
        $other = [];  // type=3 — чужие
        $data = json_decode($responseBody, true);
        if (empty($data['resources'])) return [];

        foreach ($data['resources'] as $res) {
            foreach ($res['offers'] ?? [] as $offer) {
                $r = $this->buildResultItem($res, $offer);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                if ($r->isSched) continue;

                $whType = (int)($offer['warehouse']['type'] ?? 3);
                if ($whType === 1 || $whType === 2) {
                    $own[] = $r;
                } else {
                    $other[] = $r;
                }
            }
        }

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

        $data = $this->apiPost('ordering/get_stock', [['resource_article' => $query]]);
        if (empty($data['resources'])) return $results;

        foreach ($data['resources'] as $res) {
            foreach ($res['offers'] ?? [] as $offer) {
                $r = $this->buildResultItem($res, $offer);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }

        $seen = []; $unique = [];
        foreach ($results as $item) {
            $key = $item->stockId ?: $item->getDedupeKey();
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $item; }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return array_slice($unique, 0, 30);
    }

    // ==================== НОВЫЕ МЕТОДЫ ====================

    public function supportsCrossSearch(): bool
    {
        // Berg API поддерживает "analogs":1
        return true;
    }

    public function getSearchTimeout(): int
    {
        return 7;
    }

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    public function placeOrder(array $items, bool $test = false): array
    {
        $orderItems = [];
        $basketItemIdBySequence = [];
        $skipped = 0;
        $seq = 0;
        foreach ($items as $item) {
            $resourceId  = $item['order_meta']['resource_id']  ?? null;
            $warehouseId = $item['order_meta']['warehouse_id'] ?? null;
            $qty         = (int)($item['quantity'] ?? 0);
            if (!$resourceId || !$warehouseId || $qty <= 0) { $skipped++; continue; }

            $seq++;
            $orderItem = [
                'resource_id'  => (int)$resourceId,
                'warehouse_id' => (int)$warehouseId,
                'quantity'     => $qty,
            ];
            if (!empty($item['comment'])) $orderItem['comment'] = (string)$item['comment'];
            $priceBase = (float)($item['price_base'] ?? 0);
            if ($priceBase > 0) $orderItem['max_price'] = $priceBase;
            $orderItems[] = $orderItem;
            $basketItemIdBySequence[$seq] = (int)($item['basket_item_id'] ?? 0);
        }

        if (empty($orderItems)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет resource_id/warehouse_id в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        // Дата/время отгрузки (dispatch_at/dispatch_time) — общие на весь заказ
        // у Берга (не на позицию), поэтому берём САМОЕ ПОЗДНЕЕ требуемое окно
        // среди всех позиций: товар, готовый к более ранней волне, гарантированно
        // готов и к более поздней/будущей — обратное невозможно.
        $dispatchTs = null; $dispatchDate = null; $dispatchTimeFlag = 1;
        foreach ($items as $item) {
            $w = $this->resolveDispatchWindow((array)($item['order_meta'] ?? []));
            $wTs = strtotime($w['date']) + ($w['time_flag'] === 2 ? 1 : 0);
            if ($dispatchTs === null || $wTs > $dispatchTs) {
                $dispatchTs = $wTs; $dispatchDate = $w['date']; $dispatchTimeFlag = $w['time_flag'];
            }
        }

        $orderComment = (string)($items[array_key_first($items)]['comment'] ?? '');

        // Наш orderId — числовой префикс до "_" в reference позиции (см.
        // dispatchSupplierOrders(): "{orderId}_{basketItemId}") — передаём как
        // order[reference] для защиты от дублей при повторной отправке (см.
        // документацию: "невозможно сохранить заказ с таким же reference").
        $ourOrderRef = null;
        $firstRef = (string)($items[array_key_first($items)]['reference'] ?? '');
        if (preg_match('/^(\d+)_/', $firstRef, $m)) $ourOrderRef = (int)$m[1];

        $order = [
            'is_test'       => $test ? 1 : 0,
            'dispatch_type' => 3,
            'dispatch_at'   => $dispatchDate,
            'dispatch_time' => $dispatchTimeFlag,
            'comment'       => $orderComment,
            'items'         => $orderItems,
        ];
        if ($this->addressId)     $order['shipment_address_id'] = $this->addressId;
        if ($ourOrderRef !== null) $order['reference'] = $ourOrderRef;

        // force=1 — как у ПартКома по духу: позиция с неверным количеством/ценой
        // (max_price) пропускается и уходит в warnings, а не блокирует весь заказ.
        $body = json_encode(['force' => 1, 'order' => $order], JSON_UNESCAPED_UNICODE);

        $this->log('placeOrder: request test=' . ($test ? 1 : 0) . ' items=' . count($orderItems) . ' skipped=' . $skipped
            . ' dispatch_at=' . $dispatchDate . ' dispatch_time=' . $dispatchTimeFlag . ' body=' . $body);

        $ch = curl_init(rtrim($this->baseUrl, '/') . '/ordering/place_order.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            // Заведомо короче, чем у остальных поставщиков (20с) — синхронный
            // вызов держит веб-воркер на этом хостинге (ограниченный пул
            // Apache/mod_fcgid, см. инцидент с 502 при заказе), 20-25с одного
            // повисшего запроса были заметны на всём сайте.
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log('placeOrder: response http=' . $httpCode . ' err=' . $err . ' body=' . substr((string)$resp, 0, 4000));

        $decoded = null;
        if ($resp !== false && $resp !== '') {
            $decoded = json_decode($resp, true);
            if (!is_array($decoded)) $decoded = ['_raw_text' => $resp];
        }

        // Подтверждено первым живым ответом (заказ №186): успешное создание
        // отдаёт HTTP 201 (не 200) и объект заказа ОБЁРНУТЫМ в {"order": {...}}
        // (не плоско на верхнем уровне, как в документации). Ошибка — отдельная
        // форма {"errors": [...]} на верхнем уровне (см. документацию), поэтому
        // остаётся top-level, а не внутри order.
        $orderData = (is_array($decoded) && is_array($decoded['order'] ?? null)) ? $decoded['order'] : null;
        $success = $err === '' && $httpCode >= 200 && $httpCode < 300
            && is_array($decoded) && empty($decoded['errors']) && !empty($orderData['id']);

        $itemReferences = [];
        if ($success) {
            $orderId = (int)$orderData['id'];
            foreach ($basketItemIdBySequence as $sequence => $basketItemId) {
                if ($basketItemId > 0) $itemReferences[$basketItemId] = $orderId . ':' . $sequence;
            }
        }

        $error = null;
        if (!$success) {
            $error = $err ?: (is_array($decoded['errors'] ?? null) ? json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE) : 'order_rejected');
        }

        return [
            'http_code'       => $httpCode ?: null,
            'success'         => $success,
            'raw'             => $decoded,
            'error'           => $error,
            'item_references' => $itemReferences,
        ];
    }

    /**
     * Дата/флаг времени отгрузки (dispatch_at/dispatch_time) для одной позиции,
     * исходя из окна доставки, зафиксированного в orderMeta на момент поиска
     * (см. buildResultItem()). Пересчитывается от ТЕКУЩЕГО времени (не от
     * времени поиска): если дедлайн волны (buy_until) уже прошёл к моменту
     * оформления заказа, откатываемся на assured_period/average_period, а не
     * молча подставляем просроченное окно.
     *
     * @return array{date:string,time_flag:int} dispatch_at (Y-m-d), dispatch_time (1 — до 15:00, 2 — после)
     */
    private function resolveDispatchWindow(array $meta): array
    {
        $now = time();

        $fromTs = null;
        if (!empty($meta['delivery_from'])) {
            $f = strtotime((string)$meta['delivery_from']);
            if ($f && $f > $now) {
                $buyUntilTs = !empty($meta['buy_until']) ? strtotime((string)$meta['buy_until']) : null;
                if ($buyUntilTs === null || $buyUntilTs > $now) {
                    $fromTs = $f;
                }
            }
        }

        if ($fromTs === null) {
            $days = null;
            if (isset($meta['assured_period']) && $meta['assured_period'] !== null) {
                $days = (int)$meta['assured_period'];
            } elseif (isset($meta['average_period']) && $meta['average_period'] !== null) {
                $days = (int)$meta['average_period'];
            }
            $days = max(0, $days ?? 1);
            // Без точного окна берём заведомо безопасный запас — после 15:00 в
            // расчётный день, чтобы не попасть в волну, для которой товар ещё не
            // готов.
            $fromTs = strtotime('today') + $days * 86400 + 16 * 3600;
        }

        return [
            'date'      => date('Y-m-d', $fromTs),
            'time_flag' => ((int)date('H', $fromTs) < 15) ? 1 : 2,
        ];
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference здесь — составной "{order_id}:{sequence}" (см.
     * placeOrder()::item_references), т.к. у Берга один заказ (Order.id) может
     * содержать несколько наших позиций, различаемых по sequence — тот же приём,
     * что и у MoskvorechieConnector с "{order_number}:{gid}".
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderIdRaw, $seqRaw] = explode(':', $reference, 2);
        $orderId = (int)$orderIdRaw;
        $seq     = (int)$seqRaw;
        if ($orderId <= 0) return [];

        $url = rtrim($this->baseUrl, '/') . '/ordering/states.json?orders[]=' . $orderId;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log("fetchOrderStatusByReference({$reference}): response http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));

        if ($err || $httpCode !== 200 || empty($resp)) return [];

        $data = json_decode($resp, true);
        if (!is_array($data)) return [];
        // Подтверждено первым живым ответом: коллекция заказов обёрнута в
        // {"orders": [...]}, а не {"data": [...]} (как у ПартКома/Москворечья) и
        // не голым списком — 'data' оставлен как запасной вариант на случай
        // другой обёртки, но настоящий формат именно 'orders'.
        $orders = $data['orders'] ?? $data['data'] ?? $data;
        if (!is_array($orders)) return [];

        $order = null;
        foreach ($orders as $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) === $orderId) { $order = $row; break; }
        }
        if ($order === null) return [];

        $item = null;
        foreach ((array)($order['items'] ?? []) as $it) {
            if ((int)($it['sequence'] ?? 0) === $seq) { $item = $it; break; }
        }
        // Позицию по sequence не нашли (например, поменялся формат ответа) —
        // как и у ПартКома/Москворечья, безопаснее вернуть первую позицию
        // заказа, чем молчать.
        if ($item === null) $item = $order['items'][0] ?? null;
        if ($item === null) return [];

        $state     = (array)($item['state'] ?? []);
        $stateText = isset($state['name']) ? (string)$state['name'] : null;
        $stateType = isset($state['type']) ? (int)$state['type'] : null;

        return [[
            'order_number'    => (string)$orderId,
            'state_id'        => isset($state['id']) ? (string)$state['id'] : null,
            'state_text'      => $stateText,
            'stage'           => $this->normalizeStage($stateText, $stateType),
            'expected_date'   => $item['average_time'] ?? null,
            'guaranteed_date' => $item['assured_time'] ?? null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $item['comment'] ?? $order['comment'] ?? null,
            'raw'             => $item,
        ]];
    }

    // Официального словаря названий статусов (/references/states) на момент
    // подключения ещё не сверяли по факту — как и у Москворечья, классификация
    // по ключевым фразам временная. type (0 обычный, 1 — присвоен при создании
    // заказа, 2 — присвоен по завершению обработки) — задокументированный
    // Бергом признак, ему доверяем в первую очередь: type=2 без явных признаков
    // отказа в тексте статуса — считаем 'ready' (заказ завершён), а не пытаемся
    // угадать по неполному словарю фраз.
    // 'снят с резерва' — подтверждено первым живым ответом (заказ №186, state
    // id=3, type=2): УДАЛЕНИЕ товара из заказа/резерва, реальный отказ, хотя
    // формально относится к "финальным" (type=2) статусам наравне с успешными
    // (в UI Берга эта же позиция показана как "Отменён").
    private const REFUSED_PHRASES    = ['отказ', 'отменен', 'отменён', 'возврат', 'не может быть поставлен', 'не будет поставлен', 'снят с резерва'];
    private const READY_PHRASES      = ['получен', 'выдан', 'доставлен клиенту', 'закрыт', 'завершен', 'завершён'];
    private const IN_TRANSIT_PHRASES = ['отгруж', 'передан', 'в пути', 'собран', 'складе'];

    private function normalizeStage(?string $stateText, ?int $stateType): string
    {
        $t = mb_strtolower((string)$stateText);
        if ($t !== '') {
            foreach (self::REFUSED_PHRASES as $p) { if (mb_strpos($t, $p) !== false) return 'refused'; }
        }
        if ($stateType === 2) return 'ready';
        if ($t !== '') {
            foreach (self::READY_PHRASES as $p)      { if (mb_strpos($t, $p) !== false) return 'ready'; }
            foreach (self::IN_TRANSIT_PHRASES as $p) { if (mb_strpos($t, $p) !== false) return 'in_transit'; }
        }
        return 'ordered';
    }

    private function log(string $message): void
    {
        // @-подавление и явная проверка DOCUMENT_ROOT — этот коннектор также
        // используется из голого CLI-крона (supplier_order_status_poll.php),
        // где DOCUMENT_ROOT выставляется вручную скриптом, но на всякий случай
        // не полагаемся на его гарантированное наличие (см. MoskvorechieConnector::log()).
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot === '') return;
        $logFile = $docRoot . '/upload/logs/berg_' . date('Y-m-d') . '.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
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
        if ($err || $httpCode !== 200) return null;
        return $resp;
    }

    private function buildResultItem(array $resource, array $offer): SearchResultItem
    {
        $wh    = $offer['warehouse'] ?? [];
        $qty   = (int)($offer['quantity'] ?? 0);
        $transit = !empty($offer['is_transit']);

        [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($offer);

        $r = new SearchResultItem();
        $r->source            = $this->getCode();
        $r->article           = (string)($resource['article'] ?? '');
        $r->brand             = (string)($resource['brand']['name'] ?? '');
        $r->name              = (string)($resource['name'] ?? '');
        $r->price             = (float)($offer['price'] ?? 0);
        $r->quantity          = $qty;
        $r->deliveryDays      = $deliveryDays;
        $r->deliveryPeriod    = $deliveryPeriod;
        $r->deliveryLabel     = $deliveryLabel;
        $r->deliveryTimeLabel = $deliveryTimeLabel;
        $r->deliveryToday     = $deliveryToday;
        $r->deliveryDeadline  = $deliveryDeadline;
        $r->warehouse         = (string)($wh['name'] ?? '');
        $r->stockId           = (string)($wh['id'] ?? '');
        $r->supplierName      = $this->getName();
        $r->isSched           = ($qty <= 0) || $transit;
        $r->multiplicity      = max(1, (int)($offer['multiplication_factor'] ?? 1));
        $r->unit              = 'шт.';
        $r->returnable        = true;
        $r->reliabilityPercent = is_numeric($offer['reliability'] ?? null)
            ? max(0, min(100, (int)round((float)$offer['reliability']))) : null;
        $r->raw               = $offer;

        $ttAll = $offer['address_timetable'] ?? [];
        $tt    = !empty($ttAll) ? $ttAll[0] : [];
        $dateFrom = $tt['delivery_from'] ?? $tt['pickup_from'] ?? null;
        $dateTo   = $tt['delivery_to']   ?? $tt['pickup_to']   ?? null;
        $buyUntil = $tt['buy_until'] ?? null;
        if (!empty($dateFrom)) $r->raw['deliveryDateFrom'] = $dateFrom;
        if (!empty($dateTo))   $r->raw['deliveryDateTo']   = $dateTo;
        if (!empty($buyUntil)) $r->raw['deliveryCheckout'] = $buyUntil;

        // Для оформления заказа (см. SupplierOrderable::placeOrder()) —
        // resource_id/warehouse_id, которые /ordering/place_order принимает как
        // OrderItem.resource_id/warehouse_id. Окно доставки берём СТРОГО из
        // delivery_from/delivery_to (не pickup_from/to выше — заказ у Берга
        // оформляется доставкой, см. placeOrder()), чтобы не подставить в
        // dispatch_at время самовывозной волны.
        $r->orderMeta = [
            'resource_id'    => isset($resource['id']) ? (int)$resource['id'] : null,
            'warehouse_id'   => isset($wh['id']) ? (int)$wh['id'] : null,
            'delivery_from'  => $tt['delivery_from'] ?? null,
            'delivery_to'    => $tt['delivery_to'] ?? null,
            'buy_until'      => $buyUntil,
            'assured_period' => $offer['assured_period'] ?? null,
            'average_period' => $offer['average_period'] ?? null,
        ];

        return $r;
    }

    /**
     * Срок доставки БЕРГ. Приоритет источников:
     *  1. Ближайший рейс адресной доставки/самовывоза (address_timetable[0]) —
     *     реальное окно "buy_until → delivery_from–delivery_to" (или pickup_from–to
     *     для самовывоза), самый точный источник, показывается с временем.
     *  2. assured_period — гарантированный срок поставки ДО СКЛАДА БЕРГ (не до
     *     клиента), в днях, без времени.
     *  3. average_period — средний (не гарантированный) срок до склада БЕРГ,
     *     только если assured_period не пришёл.
     * Пункты 2-3 говорят о времени прибытия НА склад Берга, а не клиенту — это
     * приближение, но точнее источника у API нет, если рейс адресной доставки
     * не назначен.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $offer): array
    {
        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');

        $ttAll = $offer['address_timetable'] ?? [];
        $tt    = !empty($ttAll) ? $ttAll[0] : [];

        $fromTs = null; $toTs = null; $deadlineTs = null;
        if (!empty($tt)) {
            $fromRaw = $tt['delivery_from'] ?? $tt['pickup_from'] ?? null;
            $toRaw   = $tt['delivery_to']   ?? $tt['pickup_to']   ?? null;
            $buyRaw  = $tt['buy_until'] ?? null;
            $f = $fromRaw ? strtotime($fromRaw) : null;
            if ($f && $f > $now) {
                $fromTs = $f;
                $t = $toRaw ? strtotime($toRaw) : null;
                if ($t && $t > $fromTs) $toTs = $t;
                $deadlineTs = $buyRaw ? strtotime($buyRaw) : null;
            }
        }

        if ($fromTs !== null) {
            $tsDay         = strtotime(date('Y-m-d', $fromTs));
            $days          = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
            $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $fromTs));
            $timeLabel     = $toTs ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $fromTs);
            $hours         = max(0, (int)ceil(($fromTs - $now) / 3600));
            $deadlineLabel = ($deadlineTs && $deadlineTs > $now) ? date('H:i', $deadlineTs) : null;
            return [$days, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart, $deadlineLabel];
        }

        $days = null;
        if (isset($offer['assured_period'])) {
            $days = (int)$offer['assured_period'];
        } elseif (isset($offer['average_period'])) {
            $days = (int)$offer['average_period'];
        }
        if ($days === null) return [null, null, null, null, false, null];

        $days     = max(0, $days);
        $dayLabel = $days === 0 ? 'Сегодня' : ($days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days")));
        return [$days, $days * 24, $dayLabel, null, $days === 0, null];
    }

    private function apiPost(string $endpoint, array $items): array
    {
        $url  = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/') . '.json';
        $json = json_encode(['items' => $items]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Berg-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resp, true);
        return is_array($result) ? $result : [];
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
}
