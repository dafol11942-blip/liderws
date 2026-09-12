<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class AutoeuroConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private ?string $deliveryKey = null;
    private string $payerKey;
    // Один аккаунт АвтоЕвро, два адреса доставки в Елабуге (найдены через
    // /get_deliveries: "...пр-кт Нефтяников, д 4" — это $deliveryKey по
    // умолчанию, второй — "...ул Баки Урманче, д 17А"). Ключ массива — код
    // склада, как его определяет resolveOrderWarehouseCode() в
    // order_create_handler.php по выбранному в форме заказа складу.
    private array $deliveryKeyByWarehouse;

    public function __construct(array $config = [])
    {
        $this->apiKey     = $config['API_KEY']      ?? '';
        $this->baseUrl    = $config['BASE_URL']     ?? 'https://api.autoeuro.ru/api/v2/json';
        $this->timeout    = $config['TIMEOUT']      ?? 10;
        $this->deliveryKey = $config['DELIVERY_KEY'] ?? null;
        $this->payerKey    = $config['PAYER_KEY']    ?? '';
        $this->deliveryKeyByWarehouse = $config['DELIVERY_KEYS_BY_WAREHOUSE'] ?? [];
    }

    public function getCode(): string       { return 'autoeuro'; }
    public function getName(): string       { return 'Авто-Евро'; }
    public function getWarehousePrefix(): string { return 'ae'; }

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
        $url = $this->baseUrl . '/search_brands?' . http_build_query(['code' => $article]);
        return [
            'url'     => $url,
            'headers' => ['key: ' . $this->apiKey, 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (empty($data['DATA'])) return $brands;

        foreach ($data['DATA'] as $item) {
            $b  = $item['brand'] ?? '';
            $n  = $item['code']  ?? '';
            $nm = $item['name']  ?? '';
            if (!$b || !$n) continue;

            $key = $b . '|' . $n;
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $b,
                    'article'     => $n,
                    'article_fix' => $n,
                    'description' => $nm,
                ];
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

        $deliveryKey = $this->getDeliveryKey();
        if (!$deliveryKey) return null;

        $body = json_encode([
            'brand'        => $brand,
            'code'         => $article,
            'delivery_key' => $deliveryKey,
            'with_crosses' => ($withCrosses ? 1 : 0),
            'with_offers'  => 1,
        ]);

        return [
            'url'     => $this->baseUrl . '/search_items',
            'headers' => ['key: ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $own = [];    // stock=1 — свой склад
        $other = [];  // stock=0 — партнёр
        // Защита от OOM: огромные cross-ответы
        $len = strlen($responseBody);
        if ($len > 2500000) {
            $this->log("parseSearchResponse: body too large ({$len} bytes), skip");
            return [];
        }

        $data = json_decode($responseBody, true);
        unset($responseBody);

        if (empty($data['DATA']) || !is_array($data['DATA'])) {
            return [];
        }

        $maxItems = 150;
        $n = 0;
        foreach ($data['DATA'] as $item) {
            if (!is_array($item)) continue;
            $r = $this->buildResultItem($item, $brand, $article);
            if (is_array($r->raw) && count($r->raw) > 0) {
                $r->raw = $this->lightRaw($r->raw);
            }
            if ($r->price <= 0 && $r->quantity <= 0) continue;
            if ($r->isSched) continue;

            // Свои: stock=1, чужие: stock=0
            if (!empty($item['stock'])) {
                $own[] = $r;
            } else {
                $other[] = $r;
            }
            $n++;
            if ($n >= $maxItems) break;
        }
        unset($data);

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

    private function lightRaw(array $item): array
    {
        $keep = [
            'delivery_time', 'delivery_time_max', 'order_before',
            'deliveryDateFrom', 'deliveryDateTo', 'deliveryCheckout',
            'warehouse_name', 'offer_key', 'stock', 'return', 'packing', 'unit', 'rejects',
        ];
        $out = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $item)) {
                $out[$k] = $item[$k];
            }
        }
        // стандартные ключи для calcDelivery
        if (!empty($item['delivery_time'])) {
            $out['deliveryDateFrom'] = $item['delivery_time'];
        }
        if (!empty($item['delivery_time_max'])) {
            $out['deliveryDateTo'] = $item['delivery_time_max'];
        }
        if (!empty($item['order_before'])) {
            $out['deliveryCheckout'] = $item['order_before'];
        }
        return $out;
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
        if ($test) {
            // В документации АвтоЕвро, как и у Москворечья, нет флага тестового
            // заказа — безопаснее ничего не отправлять, чем случайно оформить
            // реальный заказ у поставщика.
            $this->log('placeOrder: тестовый режим не поддерживается API АвтоЕвро — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        // Склад одного заказа один на все позиции (см. dispatchSupplierOrders()
        // в order_create_handler.php — warehouse_code проставляется туда для
        // всех позиций сразу, по адресу самовывоза/доставки, выбранному в форме
        // оформления заказа), поэтому смотрим на первую позицию с этим полем.
        $warehouseCode = '';
        foreach ($items as $item) {
            if (!empty($item['warehouse_code'])) { $warehouseCode = (string)$item['warehouse_code']; break; }
        }

        $deliveryKey = $this->getDeliveryKey($warehouseCode);
        if (!$deliveryKey) {
            $this->log('placeOrder: не удалось получить delivery_key');
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_delivery_key'];
        }
        if ($this->payerKey === '') {
            $this->log('placeOrder: PAYER_KEY не настроен в конфиге коннектора');
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_payer_key'];
        }

        $stockItems = [];
        $skipped = 0;
        // Комментарий одинаков для всех позиций одного нашего заказа (см.
        // dispatchSupplierOrders()) — API сам склеивает order-level comment
        // с комментариями строк через ";", поэтому берём его один раз на уровень
        // заказа и НЕ дублируем в каждой stock_items[].comment (иначе на выходе
        // получилась бы одна и та же фраза, повторённая N раз через ";").
        $orderComment = '';
        // brand|code → basket_item_id — /create_order не возвращает позиции (см.
        // ниже), а /get_orders потом отдаёт их только по brand+code (offer_key
        // там не эхуется), поэтому ключ для последующего сопоставления строим
        // сами из того, что и так уже отправляем (см. item_references ниже).
        $basketItemIdByArticleKey = [];
        foreach ($items as $item) {
            $offerKey = trim((string)($item['order_meta']['offer_key'] ?? ''));
            $qty = (int)($item['quantity'] ?? 0);
            if ($offerKey === '' || $qty <= 0) { $skipped++; continue; }
            // price сейчас игнорируется API (см. документацию create_order) — 0
            // явно означает "без сверки", а не забытый параметр.
            $stockItems[] = ['offer_key' => $offerKey, 'quantity' => $qty, 'price' => 0];
            if ($orderComment === '') $orderComment = (string)($item['comment'] ?? '');
            $articleKey = (string)($item['brand'] ?? '') . '|' . (string)($item['article'] ?? '');
            $basketItemIdByArticleKey[$articleKey] = (int)($item['basket_item_id'] ?? 0);
        }

        if (empty($stockItems)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет offer_key в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $body = json_encode([
            'delivery_key' => $deliveryKey,
            'payer_key'    => $this->payerKey,
            'stock_items'  => $stockItems,
            'comment'      => $orderComment,
        ], JSON_UNESCAPED_UNICODE);

        $this->log('placeOrder: warehouse_code=' . ($warehouseCode ?: '(default)') . ' request items=' . count($stockItems) . ' skipped=' . $skipped . ' body=' . $body);

        $ch = curl_init($this->baseUrl . '/create_order');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['key: ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
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

        // Подтверждено первым живым заказом: DATA — это СПИСОК из одного элемента
        // (та же обёртка, что у get_payers/get_deliveries, см. документацию),
        // напр. "DATA":[{"order_id":"...","result":true,...}] — а не голый объект.
        $dataNode = $decoded['DATA'] ?? $decoded;
        $data = (is_array($dataNode) && is_array($dataNode[0] ?? null)) ? $dataNode[0] : $dataNode;

        $success = $httpCode === 200 && $err === '' && is_array($data) && !empty($data['result']);

        // Как и у Москворечья (см. MoskvorechieConnector::placeOrder()) — у
        // АвтоЕвро нет способа принять наш reference, только их order_id.
        // Он один на весь заказ (может включать несколько наших позиций), а
        // /get_orders потом отдаёт позиции без offer_key — только brand+code,
        // поэтому reference строим тем же ключом уже сейчас, из отправленных
        // данных, а не из ответа (create_order позиции не возвращает вовсе).
        $itemReferences = [];
        if ($success && !empty($data['order_id'])) {
            $orderId = (string)$data['order_id'];
            foreach ($basketItemIdByArticleKey as $articleKey => $basketItemId) {
                if ($basketItemId) $itemReferences[$basketItemId] = $orderId . ':' . $articleKey;
            }
        }

        return [
            'http_code'       => $httpCode ?: null,
            'success'         => $success,
            'raw'             => $decoded,
            'error'           => $err ?: ($success ? null : (string)($data['result_description'] ?? 'order_rejected')),
            'item_references' => $itemReferences,
        ];
    }

    /**
     * У АвтоЕвро нет запроса "статус по нашему reference" — только
     * POST /get_orders с их order_id. $reference здесь — составной
     * "{order_id}:{brand}|{code}" (см. placeOrder()::item_references), т.к.
     * offer_key обратно не возвращается — только так можно понять, к какой
     * именно нашей позиции относится конкретная строка ответа.
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderId, $articleKey] = explode(':', $reference, 2);
        $orderId = trim($orderId);
        if ($orderId === '' || !ctype_digit($orderId)) return [];

        $ch = curl_init($this->baseUrl . '/get_orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['key: ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POST           => true,
            // Подтверждено вживую: "голый" массив номеров заказов из примера в
            // документации НЕ фильтрует вообще — API молча отдаёт общий список
            // последних заказов клиента (results_count всегда одинаковый,
            // нужного order_id там просто нет). Реально фильтрует только объект
            // с ключом "orders".
            CURLOPT_POSTFIELDS     => json_encode(['orders' => [(int)$orderId]]),
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log("fetchOrderStatusByReference({$reference}): http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));

        if ($err || $httpCode !== 200 || empty($resp)) return [];

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) return [];
        $rows = is_array($decoded['DATA'] ?? null) ? $decoded['DATA'] : [];
        if (empty($rows)) return [];

        // brand/code от АвтоЕвро может отличаться написанием от того, что мы
        // сохранили при заказе (напр. "MASUMA"/"KJ513" у нас → "Masuma"/"KJ-513"
        // в ответе) — сравниваем нормализованными (тот же BrandNormalizer, что
        // и у PartKomConnector), а не точным строковым совпадением.
        [$wantBrand, $wantArticle] = array_pad(explode('|', $articleKey, 2), 2, '');
        $normBrand = BrandNormalizer::normalize($wantBrand);
        $normArt   = BrandNormalizer::normalizeArticle($wantArticle);

        $match = null;
        if (count($rows) === 1 && is_array($rows[0] ?? null)) {
            // orders-фильтр выше уже гарантирует, что вся выдача — это ИМЕННО
            // наш order_id; если в нём одна позиция, дополнительно сверять
            // brand/code незачем.
            $match = $rows[0];
        } else {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                if (BrandNormalizer::normalize((string)($row['brand'] ?? '')) === $normBrand
                    && BrandNormalizer::normalizeArticle((string)($row['code'] ?? '')) === $normArt) {
                    $match = $row;
                    break;
                }
            }
        }
        // В отличие от старой версии — НЕ подставляем первую попавшуюся строку,
        // если совпадение не найдено: раз orders-фильтр реально работает,
        // "не нашли" означает баг сопоставления, а не "чуть другое написание",
        // и показывать чужую позицию как наш статус хуже, чем не показать ничего.
        if ($match === null) return [];

        $statusId = isset($match['status_id']) ? (string)$match['status_id'] : null;

        return [[
            'order_number'    => isset($match['order_number']) ? (string)$match['order_number'] : $orderId,
            'state_id'        => $statusId,
            'state_text'      => $match['status'] ?? null,
            'stage'           => $this->normalizeStage($statusId),
            'expected_date'   => $match['delivery_date'] ?? null,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $match['comment'] ?? null,
            'raw'             => $match,
        ]];
    }

    // Полный официальный словарь статусов (см. /get_statuses, 42 статуса на
    // 2026-09-08) — в отличие от PartKom/Moskvorechie, где официального перечня
    // не было, здесь классификация по status_id, а не по текстовым фразам.
    // Группы "Отказано"/"Получено" из /get_statuses переносятся как есть; группа
    // "В работе" вручную разбита на ordered/in_transit — сама по себе она
    // слишком широкая (туда попадают и "Новый", и "В пути"). Группа "Прочее" —
    // внутренние технические статусы склада, к доставке клиенту не относятся,
    // дефолт ordered.
    private const REFUSED_STATUS_IDS    = ['300','301','302','303','304','305','306','307','330','340','350','370','380'];
    private const READY_STATUS_IDS      = ['230','260'];
    private const IN_TRANSIT_STATUS_IDS = ['140','160','180','181','199'];

    private function normalizeStage(?string $statusId): string
    {
        $id = (string)$statusId;
        if (in_array($id, self::REFUSED_STATUS_IDS, true))    return 'refused';
        if (in_array($id, self::READY_STATUS_IDS, true))      return 'ready';
        if (in_array($id, self::IN_TRANSIT_STATUS_IDS, true)) return 'in_transit';
        return 'ordered';
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function getDeliveryKey(string $warehouseCode = ''): ?string
    {
        if ($warehouseCode !== '' && !empty($this->deliveryKeyByWarehouse[$warehouseCode])) {
            return $this->deliveryKeyByWarehouse[$warehouseCode];
        }
        if ($this->deliveryKey) return $this->deliveryKey;
        if (!$this->isAvailable()) return null;

        $url  = $this->baseUrl . '/get_deliveries';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        $deliveries = $data['DATA'] ?? [];

        if (empty($deliveries)) {
            $this->log('No deliveries found');
            $this->deliveryKey = '';
            return null;
        }

        $this->deliveryKey = $deliveries[0]['delivery_key'] ?? '';
        return $this->deliveryKey ?: null;
    }

    private function buildResultItem(array $item, string $defaultBrand, string $defaultArticle): SearchResultItem
    {
        $stock   = !empty($item['stock']);
        $amount  = (int)($item['amount'] ?? 0);
        $isSched = ($amount <= 0);

        [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($item);

        $r = new SearchResultItem();
        $r->source            = $this->getCode();
        $r->article           = (string)($item['code'] ?? $defaultArticle);
        $r->brand             = (string)($item['brand'] ?? $defaultBrand);
        $r->name              = (string)($item['name'] ?? '');
        $r->price             = (float)($item['price'] ?? 0);
        $r->quantity          = $amount;
        $r->deliveryDays      = $deliveryDays;
        $r->deliveryPeriod    = $deliveryPeriod;
        $r->deliveryLabel     = $deliveryLabel;
        $r->deliveryTimeLabel = $deliveryTimeLabel;
        $r->deliveryToday     = $deliveryToday;
        $r->deliveryDeadline  = $deliveryDeadline;
        $r->warehouse         = $stock ? ((string)($item['warehouse_name'] ?? 'Склад')) : 'Под заказ';
        $r->stockId        = (string)($item['offer_key'] ?? '');
        $r->supplierName   = $this->getName();
        $r->isSched        = $isSched;
        $r->multiplicity   = max(1, (int)($item['packing'] ?? 1));
        $r->unit           = !empty($item['unit']) ? (string)$item['unit'] : 'шт.';
        $r->returnable     = !empty($item['return']);
        // Для оформления заказа (см. SupplierOrderable::placeOrder()) — тот же
        // offer_key, что уже идёт в stockId для отображения, но здесь отдельно
        // и однозначно, как того требует контракт orderMeta.
        $r->orderMeta      = ['offer_key' => (string)($item['offer_key'] ?? '')];
        // rejects — "Вероятность отказа в процентах, 0% = на складе" (т.е. это
        // ОТКАЗ, а не поставка — reliability считаем от обратного).
        if (is_numeric($item['rejects'] ?? null)) {
            $r->refusalPercent     = max(0, min(100, (int)round((float)$item['rejects'])));
            $r->reliabilityPercent = 100 - $r->refusalPercent;
        }
        $r->raw            = $this->lightRaw($item);

        // Стандартные ключи для calcDelivery
        if (!empty($item['delivery_time']))     $r->raw['deliveryDateFrom'] = $item['delivery_time'];
        if (!empty($item['delivery_time_max'])) $r->raw['deliveryDateTo']   = $item['delivery_time_max'];
        if (!empty($item['order_before']))      $r->raw['deliveryCheckout'] = $item['order_before'];

        return $r;
    }

    /**
     * Срок доставки Авто-Евро. API отдаёт точные datetime-границы напрямую —
     * delivery_time ("от"), delivery_time_max ("до", может отсутствовать),
     * order_before (крайний срок заказа под это окно). Особый случай: если
     * товар уже лежит на том же складе, что и выбранный ПВЗ самовывоза,
     * delivery_time приходит NULL — значит доступен сразу, "Сегодня".
     *
     * АвтоЕвро физически не возит день в день — если delivery_time всё же
     * попал на сегодня (нестыковка на стороне API), считаем такое время
     * доверия не заслуживающим и не гадаем точный час "завтра": просто
     * "Завтра" без времени, вместо того чтобы показать заведомо неверный час.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $item): array
    {
        if (empty($item['delivery_time'])) {
            return [0, 0, 'Сегодня', null, true, null];
        }

        $fromTs = strtotime($item['delivery_time']);
        if (!$fromTs) {
            return [null, null, null, null, false, null];
        }

        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $deadlineTs    = !empty($item['order_before']) ? strtotime($item['order_before']) : null;
        $deadlineLabel = ($deadlineTs && $deadlineTs > $now) ? date('H:i', $deadlineTs) : null;

        $fromDay = strtotime(date('Y-m-d', $fromTs));
        if ($fromDay <= $todayStart) {
            return [1, null, 'Завтра', null, false, $deadlineLabel];
        }

        $toTs      = !empty($item['delivery_time_max']) ? strtotime($item['delivery_time_max']) : null;
        $days      = (int)ceil(($fromDay - $todayStart) / 86400);
        $dayLabel  = ($fromDay === $tomorrowStart) ? 'Завтра' : date('d.m', $fromTs);
        $timeLabel = $toTs ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $fromTs);
        $hours     = max(0, (int)ceil(($fromTs - $now) / 3600));
        return [$days, $hours, $dayLabel, $timeLabel, false, $deadlineLabel];
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($req['method'] === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($req['body']) $opts[CURLOPT_POSTFIELDS] = $req['body'];
        }
        curl_setopt_array($ch, $opts);

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
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/autoeuro_' . date('Y-m-d') . '.log',
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
