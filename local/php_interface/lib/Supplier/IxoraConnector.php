<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class IxoraConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $authCode;
    private string $endpoint;
    private int $timeout;
    private string $namespace = 'http://ws.ixora-auto.ru/';

    public function __construct(array $config = [])
    {
        $this->authCode = (string)($config['AUTH_CODE'] ?? $config['API_KEY'] ?? '');
        // ws.ixora-auto.ru отдаёт 301 http→https, а ни один curl-вызов в проекте
        // не следует редиректам — с http-эндпоинтом поставщик молча не отвечал никогда.
        $this->endpoint = rtrim((string)($config['ENDPOINT'] ?? 'https://ws.ixora-auto.ru/soap/ApiService.asmx'), '/');
        $this->timeout  = (int)($config['TIMEOUT'] ?? 8);
    }

    public function getCode(): string { return 'ixora'; }
    public function getName(): string { return 'Иксора'; }
    public function getWarehousePrefix(): string { return 'ixr'; }

    public function maskWarehouseName(string $realName): string
    {
        return $this->generateWarehouseCode($realName);
    }

    public function isAvailable(): bool
    {
        return $this->authCode !== '';
    }

    // ==================== ЭТАП 1: БРЕНДЫ ====================

    public function searchBrands(string $article): array
    {
        $req = $this->buildBrandsRequest($article);
        if (!$req) {
            return [];
        }
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $body = $this->soapEnvelope('GetMakers', [
            'Number'   => trim($article),
            'AuthCode' => $this->authCode,
        ]);

        return [
            'url'     => $this->endpoint,
            'headers' => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $this->namespace . 'GetMakers',
            ],
            'method'  => 'POST',
            'body'    => $body,
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $article = trim($requestArticle);
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) {
            $this->log('parseBrandsResponse: bad XML');
            return $brands;
        }

        $nodes = $xml->xpath('//*[local-name()="MakerInfo"]') ?: [];
        foreach ($nodes as $node) {
            $name = trim((string)($node->xpath('*[local-name()="name"]')[0] ?? ''));
            if ($name === '') {
                continue;
            }
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

    // ==================== ЭТАП 2: ПРЕДЛОЖЕНИЯ ====================

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $req = $this->buildSearchRequest($brand, $article, false);
        if (!$req) {
            return [];
        }
        $resp = $this->execCurl($req);
        return $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
    }

    /**
     * @param bool $withCrosses true = SubstFilter=All (оригинал+аналоги), false = Originals
     */
    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $subst = $withCrosses ? 'All' : 'Originals';

        $body = $this->soapEnvelope('FindExt', [
            'Number'           => trim($article),
            'Maker'            => trim($brand),
            'StockOnly'        => 'false',
            'SubstFilter'      => $subst,
            'ConditionsFilter' => 'All',
            'AuthCode'         => $this->authCode,
        ]);

        return [
            'url'     => $this->endpoint,
            'headers' => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . $this->namespace . 'FindExt',
            ],
            'method'  => 'POST',
            'body'    => $body,
            // помечаем режим, чтобы parse мог понять контекст при необходимости
            '_ixora_with_crosses' => $withCrosses ? 1 : 0,
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false || $xml === null) {
            $this->log('parseSearchResponse: bad XML');
            return $results;
        }

        // fault?
        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if ($fault) {
            $msg = (string)($xml->xpath('//*[local-name()="faultstring"]')[0] ?? 'SOAP Fault');
            $this->log('SOAP Fault: ' . $msg);
            return $results;
        }

        $normBrand = BrandNormalizer::normalize($brand);
        $normArt   = BrandNormalizer::normalizeArticle($article);

        // Если в запросе был Maker и Originals — API всё равно может отдать лишнее.
        // Exact-режим: оставляем совпадение brand+article.
        // Cross-режим (много разных brand): не режем по brand — stage2 сам разложит exact/analog.
        $details = $xml->xpath('//*[local-name()="DetailInfo"]') ?: [];

        // эвристика cross: если среди ответа >1 нормализованного бренда и целевой бренд задан —
        // всё равно отдаём все валидные позиции (как PartKom с substitutes).
        $brandSet = [];
        foreach ($details as $d) {
            $b = trim((string)($d->xpath('*[local-name()="maker"]')[0] ?? ''));
            if ($b !== '') {
                $brandSet[BrandNormalizer::normalize($b)] = true;
            }
        }
        $isCrossResponse = count($brandSet) > 1;

        $own = [];
        $other = [];

        foreach ($details as $d) {
            $itemBrand   = trim((string)($d->xpath('*[local-name()="maker"]')[0] ?? ''));
            $itemNumber  = trim((string)($d->xpath('*[local-name()="number"]')[0] ?? ''));
            $itemName    = trim((string)($d->xpath('*[local-name()="name"]')[0] ?? ''));
            $qtyRaw      = trim((string)($d->xpath('*[local-name()="quantity"]')[0] ?? '0'));
            $qty         = $this->parseQty($qtyRaw);
            $price       = (float)str_replace(',', '.', (string)($d->xpath('*[local-name()="price"]')[0] ?? 0));
            $lot         = max(1, (int)($d->xpath('*[local-name()="lotquantity"]')[0] ?? 1));
            $days        = (int)($d->xpath('*[local-name()="days"]')[0] ?? 0);
            $daysW       = (int)($d->xpath('*[local-name()="dayswarranty"]')[0] ?? 0);
            $region      = trim((string)($d->xpath('*[local-name()="region"]')[0] ?? ''));
            $group       = trim((string)($d->xpath('*[local-name()="group"]')[0] ?? ''));
            $dateArrival = trim((string)($d->xpath('*[local-name()="datearrival"]')[0] ?? ''));
            $retPeriod   = (int)($d->xpath('*[local-name()="returnperiod"]')[0] ?? 0);
            $retCond     = trim((string)($d->xpath('*[local-name()="returnconditions"]')[0] ?? ''));
            $retCondId   = (int)($d->xpath('*[local-name()="returnconditionsid"]')[0] ?? 0);
            $orderRef    = trim((string)($d->xpath('*[local-name()="orderreference"]')[0] ?? ''));
            $estimation  = trim((string)($d->xpath('*[local-name()="estimation"]')[0] ?? ''));

            if ($itemBrand === '' || $itemNumber === '') {
                continue;
            }
            if ($price <= 0 && $qty <= 0) {
                continue;
            }

            // В exact-ответе (один бренд / Originals) фильтруем строго.
            if (!$isCrossResponse) {
                if ($normBrand !== '' && BrandNormalizer::normalize($itemBrand) !== $normBrand) {
                    continue;
                }
                // article иногда с разделителями — сравниваем нормализованно
                if ($normArt !== '' && BrandNormalizer::normalizeArticle($itemNumber) !== $normArt) {
                    // для Originals API обычно тот же номер; аналоги отсекаем
                    if (strcasecmp($group, 'Analog') === 0 || strcasecmp($group, 'ReplacmentOriginal') === 0) {
                        continue;
                    }
                    // если group=Original, но номер другой — тоже пропустим
                    continue;
                }
            }

            $r = new SearchResultItem();
            $r->source       = $this->getCode();
            $r->article      = $itemNumber;
            $r->brand        = $itemBrand;
            $r->name         = $itemName;
            $r->price        = $price;
            $r->quantity     = $qty;
            $r->multiplicity = $lot;
            $r->unit         = 'шт.';
            $r->warehouse    = $region !== '' ? $region : 'Иксора';
            $r->stockId      = $orderRef !== '' ? $orderRef : md5($itemBrand . '|' . $itemNumber . '|' . $region . '|' . $price . '|' . $days);
            $r->supplierName = $this->getName();
            // qty>0 = реальное наличие на складе поставщика (даже если days>0)
            $r->isSched      = ($qty <= 0);
            $r->returnable   = $this->isReturnable($retPeriod, $retCondId, $retCond);
            $r->reliabilityPercent = $this->estimationToPercent($estimation);

            // Срок
            [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($dateArrival, $days, $daysW);
            $r->deliveryDays      = $deliveryDays;
            $r->deliveryPeriod    = $deliveryPeriod;
            $r->deliveryLabel     = $deliveryLabel;
            $r->deliveryTimeLabel = $deliveryTimeLabel;
            $r->deliveryToday     = $deliveryToday;
            $r->deliveryDeadline  = $deliveryDeadline;

            // Лёгкий raw: не тащим огромные SOAP-поля в кеш/память
            $r->raw = [
                'group'              => $group,
                'returnperiod'       => $retPeriod,
                'returnconditionsid' => $retCondId ?? 0,
                'returnconditions'   => $retCond,
                'days'               => $days,
                'dayswarranty'       => $daysW,
                'datearrival'        => $dateArrival,
            ];

            // Для оформления заказа (см. SupplierOrderable::placeOrder()) —
            // orderreference из DetailInfo, тот же самый "Идентификатор
            // предложения", который BasketInsertOrders принимает как
            // Order.OrderReference (см. документацию: "можно получить из
            // объекта DetailInfo вызвав метод поиска").
            $r->orderMeta = ['order_reference' => $orderRef];

            // Свои: Region начинается с "IXORA СКЛАД"
            if (mb_stripos($region, 'IXORA СКЛАД') === 0) {
                $own[] = $r;
            } else {
                $other[] = $r;
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

    /**
     * Срок доставки Иксора. Приоритет источников (от точного к грубому):
     *  1. datearrival — точная дата и время получения заказа в пункте
     *     назначения, самый точный источник, показывается со временем.
     *  2. dayswarranty — ГАРАНТИРОВАННЫЙ срок до склада отгрузки (дни).
     *  3. days — СРЕДНИЙ (негарантированный) срок до склада отгрузки,
     *     только если гарантированного нет.
     * Раньше было наоборот: days (средний, оптимистичный) шёл первым,
     * а datearrival использовалась только если days/dayswarranty вообще
     * не пришли — теперь используется наиболее точный из доступных
     * источников, а не наименее точный.
     * Как и у БЕРГ, days/dayswarranty — срок до склада отгрузки Иксоры,
     * а не до клиента; это ограничение источника данных.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(string $dateArrivalRaw, int $days, int $daysWarranty): array
    {
        $now = time();

        if ($dateArrivalRaw !== '') {
            $ts = strtotime($dateArrivalRaw);
            if ($ts && $ts > $now) {
                $todayStart    = strtotime('today');
                $tomorrowStart = strtotime('tomorrow');
                $tsDay         = strtotime(date('Y-m-d', $ts));
                $d             = ($tsDay <= $todayStart) ? 0 : (int)ceil(($tsDay - $todayStart) / 86400);
                $dayLabel      = ($tsDay <= $todayStart) ? 'Сегодня' : (($tsDay === $tomorrowStart) ? 'Завтра' : date('d.m', $ts));
                $timeLabel     = date('H:i', $ts);
                $hours         = max(0, (int)ceil(($ts - $now) / 3600));
                return [$d, $hours, $dayLabel, $timeLabel, $tsDay <= $todayStart, null];
            }
        }

        $d = $daysWarranty > 0 ? $daysWarranty : max(0, $days);
        $dayLabel = $d === 0 ? 'Сегодня' : ($d === 1 ? 'Завтра' : date('d.m', strtotime("+{$d} days")));
        return [$d, $d * 24, $dayLabel, null, $d === 0, null];
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) {
            if (!$item->isSched && $item->price > 0) {
                return $item;
            }
        }
        return $items[0] ?? null;
    }

    public function search(string $query): array
    {
        $results = [];
        if (!$this->isAvailable()) {
            return $results;
        }

        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return $results;
        }

        $brands = $this->searchBrands($query);
        $brands = array_slice($brands, 0, 8);
        foreach ($brands as $br) {
            try {
                $items = $this->searchByBrandArticle($br['brand'], $br['article_nr'] ?? $query);
                $results = array_merge($results, array_slice($items, 0, 5));
            } catch (\Throwable $e) {
                $this->log('search brand error: ' . $e->getMessage());
            }
        }

        $seen = [];
        $unique = [];
        foreach ($results as $item) {
            $k = $item->getDedupeKey() . '|' . $item->stockId;
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $unique[] = $item;
            }
        }
        usort($unique, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return array_slice($unique, 0, 40);
    }

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    public function placeOrder(array $items, bool $test = false): array
    {
        // Иксора не документирует флаг тестового заказа для BasketInsertOrders —
        // как и у Росско/Авторуси, в тестовом режиме запрос не отправляем,
        // безопаснее пропустить, чем случайно оформить реальный заказ.
        if ($test) {
            $this->log('placeOrder: тестовый режим не поддерживается API Иксоры (флага тестового заказа нет) — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        $orders = [];
        $queueByRef = [];
        $skipped = 0;
        foreach ($items as $item) {
            $orderReference = trim((string)($item['order_meta']['order_reference'] ?? ''));
            $qty   = (int)($item['quantity'] ?? 0);
            $price = (float)($item['price_base'] ?? 0);
            if ($orderReference === '' || $qty <= 0 || $price <= 0) { $skipped++; continue; }

            $orders[] = [
                'OrderReference' => $orderReference,
                'Quantity'       => $qty,
                'Price'          => number_format($price, 2, '.', ''),
                'Reference'      => (string)($item['comment'] ?? ''),
            ];
            $queueByRef[$orderReference][] = (int)($item['basket_item_id'] ?? 0);
        }

        if (empty($orders)) {
            $this->log('placeOrder: нет ни одной валидной позиции (нет order_reference в order_meta), пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        $body = $this->buildBasketInsertOrdersXml($orders);

        $this->log('placeOrder: request items=' . count($orders) . ' skipped=' . $skipped . ' body=' . $body);

        $resp = $this->execCurl([
            'url'     => $this->endpoint,
            'headers' => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: ' . $this->namespace . 'BasketInsertOrders'],
            'method'  => 'POST',
            'body'    => $body,
        ]);

        $this->log('placeOrder: response body=' . substr((string)$resp, 0, 4000));

        if ($resp === null) {
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'http_error'];
        }

        $xml = @simplexml_load_string($resp);
        if ($xml === false || $xml === null) {
            return ['http_code' => 200, 'success' => false, 'raw' => ['_raw_text' => $resp], 'error' => 'invalid_xml'];
        }

        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if ($fault) {
            $msg = (string)($xml->xpath('//*[local-name()="faultstring"]')[0] ?? 'SOAP Fault');
            return ['http_code' => 200, 'success' => false, 'raw' => ['fault' => $msg], 'error' => $msg];
        }

        $warnCode = $xml->xpath('//*[local-name()="Warning"]/*[local-name()="Code"]');
        $warnCode = $warnCode ? (int)$warnCode[0] : null;
        $warnDesc = trim((string)($xml->xpath('//*[local-name()="Warning"]/*[local-name()="Description"]')[0] ?? ''));

        $orderNodes = $xml->xpath('//*[local-name()="Data"]/*[local-name()="Order"]') ?: [];
        $itemReferences = [];
        $errorsRaw = [];
        $itemsRaw = [];
        foreach ($orderNodes as $o) {
            $ref = trim((string)($o->xpath('*[local-name()="OrderReference"]')[0] ?? ''));
            $id  = trim((string)($o->xpath('*[local-name()="Id"]')[0] ?? ''));
            $err = trim((string)($o->xpath('*[local-name()="Error"]')[0] ?? ''));
            $itemsRaw[] = ['orderReference' => $ref, 'id' => $id, 'error' => $err];

            if ($id !== '' && !empty($queueByRef[$ref])) {
                $basketItemId = array_shift($queueByRef[$ref]);
                if ($basketItemId > 0) {
                    // Id уникален на позицию сразу (в отличие от Берга/Росско,
                    // где один заказ у поставщика мог покрывать несколько наших
                    // позиций) — составной reference не нужен.
                    $itemReferences[$basketItemId] = $id;
                }
            } elseif ($err !== '') {
                $errorsRaw[] = ['orderReference' => $ref, 'message' => $err];
            }
        }

        if (empty($itemReferences)) {
            $error = $warnDesc !== '' ? $warnDesc : (self::RESULT_CODE_LABELS[$warnCode] ?? 'order_rejected');
            return [
                'http_code'       => 200,
                'success'         => false,
                'raw'             => ['warningCode' => $warnCode, 'warningDescription' => $warnDesc, 'items' => $itemsRaw, 'errors' => $errorsRaw],
                'error'           => $error,
                'item_references' => [],
            ];
        }

        // BasketInsertOrders только КЛАДЁТ позиции в корзину СЕРВИСА Иксоры —
        // подтверждено вживую (заказ №189: Id получен, Warning.Code=0, но
        // заказ не появился на сайте Иксоры) — реальная отправка поставщику
        // требует ВТОРОГО обязательного шага, BasketPositionInWork, с теми же
        // Id, что вернул этот запрос. Без него заказ так и остаётся висеть в
        // корзине и не уходит в обработку.
        $inWork = $this->sendPositionsInWork(array_values($itemReferences));

        $confirmedReferences = [];
        $inWorkErrors = [];
        foreach ($itemReferences as $basketItemId => $positionId) {
            if (in_array($positionId, $inWork['succeeded'], true)) {
                $confirmedReferences[$basketItemId] = $positionId;
            } else {
                $inWorkErrors[] = ['positionId' => $positionId, 'message' => $inWork['errors'][$positionId] ?? 'in_work_failed'];
            }
        }

        // Аналогично ПартКому/Росско/Авторуси: реальный успех — позиция,
        // подтверждённая ОБОИМИ шагами (в корзине И отправлена в работу), а не
        // общий Warning.Code=0 первого шага, который относится ко ВСЕЙ
        // операции добавления в корзину, а не к фактической отправке заказа.
        $success = !empty($confirmedReferences);

        $error = null;
        if (!$success) {
            $error = !empty($inWorkErrors) ? $inWorkErrors[0]['message'] : ($warnDesc !== '' ? $warnDesc : 'order_rejected');
        }

        return [
            'http_code'       => 200,
            'success'         => $success,
            'raw'             => [
                'warningCode'        => $warnCode,
                'warningDescription' => $warnDesc,
                'items'              => $itemsRaw,
                'errors'             => $errorsRaw,
                'inWork'             => $inWork['raw'],
                'inWorkErrors'       => $inWorkErrors,
            ],
            'error'           => $error,
            'item_references' => $confirmedReferences,
        ];
    }

    /**
     * Второй обязательный шаг оформления заказа (см. placeOrder()) —
     * отправляет позиции из корзины сервиса в реальную обработку у Иксоры.
     * $positionIds — Id, полученные от BasketInsertOrders.
     *
     * @return array{succeeded: string[], errors: array<string,string>, raw: ?array}
     */
    private function sendPositionsInWork(array $positionIds): array
    {
        if (empty($positionIds)) {
            return ['succeeded' => [], 'errors' => [], 'raw' => null];
        }

        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idsXml = '';
        foreach ($positionIds as $id) {
            $idsXml .= '<string>' . $esc($id) . '</string>';
        }

        $inner = '<BasketPositionInWork xmlns="' . $this->namespace . '">'
            . '<BasketPositions>' . $idsXml . '</BasketPositions>'
            . '<AuthCode>' . $esc($this->authCode) . '</AuthCode>'
            . '</BasketPositionInWork>';

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $inner . '</soap:Body>'
            . '</soap:Envelope>';

        $this->log('placeOrder: BasketPositionInWork request positions=' . count($positionIds) . ' body=' . $body);

        $resp = $this->execCurl([
            'url'     => $this->endpoint,
            'headers' => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: ' . $this->namespace . 'BasketPositionInWork'],
            'method'  => 'POST',
            'body'    => $body,
        ]);

        $this->log('placeOrder: BasketPositionInWork response body=' . substr((string)$resp, 0, 4000));

        if ($resp === null) {
            // Сетевая ошибка на втором шаге — не знаем реального результата,
            // безопаснее считать ВСЕ позиции неотправленными в работу, чем
            // молча засчитать успех, полагаясь только на первый шаг.
            return ['succeeded' => [], 'errors' => array_fill_keys($positionIds, 'in_work_http_error'), 'raw' => null];
        }

        $xml = @simplexml_load_string($resp);
        if ($xml === false || $xml === null) {
            return ['succeeded' => [], 'errors' => array_fill_keys($positionIds, 'in_work_invalid_xml'), 'raw' => ['_raw_text' => $resp]];
        }

        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if ($fault) {
            $msg = (string)($xml->xpath('//*[local-name()="faultstring"]')[0] ?? 'SOAP Fault');
            return ['succeeded' => [], 'errors' => array_fill_keys($positionIds, $msg), 'raw' => ['fault' => $msg]];
        }

        $succeeded = [];
        $errors = [];
        $rawItems = [];
        $resultNodes = $xml->xpath('//*[local-name()="Data"]/*[local-name()="PositionOperationResult"]') ?: [];
        foreach ($resultNodes as $r) {
            $orderId = trim((string)($r->xpath('*[local-name()="OrderId"]')[0] ?? ''));
            $codeNode = $r->xpath('*[local-name()="Warning"]/*[local-name()="Code"]');
            $code = $codeNode ? (int)$codeNode[0] : null;
            $desc = trim((string)($r->xpath('*[local-name()="Warning"]/*[local-name()="Description"]')[0] ?? ''));
            $rawItems[] = ['orderId' => $orderId, 'code' => $code, 'description' => $desc];
            if ($orderId === '') continue;
            if ($code === 0) {
                $succeeded[] = $orderId;
            } else {
                $errors[$orderId] = $desc !== '' ? $desc : (self::RESULT_CODE_LABELS[$code] ?? 'in_work_rejected');
            }
        }

        return ['succeeded' => $succeeded, 'errors' => $errors, 'raw' => $rawItems];
    }

    private function buildBasketInsertOrdersXml(array $orders): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $ordersXml = '';
        foreach ($orders as $o) {
            $ordersXml .= '<Order>'
                . '<OrderReference>' . $esc($o['OrderReference']) . '</OrderReference>'
                . '<Quantity>' . (int)$o['Quantity'] . '</Quantity>'
                . '<Price>' . $esc($o['Price']) . '</Price>';
            if (!empty($o['Reference'])) $ordersXml .= '<Reference>' . $esc($o['Reference']) . '</Reference>';
            $ordersXml .= '</Order>';
        }

        $inner = '<BasketInsertOrders xmlns="' . $this->namespace . '">'
            . '<Orders>' . $ordersXml . '</Orders>'
            . '<AuthCode>' . $esc($this->authCode) . '</AuthCode>'
            . '</BasketInsertOrders>';

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $inner . '</soap:Body>'
            . '</soap:Envelope>';
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /**
     * $reference здесь — просто Id заказа у Иксоры (см. placeOrder()::
     * item_references) — у Иксоры каждая наша позиция получает СВОЙ
     * собственный Id сразу при размещении, составной reference не нужен
     * (в отличие от Берга/Росско/Авторуси, где один заказ поставщика может
     * покрывать несколько наших позиций).
     */
    public function fetchOrderStatusByReference(string $reference): array
    {
        $orderId = trim($reference);
        if ($orderId === '') return [];

        $body = $this->soapEnvelope('OrderStatusGetByOrderId', [
            'OrderId'  => $orderId,
            'AuthCode' => $this->authCode,
        ]);

        $resp = $this->execCurl([
            'url'     => $this->endpoint,
            'headers' => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: ' . $this->namespace . 'OrderStatusGetByOrderId'],
            'method'  => 'POST',
            'body'    => $body,
        ]);

        $this->log("fetchOrderStatusByReference({$reference}): response body=" . substr((string)$resp, 0, 4000));

        if ($resp === null) return [];

        $xml = @simplexml_load_string($resp);
        if ($xml === false || $xml === null) return [];

        $fault = $xml->xpath('//*[local-name()="Fault"]');
        if ($fault) return [];

        $statusNodes = $xml->xpath('//*[local-name()="Data"]/*[local-name()="OrderStatus"]') ?: [];
        if (empty($statusNodes)) return [];

        $out = [];
        foreach ($statusNodes as $s) {
            $status              = trim((string)($s->xpath('*[local-name()="Status"]')[0] ?? ''));
            $comment             = trim((string)($s->xpath('*[local-name()="Comment"]')[0] ?? ''));
            $number              = trim((string)($s->xpath('*[local-name()="Number"]')[0] ?? ''));
            $dateArrivalOrient   = trim((string)($s->xpath('*[local-name()="DateArrivalOrient"]')[0] ?? ''));
            $dateArrivalWarranty = trim((string)($s->xpath('*[local-name()="DateArrivalWarranty"]')[0] ?? ''));

            $out[] = [
                'order_number'    => $number !== '' ? $number : $orderId,
                'state_id'        => $status !== '' ? $status : null,
                'state_text'      => $status !== '' ? (self::STATUS_LABELS[$status] ?? $status) : null,
                'stage'           => $this->normalizeStage($status),
                'expected_date'   => $dateArrivalOrient !== '' ? $dateArrivalOrient : null,
                'guaranteed_date' => $dateArrivalWarranty !== '' ? $dateArrivalWarranty : null,
                'store_count'     => null,
                'release_count'   => null,
                'refusal_count'   => null,
                'comment'         => $comment !== '' ? $comment : null,
                'raw'             => ['status' => $status],
            ];
        }
        return $out;
    }

    // Полный официальный словарь статусов заказа (OrderStatus.Status) —
    // строковый enum, задокументирован явно, угадывать не требуется.
    private const STATUS_STAGE_MAP = [
        'InOrder'      => 'ordered',
        'Ordered'      => 'ordered',
        'Purchased'    => 'in_transit',
        'OnTheWay'     => 'in_transit',
        'ToIssue'      => 'ready',
        'Issued'       => 'ready',
        'NotAvailable' => 'refused',
        'Reserve'      => 'in_transit',
        'Acceptance'   => 'in_transit',
        'Moving'       => 'in_transit',
    ];

    private const STATUS_LABELS = [
        'InOrder'      => 'В заказе',
        'Ordered'      => 'Заказано',
        'Purchased'    => 'Выкуплено',
        'OnTheWay'     => 'В пути',
        'ToIssue'      => 'К выдаче',
        'Issued'       => 'Выдано',
        'NotAvailable' => 'Нет в наличии',
        'Reserve'      => 'Резерв на складе',
        'Acceptance'   => 'Приемка',
        'Moving'       => 'Перемещение',
    ];

    private function normalizeStage(string $status): string
    {
        return self::STATUS_STAGE_MAP[$status] ?? 'ordered';
    }

    // Общий словарь кодов результата операции (см. документацию Ixora,
    // OperationWarning.Code) — используется для placeOrder(), когда позиция
    // не получила Id и своего Error не содержит.
    private const RESULT_CODE_LABELS = [
        0  => 'OK',
        1  => 'Отсутствует в прайс-листе поставщика',
        2  => 'Превышение цены',
        3  => 'Нет достаточного количества',
        4  => 'Не определен номер детали',
        5  => 'Не верно указаны параметры перезаказа',
        6  => 'Требуемое количество не соответствует минимальной партии',
        7  => 'Не указана цена заказа',
        8  => 'Не указано требуемое количество',
        9  => 'Номер детали должен состоять из цифр и букв латинского алфавита',
        10 => 'Не указан параметр',
        11 => 'Ключ безопасности не соответствует контрагенту',
        12 => 'IP не зарегистрирован',
        13 => 'Контрагент отключен',
        14 => 'Истек срок действия договора',
        15 => 'Внутренняя ошибка',
        16 => 'Отсутствует в корзине',
        17 => 'Недостаточно средств на балансе',
        18 => 'Позиция отложена',
        19 => 'Превышена максимальная длина строки',
        20 => 'Не верная длина строки',
    ];

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function parseQty(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '0') {
            return 0;
        }
        // >10 / >=10
        if (preg_match('/^>=?\s*(\d+)/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        // 10+ 
        if (preg_match('/^(\d+)\+$/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        // 5-10
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $val, $m)) {
            return max(1, (int)$m[1]);
        }
        if (preg_match('/(\d+)/', $val, $m)) {
            return max(0, (int)$m[1]);
        }
        return 0;
    }

    private function soapEnvelope(string $method, array $params): string
    {
        $inner = '<' . $method . ' xmlns="' . $this->namespace . '">';
        foreach ($params as $k => $v) {
            if ($v === null) {
                continue;
            }
            // boolean уже строкой true/false
            $inner .= '<' . $k . '>' . htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $k . '>';
        }
        $inner .= '</' . $method . '>';

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $inner . '</soap:Body>'
            . '</soap:Envelope>';
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $http !== 200 || $resp === false || $resp === '') {
            $this->log("HTTP {$http} err={$err}");
            return null;
        }
        return $resp;
    }

    /**
     * "estimation" — 3-значная строка-триплет из B2B API Ixora: 1-я цифра —
     * оценка по сроку поставки, 2-я — наличие по детали, 3-я — наличие по
     * производителю. Шкала 1-5 подтверждена по личному кабинету Ixora
     * (колонка "Оценка": "5 5 5" и т.п., где всплывающая статистика для
     * оценки "5" показывает "доставлено 100%, нет в наличии 0%") — то есть
     * 5 = 100%, 1 = 0%, линейная шкала. Недостающий/нечисловой символ
     * (напр. "-" — нет данных по этой позиции, встречается в кабинете как
     * зачёркнутая иконка) просто исключается из расчёта.
     *
     * Для "вероятности поставки" берём именно НАЛИЧИЕ (2-я и 3-я цифры) —
     * это то, будет ли деталь физически доставлена. Срок (1-я цифра) —
     * отдельная характеристика скорости, а не вероятности поставки как
     * таковой, и уже отображается отдельно колонкой "Доставка".
     */
    private function estimationToPercent(string $estimation): ?int
    {
        $chars = str_split(preg_replace('/\s+/', '', $estimation));
        $availabilityDigits = [];
        foreach ([1, 2] as $idx) { // 2-я и 3-я цифры триплета (индексы 1,2)
            if (isset($chars[$idx]) && ctype_digit($chars[$idx])) {
                $availabilityDigits[] = max(1, min(5, (int)$chars[$idx]));
            }
        }
        if (empty($availabilityDigits)) {
            return null;
        }
        $avgScore = array_sum($availabilityDigits) / count($availabilityDigits); // 1-5
        return max(0, min(100, (int)round(($avgScore - 1) / 4 * 100)));
    }

    private function isReturnable(int $returnPeriod, int $returnConditionId, string $returnConditions): bool
    {
        $txt = mb_strtolower(trim($returnConditions));

        // id=1 в справочнике Ixora = "Возврат невозможен"
        if ($returnConditionId === 1) {
            return false;
        }

        if ($txt !== '') {
            // явный запрет
            if (str_contains($txt, 'невозможен')
                || str_contains($txt, 'невозможна')
                || str_contains($txt, 'невозможн')
                || str_contains($txt, 'невозврат')
                || str_contains($txt, 'без возврата')
                || str_contains($txt, 'возврату не подлежит')
            ) {
                return false;
            }
        }

        // period > 0 или известные "можно вернуть" id
        if ($returnPeriod > 0) {
            return true;
        }

        // id 2..10 — варианты гарантированного/ограниченного возврата
        if ($returnConditionId >= 2) {
            return true;
        }

        // нет данных — безопаснее считать невозвратным? для фильтров лучше false
        // но у части позиций period=0 и пустой текст. Тогда false.
        return false;
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
        while (strlen($abbr) < 3) {
            $abbr .= 'x';
        }
        return $this->getWarehousePrefix() . '_' . $abbr;
    }

    private function log(string $message): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $file = $root . '/upload/logs/ixora_' . date('Y-m-d') . '.log';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    public function supportsCrossSearch(): bool
    {
        return true;
    }

    public function getSearchTimeout(): int
    {
        return 8;
    }
}
