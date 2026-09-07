<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

class MoskvorechieConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private string $agreementId;
    private string $filialId;
    private ?array $profileCache = null;

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
        $url = rtrim($this->apiUrl, '/') . '/search/articles?'
             . http_build_query(['brand' => $brand, 'number' => $article, 'avail' => 1, 'hide_extstor' => 1]);
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
        // Сортировка: сначала по срокам, потом по цене (все склады свои)
        usort($results, function (SearchResultItem $a, SearchResultItem $b) {
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

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

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    /**
     * У API Москворечья нет отдельного эндпоинта "оформить заказ по товару" —
     * только серверная корзина: сначала товары кладутся в неё по gid
     * (POST /cart/add), затем из неё собирается заказ по cart_position_id
     * (POST /orders). agreement_id/filial_id (заголовки X-Agreement-ID/
     * X-Filial-ID) и delivery_term (обязательное поле /orders) не задаются нами
     * напрямую — берутся из /profile (см. loadProfile()), если не были явно
     * прописаны в конфиге коннектора.
     */
    public function placeOrder(array $items, bool $test = false): array
    {
        if ($test) {
            // В документации API нет флага "тестовый заказ" (в отличие от ПартКома) —
            // безопаснее ничего не отправлять в тестовом режиме, чем случайно
            // оформить реальный заказ у поставщика.
            $this->log('placeOrder: тестовый режим не поддерживается API Москворечья — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        $cartPayload = [];
        $basketItemIdByGid = [];
        $skipped = 0;
        foreach ($items as $item) {
            $gid = trim((string)($item['order_meta']['gid'] ?? ''));
            $qty = (int)($item['quantity'] ?? 0);
            if ($gid === '' || $qty <= 0) { $skipped++; continue; }
            $cartPayload[] = ['gid' => $gid, 'quantity' => $qty, 'comment' => (string)($item['comment'] ?? '')];
            $basketItemIdByGid[$gid] = (int)($item['basket_item_id'] ?? 0);
        }

        if (empty($cartPayload)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет gid в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $profile = $this->loadProfile() ?? [];
        $this->applyProfileDefaults($profile);
        $deliveryTerm = (string)($profile['delivery_term'] ?? '');

        if ($deliveryTerm === '') {
            $this->log('placeOrder: не удалось определить delivery_term через /profile');
            return ['http_code' => null, 'success' => false, 'raw' => $profile, 'error' => 'no_delivery_term'];
        }

        $this->log('placeOrder: /cart/add items=' . count($cartPayload) . ' skipped=' . $skipped . ' payload=' . json_encode($cartPayload, JSON_UNESCAPED_UNICODE));

        $addResult = $this->requestJson('POST', '/cart/add', $cartPayload);
        $addBody   = $addResult['body'];
        if ($addResult['error'] || $addResult['http_code'] !== 200 || !is_array($addBody)) {
            return ['http_code' => $addResult['http_code'], 'success' => false, 'raw' => $addBody, 'error' => $addResult['error'] ?: ('cart_add_http_' . $addResult['http_code'])];
        }

        $positionIds = [];
        foreach ((array)($addBody['cart'] ?? []) as $row) {
            if (empty($row['status']) || !isset($row['cart_position_id'])) continue;
            $positionIds[] = (int)$row['cart_position_id'];
        }

        if (empty($positionIds)) {
            $this->log('placeOrder: /cart/add не подтвердил ни одной позиции — ' . ($addBody['message'] ?? ''));
            return ['http_code' => $addResult['http_code'], 'success' => false, 'raw' => $addBody, 'error' => 'cart_add_all_rejected'];
        }

        $orderComment = (string)($items[array_key_first($items)]['comment'] ?? '');

        $orderResult = $this->requestJson('POST', '/orders', [
            'delivery_term' => $deliveryTerm,
            'comment'       => $orderComment,
            'positions'     => $positionIds,
        ]);
        $orderBody = $orderResult['body'];

        $success = $orderResult['error'] === null
            && $orderResult['http_code'] === 200
            && is_array($orderBody)
            && !empty($orderBody['order']['order_number']);

        // У Москворечья нет способа принять от нас произвольный reference (в
        // отличие от ПартКома, чьи orderItems[][reference] потом ищутся через
        // /basket/motion/{reference}) — единственный ключ для последующего
        // опроса статуса (см. fetchOrderStatusByReference()) — их собственный
        // order_number. Он один на ВЕСЬ заказ у Москворечья (может включать
        // несколько наших позиций), поэтому для однозначного сопоставления
        // конкретной позиции корзины с конкретной позицией в их ответе
        // используется составной reference "{order_number}:{gid}" — иначе при
        // заказе 2+ разных товаров опрос статуса не знал бы, чья именно
        // позиция STAGE относится к какой строке b_supplier_order_item.
        $itemReferences = [];
        if ($success) {
            $orderNumber = (string)$orderBody['order']['order_number'];
            foreach ((array)($orderBody['order']['positions'] ?? []) as $pos) {
                $gid = (string)($pos['gid'] ?? '');
                if ($gid === '' || empty($basketItemIdByGid[$gid])) continue;
                $itemReferences[$basketItemIdByGid[$gid]] = $orderNumber . ':' . $gid;
            }
        }

        return [
            'http_code'       => $orderResult['http_code'],
            'success'         => $success,
            'raw'             => ['cart_add' => $addBody, 'order' => $orderBody],
            'error'           => $orderResult['error'] ?: ($success ? null : (string)($orderBody['message'] ?? 'order_rejected')),
            'item_references' => $itemReferences,
        ];
    }

    /**
     * У Москворечья нет запроса "статус по нашему reference" — только
     * GET /orders/list?order_numbers=... по ИХ номеру заказа. $reference здесь —
     * составной "{order_number}:{gid}" (см. placeOrder()::item_references),
     * поэтому сначала разбираем его обратно и ищем внутри ответа именно нужную
     * позицию по gid, а не берём первую попавшуюся (в одном их заказе может
     * быть несколько наших позиций).
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderNumber, $gid] = explode(':', $reference, 2);
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '') return [];

        $profile = $this->loadProfile() ?? [];
        $this->applyProfileDefaults($profile);

        $resp = $this->requestJson('GET', '/orders/list?order_numbers=' . rawurlencode($orderNumber));
        if ($resp['error'] || $resp['http_code'] !== 200 || !is_array($resp['body'])) {
            return [];
        }

        $orders = (array)($resp['body']['orders'] ?? []);
        $order  = null;
        foreach ($orders as $o) {
            if ((string)($o['order_number'] ?? '') === $orderNumber) { $order = $o; break; }
        }
        if ($order === null) return [];

        $position = null;
        foreach ((array)($order['positions'] ?? []) as $pos) {
            if ((string)($pos['gid'] ?? '') === $gid) { $position = $pos; break; }
        }
        // Если позицию по gid не нашли (например, изменилось представление API),
        // безопаснее взять статус заказа целиком, чем молчать — это тот же принцип,
        // что и в PartKomConnector (никогда не пропускать статус молча).
        $statusText = $position['status'] ?? $position['status_details'] ?? $order['status'] ?? null;

        return [[
            'order_number'    => $orderNumber,
            'state_id'        => (string)($position['status_code'] ?? $order['status_code'] ?? '') ?: null,
            'state_text'      => $statusText,
            'stage'           => $this->normalizeStage($statusText),
            'expected_date'   => $position['planned_shipment_date'] ?? null,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $position['comment'] ?? $order['comment'] ?? null,
            'raw'             => $position ?? $order,
        ]];
    }

    // Словарь неполный — в документации Москворечья нет справочника всех
    // status_code (в отличие от ПартКома, где он был сверен по 28 присланным
    // статусам), поэтому классификация по ключевым фразам как временное
    // приближение. При появлении полного справочника (/orders/statuses) —
    // уточнить по нему, а не по этим догадкам.
    private const REFUSED_PHRASES     = ['отказ', 'отменен', 'отменён', 'возврат'];
    private const READY_PHRASES       = ['получен', 'выдан', 'доставлен клиенту', 'закрыт'];
    // "Зарезервирован" намеренно НЕ здесь — это ещё подтверждение наличия, а не
    // движение к клиенту (та же логика, что у PartKomConnector::IN_TRANSIT_PHRASES).
    private const IN_TRANSIT_PHRASES  = ['отгруж', 'передан', 'в пути', 'собран'];

    private function normalizeStage(?string $stateText): string
    {
        $t = mb_strtolower((string)$stateText);
        if ($t === '') return 'ordered';
        foreach (self::REFUSED_PHRASES as $p)    { if (mb_strpos($t, $p) !== false) return 'refused'; }
        foreach (self::READY_PHRASES as $p)      { if (mb_strpos($t, $p) !== false) return 'ready'; }
        foreach (self::IN_TRANSIT_PHRASES as $p) { if (mb_strpos($t, $p) !== false) return 'in_transit'; }
        return 'ordered';
    }

    private function applyProfileDefaults(array $profile): void
    {
        if ($this->agreementId === '' && !empty($profile['agreement_id'])) $this->agreementId = $profile['agreement_id'];
        if ($this->filialId === ''    && !empty($profile['filial_id']))    $this->filialId    = $profile['filial_id'];
    }

    /**
     * Профиль API-ключа (агент/договор, филиал/адрес доставки по умолчанию,
     * условие доставки по умолчанию) — кэшируется на диск на 24ч, как и справочник
     * брендов ПартКома (см. PartKomConnector::loadBrands()), т.к. эти данные
     * меняются крайне редко, а /orders требует delivery_term на каждый вызов.
     */
    private function loadProfile(): ?array
    {
        if ($this->profileCache !== null) return $this->profileCache;

        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/moskvorechie_profile.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['delivery_term'])) {
                $this->profileCache = $cached;
                return $cached;
            }
        }

        $resp = $this->requestJson('GET', '/profile');
        $data = $resp['body']['data'] ?? null;
        if (!is_array($data)) {
            $this->log('loadProfile: /profile недоступен, http=' . $resp['http_code']);
            return null;
        }

        $agreementId = '';
        $filialId    = '';
        foreach ((array)($data['order_settings']['kontragents'] ?? []) as $k) {
            foreach ((array)($k['agreements'] ?? []) as $ag) {
                if ($agreementId === '' && !empty($ag['id'])) $agreementId = (string)$ag['id'];
            }
            foreach ((array)($k['delivery_addresses'] ?? []) as $addr) {
                if (!empty($addr['is_default']) && !empty($addr['id'])) { $filialId = (string)$addr['id']; break; }
            }
            if ($filialId === '' && !empty($k['delivery_addresses'][0]['id'])) {
                $filialId = (string)$k['delivery_addresses'][0]['id'];
            }
            if ($agreementId !== '' && $filialId !== '') break;
        }

        $deliveryTerm = '';
        foreach ((array)($data['delivery_terms'] ?? []) as $t) {
            if (!empty($t['is_default']) && !empty($t['id'])) { $deliveryTerm = (string)$t['id']; break; }
        }
        if ($deliveryTerm === '' && !empty($data['delivery_terms'][0]['id'])) {
            $deliveryTerm = (string)$data['delivery_terms'][0]['id'];
        }

        $profile = ['agreement_id' => $agreementId, 'filial_id' => $filialId, 'delivery_term' => $deliveryTerm];
        $this->log('loadProfile: resolved ' . json_encode($profile, JSON_UNESCAPED_UNICODE));

        if ($deliveryTerm !== '') {
            @mkdir(dirname($cacheFile), 0755, true);
            @file_put_contents($cacheFile, json_encode($profile, JSON_UNESCAPED_UNICODE));
        }

        $this->profileCache = $profile;
        return $profile;
    }

    /** JSON-запрос с полными деталями ответа (в отличие от execCurl() — нужны
     * http_code/error отдельно от тела для семантики success в placeOrder()). */
    private function requestJson(string $method, string $path, $body = null): array
    {
        $url = rtrim($this->apiUrl, '/') . $path;
        $headers = $this->buildHeaders();
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log("{$method} {$path}: http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));

        $decoded = null;
        if ($resp !== false && $resp !== '') {
            $decoded = json_decode($resp, true);
            if (!is_array($decoded)) $decoded = ['_raw_text' => $resp];
        }

        return ['http_code' => $httpCode ?: null, 'body' => $decoded, 'error' => $err ?: null];
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
        [$r->deliveryDays, $r->deliveryPeriod, $r->deliveryLabel, $r->deliveryTimeLabel, $r->deliveryToday, $r->deliveryDeadline] = $this->resolveDelivery($item);
        $r->warehouse      = (string)($item['stock_name'] ?? '');
        $r->stockId        = (string)($item['stock_id'] ?? '');
        $r->supplierName   = $this->getName();
        $r->multiplicity   = max(1, (int)($item['packing'] ?? 1));
        $r->unit           = !empty($item['unit']) ? (string)$item['unit'] : 'шт.';
        $r->isSched        = $isSched;
        $r->returnable     = $returnable;
        // Для оформления заказа (см. SupplierOrderable::placeOrder()) — тот самый
        // "внутренний идентификатор товара", который /cart/add принимает как gid.
        $r->orderMeta      = ['gid' => (string)($item['gid'] ?? '')];
        $r->raw            = $item;
        return $r;
    }

    /**
     * Срок доставки Москворечье. У API нет явных дат/окон (только delivery_period
     * в часах, 0 = в наличии, + флаги) — окно "от–до" реконструируем по реальному
     * расписанию их курьерки/самовывоза (перенесено с ранее работавшей страницы
     * parts-search/index.php::calcDelivery, там это было проверено на практике):
     *  1. hours=0 + флаг pickup — самовывоз в тот же день волнами 12:00-14:00 /
     *     15:00-17:00 (порог заказа 11:02 / 14:02), после 14:02 — только завтра
     *     09:00-11:00.
     *  2. stock_id='10' — этот склад всегда только на завтра, 09:00-11:00.
     *  3. hours>0 — реальный срок округляется вверх до ближайшей волны развоза
     *     (7:00 / 11:00 / 14:00).
     *  4. is_sched (позиция "по графику", а не гарантированная) — точное окно не
     *     показываем, только приблизительный день (минимум "Завтра").
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $item): array
    {
        $hours   = isset($item['delivery_period']) ? (int)$item['delivery_period'] : 0;
        $isSched = !empty($item['is_sched']);

        if ($isSched) {
            $days     = max(1, (int)ceil($hours / 24));
            $dayLabel = $days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days"));
            return [$days, $hours, $dayLabel, null, false, null];
        }

        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $flags         = (array)($item['flags'] ?? []);
        $stockId       = (string)($item['stock_id'] ?? '');

        $fromTs = null; $toTs = null; $deadlineTs = null;

        if ($hours === 0 && in_array('pickup', $flags, true)) {
            $hms = (int)date('Hi');
            if ($hms < 1102) {
                $fromTs = strtotime('today 12:00'); $toTs = strtotime('today 14:00'); $deadlineTs = strtotime('today 11:02');
            } elseif ($hms < 1402) {
                $fromTs = strtotime('today 15:00'); $toTs = strtotime('today 17:00'); $deadlineTs = strtotime('today 14:02');
            } else {
                $fromTs = strtotime('tomorrow 09:00'); $toTs = strtotime('tomorrow 11:00'); $deadlineTs = strtotime('tomorrow 14:02');
            }
        } elseif ($stockId === '10') {
            $fromTs = strtotime('tomorrow 09:00'); $toTs = strtotime('tomorrow 11:00'); $deadlineTs = strtotime('tomorrow 07:02');
        } elseif ($hours > 0) {
            $deliveryTs  = $now + $hours * 3600;
            $deliveryDay = strtotime(date('Y-m-d', $deliveryTs));
            $h           = (int)date('H', $deliveryTs);
            $waveHour    = $h < 9 ? 7 : ($h < 12 ? 11 : 14);
            $waveTs      = $deliveryDay + $waveHour * 3600;
            $fromTs = $waveTs; $toTs = $waveTs + 3 * 3600; $deadlineTs = $waveTs + 120;
        }

        if ($fromTs !== null) {
            $tsDay         = strtotime(date('Y-m-d', $fromTs));
            $days          = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
            $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $fromTs));
            $timeLabel     = $toTs ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $fromTs);
            $deliveryHours = max(0, (int)ceil(($fromTs - $now) / 3600));
            $deadlineLabel = ($deadlineTs && $deadlineTs > $now) ? date('H:i', $deadlineTs) : null;
            return [$days, $deliveryHours, $dayLabel, $timeLabel, $tsDay <= $todayStart, $deadlineLabel];
        }

        // Нет специфичного окна (например: в наличии локально, но не самовывоз) — просто по hours.
        $days     = (int)ceil($hours / 24);
        $dayLabel = $days === 0 ? 'Сегодня' : ($days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days")));
        return [$days, $hours, $dayLabel, null, $days === 0, null];
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
