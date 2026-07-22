<?php
namespace Lider\Search;

use Lider\Supplier\SupplierFactory;

class SearchService
{
    private SupplierFactory $supplierFactory;
    private SearchCacheManager $cache;
    private int $localIblockId;

    public function __construct(SupplierFactory $supplierFactory, int $localIblockId = 42)
    {
        $this->supplierFactory = $supplierFactory;
        $this->cache           = new SearchCacheManager();
        $this->localIblockId   = $localIblockId;
    }

    public function search(string $query, bool $includeLocal = true, bool $includeSuppliers = true): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];

        $allResults = [];

        // Локальный поиск (с кешем)
        if ($includeLocal) {
            $cacheKey = SearchCacheManager::buildKey($query, 'local');
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $allResults = array_merge($allResults, $cached);
            } else {
                $localResults = $this->searchLocal($query);
                $this->cache->set($cacheKey, $localResults, 300);
                $allResults = array_merge($allResults, $localResults);
            }
        }

        // Поставщики — параллельно
        if ($includeSuppliers) {
            $allResults = array_merge($allResults, $this->searchSuppliersParallel($query));
        }

        usort($allResults, function (SearchResultItem $a, SearchResultItem $b) {
            if ($a->source === 'local' && $b->source !== 'local') return -1;
            if ($a->source !== 'local' && $b->source === 'local') return 1;
            return $a->price <=> $b->price;
        });

        return $allResults;
    }

    /**
     * Параллельный поиск по поставщикам.
     * Использует buildBrandsRequest для Этапа 1, затем buildSearchRequest для Этапа 2.
     */
    private function searchSuppliersParallel(string $query): array
    {
        $allResults = [];

        // === Этап 1: параллельный сбор брендов ===
        $brandRequests = [];
        foreach ($this->supplierFactory->allAvailable() as $supplier) {
            $cacheKey = SearchCacheManager::buildKey($query, $supplier->getCode() . '_brands');
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // Бренды из кеша — передаём дальше без запроса
                $brandRequests[$supplier->getCode()] = ['cached' => $cached, 'supplier' => $supplier];
                continue;
            }

            $req = $supplier->buildBrandsRequest($query);
            if ($req) {
                $brandRequests[$supplier->getCode()] = ['req' => $req, 'supplier' => $supplier];
            }
        }

        // Разделяем: что в кеше, что нужно запрашивать
        $toFetch = array_filter($brandRequests, fn($d) => isset($d['req']));
        $cachedBrands = array_filter($brandRequests, fn($d) => isset($d['cached']));

        $allBrandsBySupplier = [];

        // Собираем кешированные бренды
        foreach ($cachedBrands as $code => $data) {
            $allBrandsBySupplier[$code] = $data['cached'];
        }

        // Параллельно запрашиваем остальные
        if (!empty($toFetch)) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($toFetch as $code => $data) {
                $ch = curl_init();
                $req = $data['req'];
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $req['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $req['headers'],
                    CURLOPT_TIMEOUT        => 6,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                if ($req['method'] === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if ($req['body']) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                }
                curl_multi_add_handle($mh, $ch);
                $handles[$code] = $ch;
            }

            $running = null;
            do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);

            foreach ($handles as $code => $ch) {
                $responseBody = curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if (!empty($responseBody)) {
                    try {
                        $brands = $toFetch[$code]['supplier']->parseBrandsResponse($responseBody, $query);
                        $allBrandsBySupplier[$code] = $brands;
                        // Кешируем бренды
                        $this->cache->set(
                            SearchCacheManager::buildKey($query, $code . '_brands'),
                            $brands,
                            900
                        );
                    } catch (\Throwable $e) {
                        $allBrandsBySupplier[$code] = [];
                    }
                } else {
                    $allBrandsBySupplier[$code] = [];
                }
            }
            curl_multi_close($mh);
        }

        // === Этап 2: параллельный сбор предложений (топ-3 бренда от каждого поставщика) ===
        $searchRequests = [];
        foreach ($allBrandsBySupplier as $code => $brands) {
            $supplier = $this->supplierFactory->get($code);
            if (!$supplier) continue;

            $brands = array_slice($brands, 0, 3); // топ-3 бренда
            foreach ($brands as $br) {
                $b = $br['brand'] ?? '';
                $a = $br['article_fix'] ?? $br['article'] ?? '';
                if (!$b || !$a) continue;

                $req = $supplier->buildSearchRequest($b, $a);
                if ($req) {
                    $searchRequests[] = ['req' => $req, 'supplier' => $supplier, 'brand' => $b, 'article' => $a];
                } else {
                    // ShateM — последовательный fallback
                    try {
                        $items = $supplier->searchByBrandArticle($b, $a);
                        $allResults = array_merge($allResults, array_slice($items, 0, 3));
                    } catch (\Throwable $e) {}
                }
            }
        }

        if (!empty($searchRequests)) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($searchRequests as $idx => $data) {
                $ch = curl_init();
                $req = $data['req'];
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $req['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $req['headers'],
                    CURLOPT_TIMEOUT        => 6,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                if ($req['method'] === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if ($req['body']) curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                }
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = $ch;
            }

            $running = null;
            do { curl_multi_exec($mh, $running); curl_multi_select($mh, 0.1); } while ($running > 0);

            foreach ($handles as $idx => $ch) {
                $responseBody = curl_multi_getcontent($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if (!empty($responseBody)) {
                    try {
                        $items = $searchRequests[$idx]['supplier']->parseSearchResponse(
                            $responseBody,
                            $searchRequests[$idx]['brand'],
                            $searchRequests[$idx]['article']
                        );
                        $allResults = array_merge($allResults, array_slice($items, 0, 3));
                    } catch (\Throwable $e) {}
                }
            }
            curl_multi_close($mh);
        }

        return $allResults;
    }

    private function searchLocal(string $query): array
    {
        $results = [];
        if (!\CModule::IncludeModule('iblock') || !\CModule::IncludeModule('catalog')) return $results;

        $filter = [
            'IBLOCK_ID' => $this->localIblockId, 'ACTIVE' => 'Y',
            ['LOGIC' => 'OR',
                ['%NAME' => $query], ['PROPERTY_CML2_ARTICLE' => $query],
                ['%PROPERTY_CML2_ARTICLE' => $query], ['%DETAIL_TEXT' => $query],
                ['PROPERTY_CML2_MANUFACTURER' => $query], ['%PROPERTY_CML2_MANUFACTURER' => $query],
            ],
        ];

        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, ['nTopCount' => 50],
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'PROPERTY_CML2_ARTICLE', 'PROPERTY_CML2_MANUFACTURER', 'PROPERTY_IN_STOCK']);

        while ($item = $res->GetNext()) {
            $price = 0;
            $dbPrice = \CPrice::GetList([], ['PRODUCT_ID' => $item['ID']]);
            if ($arPrice = $dbPrice->Fetch()) $price = (float)$arPrice['PRICE'];

            $quantity = 0;
            $dbProduct = \CCatalogProduct::GetList([], ['ID' => $item['ID']], false, false, ['QUANTITY']);
            if ($arProduct = $dbProduct->Fetch()) $quantity = (int)$arProduct['QUANTITY'];

            $imgUrl = !empty($item['PREVIEW_PICTURE']) ? \CFile::GetPath($item['PREVIEW_PICTURE']) : '';

            $r = new SearchResultItem();
            $r->source = 'local'; $r->article = (string)($item['PROPERTY_CML2_ARTICLE_VALUE'] ?? '');
            $r->brand = (string)($item['PROPERTY_CML2_MANUFACTURER_VALUE'] ?? '');
            $r->name = $item['NAME']; $r->price = $price; $r->quantity = $quantity;
            $r->deliveryDays = $quantity > 0 ? null : 1;
            $r->warehouse = $quantity > 0 ? 'Елабуга' : 'Под заказ';
            $r->supplierName = 'Собственный склад';
            $r->localProductId = (int)$item['ID'];
            $r->imageUrl = $imgUrl; $r->detailUrl = $item['DETAIL_PAGE_URL'];
            $results[] = $r;
        }
        return $results;
    }
}
