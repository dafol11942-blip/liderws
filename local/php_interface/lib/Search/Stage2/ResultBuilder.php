<?php
namespace Lider\Search\Stage2;

use Lider\Search\BrandNormalizer;

class ResultBuilder
{
    public function __construct(
        private int $analogDisplayCap = 300,
        private int $maxWhPerSupplier = 15,
        private int $maxWhTotal = 60
    ) {}

    public function build(
        array $groupedItems, string $exactKey, string $normTargetBrand, string $normTargetArt,
        string $displayBrand, string $displayArticle, array $brandMap,
        array $filters = [], string $sortExact = 'default', string $sortAnalog = 'default'
    ): array {
        $exactGroups = []; $analogGroups = [];

        foreach ($groupedItems as $key => &$g) {
            if (empty($g['warehouses']) && empty($g['_by_sup'])) continue;
            if ($key === $exactKey || (BrandNormalizer::normalize($g['brand']) === $normTargetBrand && BrandNormalizer::normalizeArticle($g['article']) === $normTargetArt)) {
                $g['brand'] = $displayBrand; $g['article'] = $displayArticle;
            } elseif (isset($brandMap[$key])) {
                $info = $brandMap[$key];
                $g['brand'] = BrandNormalizer::displayBrand((string)reset($info['brands']));
                $g['article'] = BrandNormalizer::pickDisplayArticle($info['articles'] ?? [], $info['article_nr'] ?? $g['article']);
            } else {
                $g['article'] = BrandNormalizer::pickDisplayArticle($g['_articles_raw'] ?? [], $g['article']);
                $g['brand'] = BrandNormalizer::displayBrand($g['brand']);
            }
            $g['warehouses'] = $this->roundRobin($g['_by_sup'] ?? [], $this->maxWhPerSupplier, $this->maxWhTotal);
            $this->recalc($g);
            unset($g['_seen_wh'], $g['_articles_raw'], $g['_by_sup']);
        }
        unset($g);

        foreach ($groupedItems as $key => $g) {
            if (empty($g['warehouses'])) continue;
            $gbn = BrandNormalizer::normalize($g['brand']); $gan = BrandNormalizer::normalizeArticle($g['article']);
            if (($gbn === $normTargetBrand && $gan === $normTargetArt) || $key === $exactKey) {
                $exactGroups[$key] = $g;
        } elseif ($gan === $normTargetArt && $gbn !== $normTargetBrand) {
            // Тот же артикул, другой бренд (LYNX vs LYNXauto) → в exactGroups
            $exactGroups[$key] = $g;
            } else {
            $analogGroups[$key] = $g;
        }
        }    

        $fpmin = (int)($filters['price_min'] ?? 0); $fpmax = (int)($filters['price_max'] ?? 0);
        $fb = trim((string)($filters['brand'] ?? ''));
        $flt = fn($g) => !($fpmin>0&&$g['min_price']<$fpmin) && !($fpmax>0&&$g['min_price']>$fpmax) && !($fb!==''&&mb_stripos($g['brand'],$fb)===false);
        $exactGroups = array_filter($exactGroups, $flt);
        $analogGroups = array_filter($analogGroups, $flt);

        // СОРТИРОВКА ГРУПП АНАЛОГОВ: доставка → цена
        uasort($analogGroups, function ($a, $b) {
            // 1. В наличии выше заказных
            $ai = !empty($a['has_instock']) ? 1 : 0;
            $bi = !empty($b['has_instock']) ? 1 : 0;
            if ($ai !== $bi) return $bi <=> $ai;

            // 2. Доставка: быстрее → выше
            $ad = $a['min_delivery']['days'] ?? 999;
            $bd = $b['min_delivery']['days'] ?? 999;
            if ($ad !== $bd) return $ad <=> $bd;

            // 3. Цена: дешевле → выше (0 = заказной → в конец)
            $ap = ($a['min_price'] ?? 0) > 0 ? $a['min_price'] : PHP_FLOAT_MAX;
            $bp = ($b['min_price'] ?? 0) > 0 ? $b['min_price'] : PHP_FLOAT_MAX;
            return $ap <=> $bp;
        });

        if (count($analogGroups) > $this->analogDisplayCap) {
            $analogGroups = array_slice($analogGroups, 0, $this->analogDisplayCap, true);
        }

        foreach ($exactGroups as &$g) $this->sortWh($g['warehouses'], $sortExact); unset($g);
        foreach ($analogGroups as &$g) $this->sortWh($g['warehouses'], $sortAnalog); unset($g);

        $allBrands = [];
        foreach (array_merge($exactGroups, $analogGroups) as $g) $allBrands[$g['brand']] = true;
        ksort($allBrands);

        return [
            'exactGroups' => $exactGroups, 'analogGroups' => $analogGroups, 'allBrands' => $allBrands,
            'totalGroups' => count($exactGroups) + count($analogGroups),
            'totalWarehouses' => array_sum(array_map(fn($g) => count($g['warehouses']), array_merge($exactGroups, $analogGroups))),
        ];
    }

    private function roundRobin(array $bySup, int $mps, int $mt): array {
        $srt = fn($a,$b) => (($a['is_sched']??false)&&!($b['is_sched']??false))?1:(!($a['is_sched']??false)&&($b['is_sched']??false)?-1:($a['price']<=>$b['price']?:($a['delivery']['days']??999)<=>($b['delivery']['days']??999)));
        $q = []; foreach ($bySup as $s => $l) { usort($l, $srt); $l = array_slice($l, 0, $mps); if ($l) $q[$s] = $l; }
        $m = []; while (count($m) < $mt && $q) { foreach (array_keys($q) as $s) { if (count($m) >= $mt) break; if ($q[$s]) { $m[] = array_shift($q[$s]); if (!$q[$s]) unset($q[$s]); } } }
        return $m;
    }

    private function recalc(array &$g): void {
        $g['min_price'] = PHP_FLOAT_MAX; $g['max_price'] = 0; $g['total_qty'] = 0; $g['in_stock_qty'] = 0;
        $g['has_instock'] = false; $g['min_delivery'] = ['days' => PHP_INT_MAX, 'is_approx' => false];
        foreach ($g['warehouses'] as $w) {
            if ($w['price'] > 0 && $w['price'] < $g['min_price']) $g['min_price'] = $w['price'];
            if ($w['price'] > $g['max_price']) $g['max_price'] = $w['price'];
            $g['total_qty'] += (int)$w['qty'];
            if (empty($w['is_sched'])) { $g['in_stock_qty'] += (int)$w['qty']; $g['has_instock'] = true; }
            if (($w['delivery']['days'] ?? PHP_INT_MAX) < ($g['min_delivery']['days'] ?? PHP_INT_MAX)) $g['min_delivery'] = $w['delivery'];
        }
        if ($g['min_price'] === PHP_FLOAT_MAX) $g['min_price'] = 0;
    }

    private function sortWh(array &$w, string $m): void {
        match($m) {
            'delivery_asc' => usort($w, fn($a,$b) => ($a['delivery']['days']??999) <=> ($b['delivery']['days']??999) ?: $a['price'] <=> $b['price']),
            'price_asc'    => usort($w, fn($a,$b) => ($a['is_sched']&&!$b['is_sched'])?1:(!$a['is_sched']&&$b['is_sched']?-1:$a['price']<=>$b['price'])),
            'price_desc'   => usort($w, fn($a,$b) => ($a['is_sched']&&!$b['is_sched'])?1:(!$a['is_sched']&&$b['is_sched']?-1:$b['price']<=>$a['price'])),
            default        => usort($w, fn($a,$b) => ($a['is_sched']&&!$b['is_sched'])?1:(!$a['is_sched']&&$b['is_sched']?-1:(($a['delivery']['days']??999)<=>($b['delivery']['days']??999)?:$a['price']<=>$b['price']))),
        };
    }
}
