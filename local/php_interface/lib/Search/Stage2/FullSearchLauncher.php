<?php
namespace Lider\Search\Stage2;

use Lider\Search\Common\MultiCurlExecutor;
use Lider\Supplier\SupplierFactory;
use Lider\Search\SearchResultItem;
use Lider\Search\BrandNormalizer;

class FullSearchLauncher
{
    private SupplierFactory $factory;

    public function __construct(SupplierFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Полный поиск: Phase1 (exact) + Phase2 (UMAPI-кроссы).
     *
     * @param array $umapiAnalogs Кроссы из UMAPI (или [] если UMAPI недоступен)
     */
    public function launch(
        string $brand,
        string $article,
        array $umapiAnalogs = [],
        float $deadline = 15.0
    ): array {
        $results = $this->launchPhase1($brand, $article, $deadline);

        if (!empty($umapiAnalogs)) {
            $p2Results = $this->executePhase2($umapiAnalogs, $deadline);
            $results = array_merge($results, $p2Results);
        }

        $this->log("TOTAL: " . count($results));
        usort($results, function ($a, $b) {
            if (!$a->isSched && $b->isSched) return -1;
            if ($a->isSched && !$b->isSched) return 1;
            return $a->price <=> $b->price;
        });
        return $results;
    }

    /**
     * Фаза 1: поиск искомого артикула у всех поставщиков (exact + nobrand).
     * Без cross-запросов и без построения analogMap — кроссы теперь из UMAPI.
     */
    public function launchPhase1(
        string $brand,
        string $article,
        float $deadline = 15.0
    ): array {
        $suppliers = $this->factory->allAvailable();
        if (empty($suppliers)) return [];

        $results = [];
        $seen    = [];
        $p1r     = [];
        $p1m     = [];

        // exact: с брендом
        foreach ($suppliers as $sup) {
            $req = $sup->buildSearchRequest($brand, $article, false);
            if (!$req) continue;
            $code = $sup->getCode();
            $k = $code . ':exact';
            $req['_key'] = $k;
            $req['_timeout'] = $sup->getSearchTimeout();
            $req['_priority'] = 0;
            $p1r[] = $req;
            $p1m[$k] = ['sup' => $sup, 'brand' => $brand, 'article' => $article];
        }

        // nobrand: без бренда (ловим все варианты)
        foreach ($suppliers as $sup) {
            $req = $sup->buildSearchRequest('', $article, false);
            if (!$req) continue;
            $code = $sup->getCode();
            $k = $code . ':nobrand';
            $req['_key'] = $k;
            $req['_timeout'] = $sup->getSearchTimeout();
            $req['_priority'] = 1;
            $p1r[] = $req;
            $p1m[$k] = ['sup' => $sup, 'brand' => '', 'article' => $article, 'noBrand' => true];
        }

        $executor = new MultiCurlExecutor();
        $responses = $executor->executeAll($p1r, $deadline * 0.5);

        foreach ($responses as $key => $resp) {
            if (empty($resp['body'])) continue;
            $meta = $p1m[$key] ?? null;
            if (!$meta) continue;
            $sup  = $meta['sup'];
            $src  = $sup->getCode();

            try {
                $items = $sup->parseSearchResponse($resp['body'], $meta['brand'], $meta['article']);
                foreach ($items as $item) {
                    if (!($item instanceof SearchResultItem)) continue;
                    if ($item->price <= 0 && $item->quantity <= 0) continue;

                    $dk = $src . '|' . ($item->stockId ?: '') . '|' . $item->price
                        . '|' . ($item->warehouse ?? '') . '|' . $item->brand . '|' . $item->article;

                    if (isset($seen[$dk])) continue;
                    $seen[$dk] = true;
                    $results[] = $item;
                }
            } catch (\Throwable $e) {
                // игнорируем ошибки парсинга отдельного поставщика
            }
        }

        $this->log("P1: results=" . count($results));
        return $results;
    }

    /**
     * Фаза 2: поиск кросс-номеров из UMAPI у всех поставщиков.
     * Запрос без бренда — поставщик сам найдёт все варианты.
     *
     * @param array $umapiAnalogs Массив кроссов из UMAPI [['article'=>..., 'brand'=>...], ...]
     */
    public function executePhase2(array $umapiAnalogs, float $deadline = 15.0): array
    {
        if (empty($umapiAnalogs)) return [];

        $suppliers = $this->factory->allAvailable();
        $results   = [];
        $seen      = [];
        $p2r       = [];
        $p2m       = [];

        // Уникальные пары brand|article из UMAPI (дедупликация)
        $uniquePairs = [];
        foreach ($umapiAnalogs as $a) {
            $ab = trim((string)($a['brand'] ?? ''));
            $aa = trim((string)($a['article'] ?? ''));
            if ($ab === '' || $aa === '') continue;
            $k = BrandNormalizer::normalize($ab) . '|' . BrandNormalizer::normalizeArticle($aa);
            if (!isset($uniquePairs[$k])) {
                $uniquePairs[$k] = ['brand' => $ab, 'article' => $aa];
            }
        }

        // Для каждой уникальной пары → запрос без бренда ко всем поставщикам
        foreach ($uniquePairs as $pair) {
            $normBrand = BrandNormalizer::normalize($pair['brand']);
            $normArt   = BrandNormalizer::normalizeArticle($pair['article']);

            foreach ($suppliers as $sup) {
                // Запрос БЕЗ бренда — поставщик сам найдёт товар под любым брендом
                $req = $sup->buildSearchRequest('', $pair['article'], false);
                if (!$req) {
                    // fallback: с брендом
                    $req = $sup->buildSearchRequest($pair['brand'], $pair['article'], false);
                }
                if (!$req) continue;

                $code = $sup->getCode();
                $k2 = $code . ':analog:' . $normArt;
                $req['_key'] = $k2;
                $req['_timeout'] = min(6, $sup->getSearchTimeout());
                $req['_priority'] = 3;
                $p2r[] = $req;
                $p2m[$k2] = [
                    'sup'      => $sup,
                    'normArt'  => $normArt,
                    'normBrand'=> $normBrand,
                    'brand'    => $pair['brand'],
                    'article'  => $pair['article'],
                ];
            }
        }

        if (empty($p2r)) {
            $this->log("P2: no requests");
            return [];
        }

        $p2Deadline = max(10.0, $deadline * 0.6);
        $this->log("P2: " . count($p2r) . " requests for " . count($uniquePairs) . " unique analogs");

        $executor  = new MultiCurlExecutor();
        $responses = $executor->executeAll($p2r, $p2Deadline);
        $added     = 0;

        foreach ($responses as $key => $resp) {
            if (empty($resp['body'])) continue;
            $meta = $p2m[$key] ?? null;
            if (!$meta) continue;

            $sup      = $meta['sup'];
            $src      = $sup->getCode();
            $normArt  = $meta['normArt'];

            try {
                $items = $sup->parseSearchResponse($resp['body'], '', $meta['article']);
                foreach ($items as $item) {
                    if (!($item instanceof SearchResultItem)) continue;
                    if ($item->price <= 0 && $item->quantity <= 0) continue;
                    if (BrandNormalizer::normalizeArticle($item->article) !== $normArt) continue;

                    $dk = $src . '|' . ($item->stockId ?: '') . '|' . $item->price
                        . '|' . ($item->warehouse ?? '') . '|' . $item->brand . '|' . $item->article;

                    if (isset($seen[$dk])) continue;
                    $seen[$dk] = true;
                    $results[] = $item;
                    $added++;
                }
            } catch (\Throwable $e) {
                // игнорируем
            }
        }

        $this->log("P2 done: +{$added} results");
        return $results;
    }

    private function log(string $msg): void
    {
        @file_put_contents(
            '/var/www/u3564357/data/www/liderws.ru/upload/logs/fullsearch_' . date('Y-m-d') . '.log',
            '[' . date('H:i:s') . '] ' . $msg . "\n",
            FILE_APPEND
        );
    }
}