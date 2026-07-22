<?php
namespace Lider\Search\Stage2;

use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

/**
 * Группирует SearchResultItem[] по ключу normBrand|normArticle.
 * Внутри группы: объединяет склады, считает min/max, строит _by_sup.
 */
class OfferAggregator
{
    private int $maxWhPerSupplier;
    private int $maxWhTotal;

    public function __construct(int $maxWhPerSupplier = 10, int $maxWhTotal = 40)
    {
        $this->maxWhPerSupplier = $maxWhPerSupplier;
        $this->maxWhTotal       = $maxWhTotal;
    }

    /**
     * @param SearchResultItem[] $items
     * @return array  [groupKey => [brand, article, description, min_price, max_price, 
     *                              total_qty, in_stock_qty, min_delivery, has_instock,
     *                              _by_sup, _seen_wh, _articles_raw, warehouses]]
     */
    public function aggregate(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (!($item instanceof SearchResultItem)) continue;

            $key = BrandNormalizer::groupKey($item->brand, $item->article);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    '_seen_wh'      => [],
                    '_articles_raw' => [],
                    '_by_sup'       => [],
                    'brand'         => BrandNormalizer::displayBrand($item->brand),
                    'article'       => $item->article,
                    'description'   => $item->name,
                    'min_price'     => PHP_FLOAT_MAX,
                    'max_price'     => 0.0,
                    'total_qty'     => 0,
                    'in_stock_qty'  => 0,
                    'min_delivery'  => ['days' => PHP_INT_MAX, 'is_approx' => false],
                    'has_instock'   => false,
                    'warehouses'    => [],
                ];
            }

            $g = &$groups[$key];

            $g['_articles_raw'][] = $item->article;
            $g['brand'] = BrandNormalizer::displayBrand($g['brand'] ?: $item->brand);

            if (mb_strlen((string)$item->name) > mb_strlen((string)$g['description'])) {
                $g['description'] = $item->name;
            }

            $priceBase    = round((float)$item->price, 2);
            $priceDisplay = function_exists('getDisplayPrice')
                ? getDisplayPrice($priceBase) : $priceBase;
            $delivery     = function_exists('calcDelivery')
                ? calcDelivery($item)
                : ['days' => $item->deliveryDays ?? 0, 'is_approx' => false];
            $stockName    = $item->isSched
                ? 'Под заказ'
                : (function_exists('maskWarehouse') ? maskWarehouse($item) : ($item->warehouse ?: '—'));
            $src = (string)$item->source;

            // min/max
            if ($priceDisplay > 0 && $priceDisplay < $g['min_price']) $g['min_price'] = $priceDisplay;
            if ($priceDisplay > $g['max_price']) $g['max_price'] = $priceDisplay;

            $g['total_qty'] += (int)$item->quantity;
            if (!$item->isSched) {
                $g['in_stock_qty'] += (int)$item->quantity;
                $g['has_instock'] = true;
            }

            if (($delivery['days'] ?? PHP_INT_MAX) < ($g['min_delivery']['days'] ?? PHP_INT_MAX)) {
                $g['min_delivery'] = $delivery;
            }

            // Дедупликация складов
            $whKey = $src . '|' . ($item->stockId ?: $item->warehouse)
                   . '|' . $priceDisplay . '|' . ((int)$item->quantity);
            if (isset($g['_seen_wh'][$whKey])) { unset($g); continue; }

            if (!isset($g['_by_sup'][$src])) $g['_by_sup'][$src] = [];
            if (count($g['_by_sup'][$src]) >= $this->maxWhPerSupplier) { unset($g); continue; }

            $g['_seen_wh'][$whKey] = true;
            $g['_by_sup'][$src][] = [
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
                'supplier'    => $item->supplierName,
            ];

            unset($g);
        }

        return $groups;
    }
}
