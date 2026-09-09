<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class ArmtekConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $login;
    private string $password;
    private string $baseUrl;
    private string $vkorg;
    private string $kunrg;
    private string $kunwe;
    private string $kunza;
    private string $incoterms;
    private string $vbeln;
    private string $program;
    private int $timeout;
    private bool $lastWithCrosses = false;

    public function __construct(array $config = [])
    {
        $this->login     = (string)($config['LOGIN']    ?? '');
        $this->password  = (string)($config['PASSWORD'] ?? '');
        // URL взят как есть из документации Армтека (ws.armtek.ru), схема
        // http — как указано в примерах вызова методов.
        $this->baseUrl   = rtrim((string)($config['BASE_URL'] ?? 'http://ws.armtek.ru/api'), '/');
        // VKORG (сбытовая организация) и KUNRG (код клиента) — обязательные
        // параметры ДАЖЕ для поиска (assortment_search/search), получаются не
        // из этой документации, а из отдельного "Сервиса получения структуры
        // клиента" личного кабинета Армтек. Без них коннектор недоступен
        // (см. isAvailable()).
        $this->vkorg      = (string)($config['VKORG'] ?? '');
        $this->kunrg      = (string)($config['KUNRG'] ?? '');
        $this->kunwe      = (string)($config['KUNWE'] ?? '');
        $this->kunza      = (string)($config['KUNZA'] ?? '');
        $this->incoterms  = (string)($config['INCOTERMS'] ?? '');
        $this->vbeln      = (string)($config['VBELN'] ?? '');
        $this->program    = (string)($config['PROGRAM'] ?? '');
        $this->timeout    = (int)($config['TIMEOUT'] ?? 10);
    }

    public function getCode(): string           { return 'armtek'; }
    public function getName(): string           { return 'Армтек'; }
    public function getWarehousePrefix(): string { return 'amt'; }
    public function supportsCrossSearch(): bool { return true; }
    public function getSearchTimeout(): int     { return 10; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return $this->login !== '' && $this->password !== '' && $this->vkorg !== '' && $this->kunrg !== '';
    }

    // ==================== АВТОРИЗАЦИЯ ====================
    // Basic Auth — тот же механизм, что у ПартКома (см. PartKomConnector::authHeader()).
    // В документации Армтека пример передачи авторизации не приведён — если
    // после подключения будут ошибки 401, значит нужен другой механизм,
    // проверить по логу первого живого запроса.
    private function authHeader(): string
    {
        return 'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password);
    }

    // ==================== БРЕНДЫ (assortment_search) ====================

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) return null;

        $fields = [
            'VKORG' => $this->vkorg,
            'PIN'   => trim($article),
        ];
        if ($this->program !== '') $fields['PROGRAM'] = $this->program;

        return [
            'url'     => $this->baseUrl . '/ws_search/assortment_search?format=json',
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => http_build_query($fields),
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        $rows = $this->unwrapArray($data);
        if (!is_array($rows)) return $brands;

        $article = trim($requestArticle);
        foreach ($rows as $item) {
            if (!is_array($item)) continue;
            $brand = trim((string)($item['BRAND'] ?? ''));
            $pin   = trim((string)($item['PIN'] ?? $article));
            $name  = trim((string)($item['NAME'] ?? ''));
            if ($brand === '') continue;

            $key = mb_strtolower($brand) . '|' . mb_strtolower($pin);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $brand,
                    'article'     => $article,
                    'article_nr'  => $pin,
                    'description' => $name,
                ];
            }
        }
        return array_values($brands);
    }

    // ==================== ПРЕДЛОЖЕНИЯ (search) ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article, false);
        if (!$req) return [];
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) return null;
        $this->lastWithCrosses = $withCrosses;

        $fields = [
            'VKORG'      => $this->vkorg,
            'KUNNR_RG'   => $this->kunrg,
            'PIN'        => trim($article),
            // 1 — без аналогов (рекомендуется, если BRAND не пуст), 2 — с аналогами.
            'QUERY_TYPE' => $withCrosses ? 2 : 1,
        ];
        if (trim($brand) !== '') $fields['BRAND'] = trim($brand);
        if ($this->program !== '') $fields['PROGRAM'] = $this->program;
        if ($this->kunza !== '') $fields['KUNNR_ZA'] = $this->kunza;
        if ($this->incoterms !== '') $fields['INCOTERMS'] = $this->incoterms;
        if ($this->vbeln !== '') $fields['VBELN'] = $this->vbeln;

        return [
            'url'     => $this->baseUrl . '/ws_search/search?format=json',
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => http_build_query($fields),
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        $rows = $this->unwrapArray($data);
        if (!is_array($rows)) return $results;

        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);
        $withCrosses = $this->lastWithCrosses;

        foreach ($rows as $item) {
            if (!is_array($item)) continue;

            $itemBrand  = trim((string)($item['BRAND'] ?? ''));
            $itemNumber = trim((string)($item['PIN'] ?? ''));
            $itemName   = trim((string)($item['NAME'] ?? ''));
            $keyzak     = trim((string)($item['KEYZAK'] ?? ''));
            $price      = (float)str_replace(',', '.', (string)($item['PRICE'] ?? 0));

            if ($itemBrand === '' || $itemNumber === '' || $keyzak === '' || $price <= 0) continue;

            if (!$withCrosses) {
                if ($normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand) continue;
                if ($normArt !== '' && BrandNormalizer::normalizeArticle($itemNumber) !== $normArt) continue;
            }

            $qty     = $this->parseQty((string)($item['RVALUE'] ?? ''));
            $isSched = ($qty <= 0);
            $minQty  = max(1, (int)($item['MINBM'] ?: 1));
            $retDays = (int)($item['RETDAYS'] ?? 0);
            $reliability = is_numeric($item['VENSL'] ?? null) ? max(0, min(100, (int)round((float)$item['VENSL']))) : null;

            [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday] =
                $this->resolveDelivery((string)($item['DLVDT'] ?? ''), (string)($item['WRNTDT'] ?? ''));

            $r = new SearchResultItem();
            $r->source            = $this->getCode();
            $r->article           = $itemNumber;
            $r->brand             = $itemBrand;
            $r->name              = $itemName ?: trim($itemBrand . ' ' . $itemNumber);
            $r->price             = $price;
            $r->quantity          = max(0, $qty);
            $r->unit              = 'шт.';
            $r->multiplicity      = $minQty;
            $r->isSched           = $isSched;
            $r->returnable        = $retDays > 0;
            $r->reliabilityPercent = $reliability;
            $r->supplierName      = $this->getName();
            $r->warehouse         = 'Армтек: ' . $keyzak;
            $r->stockId           = $keyzak . '|' . $itemNumber;
            $r->deliveryDays      = $deliveryDays;
            $r->deliveryPeriod    = $deliveryPeriod;
            $r->deliveryLabel     = $deliveryLabel;
            $r->deliveryTimeLabel = $deliveryTimeLabel;
            $r->deliveryToday     = $deliveryToday;

            $r->raw = [
                'keyzak'   => $keyzak,
                'retdays'  => $retDays,
                'analog'   => $item['ANALOG'] ?? null,
                'typeb'    => $item['TYPEB'] ?? null,
                'dlvdt'    => $item['DLVDT'] ?? null,
                'wrntdt'   => $item['WRNTDT'] ?? null,
            ];

            // Для оформления заказа (см. SupplierOrderable::placeOrder()) — KEYZAK
            // обязателен у createOrder (без него система ищет остатки только на
            // основном складе КОНТУР и, если там пусто, заказ не будет создан).
            $r->orderMeta = [
                'keyzak' => $keyzak,
                'pin'    => $itemNumber,
                'brand'  => $itemBrand,
            ];

            $results[] = $r;
            if (count($results) >= 160) break;
        }

        $seen = []; $unique = [];
        foreach ($results as $it) {
            $dk = ($it->stockId ?: '') . '|' . $it->price;
            if (!isset($seen[$dk])) { $seen[$dk] = true; $unique[] = $it; }
        }

        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

        return array_slice($unique, 0, 30);
    }

    /**
     * Срок доставки Армтек. DLVDT — ожидаемая дата поставки, WRNTDT —
     * гарантированная (используется, только если DLVDT не пришла). Формат
     * дат по документации — YYYYMMDDHHIISS. В отличие от Автопитера/Авторуси
     * здесь НЕТ подтверждённого бизнес-правила о буфере поверх значений API —
     * показываем то, что вернул сервис, как есть.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool}
     */
    private function resolveDelivery(string $dlvdt, string $wrntdt): array
    {
        $raw = $dlvdt !== '' ? $dlvdt : $wrntdt;
        if ($raw === '' || !preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?/', $raw, $m)) {
            return [null, null, null, null, false];
        }
        $ts = mktime((int)($m[4] ?? 0), (int)($m[5] ?? 0), 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        if (!$ts) return [null, null, null, null, false];

        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $tsDay         = strtotime(date('Y-m-d', $ts));
        $days          = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
        $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $ts));
        $timeLabel     = (isset($m[4]) && $m[4] !== '') ? date('H:i', $ts) : null;
        $hours         = max(0, (int)ceil(($ts - $now) / 3600));

        return [$days, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart];
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
        $fields = [
            'VKORG' => $this->vkorg,
            'KUNRG' => $this->kunrg,
        ];
        if ($this->kunwe !== '')     $fields['KUNWE']     = $this->kunwe;
        if ($this->kunza !== '')     $fields['KUNZA']     = $this->kunza;
        if ($this->incoterms !== '') $fields['INCOTERMS'] = $this->incoterms;
        if ($this->vbeln !== '')     $fields['VBELN']     = $this->vbeln;

        $orderedItems = [];
        $skipped = 0;
        $idx = 0;
        foreach ($items as $item) {
            $keyzak = trim((string)($item['order_meta']['keyzak'] ?? ''));
            $pin    = trim((string)($item['order_meta']['pin'] ?? $item['article'] ?? ''));
            $brand  = trim((string)($item['order_meta']['brand'] ?? $item['brand'] ?? ''));
            $qty    = (int)($item['quantity'] ?? 0);
            if ($keyzak === '' || $pin === '' || $brand === '' || $qty <= 0) { $skipped++; continue; }

            $fields["ITEMS[{$idx}][PIN]"]    = $pin;
            $fields["ITEMS[{$idx}][BRAND]"]  = $brand;
            $fields["ITEMS[{$idx}][KWMENG]"] = (string)$qty;
            $fields["ITEMS[{$idx}][KEYZAK]"] = $keyzak;
            if (!empty($item['comment'])) {
                $fields["ITEMS[{$idx}][COMMENT]"] = mb_substr((string)$item['comment'], 0, 100);
            }
            $orderedItems[$idx] = (int)($item['basket_item_id'] ?? 0);
            $idx++;
        }

        if ($idx === 0) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет keyzak/pin/brand в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        // Армтек, в отличие от Иксоры/Авторуси/Росско, документирует отдельный
        // метод createTestOrder — реальный тестовый режим, а не имитация без
        // отправки запроса.
        $method = $test ? 'createTestOrder' : 'createOrder';

        $this->log("placeOrder: method={$method} items={$idx} skipped={$skipped} fields=" . json_encode($fields, JSON_UNESCAPED_UNICODE));

        $resp = $this->execCurl([
            'url'     => $this->baseUrl . "/ws_order/{$method}?format=json",
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => http_build_query($fields),
        ]);

        $this->log('placeOrder: response body=' . substr((string)$resp, 0, 4000));

        if ($resp === null) {
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'http_error'];
        }

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            return ['http_code' => 200, 'success' => false, 'raw' => ['_raw_text' => $resp], 'error' => 'invalid_json'];
        }

        $respItems = $this->unwrapArray($decoded['RESP']['ITEMS'] ?? $decoded['ITEMS'] ?? $decoded);
        if (!is_array($respItems)) {
            return ['http_code' => 200, 'success' => false, 'raw' => $decoded, 'error' => 'unparsable_response'];
        }

        // Сопоставление позиций ответа с basket_item_id — по индексу массива:
        // ITEMS в ответе документированы как эхо входных полей построчно в том
        // же порядке и количестве, что и запрос (в отличие от Авторуси/Иксоры
        // здесь нет отдельного непрозрачного ключа для сопоставления).
        $itemReferences = [];
        $itemsRaw = [];
        $i = 0;
        foreach ($respItems as $respItem) {
            $basketItemId = $orderedItems[$i] ?? 0;
            $i++;
            if (!is_array($respItem) || $basketItemId <= 0) continue;

            $resultRows = $this->unwrapArray($respItem['RESULT'] ?? []);
            $itemsRaw[] = ['index' => $i - 1, 'error' => $respItem['ERROR'] ?? null, 'result' => $resultRows];

            if (!is_array($resultRows)) continue;
            foreach ($resultRows as $row) {
                if (!is_array($row)) continue;
                $vbeln = trim((string)($row['VBELN'] ?? ''));
                $posnr = trim((string)($row['POSNR'] ?? ''));
                $block = trim((string)($row['BLOCK'] ?? ''));
                // BLOCK: A — не блокирован, B — блокирован, C — закрыт,
                // D — разблокирован вручную. Только A/D считаем подтверждением.
                if ($vbeln === '' || $posnr === '' || !in_array($block, ['A', 'D', ''], true)) continue;
                $itemReferences[$basketItemId] = $vbeln . ':' . $posnr;
                break;
            }
        }

        $success = !empty($itemReferences);

        return [
            'http_code'       => 200,
            'success'         => $success,
            'raw'             => ['items' => $itemsRaw],
            'error'           => $success ? null : 'order_rejected',
            'item_references' => $itemReferences,
        ];
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference — составной "{VBELN}:{POSNR}" (см. placeOrder()::item_references).
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$vbeln, $posnr] = explode(':', $reference, 2);
        $vbeln = trim($vbeln);
        $posnr = trim($posnr);
        if ($vbeln === '' || $posnr === '') return [];

        $fields = [
            'VKORG' => $this->vkorg,
            'KUNRG' => $this->kunrg,
            'ORDER' => $vbeln,
        ];

        $resp = $this->execCurl([
            'url'     => $this->baseUrl . '/ws_order/getOrder2?' . http_build_query($fields) . '&format=json',
            'headers' => [$this->authHeader(), 'Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ]);

        $this->log("fetchOrderStatusByReference({$reference}): response body=" . substr((string)$resp, 0, 4000));

        if ($resp === null) return [];

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) return [];

        $resp2 = $decoded['RESP'] ?? $decoded;
        $header = $this->unwrapArray($resp2['HEADER'] ?? []);
        $header = is_array($header) ? ($header[0] ?? $header) : [];
        $itemsRows = $this->unwrapArray($resp2['ITEMS'] ?? []);
        if (!is_array($itemsRows)) return [];

        $position = null;
        foreach ($itemsRows as $row) {
            if (is_array($row) && trim((string)($row['POSNR'] ?? '')) === $posnr) { $position = $row; break; }
        }
        if ($position === null) $position = $itemsRows[0] ?? null;
        if (!is_array($position)) return [];

        $statusText = trim((string)($position['STATUS'] ?? ''));

        return [[
            'order_number'    => $vbeln,
            'state_id'        => $statusText !== '' ? $statusText : null,
            'state_text'      => $statusText !== '' ? $statusText : (string)($header['ORDER_STATUS'] ?? ''),
            'stage'           => $this->normalizeStage($statusText, (string)($header['ORDER_STATUS'] ?? '')),
            'expected_date'   => $position['DLVRD'] ?? null,
            'guaranteed_date' => $position['WRNTD'] ?? null,
            'store_count'     => null,
            'release_count'   => is_numeric($position['KWMENG_R'] ?? null) ? (int)$position['KWMENG_R'] : null,
            'refusal_count'   => is_numeric($position['KWMENG_REJ'] ?? null) ? (int)$position['KWMENG_REJ'] : null,
            'comment'         => $position['NOTE'] ?? null,
            'raw'             => ['status' => $statusText, 'orderStatus' => $header['ORDER_STATUS'] ?? null],
        ]];
    }

    /**
     * STATUS позиции (getOrder/getOrder2) — конечный документированный набор
     * значений: пустая строка | "позиция полностью поставлена" |
     * "позиция частично поставлена" | "позиция частично отклонена" |
     * "позиция полностью отклонена". ORDER_STATUS шапки заказа — "Создан" |
     * "В работе" | "Закрыт" | "Отклонен" — используется как запасной
     * источник, если у позиции STATUS ещё пуст.
     */
    private function normalizeStage(string $itemStatus, string $orderStatus): string
    {
        $s = mb_strtolower($itemStatus);
        if (str_contains($s, 'полностью отклонена')) return 'refused';
        if (str_contains($s, 'полностью поставлена')) return 'ready';
        if (str_contains($s, 'частично')) return 'in_transit';

        $os = mb_strtolower($orderStatus);
        if ($os === 'отклонен') return 'refused';
        if ($os === 'закрыт') return 'ready';
        if ($os === 'в работе') return 'in_transit';
        return 'ordered';
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    /**
     * Ответы Армтека могут прийти как голый массив, так и обёрнутыми в
     * {"RESP": {...}} или с одиночным объектом вместо списка из одного
     * элемента — не полагаемся заранее на один формат (тот же принцип, что и
     * у Авторуси/ПартКома), сверить на первом живом ответе.
     */
    private function unwrapArray($data)
    {
        if (is_array($data) && isset($data['RESP'])) $data = $data['RESP'];
        if (is_array($data) && isset($data['ARRAY'])) $data = $data['ARRAY'];
        if (is_array($data) && !empty($data) && array_keys($data) !== range(0, count($data) - 1)) {
            // Ключи не 0,1,2... — значит это одиночный объект (напр. один
            // результат сериализован без обёртки в список из одного элемента),
            // а не массив строк. Оборачиваем в список для единообразной
            // обработки вызывающим кодом.
            $keys = array_keys($data);
            if (isset($keys[0]) && is_string($keys[0]) && $keys[0] !== '' && ctype_upper($keys[0][0])) {
                return [$data];
            }
        }
        return $data;
    }

    private function parseQty(string $val): int
    {
        $val = trim($val);
        if ($val === '') return 0;
        if ($val[0] === '>' || $val[0] === '<') return max(1, (int)substr($val, 1));
        return max(0, (int)$val);
    }

    private function execCurl(array $req): ?string
    {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $req['headers'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
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

        if ($err || $httpCode !== 200) {
            $this->log("HTTP {$httpCode} err={$err} url={$req['url']}");
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
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $file = $root . '/upload/logs/armtek_' . date('Y-m-d') . '.log';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}
