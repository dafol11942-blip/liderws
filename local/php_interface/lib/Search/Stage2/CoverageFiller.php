<?php
namespace Lider\Search\Stage2;

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
        float $deadline = 10.0
    ): array {
        $analyzer = new CoverageAnalyzer($this->factory);
        $coverage = $analyzer->analyze($groupedItems, $exactKey);

        if (empty($coverage)) {
            $this->log("FILL: no groups need filling");
            return $groupedItems;
        }

        $this->log("FILL: " . count($coverage) . " groups need filling");
        $toFill = array_slice($coverage, 0, 50, true);

        $allRequests  = [];
        $requestMeta  = [];

        foreach ($toFill as $gk => $info) {
            $brand   = $info['brand'];
            $article = $info['article'];
            $missing = $info['missing'];
            $bmInfo  = $brandMap[$gk] ?? null;

            foreach ($missing as $supCode) {
                $sup = $this->factory->get($supCode);
                if (!$sup) continue;

                $supBrand = $brand;
                $supArt   = $article;
                if ($bmInfo) {
                    $supBrand = !empty($bmInfo['brands'][$supCode])
                        ? (string)$bmInfo['brands'][$supCode] : $brand;
                    $supArt = !empty($bmInfo['articles'][$supCode])
                        ? (string)$bmInfo['articles'][$supCode] : $article;
                }

                $req = $sup->buildSearchRequest($supBrand, $supArt, false);
                if (!$req) {
                    $req = $sup->buildSearchRequest('', $supArt, false);
                }
                if (!$req) continue;

                $key = $supCode . ':fill:' . $gk;
                $allRequests[$key] = $req;
                $requestMeta[$key] = [
                    'sup'      => $sup,
                    'groupKey' => $gk,
                    'brand'    => $supBrand,
                    'article'  => $supArt,
                ];
            }
        }

        if (empty($allRequests)) {
            $this->log("FILL: no requests generated");
            return $groupedItems;
        }

        $this->log("FILL: executing " . count($allRequests) . " requests IN PARALLEL, deadline={$deadline}s");
        $responses = $this->executeAllParallel($allRequests, $deadline);

        $addedCount = 0;
        foreach ($responses as $key => $resp) {
            $meta = $requestMeta[$key] ?? null;
            if (!$meta || empty($resp['body'])) {
                if ($meta) {
                    $this->log("  FILL EMPTY [{$meta['sup']->getCode()}] [{$meta['groupKey']}] {$meta['brand']}|{$meta['article']} err=" . ($resp['error'] ?? 'empty'));
                }
                continue;
            }

            /** @var SupplierInterface $sup */
            $sup = $meta['sup'];
            $gk  = $meta['groupKey'];
            $supCode = $sup->getCode();
            $supName = $sup->getName();

            try {
                $items = $sup->parseSearchResponse($resp['body'], $meta['brand'], $meta['article']);
                $matched = 0;
                foreach ($items as $item) {
                    if (!($item instanceof SearchResultItem)) continue;
                    if ($item->price <= 0 && $item->quantity <= 0) continue;

                    $itemKey = BrandNormalizer::groupKey($item->brand, $item->article);
                    if ($itemKey !== $gk) continue;

                    $itemSupplierName = $item->supplierName ?: $supName;
                    $priceBase = round((float)$item->price, 2);
                    $priceDisplay = function_exists('getDisplayPrice')
                        ? getDisplayPrice($priceBase) : $priceBase;

                    if ($item->isSched) {
                        $stockName = 'Под заказ';
                    } elseif (function_exists('maskWarehouse')) {
                        $stockName = maskWarehouse($item);
                    } else {
                        $stockName = $item->warehouse ?: '—';
                    }

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
                        'price_base'  => $priceBase,
                        'qty'         => $item->quantity,
                        'multiplicity'=> $item->multiplicity ?? 1,
                        'unit'        => $item->unit ?? 'шт.',
                        'delivery'    => $delivery,
                        'is_sched'    => $item->isSched,
                        'returnable'  => $item->returnable,
                        'source'      => $src,
                        'supplier'    => $itemSupplierName,
                    ];
                    $matched++;
                }

                if ($matched > 0) {
                    $this->log("  FILL OK [{$supCode}] [{$gk}] +{$matched} items");
                    $addedCount += $matched;
                } else {
                    $this->log("  FILL ZERO [{$supCode}] [{$gk}] parsed but 0 matched");
                }
            } catch (\Throwable $e) {
                $this->log("  FILL ERR [{$supCode}] [{$gk}]: " . $e->getMessage());
            }
        }

        $this->log("FILL DONE: added {$addedCount} items across all groups");
        return $groupedItems;
    }

    private function executeAllParallel(array $requests, float $deadline): array
    {
        $results = [];
        $startTime = microtime(true);
        if (empty($requests)) return $results;

        $mh = curl_multi_init();
        $handles = [];

        foreach ($requests as $key => $req) {
            $ch = curl_init($req['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
                CURLOPT_TIMEOUT        => max(15, (int)ceil($deadline)),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
            ]);

            if (($req['method'] ?? 'GET') === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($req['body'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                }
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($status !== CURLM_OK) break;
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= $deadline) break;
            curl_multi_select($mh, min(0.2, max(0.05, $deadline - $elapsed)));
        } while ($active > 0);

        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errMsg = curl_error($ch);
            $results[$key] = [
                'body'  => ($httpCode === 200 && is_string($body) && $body !== '') ? $body : null,
                'http'  => $httpCode,
                'error' => $errMsg ?: ($httpCode === 200 ? '' : "HTTP {$httpCode}"),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
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
