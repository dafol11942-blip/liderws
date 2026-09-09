<?php
namespace Lider\Supplier;

use Lider\Search\SearchResultItem;

class TatpartsConnector implements SupplierInterface, SupplierOrderable, SupplierOrderStatusProvider
{
    private string $user     = 'lider16';
    private string $password = "'8dTpDU8}Myr)*&";
    private string $provider = 'tatparts_ru';
    private string $supLogin = 'lider-16@bk.ru';
    private string $supPass  = 'elabuga16';
    private string $baseUrl  = 'https://service.tradesoft.ru/3/';
    private int    $timeout  = 8;

    public function __construct(array $config = [])
    {
        // USER и PASSWORD — это авторизация API Tradesoft
        $this->user     = $config['USER']     ?? $this->user;
        $this->password = $config['PASSWORD'] ?? $this->password;
        // SUP_LOGIN и SUP_PASS — авторизация конкретного поставщика внутри контейнера
        $this->supLogin = $config['SUP_LOGIN'] ?? $config['LOGIN'] ?? $this->supLogin;
        $this->supPass  = $config['SUP_PASS']  ?? $this->supPass;
        $this->provider = $config['PROVIDER'] ?? $this->provider;
        $this->baseUrl  = $config['BASE_URL']  ?? $this->baseUrl;
        $this->timeout  = $config['TIMEOUT']   ?? $this->timeout;
    }

    public function getCode(): string       { return 'tatparts'; }
    public function getName(): string       { return 'ТатПартс'; }
    public function getWarehousePrefix(): string { return 'ttp'; }
    public function maskWarehouseName(string $realName): string { return $this->generateWarehouseCode($realName); }
    public function isAvailable(): bool     { return true; }

    public function searchBrands(string $article): array
    {
        $body = $this->buildBrandsBody($article);
        $resp = $this->execPost($body);
        return $resp !== null ? $this->parseBrandsResponse($resp, $article) : [];
    }

    public function buildBrandsBody(string $article): array
    {
        return [
            'service'   => 'provider',
            'action'    => 'getProducerList',
            'user'      => $this->user,
            'password'  => $this->password,
            'timeLimit' => $this->timeout,
            'container' => [[
                'provider' => $this->provider,
                'login'    => $this->supLogin,
                'password' => $this->supPass,
                'code'     => $article,
            ]]
        ];
    }

    public function buildBrandsRequest(string $article): ?array
    {
        $body = $this->buildBrandsBody($article);
        return [
            'url'     => $this->baseUrl,
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => json_encode($body),
        ];
    }

    public function parseBrandsResponse(string $responseBody, string $requestArticle = ''): array
    {
        $brands = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $brands;
        foreach (($data['container'] ?? []) as $cont) {
            foreach (($cont['data'] ?? []) as $item) {
                $b = trim((string)($item['producer'] ?? ''));
                $d = trim((string)($item['caption'] ?? ''));
                if ($b === '' || $requestArticle === '') continue;
                $key = $b . '|' . $requestArticle;
                if (!isset($brands[$key])) {
                    $brands[$key] = ['brand' => $b, 'article' => $requestArticle, 'article_fix' => $requestArticle, 'description' => $d];
                }
            }
        }
        return array_values($brands);
    }

    public function searchByBrandArticle(string $brand, string $article): array
    {
        $body = $this->buildSearchBody($brand, $article);
        $resp = $this->execPost($body);
        $items = $resp !== null ? $this->parseSearchResponse($resp, $brand, $article) : [];
        if (!empty($items)) return $items;

        if ($brand !== '' || $article !== '') {
            $brands = $this->searchBrands($article);
            $brandLower = mb_strtolower(trim($brand));
            foreach ($brands as $br) {
                $b = $br['brand'];
                if ($brandLower === '' || 
                    mb_stripos($b, $brandLower) !== false || 
                    mb_stripos($brandLower, $b) !== false ||
                    (mb_strlen($b) >= 3 && mb_strlen($brandLower) >= 3 && 
                     mb_substr(mb_strtolower($b), 0, 3) === mb_substr($brandLower, 0, 3))) {
                    $body2 = $this->buildSearchBody($b, $article);
                    $resp2 = $this->execPost($body2);
                    $items2 = $resp2 !== null ? $this->parseSearchResponse($resp2, $b, $article) : [];
                    if (!empty($items2)) return $items2;
                }
            }
        }

        return [];
    }

    public function buildSearchBody(string $brand, string $article): array
    {
        $container = [
            'provider' => $this->provider,
            'login'    => $this->supLogin,
            'password' => $this->supPass,
            'code'     => $article,
        ];
        if ($brand !== '') $container['producer'] = $brand;
        return [
            'service'   => 'provider',
            'action'    => 'getPriceList',
            'user'      => $this->user,
            'password'  => $this->password,
            'timeLimit' => $this->timeout,
            'container' => [$container]
        ];
    }

    public function buildSearchRequest(string $brand, string $article, bool $withCrosses = false): ?array
    {
        $body = $this->buildSearchBody($brand, $article);
        return [
            'url'     => $this->baseUrl,
            'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'method'  => 'POST',
            'body'    => json_encode($body),
        ];
    }

    public function parseSearchResponse(string $responseBody, string $brand, string $article): array
    {
        $results = [];
        $data = json_decode($responseBody, true);
        if (!is_array($data)) return $results;
        foreach (($data['container'] ?? []) as $cont) {
            foreach (($cont['data'] ?? []) as $item) {
                $r = $this->buildResultItem($item, $brand, $article);
                if ($r->price <= 0 && $r->quantity <= 0) continue;
                $results[] = $r;
            }
        }
        // Tatparts — агрегатор, своих складов нет. Топ-10 по срокам+цене.
        usort($results, function (SearchResultItem $a, SearchResultItem $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            $da = $a->deliveryDays ?? 0;
            $db = $b->deliveryDays ?? 0;
            if ($da !== $db) return $da <=> $db;
            return $a->price <=> $b->price;
        });

        return array_slice($results, 0, 30);
    }

    public function getDetail(string $article, string $brand): ?SearchResultItem
    {
        $items = $this->searchByBrandArticle($brand, $article);
        foreach ($items as $item) if (!$item->isSched && $item->price > 0) return $item;
        return $items[0] ?? null;
    }

    public function search(string $query): array
    {
        $results = [];
        $query = trim($query);
        if (mb_strlen($query) < 2) return $results;
        $brands = $this->searchBrands($query);
        $seed = []; foreach (array_slice($brands,0,10) as $br) $seed[]=$br;
        $seen = [];
        foreach ($seed as $br) {
            $items = $this->searchByBrandArticle($br['brand'], $br['article_fix']);
            foreach ($items as $r) { $dk=$r->getDedupeKey(); if (!isset($seen[$dk])) { $seen[$dk]=true; $results[]=$r; } }
            if (count($results)>=30) break;
        }
        usort($results, fn($a,$b)=>$a->price<=>$b->price);
        return array_slice($results,0,30);
    }

    public function supportsCrossSearch(): bool { return false; }
    public function getSearchTimeout(): int { return $this->timeout; }

    private function execPost(array $body): ?string
    {
        $json = json_encode($body);
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$json, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
            CURLOPT_TIMEOUT=>$this->timeout+5, CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0,
        ]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        return ($err || !$resp) ? null : $resp;
    }

    private function buildResultItem(array $item, string $db, string $da): SearchResultItem
    {
        $p=(float)str_replace(',','.',(string)($item['price']??'0'));
        $restStr=trim((string)($item['rest']??0));
        if ($restStr==='') {
            $q=0;
        } elseif (is_numeric($restStr)) {
            $q=(int)$restStr;
        } else {
            // TatParts иногда вместо точного остатка отдаёт текстовый флаг наличия
            // ("Есть" и т.п.) без числа — по просьбе заказчика считаем это как 100 шт.
            $q=100;
        }
        [$deliveryDays, $deliveryPeriod, $deliveryLabel, $deliveryTimeLabel, $deliveryToday, $deliveryDeadline] = $this->resolveDelivery($item);
        $r=new SearchResultItem();
        $r->source='tatparts'; $r->article=(string)($item['code']??$da); $r->brand=(string)($item['producer']??$db);
        $r->name=(string)($item['caption']??''); $r->price=$p; $r->quantity=$q;
        $r->deliveryDays=$deliveryDays; $r->deliveryPeriod=$deliveryPeriod;
        $r->deliveryLabel=$deliveryLabel; $r->deliveryTimeLabel=$deliveryTimeLabel;
        $r->deliveryToday=$deliveryToday; $r->deliveryDeadline=$deliveryDeadline;
        $r->warehouse=(string)($item['direction']??'');
        $r->stockId=(string)($item['itemHash']??''); $r->supplierName='ТатПартс';
        $r->isSched=($q<=0); $r->multiplicity=max(1,(int)($item['packing']??1)); $r->unit='шт.';
        $r->returnable=($item['return']??'')==='possible'; $r->raw=$item;

        // Для оформления заказа (см. SupplierOrderable::placeOrder()) — itemHash
        // из поиска (GetPriceList) устаревает к моменту оформления, перед
        // makeOrderOffline нужно заново подтвердить его через PreOrderSearch,
        // который и выдаёт актуальный itemId. code/producer сохраняем как
        // пришли от ТатПартс (а не нормализованные article/brand сайта) —
        // именно их ждёт PreOrderSearch.
        $r->orderMeta = [
            'item_hash' => (string)($item['itemHash'] ?? ''),
            'code'      => (string)($item['code'] ?? $da),
            'producer'  => (string)($item['producer'] ?? $db),
        ];
        return $r;
    }

    /**
     * Срок доставки ТатПартс. API отдаёт готовый ДИАПАЗОН в днях —
     * deliverydays_min/deliverydays_max (без времени суток, без дат) —
     * в отличие от остальных поставщиков тут нет единой точки, а есть
     * настоящий диапазон "от-до" в днях, поэтому показываем обе границы
     * как есть, а не выбираем одну.
     *
     * Поверх реальных цифр API — осознанный запас под логистику ТатПартс
     * (как у АвтоЕвро/Авторуси/Автопитера): +2 дня к обеим границам
     * диапазона.
     *
     * Для сортировки (deliveryDays) берём КОНСЕРВАТИВНУЮ верхнюю границу
     * (deliverydays_max) — по тому же принципу, что и у остальных
     * поставщиков (гарантированный/верхний срок надёжнее оптимистичного).
     * Зелёным бейджем "Сегодня" помечаем только когда обе границы = 0 —
     * диапазон, начинающийся сегодня но растянутый на несколько дней,
     * бейджем не выделяем, чтобы не обещать доставку прямо сегодня.
     *
     * @return array{0:?int,1:?int,2:?string,3:?string,4:bool,5:?string} [deliveryDays, deliveryPeriod(часы), dayLabel, timeLabel, isToday, deadlineHHMM]
     */
    private function resolveDelivery(array $item): array
    {
        $bufferDays = 2;

        $minRaw = $item['deliverydays_min'] ?? null;
        $maxRaw = $item['deliverydays_max'] ?? null;

        $dMin = (($minRaw !== null && $minRaw !== '') ? max(0, (int)$minRaw) : 1) + $bufferDays;
        $dMax = (($maxRaw !== null && $maxRaw !== '') ? max(0, (int)$maxRaw) + $bufferDays : $dMin);
        if ($dMax < $dMin) $dMax = $dMin;

        $fromLabel = $this->dayLabel($dMin);
        $isToday   = ($dMin === 0 && $dMax === 0);

        if ($dMax === $dMin) {
            return [$dMax, $dMax * 24, $fromLabel, null, $isToday, null];
        }

        $toLabel = $this->dayLabel($dMax);
        return [$dMax, $dMax * 24, $fromLabel, '- ' . $toLabel, $isToday, null];
    }

    private function dayLabel(int $days): string
    {
        if ($days === 0) return 'Сегодня';
        if ($days === 1) return 'Завтра';
        return date('d.m', strtotime("+{$days} days"));
    }

    private function generateWarehouseCode(string $n): string
    {
        static $m=['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',' '=>'_','.'=>'','-'=>'','('=>'',')'=>'','«'=>'','»'=>'','"'=>''];
        $t='';foreach(mb_str_split(mb_strtolower(trim($n))) as $c)$t.=$m[$c]??$c;
        return 'ttp_'.str_pad(substr(preg_replace('/[^a-z0-9]/','',$t),0,3),3,'x');
    }

    // ==================== ЗАКАЗ (SupplierOrderable) ====================

    /**
     * Один запрос на позицию (а не батч из нескольких container-элементов) —
     * пример в документации показывает ровно один provider/itemHash на
     * container-элемент, поведение батча из нескольких элементов с разными
     * itemHash не описано и не проверено, поэтому не рискуем.
     */
    private function preOrderSearch(string $itemHash, string $article, string $brand): ?array
    {
        $body = [
            'user'      => $this->user,
            'password'  => $this->password,
            'service'   => 'provider',
            'timeLimit' => $this->timeout,
            'action'    => 'PreOrderSearch',
            'container' => [[
                'provider' => $this->provider,
                'login'    => $this->supLogin,
                'password' => $this->supPass,
                'code'     => $article,
                'producer' => $brand,
                'itemHash' => $itemHash,
            ]],
        ];
        $resp = $this->execPost($body);
        if ($resp === null) return null;
        $data = json_decode($resp, true);
        if (!is_array($data)) return null;
        foreach (($data['container'] ?? []) as $cont) {
            foreach (($cont['items'] ?? []) as $row) {
                if (is_array($row) && !empty($row['itemId'])) return $row;
            }
        }
        return null;
    }

    public function placeOrder(array $items, bool $test = false): array
    {
        // Доступ к makeOrderOffline "оговаривается дополнительно с
        // менеджерами" и не имеет документированного тестового флага (в
        // отличие от Армтека с его createTestOrder) — как у Иксоры/Авторуси/
        // Росско, в тестовом режиме запрос вообще не отправляется.
        if ($test) {
            $this->log('placeOrder: тестовый режим не поддерживается API ТатПартс (нет флага теста у makeOrderOffline) — запрос не отправлен, items=' . count($items));
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'test_mode_not_supported'];
        }

        // Шаг 1 (обязателен по документации): PreOrderSearch по каждой
        // позиции — актуализирует цену/наличие и выдаёт свежий itemId,
        // itemHash из поиска (GetPriceList) к моменту оформления мог устареть.
        $orderItems = [];
        $basketByItemId = [];
        $skipped = 0;
        foreach ($items as $item) {
            $itemHash     = trim((string)($item['order_meta']['item_hash'] ?? ''));
            $article      = trim((string)($item['order_meta']['code'] ?? $item['article'] ?? ''));
            $brand        = trim((string)($item['order_meta']['producer'] ?? $item['brand'] ?? ''));
            $qty          = (int)($item['quantity'] ?? 0);
            $basketItemId = (int)($item['basket_item_id'] ?? 0);
            if ($itemHash === '' || $qty <= 0 || $basketItemId <= 0) { $skipped++; continue; }

            $fresh = $this->preOrderSearch($itemHash, $article, $brand);
            if (!$fresh || empty($fresh['itemId'])) {
                $this->log("placeOrder: PreOrderSearch не подтвердил позицию itemHash={$itemHash} basket_item_id={$basketItemId}");
                $skipped++;
                continue;
            }

            $itemId = (string)$fresh['itemId'];
            $orderItems[] = [
                'itemId'    => $itemId,
                'quantity'  => (string)$qty,
                'reference' => (string)($item['reference'] ?? $basketItemId),
                'comment'   => (string)($item['comment'] ?? ''),
            ];
            $basketByItemId[$itemId] = $basketItemId;
        }

        if (empty($orderItems)) {
            $this->log('placeOrder: нет ни одной валидной позиции после PreOrderSearch, пропущено ' . $skipped);
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'no_valid_items'];
        }

        // Шаг 2: makeOrderOffline — в отличие от PreOrderSearch, здесь один
        // provider-элемент батчит сразу несколько items по документации.
        $body = [
            'user'     => $this->user,
            'password' => $this->password,
            'service'  => 'provider',
            'action'   => 'makeOrderOffline',
            'param'    => [[
                'provider' => $this->provider,
                'login'    => $this->supLogin,
                'password' => $this->supPass,
                'comment'  => (string)($items[0]['comment'] ?? ''),
                'items'    => $orderItems,
            ]],
        ];

        $this->log('placeOrder: request items=' . count($orderItems) . ' skipped=' . $skipped . ' body=' . json_encode($body, JSON_UNESCAPED_UNICODE));

        $resp = $this->execPost($body);
        $this->log('placeOrder: response body=' . substr((string)$resp, 0, 4000));

        if ($resp === null) {
            return ['http_code' => null, 'success' => false, 'raw' => null, 'error' => 'http_error'];
        }

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            return ['http_code' => 200, 'success' => false, 'raw' => ['_raw_text' => $resp], 'error' => 'invalid_json'];
        }

        if (!empty($decoded['error'])) {
            return ['http_code' => 200, 'success' => false, 'raw' => $decoded, 'error' => (string)$decoded['error']];
        }

        $itemReferences = [];
        $overallStatus  = null;
        foreach (($decoded['result'] ?? []) as $res) {
            if (!is_array($res)) continue;
            $overallStatus = $res['orderStatus'] ?? $overallStatus;
            foreach (($res['items'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $itemId       = (string)($row['itemId'] ?? '');
                $orderItemId  = (string)($row['orderItemId'] ?? '');
                $rowError     = trim((string)($row['error'] ?? ''));
                $basketItemId = $basketByItemId[$itemId] ?? 0;
                if ($basketItemId <= 0 || $orderItemId === '' || $rowError !== '') continue;
                $itemReferences[$basketItemId] = $orderItemId;
            }
        }

        // orderStatus MakeOrderError — заказ не создан целиком, даже если
        // где-то по позиции error пуст (см. документацию makeOrderOffline).
        $success = !empty($itemReferences) && $overallStatus !== 'MakeOrderError';

        return [
            'http_code'       => 200,
            'success'         => $success,
            'raw'             => $decoded,
            'error'           => $success ? null : ($overallStatus === 'MakeOrderError' ? 'order_rejected' : 'unparsable_response'),
            'item_references' => $itemReferences,
        ];
    }

    // ==================== СТАТУС ЗАКАЗА (SupplierOrderStatusProvider) ====================

    /** $reference — orderItemId, полученный из placeOrder()::item_references. */
    public function fetchOrderStatusByReference(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') return [];

        $body = [
            'user'      => $this->user,
            'password'  => $this->password,
            'service'   => 'provider',
            'action'    => 'getItemsStatus',
            'timeLimit' => $this->timeout,
            'container' => [[
                'provider' => $this->provider,
                'login'    => $this->supLogin,
                'password' => $this->supPass,
                'items'    => [$reference],
            ]],
        ];

        $resp = $this->execPost($body);
        $this->log("fetchOrderStatusByReference({$reference}): response body=" . substr((string)$resp, 0, 4000));
        if ($resp === null) return [];

        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) return [];

        foreach (($decoded['container'] ?? []) as $cont) {
            if (!is_array($cont)) continue;
            foreach (($cont['items'] ?? []) as $row) {
                if (!is_array($row)) continue;
                // Документация ТатПартс сама себе противоречит: в примере JSON
                // ответа поле называется providerItemId, а в таблице параметров
                // ниже — orderItemId. Проверяем оба варианта.
                $id = (string)($row['orderItemId'] ?? $row['providerItemId'] ?? '');
                if ($id !== '' && $id !== $reference) continue;

                $stateName = trim((string)($row['stateName'] ?? ''));
                $stateId   = isset($row['stateId']) && $row['stateId'] !== '' ? (string)$row['stateId'] : null;
                $rowError  = trim((string)($row['error'] ?? ''));

                return [[
                    'order_number'    => (string)($row['providerOrderNumber'] ?? ''),
                    'state_id'        => $stateId,
                    'state_text'      => $stateName !== '' ? $stateName : ($rowError ?: null),
                    'stage'           => $this->normalizeStage($stateId, $stateName, $rowError),
                    'expected_date'   => null,
                    'guaranteed_date' => null,
                    'store_count'     => null,
                    'release_count'   => null,
                    'refusal_count'   => null,
                    'comment'         => $rowError ?: null,
                    'raw'             => $row,
                ]];
            }
        }
        return [];
    }

    /**
     * Полный официальный словарь статусов ТатПартс (getStatusList, снят вживую
     * 2026-09-09 для provider=tatparts_ru, 70 значений) — числовые id это
     * реальные статусы позиции заказа, id вида "provider.*"/"error.*" —
     * технические события конвейера оформления (добавление в корзину
     * поставщика, ошибки API). Точное сопоставление по id, а не по тексту:
     * текстовые фразы ТатПартс используют неочевидно ("запрос на отмену
     * заказа", id=15, подтверждено вживую по заказу №192 — это УЖЕ одобренный
     * отказ, а не ожидание решения, при этом на сайте ТатПартс дальше видно
     * финальный статус id=14 "отказ клиента" — оба считаем refused).
     *
     * Разметка неочевидных случаев:
     *  - id 27 "Выдано не все количество" / 19 "не все кол-во" — частичное
     *    исполнение, не финал ни в одну сторону → in_transit.
     *  - id 30 "возврат отклонён" — запрос на возврат СОРВАЛСЯ, значит товар
     *    остаётся у клиента → ready (успешная поставка в силе).
     *  - id 44/45/47/48/50 "недовоз от поставщика"/"МСК брак/не влезло, для
     *    прихода" — внутренние проблемы снабжения ТатПартс ДО решения по
     *    нашей позиции, ещё не финал → ordered.
     *  - id 52 "Возврат на согласовании" — решение не принято (в отличие от
     *    id 28/29, где ТатПартс использует "запрос"/"одобрен" как готовый
     *    факт) → ordered.
     */
    private const STAGE_BY_STATE_ID = [
        // --- финальный успех ---
        '7'  => 'ready',  // выдано
        '22' => 'ready',  // К выдаче
        '30' => 'ready',  // возврат отклонён — товар остаётся у клиента

        // --- физически движется/подтверждено, но ещё не выдано ---
        '2'  => 'in_transit', // в работе
        '3'  => 'in_transit', // пришло на склад
        '8'  => 'in_transit', // подтвержден поставщиком
        '11' => 'in_transit', // ожидается приход на склад ночью
        '13' => 'in_transit', // Собран поставщиком
        '19' => 'in_transit', // в пути на склад не все кол-во
        '27' => 'in_transit', // выдано не все количество (частично)
        '33' => 'in_transit', // в пути на склад, изменилась дата
        '39' => 'in_transit', // ожидается приход на склад днём
        '40' => 'in_transit', // ожидается приход на склад вечером
        '43' => 'in_transit', // завтра дневной приход
        '53' => 'in_transit', // завтра ночной приход
        '57' => 'in_transit', // в пути на склад
        'provider.send_basket' => 'in_transit',

        // --- отказ/возврат/ошибка — финал НЕ в пользу заказа ---
        '4'  => 'refused', // нет в наличии
        '14' => 'refused', // отказ клиента (подтверждено вживую, заказ №192)
        '15' => 'refused', // запрос на отмену заказа (подтверждено вживую — уже одобрен)
        '16' => 'refused', // возврат от покупателя
        '17' => 'refused', // возврат поставщику
        '18' => 'refused', // отказ поставщика
        '28' => 'refused', // запрос на возврат
        '29' => 'refused', // возврат одобрен
        '31' => 'refused', // Отказ, брак
        '32' => 'refused', // Отказ, пересортица
        '34' => 'refused', // Отказ, недовоз
        '37' => 'refused', // отказ, изменение цены
        '42' => 'refused', // Недовоз клиенту
        '49' => 'refused', // МСК отказ клиента, для прихода
        'error.order'                       => 'refused',
        'error.basket'                      => 'refused',
        'error.service'                     => 'refused',
        'error.not_found'                   => 'refused',
        'provider.order_canceled'           => 'refused',
        'provider.basket_canceled'          => 'refused',
        'provider.basket_canceled.by_price'  => 'refused',
        'provider.basket_canceled.by_amount' => 'refused',

        // --- принято/в обработке, финал ещё не наступил ---
        '0'  => 'ordered', // в корзине
        '1'  => 'ordered', // заказ принят
        '9'  => 'ordered', // приостановлено
        '10' => 'ordered', // возможна задержка
        '20' => 'ordered', // замена номера
        '23' => 'ordered', // задержка поставки 1 день
        '24' => 'ordered', // задержка поставки 2 дня
        '25' => 'ordered', // задержка, причины и сроки выясняются
        '26' => 'ordered', // задержка, изменение даты поставки
        '35' => 'ordered', // Подтвердите (увеличение цены!)
        '36' => 'ordered', // Подтверждено клиентом
        '38' => 'ordered', // для внутреннего пользования
        '41' => 'ordered', // WEB ЗАКАЗ
        '44' => 'ordered', // Недовоз от пост. в Н.Ч.
        '45' => 'ordered', // недовоз от пост. в МСК
        '46' => 'ordered', // Перемещено
        '47' => 'ordered', // МСК недовоз, для прихода
        '48' => 'ordered', // МСК брак, для прихода
        '50' => 'ordered', // МСК не влезло, для прихода
        '51' => 'ordered', // На экспертизе
        '52' => 'ordered', // Возврат на согласовании
        '54' => 'ordered', // Ожидает приемки (ночь)
        '55' => 'ordered', // Ожидает приемки (МСК)
        '56' => 'ordered', // Ожидает приемки (день)
        '58' => 'ordered', // Ожидает приемки WMS
        '59' => 'ordered', // Запрос на рассмотрении
        '60' => 'ordered', // УПД в МСК
        '61' => 'ordered', // Требуется доп. информация
        'provider.order_done'  => 'ordered', // товар добавлен в заказ
        'provider.basket_done' => 'ordered', // товар добавлен в корзину
        'service.divided'      => 'ordered', // технический маркер разделения позиции
    ];

    private function normalizeStage(?string $stateId, string $stateName, string $error): string
    {
        if ($stateId !== null && isset(self::STAGE_BY_STATE_ID[$stateId])) {
            return self::STAGE_BY_STATE_ID[$stateId];
        }

        // Fallback для id, которого нет в словаре (новый статус у ТатПартс,
        // не попавший в снятый вживую список) — эвристика по тексту, тот же
        // безопасный дефолт 'ordered', что и раньше.
        $this->log("normalizeStage: неизвестный id='" . ($stateId ?? '') . "' name='{$stateName}' — использую текстовый фолбэк");
        $s = mb_strtolower($stateName . ' ' . $error);
        if (str_contains($s, 'не найдена') || str_contains($s, 'отказ') || str_contains($s, 'отмен') || str_contains($s, 'аннулир') || str_contains($s, 'нет в наличии')) return 'refused';
        if (str_contains($s, 'выдан') || str_contains($s, 'получен') || str_contains($s, 'доставлен') || str_contains($s, 'завершен')) return 'ready';
        if (str_contains($s, 'пути') || str_contains($s, 'отгруж') || str_contains($s, 'транзит')) return 'in_transit';
        return 'ordered';
    }

    private function log(string $message): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru';
        $file = $root . '/upload/logs/tatparts_' . date('Y-m-d') . '.log';
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}
