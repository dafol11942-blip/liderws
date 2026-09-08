<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class AutorussConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $login;
    private string $passwordMd5;
    private string $baseUrl;
    private int $timeout;
    private bool $lastWithCrosses = false;
    private int $paymentMethod;
    private string $shipmentAddress;
    private string $shipmentMethod;

    public function __construct(array $config = [])
    {
        $this->login       = $config['LOGIN']       ?? '';
        $this->passwordMd5 = $config['PASSWORD_MD5'] ?? '';
        $this->baseUrl     = $config['BASE_URL']     ?? 'https://autorus.public.api.abcp.ru';
        $this->timeout     = $config['TIMEOUT']      ?? 10;
        $this->paymentMethod   = (int)($config['PAYMENT_METHOD'] ?? 0);
        $this->shipmentAddress = (string)($config['SHIPMENT_ADDRESS'] ?? '');
        $this->shipmentMethod  = (string)($config['SHIPMENT_METHOD'] ?? '');
    }

    public function getCode(): string       { return 'autoruss'; }
    public function getName(): string       { return 'Авторусь'; }
    public function getWarehousePrefix(): string { return 'ar'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return !empty($this->login) && !empty($this->passwordMd5);
    }

    // ==================== АВТОРИЗАЦИЯ ====================

    private function authQuery(): string
    {
        return 'userlogin=' . urlencode($this->login)
            . '&userpsw=' . urlencode($this->passwordMd5);
    }

    // ==================== БРЕНДЫ ====================

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
        $url = $this->baseUrl . '/search/brands/?'
            . $this->authQuery()
            . '&number=' . urlencode($article)
            . '&useOnlineStocks=1';
        return [
            'url'     => $url,
            'headers' => ['Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $brands;

        $article = trim($requestArticle);
        foreach ($data as $item) {
            if (!is_array($item)) continue;
            $b  = trim((string)($item['brand'] ?? ''));
            $n  = trim((string)($item['number'] ?? ''));
            $nf = trim((string)($item['numberFix'] ?? $n));
            if (!$b || !$nf) continue;

            $key = mb_strtolower($b) . '|' . mb_strtolower($nf);
            if (!isset($brands[$key])) {
                $brands[$key] = [
                    'brand'       => $b,
                    'article'     => $article,
                    'article_nr'  => $nf,
                    'description' => (string)($item['description'] ?? ''),
                ];
            }
        }
        return array_values($brands);
    }

    // ==================== ПРЕДЛОЖЕНИЯ ====================

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
        $this->lastWithCrosses = $withCrosses;

        $url = $this->baseUrl . '/search/articles/?'
            . $this->authQuery()
            . '&number=' . urlencode($article)
            . '&brand=' . urlencode($brand)
            . '&useOnlineStocks=1';

        // withOutAnalogs: если кроссы НЕ нужны → исключаем аналоги
        if (!$withCrosses) {
            $url .= '&withOutAnalogs=1';
        }

        return [
            'url'     => $url,
            'headers' => ['Accept: application/json'],
            'method'  => 'GET',
            'body'    => null,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $results;

        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);
        $withCrosses = $this->lastWithCrosses;

        foreach ($data as $item) {
            if (!is_array($item)) continue;

            $itemBrand  = trim((string)($item['brand'] ?? ''));
            $itemNumber = (string)($item['number'] ?? '');
            $itemNumberFix = (string)($item['numberFix'] ?? $itemNumber);

            // Фильтрация по бренду — только для точного поиска
            if (!$withCrosses) {
                if ($normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand) {
                    continue;
                }
                if ($normArt !== '' && $itemNumberFix !== ''
                    && BrandNormalizer::normalizeArticle($itemNumberFix) !== $normArt) {
                    continue;
                }
            } else {
                // Cross: выкидываем "однофамильцев" — тот же артикул, другой бренд
                if ($normArt !== '' && $itemNumberFix !== ''
                    && BrandNormalizer::normalizeArticle($itemNumberFix) === $normArt
                    && $normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand
                ) {
                    continue;
                }
            }

            // availability: >0 = кол-во; -1,-2,-3 = неточное; -10 = под заказ; 0 = нет
            $avail = (int)($item['availability'] ?? 0);
            if ($avail > 0) {
                $qty     = $avail;
                $isSched = false;
            } elseif ($avail < 0 && $avail > -10) {
                // -1, -2, -3 — неточное наличие, считаем как 1+
                $qty     = max(1, abs($avail));
                $isSched = false;
            } else {
                // -10 (под заказ), 0, или другое → под заказ
                $qty     = 0;
                $isSched = true;
            }

            [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($item, $isSched);

            $r = new SearchResultItem();
            $r->source            = $this->getCode();
            $r->article           = $itemNumberFix !== '' ? $itemNumberFix : $article;
            $r->brand             = $itemBrand !== '' ? $itemBrand : $brand;
            $r->name              = (string)($item['description'] ?? '');
            $r->price             = (float)($item['price'] ?? 0);
            $r->quantity          = $qty;
            $r->deliveryDays      = $deliveryDays;
            $r->deliveryPeriod    = $deliveryPeriod;
            $r->deliveryLabel     = $deliveryLabel;
            $r->deliveryTimeLabel = $deliveryTimeLabel;
            $r->deliveryToday     = $deliveryToday;
            $r->deliveryDeadline  = $deliveryDeadline;
            $r->warehouse         = 'Авторусь: ' . ((string)($item['supplierDescription'] ?? $item['distributorId'] ?? 'Склад'));
            $r->stockId           = (string)($item['supplierCode'] ?? '') . '|' . (string)($item['itemKey'] ?? '');
            $r->supplierName      = $this->getName();
            $r->isSched           = $isSched;
            $r->multiplicity      = max(1, (int)($item['packing'] ?? 1));
            $r->unit              = 'шт.';
            $r->returnable        = empty($item['noReturn']);
            // reliabilityPercent НЕ заполняется из deliveryProbability: подтверждено
            // логом реального ответа search/articles — поле там всегда 0, а
            // descriptionOfDeliveryProbability всегда "". Реальная цифра (у Авторусь
            // в личном кабинете, напр. 76.4%) считается только отдельным детальным
            // запросом по одному предложению, которого этот (списочный) эндпоинт не
            // делает — дёргать его на каждую строку результата поиска было бы слишком
            // медленно. Показывать всем подряд ложные "0% / 100% отказ" хуже, чем не
            // показывать бейдж вовсе.

            $r->raw = [
                'deliveryPeriod'      => $item['deliveryPeriod'] ?? null,
                'deliveryPeriodMax'   => $item['deliveryPeriodMax'] ?? null,
                'deadlineReplace'     => $item['deadlineReplace'] ?? null,
                'supplierCode'        => $item['supplierCode'] ?? null,
                'itemKey'             => $item['itemKey'] ?? null,
                'distributorId'       => $item['distributorId'] ?? null,
                'lastUpdateTime'      => $item['lastUpdateTime'] ?? null,
                'deliveryProbability' => $item['deliveryProbability'] ?? null,
                'noReturn'            => $item['noReturn'] ?? null,
                'isAnalog'            => $item['isAnalog'] ?? null,
            ];

            // Для оформления заказа (см. SupplierOrderable::placeOrder()) —
            // supplierCode/itemKey нужны basket/add и orders/instant как есть;
            // deadline/deadline_max — СЫРЫЕ часы от API (до нашей +48ч бизнес-
            // надбавки в resolveDelivery()) для basket/shipmentDates, которому
            // нужен реальный срок поставки, а не наш показанный клиенту запас.
            $r->orderMeta = [
                'supplier_code' => (string)($item['supplierCode'] ?? ''),
                'item_key'      => (string)($item['itemKey'] ?? ''),
                'deadline'      => isset($item['deliveryPeriod']) ? (int)$item['deliveryPeriod'] : null,
                'deadline_max'  => isset($item['deliveryPeriodMax']) ? (int)$item['deliveryPeriodMax'] : null,
            ];

            if ($r->price <= 0 && $r->quantity <= 0) continue;
            $results[] = $r;
            if (count($results) >= 160) break;
        }

        // Дедупликация
        $seen = [];
        $unique = [];
        foreach ($results as $res) {
            $dk = ($res->stockId ?: '') . '|' . $res->price;
            if (!isset($seen[$dk])) {
                $seen[$dk] = true;
                $unique[] = $res;
            }
        }

        // Autoruss — маркетплейс, своих складов нет. Топ-10 по срокам+цене.
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
     * Срок доставки Авторусь. API отдаёт deliveryPeriod ("от", часы) и
     * deliveryPeriodMax ("до", часы); если deliveryPeriodMax не заполнен,
     * по документации вместо deliveryPeriod используется deadlineReplace.
     *
     * Поверх реальных цифр API — осознанный запас под логистику Авторуси
     * (маркетплейс без своих складов, реальная доставка клиенту дольше, чем
     * заявляет поставщик): 0 часов ("в наличии") превращается в фикс. 48-72ч,
     * иначе к обеим границам добавляется +48ч. Это подтверждённое бизнес-
     * правило, а не приближение, которое можно убрать.
     *
     * Для позиций "под заказ" (isSched, наличие неточное/нулевое) точное
     * окно не показываем — только приблизительный день, как и раньше.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $item, bool $isSched): array
    {
        $deliveryPeriod    = (int)($item['deliveryPeriod'] ?? 0);
        $deliveryPeriodMax = isset($item['deliveryPeriodMax']) ? (int)$item['deliveryPeriodMax'] : 0;
        $deadlineReplace   = $item['deadlineReplace'] ?? null;

        if ($deliveryPeriodMax <= 0 && $deadlineReplace !== null && $deadlineReplace !== '' && is_numeric($deadlineReplace)) {
            $deliveryPeriod = (int)$deadlineReplace;
        }

        if ($deliveryPeriod <= 0) {
            $deliveryPeriod    = 48;
            $deliveryPeriodMax = 72;
        } else {
            $deliveryPeriodMax = ($deliveryPeriodMax > 0 ? $deliveryPeriodMax : $deliveryPeriod) + 48;
            $deliveryPeriod   += 48;
        }
        if ($deliveryPeriodMax <= $deliveryPeriod) {
            $deliveryPeriodMax = $deliveryPeriod + 24;
        }

        if ($isSched) {
            $days     = max(1, (int)ceil($deliveryPeriod / 24));
            $dayLabel = $days === 1 ? 'Завтра' : date('d.m', strtotime("+{$days} days"));
            return [$days, $deliveryPeriod, $dayLabel, null, false, null];
        }

        $now           = time();
        $todayStart    = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $fromTs = $now + $deliveryPeriod * 3600;
        $toTs   = $now + $deliveryPeriodMax * 3600;

        $tsDay     = strtotime(date('Y-m-d', $fromTs));
        $days      = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
        $dayLabel  = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $fromTs));
        $timeLabel = ($toTs > $fromTs) ? (date('H:i', $fromTs) . ' - ' . date('H:i', $toTs)) : date('H:i', $fromTs);

        return [$days, $deliveryPeriod, $dayLabel, $timeLabel, $tsDay <= $todayStart, null];
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

        // Сначала получаем бренды
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

        // Дедупликация
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
        // У API ABCP (Авторусь) нет флага тестового заказа в orders/instant —
        // как и у Росско, в тестовом режиме запрос не отправляем вообще,
        // безопаснее пропустить, чем случайно оформить реальный заказ.
        if ($test) {
            $this->log('placeOrder: тестовый режим не поддерживается API Авторуси (флага тестового заказа нет) — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        $positions = [];
        $queueByItemKey = [];
        $skipped = 0;
        $minDeadline = null;
        $maxDeadline = null;
        foreach ($items as $item) {
            $supplierCode = trim((string)($item['order_meta']['supplier_code'] ?? ''));
            $itemKey      = trim((string)($item['order_meta']['item_key'] ?? ''));
            $article      = trim((string)($item['article'] ?? ''));
            $brand        = trim((string)($item['brand'] ?? ''));
            $qty          = (int)($item['quantity'] ?? 0);
            if ($supplierCode === '' || $itemKey === '' || $article === '' || $brand === '' || $qty <= 0) { $skipped++; continue; }

            $idx = count($positions);
            $positions[$idx] = [
                'number'       => $article,
                'brand'        => $brand,
                'supplierCode' => $supplierCode,
                'itemKey'      => $itemKey,
                'quantity'     => $qty,
                'comment'      => (string)($item['comment'] ?? ''),
            ];
            $queueByItemKey[$itemKey][] = (int)($item['basket_item_id'] ?? 0);

            $d    = isset($item['order_meta']['deadline']) ? (int)$item['order_meta']['deadline'] : null;
            $dMax = isset($item['order_meta']['deadline_max']) ? (int)$item['order_meta']['deadline_max'] : $d;
            if ($d !== null) {
                $minDeadline = $minDeadline === null ? $d : min($minDeadline, $d);
                $maxDeadline = $maxDeadline === null ? ($dMax ?? $d) : max($maxDeadline, $dMax ?? $d);
            }
        }

        if (empty($positions)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет supplier_code/item_key в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        // shipmentDate обязателен, если в ЛК включена опция "Дни отгрузки" —
        // подтверждено вживую: basket/shipmentDates отдаёт непустой список для
        // этого аккаунта. Берём САМУЮ РАННЮЮ дату из тех, что сам API считает
        // валидной для диапазона сроков поставки этих позиций.
        $shipmentDate = $this->resolveShipmentDate($minDeadline, $maxDeadline);

        $orderComment = (string)($items[array_key_first($items)]['comment'] ?? '');

        // Наш orderId — числовой префикс до "_" в reference позиции (см.
        // dispatchSupplierOrders(): "{orderId}_{basketItemId}") — передаём как
        // clientOrderNumber для трассировки в личном кабинете Авторуси.
        $ourOrderRef = null;
        $firstRef = (string)($items[array_key_first($items)]['reference'] ?? '');
        if (preg_match('/^(\d+)_/', $firstRef, $m)) $ourOrderRef = (int)$m[1];

        $fields = [
            'userlogin'       => $this->login,
            'userpsw'         => $this->passwordMd5,
            'paymentMethod'   => (string)$this->paymentMethod,
            'shipmentAddress' => $this->shipmentAddress,
            'comment'         => $orderComment,
        ];
        if ($this->shipmentMethod !== '') $fields['shipmentMethod'] = $this->shipmentMethod;
        if ($shipmentDate !== null) $fields['shipmentDate'] = $shipmentDate;
        if ($ourOrderRef !== null) $fields['clientOrderNumber'] = (string)$ourOrderRef;

        foreach ($positions as $idx => $p) {
            foreach ($p as $k => $v) {
                $fields["positions[{$idx}][{$k}]"] = (string)$v;
            }
        }

        $body = http_build_query($fields);

        $this->log('placeOrder: request items=' . count($positions) . ' skipped=' . $skipped . ' shipmentDate=' . ($shipmentDate ?? 'null') . ' body=' . $body);

        $ch = curl_init($this->baseUrl . '/orders/instant');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 3,
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

        if ($err || $httpCode !== 200 || !is_array($decoded)) {
            return ['http_code' => $httpCode ?: null, 'success' => false, 'raw' => $decoded, 'error' => $err ?: ('http_' . $httpCode)];
        }

        // Сопоставляем позиции ответа с basket_item_id через itemKey — это
        // непрозрачный служебный код, который API возвращает как есть (в
        // отличие от partnumber у Росско, itemKey не переформатируется), самый
        // надёжный ключ сопоставления из всех, что даёт этот API.
        $itemReferences = [];
        foreach ((array)($decoded['orders'] ?? []) as $order) {
            $orderNumber = (string)($order['number'] ?? '');
            if ($orderNumber === '') continue;
            foreach ((array)($order['positions'] ?? []) as $pos) {
                $ik = (string)($pos['itemKey'] ?? '');
                $positionId = (int)($pos['positionId'] ?? 0);
                if ($ik === '' || $positionId <= 0) continue;
                if (!empty($queueByItemKey[$ik])) {
                    $basketItemId = array_shift($queueByItemKey[$ik]);
                    if ($basketItemId > 0) {
                        $itemReferences[$basketItemId] = $orderNumber . ':' . $positionId;
                    }
                }
            }
        }

        // status=1 в ответе не гарантирует полный успех (документация прямо
        // предупреждает: часть позиций может не попасть в заказ, независимо от
        // status, всегда проверять узел orders) — нашим успехом считаем
        // размещение хотя бы одной позиции, тот же принцип, что у ПартКома/Росско.
        $success = !empty($itemReferences);

        return [
            'http_code'       => $httpCode,
            'success'         => $success,
            'raw'             => $decoded,
            'error'           => $success ? null : ((string)($decoded['errorMessage'] ?? '') ?: 'order_rejected'),
            'item_references' => $itemReferences,
        ];
    }

    private function resolveShipmentDate(?int $minDeadline, ?int $maxDeadline): ?string
    {
        $min = $minDeadline ?? 0;
        $max = $maxDeadline ?? $min;
        if ($max < $min) $max = $min;

        $url = $this->baseUrl . '/basket/shipmentDates?' . $this->authQuery()
            . '&minDeadlineTime=' . $min . '&maxDeadlineTime=' . $max
            . '&shipmentAddress=' . urlencode($this->shipmentAddress);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || empty($resp)) return null;
        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data[0]['date'])) return null;
        return (string)$data[0]['date'];
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference — составной "{orderNumber}:{positionId}" (см.
     * placeOrder()::item_references) — orders/list запрашивается по номеру
     * заказа, positionId однозначно определяет конкретную позицию внутри него.
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        if (strpos($reference, ':') === false) return [];
        [$orderNumber, $positionIdRaw] = explode(':', $reference, 2);
        $orderNumber = trim($orderNumber);
        $positionId  = (int)$positionIdRaw;
        if ($orderNumber === '' || $positionId <= 0) return [];

        $url = $this->baseUrl . '/orders/list?' . $this->authQuery() . '&orders[0]=' . urlencode($orderNumber);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        $this->log("fetchOrderStatusByReference({$reference}): response http={$httpCode} err={$err} body=" . substr((string)$resp, 0, 4000));

        if ($err || $httpCode !== 200 || empty($resp)) return [];

        $data = json_decode($resp, true);
        if (!is_array($data)) return [];
        // Разные ответы ABCP могут отдавать либо голый список, либо обёртку
        // (см. похожие сюрпризы у Берга/Росско) — не полагаемся заранее на
        // один формат.
        $orders = $data['items'] ?? $data['data'] ?? $data;
        if (!is_array($orders)) return [];

        $order = null;
        foreach ($orders as $o) {
            if (is_array($o) && (string)($o['number'] ?? '') === $orderNumber) { $order = $o; break; }
        }
        if ($order === null) return [];

        $position = null;
        foreach ((array)($order['positions'] ?? []) as $p) {
            if ((int)($p['positionId'] ?? 0) === $positionId) { $position = $p; break; }
        }
        // Позицию по positionId не нашли — как и у остальных коннекторов,
        // безопаснее вернуть первую позицию заказа, чем молчать.
        if ($position === null) $position = $order['positions'][0] ?? null;
        if ($position === null) return [];

        $statusId   = isset($position['statusId']) ? (int)$position['statusId'] : null;
        $statusText = trim((string)($position['status'] ?? ''));

        return [[
            'order_number'    => $orderNumber,
            'state_id'        => $statusId !== null ? (string)$statusId : null,
            'state_text'      => $statusText !== '' ? $statusText : ($statusId !== null ? (self::STATUS_LABELS[$statusId] ?? null) : null),
            'stage'           => $this->normalizeStage($statusId),
            'expected_date'   => $order['shipmentDate'] ?? null,
            'guaranteed_date' => null,
            'store_count'     => null,
            'release_count'   => null,
            'refusal_count'   => null,
            'comment'         => $position['comment'] ?? $order['comment'] ?? null,
            'raw'             => $position,
        ]];
    }

    // Полный официальный словарь статусов (см. orders/statuses, снят вживую
    // 2026-09-08, id => [название, isFinalStatus]):
    //   1803 Получен(false)  1804 В работе(false)  1805 Пришло на склад(false)
    //   1806 Выдано(true)  1807 Задержка поставки(false)  1808 Отказ(true)
    //   64222 Технический(false)  68891 Обрабатывается(false)
    //   129198 Возврат(false)
    // isFinalStatus сам по себе не различает успех/отказ (оба 1806 и 1808
    // финальные) — классифицируем по конкретному id, не по одному лишь флагу.
    // 129198 "Возврат" не финальный, но это статус ПОСЛЕ отгрузки — заказ был
    // выполнен, дальнейшая судьба возврата не моделируется нашей шкалой,
    // поэтому 'ready', а не 'refused' (та же логика, что у Росско 32-36).
    private const STATUS_STAGE_MAP = [
        1803   => 'ordered',
        1804   => 'in_transit',
        1805   => 'in_transit',
        1806   => 'ready',
        1807   => 'in_transit',
        1808   => 'refused',
        64222  => 'ordered',
        68891  => 'ordered',
        129198 => 'ready',
    ];

    private const STATUS_LABELS = [
        1803   => 'Получен',
        1804   => 'В работе',
        1805   => 'Пришло на склад',
        1806   => 'Выдано',
        1807   => 'Задержка поставки',
        1808   => 'Отказ',
        64222  => 'Технический',
        68891  => 'Обрабатывается',
        129198 => 'Возврат',
    ];

    private function normalizeStage(?int $statusId): string
    {
        if ($statusId === null) return 'ordered';
        return self::STATUS_STAGE_MAP[$statusId] ?? 'ordered';
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

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
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/autoruss_' . date('Y-m-d') . '.log',
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