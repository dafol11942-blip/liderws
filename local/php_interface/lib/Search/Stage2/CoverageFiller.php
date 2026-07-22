<?php
namespace Lider\Search\Stage2;

use Lider\Search\Common\MultiCurlExecutor;
use Lider\Supplier\SupplierFactory;
use Lider\Supplier\SupplierInterface;
use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class CoverageFiller
{
    private SupplierFactory $factory;

    public function __construct(SupplierFactory $factory)
    {
        $this->factory = $factory;
    }

    public function fill(
        array $groupedItems,
        string $exactKey,
        array $brandMap,
        float $deadline = 6.0
    ): array {
        $analyzer = new CoverageAnalyzer($this->factory);
        $coverage = $analyzer->analyze($groupedItems, $exactKey);

        if (empty($coverage)) {
            $this->log("FILL: no groups need filling");
            return $groupedItems;
        }

        $this->log("FILL: " . count($coverage) . " groups need filling");

        // Берём топ-15 с наихудшим покрытием
        $toFill = array_slice($coverage, 0, 15, true);

        $allRequests  = [];
        $requestMeta  = [];

        foreach ($toFill as $gk => $info) {
            $brand   = $info['brand'];
            $article = $info['article'];
            $missing = $info['missing'];
            $bmInfo  = $brandMap[$gk] ?? null;

            $this->log("FILL group [{$gk}] {$brand}|{$article} missing=" . implode(',', $missing));

            foreach ($missing as $supCode) {
                $sup = $this->factory->get($supCode);
                if (!$sup) continue;

                // Пробуем получить бренд+артикул из brandmap
                $supBrand = $brand;
                $supArt   = $article;
                if ($bmInfo) {
                    $supBrand = !empty($bmInfo['brands'][$supCode])
                        ? (string)$bmInfo['brands'][$supCode] : $brand;
                    $supArt = !empty($bmInfo['articles'][$supCode])
                        ? (string)$bmInfo['articles'][$supCode] : $article;
                }

                // Попытка 1: поиск с брендом
                $req = $sup->buildSearchRequest($supBrand, $supArt, false);

                // Попытка 2: если бренд нестандартный — поиск только по артикулу
                if (!$req) {
                    $req = $sup->buildSearchRequest('', $supArt, false);
                }

                if (!$req) {
                    $this->log("  FILL [{$supCode}] no request for {$supBrand}|{$supArt}");
                    continue;
                }

                $key = $supCode . ':fill:' . $gk;
                $req['_key']      = $key;
                $req['_timeout']  = 4;
                $req['_priority'] = 3;
                $allRequests[]    = $req;
                $requestMeta[$key] = [
                    'sup'      => $sup,
                    'groupKey' => $gk,
                    'brand'    => $supBrand,
                    'article'  => $supArt,
                    'bmInfo'   => $bmInfo,
                ];

                $this->log("  FILL req [{$supCode}] {$supBrand}|{$supArt}");
            }
        }

        if (empty($allRequests)) {
            $this->log("FILL: no requests generated");
            return $groupedItems;
        }

        $this->log("FILL: executing " . count($allRequests) . " requests");
        $executor  = new MultiCurlExecutor();
        $responses = $executor->executeAll($allRequests, $deadline);

        $addedCount = 0;
        foreach ($responses as $key => $resp) {
            $meta = $requestMeta[$key] ?? null;
            if (!$meta || empty($resp['body'])) continue;

            /** @var SupplierInterface $sup */
            $sup = $meta['sup'];
            $gk  = $meta['groupKey'];

            try {
                $items = $sup->parseSearchResponse($resp['body'], $meta['brand'], $meta['article']);

                $matched = 0;
                foreach ($items as $item) {
                    if (!($item instanceof SearchResultItem)) continue;
                    if ($item->price <= 0 && $item->quantity <= 0) continue;

                    // Проверяем, что item принадлежит нужной группе
                    $itemKey = BrandNormalizer::groupKey($item->brand, $item->article);
                    if ($itemKey !== $gk) continue;

                    $priceDisplay = function_exists('getDisplayPrice')
                        ? getDisplayPrice(round((float)$item->price, 2))
                        : round((float)$item->price, 2);

                    $stockName = $item->isSched ? 'Под заказ'
                        : (function_exists('maskWarehouse') ? maskWarehouse($item) : ($item->warehouse ?: '—'));

                    $delivery = function_exists('calcDelivery')
                        ? calcDelivery($item)
                        : ['days' => $item->deliveryDays ?? 0, 'is_approx' => false];

                    $src = (string)$item->source;
                    $whKey = $src . '|' . ($item->stockId ?: $item->warehouse) . '|' . $priceDisplay . '|' . ((int)$item->quantity);

                    if (!isset($groupedItems[$gk])) continue;
                    if (isset($groupedItems[$gk]['_seen_wh'][$whKey])) continue;

                    if (!isset($groupedItems[$gk]['_by_sup'][$src])) {
                        $groupedItems[$gk]['_by_sup'][$src] = [];
                    }

                    $groupedItems[$gk]['_seen_wh'][$whKey] = true;
                    $groupedItems[$gk]['_by_sup'][$src][] = [
                        'stock'       => $stockName,
                        'price'       => $priceDisplay,
                        'price_base'  => round((float)$item->price, 2),
                        'qty'         => $item->quantity,
                        'multiplicity'=> $item->multiplicity ?? 1,
                        'unit'        => $item->unit ?? 'шт.',
                        'delivery'    => $delivery,
                        'is_sched'    => $item->isSched,
                        'returnable'  => $item->returnable,
                        'source'      => $src,
                        'supplier'    => $item->supplierName,
                    ];
                    $matched++;
                }

                if ($matched > 0) {
                    $this->log("  FILL OK [{$sup->getCode()}] [{$gk}] +{$matched} items");
                    $addedCount += $matched;
                }
            } catch (\Throwable $e) {
                $this->log("  FILL ERR [{$sup->getCode()}] [{$gk}]: " . $e->getMessage());
            }
        }

        $this->log("FILL DONE: added {$addedCount} items across all groups");
        return $groupedItems;
    }

    private function log(string $message): void
    {
        @file_put_contents(
            ($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/u3564357/data/www/liderws.ru') . '/upload/logs/coverage_filler.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            FILE_APPEND
        );
    }
}
