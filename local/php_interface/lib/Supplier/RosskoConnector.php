<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class RosskoConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $key1;
    private string $key2;
    private string $deliveryId;
    private ?string $addressId;
    private int $timeout;
    private int $paymentId;
    private int $requisiteId;
    private string $contactName;
    private string $contactPhone;

    public function __construct(array $config = [])
    {
        $this->key1        = $config['KEY1']        ?? '';
        $this->key2        = $config['KEY2']        ?? '';
        $this->deliveryId  = $config['DELIVERY_ID'] ?? '000000002';
        $this->addressId   = $config['ADDRESS_ID']  ?? '71520';
        $this->timeout     = $config['TIMEOUT']     ?? 8;
        $this->paymentId   = (int)($config['PAYMENT_ID']   ?? 1);
        $this->requisiteId = (int)($config['REQUISITE_ID'] ?? 0);
        $this->contactName  = $config['CONTACT_NAME']  ?? '';
        $this->contactPhone = $config['CONTACT_PHONE'] ?? '';
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

        // Разделение: свои vs партнёрские
        $own = [];
        $other = [];
        foreach ($unique as $item) {
            // Партнерский склад = чужой (по description)
            if (mb_stripos($item->warehouse, 'Партнерский') !== false || mb_stripos($item->warehouse, 'Партнёрский') !== false) {
                $other[] = $item;
            } else {
                $own[] = $item;
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

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    public function placeOrder(array $items, bool $test = false): array
    {
        // У API Росско нет флага тестового заказа в самом запросе — тестовый
        // режим переключается только вручную в личном кабинете на портале
        // Росско (тогда тестовые заказы живут там 24ч и видны только в
        // GetOrders). Мы не можем включить его из кода, поэтому, как и у
        // Москворечья, в тестовом режиме просто не отправляем запрос —
        // безопаснее пропустить, чем случайно оформить реальный заказ.
        if ($test) {
            $this->log('placeOrder: тестовый режим не поддерживается API Росско напрямую (переключается в личном кабинете поставщика) — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        $parts = [];
        $partKeys = [];
        $skipped = 0;
        foreach ($items as $item) {
            $stock   = trim((string)($item['order_meta']['stock'] ?? ''));
            $article = trim((string)($item['article'] ?? ''));
            $brand   = trim((string)($item['brand'] ?? ''));
            $qty     = (int)($item['quantity'] ?? 0);
            if ($stock === '' || $article === '' || $brand === '' || $qty <= 0) { $skipped++; continue; }

            $parts[] = [
                'partnumber' => $article,
                'brand'      => $brand,
                'stock'      => $stock,
                'count'      => $qty,
                // Лимит 50 символов по документации.
                'comment'    => mb_substr((string)($item['comment'] ?? ''), 0, 50),
            ];
            $partKeys[] = [
                'basket_item_id' => (int)($item['basket_item_id'] ?? 0),
                'partnumber'     => $article,
                'brand'          => $brand,
            ];
        }

        if (empty($parts)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет stock в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $orderComment = (string)($items[array_key_first($items)]['comment'] ?? '');
        $body = $this->buildCheckoutXml($parts, $orderComment);

        $this->log('placeOrder: request items=' . count($parts) . ' skipped=' . $skipped . ' body=' . $body);

        $ch = curl_init('https://api.rossko.ru/service/v2.1/GetCheckout');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: https://api.rossko.ru/GetCheckout'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log('placeOrder: response http=' . $httpCode . ' err=' . $err . ' body=' . substr((string)$resp, 0, 4000));

        if ($err || $httpCode !== 200 || empty($resp)) {
            return ['http_code' => $httpCode ?: null, 'success' => false, 'raw' => null, 'error' => $err ?: ('http_' . $httpCode)];
        }

        $xml = simplexml_load_string($resp);
        if ($xml === false || $xml === null) {
            return ['http_code' => $httpCode, 'success' => false, 'raw' => ['_raw_text' => $resp], 'error' => 'invalid_xml'];
        }

        $sn = $xml->xpath('//*[local-name()="success"]');
        $xmlSuccess = $sn && (string)$sn[0] === 'true';
        $msgNode = $xml->xpath('//*[local-name()="message"]');
        $message = $msgNode ? trim((string)$msgNode[0]) : '';

        // Очередь по ключу "бренд|артикул" (FIFO) — сопоставляем позиции ответа
        // с нашими basket_item_id. Подтверждено первым живым ответом (заказ
        // №187): Росско переформатирует partnumber в ответе (мы отправили
        // "AMDFL126", вернулось "AMD.FL126") — точное строковое сравнение не
        // совпадало, ни одна позиция не находилась, и реально созданный заказ
        // засчитывался как error. Сравниваем через BrandNormalizer::normalizeArticle()
        // (снимает точки/дефисы/пробелы), как и ПартКом при сверке артикулов.
        // Ответ ItemsList не содержит код склада, только partnumber/brand,
        // поэтому при заказе одного и того же артикула с двух РАЗНЫХ складов
        // Росско в одном вызове однозначно различить их нельзя — берём по
        // порядку появления в ответе (тот же принцип "best-effort", что у
        // Москворечье/Берга, а не молчаливый отказ).
        $queue = [];
        foreach ($partKeys as $pk) {
            $key = BrandNormalizer::normalize($pk['brand']) . '|' . BrandNormalizer::normalizeArticle($pk['partnumber']);
            $queue[$key][] = $pk['basket_item_id'];
        }

        $itemsRaw = [];
        $itemReferences = [];
        $itemNodes = $xml->xpath('//*[local-name()="ItemsList"]/*[local-name()="Item"]');
        foreach ((array)$itemNodes as $it) {
            $pn = trim((string)($it->xpath('*[local-name()="partnumber"]')[0] ?? ''));
            $br = trim((string)($it->xpath('*[local-name()="brand"]')[0] ?? ''));
            $orderId = (int)($it->xpath('*[local-name()="order_id"]')[0] ?? 0);
            $itemsRaw[] = [
                'partnumber' => $pn, 'brand' => $br, 'order_id' => $orderId,
                'count'      => (int)($it->xpath('*[local-name()="count"]')[0] ?? 0),
                'price'      => (float)($it->xpath('*[local-name()="price"]')[0] ?? 0),
            ];
            if ($orderId <= 0) continue;
            $key = BrandNormalizer::normalize($br) . '|' . BrandNormalizer::normalizeArticle($pn);
            if (!empty($queue[$key])) {
                $basketItemId = array_shift($queue[$key]);
                if ($basketItemId > 0) {
                    $itemReferences[$basketItemId] = $orderId . ':' . $pn . '|' . $br;
                }
            }
        }

        $errorsRaw = [];
        $errorNodes = $xml->xpath('//*[local-name()="ItemsErrorList"]/*[local-name()="ItemError"]');
        foreach ((array)$errorNodes as $e) {
            $errorsRaw[] = [
                'partnumber' => trim((string)($e->xpath('*[local-name()="partnumber"]')[0] ?? '')),
                'brand'      => trim((string)($e->xpath('*[local-name()="brand"]')[0] ?? '')),
                'message'    => trim((string)($e->xpath('*[local-name()="message"]')[0] ?? '')),
            ];
        }

        // success=true у Росско означает только "запрос обработан корректно" —
        // может вернуться success=true при ЧАСТИЧНОМ заказе (часть позиций в
        // ItemsErrorList, см. документацию). Нашим успехом считаем размещение
        // хотя бы одной позиции (та же логика частичного успеха, что у ПартКома).
        $success = $xmlSuccess && !empty($itemReferences);

        return [
            'http_code'       => $httpCode,
            'success'         => $success,
            'raw'             => ['success' => $xmlSuccess, 'message' => $message, 'items' => $itemsRaw, 'errors' => $errorsRaw],
            'error'           => $success ? null : ($message ?: 'order_rejected'),
            'item_references' => $itemReferences,
        ];
    }

    private function buildCheckoutXml(array $parts, string $comment): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $partsXml = '';
        foreach ($parts as $p) {
            $partsXml .= '<ns1:Part>'
                . '<ns1:partnumber>' . $esc($p['partnumber']) . '</ns1:partnumber>'
                . '<ns1:brand>' . $esc($p['brand']) . '</ns1:brand>'
                . '<ns1:stock>' . $esc($p['stock']) . '</ns1:stock>'
                . '<ns1:count>' . (int)$p['count'] . '</ns1:count>'
                . '<ns1:comment>' . $esc($p['comment']) . '</ns1:comment>'
                . '</ns1:Part>';
        }

        $body = '<ns1:GetCheckout xmlns:ns1="https://api.rossko.ru/">'
            . '<ns1:KEY1>' . $esc($this->key1) . '</ns1:KEY1>'
            . '<ns1:KEY2>' . $esc($this->key2) . '</ns1:KEY2>'
            . '<ns1:delivery>'
                . '<ns1:delivery_id>' . $esc($this->deliveryId) . '</ns1:delivery_id>'
                . '<ns1:address_id>' . $esc($this->addressId) . '</ns1:address_id>'
            . '</ns1:delivery>'
            . '<ns1:payment>'
                . '<ns1:payment_id>' . (int)$this->paymentId . '</ns1:payment_id>'
                . '<ns1:requisite_id>' . (int)$this->requisiteId . '</ns1:requisite_id>'
            . '</ns1:payment>'
            . '<ns1:contact>'
                . '<ns1:name>' . $esc($this->contactName) . '</ns1:name>'
                . '<ns1:phone>' . $esc($this->contactPhone) . '</ns1:phone>'
                . '<ns1:comment>' . $esc($comment) . '</ns1:comment>'
            . '</ns1:contact>'
            . '<ns1:delivery_parts>true</ns1:delivery_parts>'
            . '<ns1:PARTS>' . $partsXml . '</ns1:PARTS>'
            . '</ns1:GetCheckout>';

        return '<?xml version="1.0" encoding="utf-8"?>'
             . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soap:Body>' . $body . '</soap:Body>'
             . '</soap:Envelope>';
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference — составной "{order_id}:{partnumber}|{brand}" (см.
     * placeOrder()::item_references) — GetOrders возвращает позиции заказа без
     * какого-либо собственного построчного ID, только artikul/brand внутри
     * заказа, поэтому, как и у Москворечья/Берга, различаем позиции одного
     * заказа именно так.
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderIdRaw, $partKeyRaw] = explode(':', $reference, 2);
        $orderId = (int)$orderIdRaw;
        if ($orderId <= 0) return [];
        $partKeyParts  = explode('|', $partKeyRaw, 2);
        $wantPartnumber = $partKeyParts[0] ?? '';
        $wantBrand      = $partKeyParts[1] ?? '';

        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '<ns1:GetOrders xmlns:ns1="https://api.rossko.ru/">'
            . '<ns1:KEY1>' . $esc($this->key1) . '</ns1:KEY1>'
            . '<ns1:KEY2>' . $esc($this->key2) . '</ns1:KEY2>'
            . '<ns1:order_ids><ns1:id>' . (int)$orderId . '</ns1:id></ns1:order_ids>'
            . '</ns1:GetOrders>';
        $body = '<?xml version="1.0" encoding="utf-8"?>'
              . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
              . '<soap:Body>' . $body . '</soap:Body>'
              . '</soap:Envelope>';

        $ch = curl_init('https://api.rossko.ru/service/v2.1/GetOrders');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: https://api.rossko.ru/GetOrders'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log("fetchOrderStatusByReference({$reference}): response http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));

        if ($err || $httpCode !== 200 || empty($resp)) return [];

        $xml = simplexml_load_string($resp);
        if ($xml === false || $xml === null) return [];

        $sn = $xml->xpath('//*[local-name()="success"]');
        if (!$sn || (string)$sn[0] !== 'true') return [];

        $orderNodes = $xml->xpath('//*[local-name()="OrdersList"]/*[local-name()="Order"]');
        if (!$orderNodes) return [];

        $order = null;
        foreach ($orderNodes as $o) {
            $oid = (int)($o->xpath('*[local-name()="id"]')[0] ?? 0);
            if ($oid === $orderId) { $order = $o; break; }
        }
        if ($order === null) return [];

        $partNodes = $order->xpath('*[local-name()="parts"]/*[local-name()="part"]');
        if (!$partNodes) return [];

        $wantKey = BrandNormalizer::normalize($wantBrand) . '|' . BrandNormalizer::normalizeArticle($wantPartnumber);
        $part = null;
        foreach ($partNodes as $p) {
            $pn = trim((string)($p->xpath('*[local-name()="partnumber"]')[0] ?? ''));
            $br = trim((string)($p->xpath('*[local-name()="brand"]')[0] ?? ''));
            $key = BrandNormalizer::normalize($br) . '|' . BrandNormalizer::normalizeArticle($pn);
            if ($key === $wantKey) {
                $part = $p;
                break;
            }
        }
        // Позицию по артикулу/бренду не нашли — как и у остальных коннекторов,
        // безопаснее вернуть первую позицию заказа, чем молчать.
        if ($part === null) $part = $partNodes[0];

        $statusNode = $part->xpath('*[local-name()="status"]');
        $statusCode = $statusNode ? (int)$statusNode[0] : null;
        $comment    = trim((string)($part->xpath('*[local-name()="comment"]')[0] ?? ''));
        $deliveryDateNode = $order->xpath('*[local-name()="delivery_date"]');
        $deliveryDate = $deliveryDateNode ? trim((string)$deliveryDateNode[0]) : '';

        return [[
            'order_number'    => (string)$orderId,
            'state_id'        => $statusCode !== null ? (string)$statusCode : null,
            'state_text'      => $statusCode !== null ? (self::STATUS_LABELS[$statusCode] ?? ('Статус ' . $statusCode)) : null,
            'stage'           => $this->normalizeStage($statusCode),
            'expected_date'   => $deliveryDate !== '' ? $deliveryDate : null,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $comment !== '' ? $comment : null,
            'raw'             => ['status' => $statusCode, 'partnumber' => (string)($part->xpath('*[local-name()="partnumber"]')[0] ?? '')],
        ]];
    }

    // Полный официальный словарь статусов строки заказа (см. документацию
    // GetOrders/part.status) — в отличие от Берга/ПартКома, угадывать не
    // требуется, весь список задокументирован явно.
    // 32-36 — статусы уже ПОСЛЕ отгрузки (согласование/экспертиза/отклонение
    // возврата) — заказ был выполнен, дальнейшая судьба возврата не
    // моделируется нашей шкалой (ordered/in_transit/ready/refused), поэтому
    // остаются 'ready', а не 'refused' — иначе аггрегирующий крон (см.
    // supplier_order_status_aggregate.php) ошибочно отменил бы уже
    // исполненный заказ из-за пост-фактум логистики возврата.
    private const STATUS_STAGE_MAP = [
        0  => 'ordered',    // ждёт подтверждения
        1  => 'in_transit', // комплектуется
        2  => 'ready',      // отгружено
        3  => 'ready',      // готово к отгрузке
        5  => 'ordered',    // ожидаем поступление
        6  => 'in_transit', // на складе филиала
        7  => 'refused',    // нет в наличии
        8  => 'refused',    // отменён клиентом
        9  => 'refused',    // просрочен
        31 => 'ordered',    // ожидаем товар на складе
        32 => 'ready',      // возврат на согласовании
        33 => 'ready',      // товар на экспертизе
        34 => 'ready',      // возврат отклонён
        35 => 'ready',      // возврат частично отклонён
        36 => 'ready',      // товар возвращён
    ];

    private const STATUS_LABELS = [
        0  => 'Ждёт подтверждения', 1  => 'Комплектуется', 2  => 'Отгружено',
        3  => 'Готово к отгрузке', 5  => 'Ожидаем поступление', 6  => 'На складе филиала',
        7  => 'Нет в наличии', 8  => 'Отменён клиентом', 9  => 'Просрочен',
        31 => 'Ожидаем товар на складе', 32 => 'Возврат на согласовании',
        33 => 'Товар на экспертизе', 34 => 'Возврат отклонён',
        35 => 'Возврат частично отклонён', 36 => 'Товар возвращён',
    ];

    private function normalizeStage(?int $statusCode): string
    {
        if ($statusCode === null) return 'ordered';
        return self::STATUS_STAGE_MAP[$statusCode] ?? 'ordered';
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function parseStock(\SimpleXMLElement $stock, \SimpleXMLElement $part): SearchResultItem
    {
        $qty = (int)($stock->xpath('*[local-name()="count"]')[0] ?? 0);
        $del = (int)($stock->xpath('*[local-name()="delivery"]')[0] ?? 0);
        $sid = trim((string)($stock->xpath('*[local-name()="id"]')[0] ?? ''));

        [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($stock, $del);

        $r = new SearchResultItem();
        $r->source            = $this->getCode();
        $r->article           = trim((string)($part->xpath('*[local-name()="partnumber"]')[0] ?? ''));
        $r->brand             = trim((string)($part->xpath('*[local-name()="brand"]')[0] ?? ''));
        $r->name              = trim((string)($part->xpath('*[local-name()="name"]')[0] ?? ''));
        $r->price             = (float)($stock->xpath('*[local-name()="price"]')[0] ?? 0);
        $r->quantity          = $qty;
        $r->deliveryDays      = $deliveryDays;
        $r->deliveryPeriod    = $deliveryPeriod;
        $r->deliveryLabel     = $deliveryLabel;
        $r->deliveryTimeLabel = $deliveryTimeLabel;
        $r->deliveryToday     = $deliveryToday;
        $r->deliveryDeadline  = $deliveryDeadline;
        $r->warehouse         = trim((string)($stock->xpath('*[local-name()="description"]')[0] ?? ''));
        $r->stockId           = $sid;
        $r->supplierName      = $this->getName();
        $r->isSched           = $qty <= 0;
        $r->multiplicity      = max(1, (int)($stock->xpath('*[local-name()="multiplicity"]')[0] ?? 1));
        $r->unit              = 'шт.';
        $r->returnable        = true;

        // Для оформления заказа (см. SupplierOrderable::placeOrder()) — GetCheckout
        // принимает именно этот код склада как OrderItem.stock (тот же id, что
        // выше идёт в stockId).
        $r->orderMeta = ['stock' => $sid];

        return $r;
    }

    /**
     * Срок доставки Rossko. GetSearch отдаёт deliveryStart/deliveryEnd УЖЕ
     * ПО КАЖДОМУ СКЛАДУ прямо в ответе поиска — это точное окно "от-до" для
     * конкретного предложения, не нужен ни отдельный запрос, ни угадывание.
     * (Раньше здесь был отдельный SOAP-запрос GetDeliveryDetails с почасовым
     * кэшем, который подбирал "волну" по ОБЩЕЙ дате — приблизительно и с
     * лишним сетевым вызовом на каждый поиск; deliveryStart/deliveryEnd этого
     * не требуют и точнее, т.к. привязаны именно к этому складу.)
     * Если дат нет (обычно у дополнительных складов, куда этот способ
     * доставки не распространяется) — используем только <delivery> (дни).
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(\SimpleXMLElement $stock, int $deliveryDays): array
    {
        $startRaw = trim((string)($stock->xpath('*[local-name()="deliveryStart"]')[0] ?? ''));
        $endRaw   = trim((string)($stock->xpath('*[local-name()="deliveryEnd"]')[0] ?? ''));

        $now    = time();
        $fromTs = $startRaw !== '' ? strtotime($startRaw) : null;
        $toTs   = $endRaw   !== '' ? strtotime($endRaw)   : null;

        if ($fromTs && $fromTs > $now) {
            $todayStart    = strtotime('today');
            $tomorrowStart = strtotime('tomorrow');
            $tsDay         = strtotime(date('Y-m-d', $fromTs));
            $days          = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
            $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $fromTs));
            $timeLabel     = ($toTs && $toTs > $fromTs) ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $fromTs);
            $hours         = max(0, (int)ceil(($fromTs - $now) / 3600));
            return [$days, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart, null];
        }

        $days     = max(0, $deliveryDays);
        $dayLabel = $days === 0 ? 'Сегодня' : ($days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days")));
        return [$days, $days * 24, $dayLabel, null, $days === 0, null];
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

}
