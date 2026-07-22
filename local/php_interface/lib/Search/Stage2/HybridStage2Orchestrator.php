<?php
namespace Lider\Search\Stage2;

use Lider\Search\InstantSearcher;
use Lider\Supplier\SupplierFactory;
use Lider\Search\BrandNormalizer;

/**
 * ГИБРИДНЫЙ ОРКЕСТРАТОР ЭТАПА 2.
 * 
 * Логика:
 * 1. МГНОВЕННО (< 0.1 сек): поиск в b_supplier_stock → отдаём пользователю
 * 2. ПАРАЛЛЕЛЬНО: запускаем CachingFullSearchLauncher (live API)
 * 3. Результаты live-поиска сохраняются в кэш автоматически
 * 4. Фронтенд по task_hash дозапрашивает свежие результаты
 */
class HybridStage2Orchestrator
{
    private SupplierFactory $supplierFactory;
    private InstantSearcher $cache;
    private OfferAggregator $aggregator;
    private ResultBuilder $resultBuilder;
    private ?CachingFullSearchLauncher $liveLauncher = null;

    public function __construct(
        SupplierFactory $supplierFactory,
        InstantSearcher $cache,
        OfferAggregator $aggregator,
        ResultBuilder $resultBuilder
    ) {
        $this->supplierFactory = $supplierFactory;
        $this->cache = $cache;
        $this->aggregator = $aggregator;
        $this->resultBuilder = $resultBuilder;
    }

    /**
     * Основной метод. Возвращает мгновенные результаты + task_hash для дозагрузки.
     */
    public function execute(
        string $article,
        string $brand,
        array $brandMap,
        string $exactKey,
        ?array $targetEntry,
        array $filters = [],
        string $sortExact = 'default',
        string $sortAnalog = 'default'
    ): array {
        $normArticle = BrandNormalizer::normalizeArticle($article);
        $normBrand = BrandNormalizer::normalize($brand);
        $displayBrand = BrandNormalizer::displayBrand($brand);
        $displayArticle = $article;

        // === ШАГ 1: МГНОВЕННЫЙ ПОИСК ПО КЭШУ ===
        $cacheStart = microtime(true);
        $cachedItems = $this->cache->search($normArticle, $normBrand);
        $cacheTime = round((microtime(true) - $cacheStart) * 1000, 1);

        // Группируем и строим результат из кэша
        $cachedGroups = $this->aggregator->aggregate($cachedItems);
        $instantResult = $this->resultBuilder->build(
            $cachedGroups, $exactKey, $normBrand, $normArticle,
            $displayBrand, $displayArticle, $brandMap,
            $filters, $sortExact, $sortAnalog
        );

        // Сколько поставщиков покрыто в кэше?
        $cachedSuppliers = [];
        foreach ($cachedItems as $item) {
            $cachedSuppliers[$item->source] = true;
        }
        $allSuppliers = $this->supplierFactory->allAvailable();
        $totalSupplierCount = count($allSuppliers);
        $cachedSupplierCount = count($cachedSuppliers);

        // === ШАГ 2: ЗАПУСКАЕМ LIVE-ПОИСК ===
        $taskHash = md5($normArticle . '|' . $normBrand . '|' . microtime(true));
        $livePromise = null;

        // Сохраняем быстрый «слепок» для верификации
        $this->storeVerifyTask($taskHash, $article, $brand, $instantResult);

        $this->log(sprintf(
            "HYBRID article=%s brand=%s | cache=%d items (%d suppliers/%d total) in %sms | task=%s",
            $normArticle, $normBrand,
            count($cachedItems), $cachedSupplierCount, $totalSupplierCount,
            $cacheTime, $taskHash
        ));

        return [
            'instant' => [
                'exactGroups'      => $instantResult['exactGroups'],
                'analogGroups'     => $instantResult['analogGroups'],
                'allBrands'        => $instantResult['allBrands'],
                'totalGroups'      => $instantResult['totalGroups'],
                'totalWarehouses'  => $instantResult['totalWarehouses'],
            ],
            'meta' => [
                'task_hash'            => $taskHash,
                'cache_time_ms'        => $cacheTime,
                'cached_items'         => count($cachedItems),
                'cached_suppliers'     => $cachedSupplierCount,
                'total_suppliers'      => $totalSupplierCount,
                'is_full'              => ($cachedSupplierCount >= $totalSupplierCount),
                'cache_age_sec'        => $this->getMaxCacheAge($cachedItems),
            ],
        ];
    }

    /**
     * Запустить live-поиск для task_hash (вызывается асинхронно).
     */
    public function executeLive(string $taskHash, string $article, string $brand,
        array $brandMap, string $exactKey, ?array $targetEntry): array
    {
        $this->updateVerifyTaskStatus($taskHash, 'running');

        $launcher = $this->getLiveLauncher();
        $liveItems = $launcher->launch($brand, $article, $brandMap, $exactKey, $targetEntry, 15.0);

        $normBrand = BrandNormalizer::normalize($brand);
        $normArticle = BrandNormalizer::normalizeArticle($article);
        $displayBrand = BrandNormalizer::displayBrand($brand);

        // Группируем и строим
        $liveGroups = $this->aggregator->aggregate($liveItems);
        $liveResult = $this->resultBuilder->build(
            $liveGroups, $exactKey, $normBrand, $normArticle,
            $displayBrand, $article, $brandMap
        );

        // Сохраняем в task
        $this->updateVerifyTaskDone($taskHash, $liveResult);

        $this->log("LIVE task={$taskHash} done: " . count($liveItems) . " items");

        return [
            'exactGroups'     => $liveResult['exactGroups'],
            'analogGroups'    => $liveResult['analogGroups'],
            'allBrands'       => $liveResult['allBrands'],
            'totalGroups'     => $liveResult['totalGroups'],
            'totalWarehouses' => $liveResult['totalWarehouses'],
        ];
    }

    /**
     * Получить статус верификации (для polling).
     */
    public function getTaskStatus(string $taskHash): ?array
    {
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $stmt = $db->prepare("SELECT status, result_json FROM b_search_verify_tasks WHERE task_hash = ?");
        $stmt->bind_param('s', $taskHash);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        $db->close();

        if (!$row) return null;

        return [
            'status' => $row['status'],
            'result' => $row['result_json'] ? json_decode($row['result_json'], true) : null,
        ];
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ====================

    private function getLiveLauncher(): CachingFullSearchLauncher
    {
        if ($this->liveLauncher === null) {
            $this->liveLauncher = new CachingFullSearchLauncher($this->supplierFactory, $this->cache);
        }
        return $this->liveLauncher;
    }

    private function storeVerifyTask(string $taskHash, string $article, string $brand, array $instantResult): void
    {
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $stmt = $db->prepare(
            "INSERT INTO b_search_verify_tasks (task_hash, article, brand, status) VALUES (?, ?, ?, 'pending')"
        );
        $stmt->bind_param('sss', $taskHash, $article, $brand);
        $stmt->execute();
        $stmt->close();
        $db->close();
    }

    private function updateVerifyTaskStatus(string $taskHash, string $status): void
    {
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $stmt = $db->prepare("UPDATE b_search_verify_tasks SET status = ?, updated_at = NOW() WHERE task_hash = ?");
        $stmt->bind_param('ss', $status, $taskHash);
        $stmt->execute();
        $stmt->close();
        $db->close();
    }

    private function updateVerifyTaskDone(string $taskHash, array $result): void
    {
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        $status = 'done';
        $stmt = $db->prepare(
            "UPDATE b_search_verify_tasks SET status = ?, result_json = ?, updated_at = NOW() WHERE task_hash = ?"
        );
        $stmt->bind_param('sss', $status, $json, $taskHash);
        $stmt->execute();
        $stmt->close();
        $db->close();
    }

    private function getMaxCacheAge(array $items): int
    {
        if (empty($items)) return 0;
        $max = 0;
        foreach ($items as $item) {
            $age = $item->raw['cache_age'] ?? 0;
            if ($age > $max) $max = $age;
        }
        return (int)$max;
    }

    private function log(string $msg): void
    {
        @file_put_contents(
            '/var/www/u3564357/data/www/liderws.ru/upload/logs/hybrid_stage2_' . date('Y-m-d') . '.log',
            '[' . date('H:i:s') . '] ' . $msg . "\n",
            FILE_APPEND
        );
    }
}
