<?php
/**
 * ГИБРИДНЫЙ stage2_search: МГНОВЕННАЯ отдача + фоновая верификация.
 * 
 * 1. Мгновенно (< 0.1 сек): поиск по b_supplier_stock
 * 2. Параллельно: FullSearchLauncher с сохранением в кэш
 * 3. Фронтенд дозагружает свежие данные через AJAX
 */
@ini_set('memory_limit', '512M');

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;
use Lider\Search\InstantSearcher;

$searchNumberRaw = trim((string)($selectedNumber ?: $q));
$normTargetBrand = BrandNormalizer::normalize($selectedBrand);
$canonBrand      = BrandNormalizer::displayBrand($selectedBrand);

$exactGroups = []; $analogGroups = []; $allBrands = [];
$totalGroups = 0; $totalWarehouses = 0; $searchNumber = $searchNumberRaw;
$analogToken = '';
$verifyTaskHash = ''; // Для фронтенда

if ($searchNumberRaw === '' || $normTargetBrand === '') return;

$isMgr = function_exists('isManager') ? (isManager() ? '1' : '0') : '0';

// Получаем brandMap (как раньше)
$bmCache = new SearchCacheManager('/search/supplier', 900);
$bmKey = 'brandmap_' . md5(mb_strtolower($q));
$cachedBrandMap = $bmCache->get($bmKey);
if (!is_array($cachedBrandMap) || empty($cachedBrandMap)) {
    $raw = []; $breqs = []; $bsups = [];
    foreach (getSupplierFactory()->allAvailable() as $s) {
        $r = $s->buildBrandsRequest($q);
        if ($r) { $breqs[$s->getCode()] = $r; $bsups[$s->getCode()] = $s; }
    }
    $e = new \Lider\Search\Common\MultiCurlExecutor();
    foreach ($e->executeAll($breqs, 6.0) as $code => $resp) {
        if (empty($resp['body'])) continue;
        try { foreach ($bsups[$code]->parseBrandsResponse($resp['body'], $q) as $br) { $br['source'] = $code; $raw[] = $br; } } catch (\Throwable $e) {}
    }
    $cachedBrandMap = [];
    foreach ($raw as $br) {
        $b = trim((string)($br['brand'] ?? '')); $a = trim((string)($br['article_nr'] ?? $br['article'] ?? ''));
        if ($b === '' || $a === '') continue;
        $k = BrandNormalizer::groupKey($b, $a);
        if (!isset($cachedBrandMap[$k])) $cachedBrandMap[$k] = ['brands'=>[], 'articles'=>[], 'article_nr'=>$a, 'description'=>(string)($br['description']??''), 'sources'=>[]];
        $src = $br['source']; $cachedBrandMap[$k]['brands'][$src] = $b; $cachedBrandMap[$k]['articles'][$src] = $a;
        if (!in_array($src, $cachedBrandMap[$k]['sources'], true)) $cachedBrandMap[$k]['sources'][] = $src;
        $cachedBrandMap[$k]['article_nr'] = BrandNormalizer::pickDisplayArticle($cachedBrandMap[$k]['articles'], $cachedBrandMap[$k]['article_nr']);
        $desc = (string)($br['description'] ?? ''); if (mb_strlen($desc) > mb_strlen($cachedBrandMap[$k]['description'])) $cachedBrandMap[$k]['description'] = $desc;
    }
    $bmCache->set($bmKey, $cachedBrandMap, 900);
}

$normQArt = BrandNormalizer::normalizeArticle($searchNumberRaw);
$targetKey = (is_string($brandKey ?? null) && $brandKey !== '') ? $brandKey : BrandNormalizer::groupKey($selectedBrand, $searchNumberRaw);
$targetEntry = $cachedBrandMap[$targetKey] ?? null;
if ($targetEntry === null) foreach ($cachedBrandMap as $k => $info) { [$kb, $ka] = array_pad(explode('|', $k, 2), 2, ''); if ($kb === $normTargetBrand && $ka === $normQArt) { $targetKey = $k; $targetEntry = $info; break; } }

$displayArticle = $searchNumberRaw; $displayBrand = $canonBrand;
if ($targetEntry) {
    $arts = $targetEntry['articles'] ?? [];
    $displayArticle = BrandNormalizer::pickDisplayArticle($arts, $targetEntry['article_nr'] ?? $searchNumberRaw);
    $displayBrand = $canonBrand ?: BrandNormalizer::displayBrand((string)reset($targetEntry['brands']));
}

$normTargetArt = BrandNormalizer::normalizeArticle($displayArticle);
$normTargetBrand = BrandNormalizer::normalize($displayBrand);
$exactKey = $normTargetBrand . '|' . $normTargetArt;
$analogToken = md5($q . '|' . $displayBrand . '|' . $displayArticle . '|analog_v2');

// ==================== ГИБРИДНЫЙ ПОИСК ====================
$useHybrid = true; // Флаг: включить гибридный режим

if ($useHybrid) {
    // === ШАГ 1: МГНОВЕННЫЙ поиск по кэшу ===
    $instantStart = microtime(true);
    $cache = new InstantSearcher();
    file_put_contents(__DIR__ . '/../upload/logs/debug_cache.log', date('H:i:s') . " search(article='$normTargetArt', brand='$normTargetBrand')\n", FILE_APPEND);
$cachedItems = $cache->search($normTargetArt, $normTargetBrand);
file_put_contents(__DIR__ . '/../upload/logs/debug_cache.log', date('H:i:s') . " found=" . count($cachedItems) . "\n", FILE_APPEND);
    $instantMs = round((microtime(true) - $instantStart) * 1000, 1);
    
    if (!empty($cachedItems)) {
        $aggregator = new OfferAggregator();
        $builder = new ResultBuilder();
        $cachedGroups = $aggregator->aggregate($cachedItems);
        $instantResult = $builder->build(
            $cachedGroups, $exactKey, $normTargetBrand, $normTargetArt,
            $displayBrand, $displayArticle, $cachedBrandMap,
            [], 'default', 'default'
        );
        
        $exactGroups = $instantResult['exactGroups'] ?? [];
        $analogGroups = $instantResult['analogGroups'] ?? [];
        $allBrands = $instantResult['allBrands'] ?? [];
        $totalGroups = $instantResult['totalGroups'] ?? 0;
        $totalWarehouses = $instantResult['totalWarehouses'] ?? 0;
        $searchNumber = $displayArticle;
        
        // Генерируем task_hash для последующей верификации
        $verifyTaskHash = md5($normTargetArt . '|' . $normTargetBrand . '|' . microtime(true));
        
        // Сохраняем задачу в БД
        $db = new \mysqli('localhost', 'u3564357_liderws', "S)'uAp]3.\$@wWd-", 'u3564357_liderws_db');
        $db->query("INSERT INTO b_search_verify_tasks (task_hash, article, brand, status) 
                     VALUES ('{$verifyTaskHash}', '{$db->real_escape_string($displayArticle)}', '{$db->real_escape_string($displayBrand)}', 'pending')");
        $db->close();
        
        // Лог
        @file_put_contents(
            __DIR__ . '/../upload/logs/hybrid_' . date('Y-m-d') . '.log',
            '[' . date('H:i:s') . '] INSTANT article=' . $normTargetArt . ' brand=' . $normTargetBrand 
            . ' items=' . count($cachedItems) . ' ms=' . $instantMs 
            . ' task=' . $verifyTaskHash . "\n",
            FILE_APPEND
        );
        
        // Показываем предупреждение что данные из кэша
        echo '<div class="instant-notice" id="instant-notice" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin:12px 0;font-size:14px;display:flex;align-items:center;gap:10px;">';
        echo '<span style="font-size:20px;">⚡</span>';
        echo '<span>Показаны результаты из кэша (' . count($cachedItems) . ' складов, ' . number_format($instantMs, 1, ',', ' ') . ' мс). ';
        echo '<span id="verify-status" style="color:#0066ff;">Обновляем актуальные цены...</span></span>';
        echo '</div>';
        
        // JS для фоновой верификации
        echo '<script>
        (function() {
            var taskHash = "' . $verifyTaskHash . '";
            var statusEl = document.getElementById("verify-status");
            var noticeEl = document.getElementById("instant-notice");
            var checked = 0;
            var maxChecks = 30; // макс 15 секунд
            
            function pollVerify() {
                checked++;
                fetch("/local/php_interface/ajax/verify_poll.php?task_hash=" + taskHash)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === "done" || data.status === "failed") {
                            if (data.status === "done") {
                                statusEl.innerHTML = "✅ Обновляем страницу...";
                                setTimeout(function(){ window.location.reload(); }, 500);
                                noticeEl.style.background = "#f0fdf4";
                                noticeEl.style.border = "1px solid #bbf7d0";
                            } else {
                                statusEl.innerHTML = "⚠️ Не удалось обновить";
                                noticeEl.style.background = "#fff7ed";
                                noticeEl.style.border = "1px solid #fed7aa";
                            }
                        } else if (checked >= maxChecks) {
                            statusEl.innerHTML = "⏱️ Обновление затянулось, данные из кэша";
                            noticeEl.style.background = "#fff7ed";
                            noticeEl.style.border = "1px solid #fed7aa";
                        } else {
                            setTimeout(pollVerify, 500);
                        }
                    })
                    .catch(() => {
                        if (checked < maxChecks) setTimeout(pollVerify, 500);
                        else statusEl.innerHTML = "⏱️ Обновление затянулось, данные из кэша";
                    });
            }
            
            // Запускаем верификацию на сервере
            fetch("/local/php_interface/ajax/verify_start.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "task_hash=" + taskHash 
                    + "&article=" + encodeURIComponent("' . addslashes($displayArticle) . '")
                    + "&brand=" + encodeURIComponent("' . addslashes($displayBrand) . '")
                    + "&brandMap=" + encodeURIComponent(\'' . addslashes(json_encode($cachedBrandMap, JSON_UNESCAPED_UNICODE)) . '\')
                    + "&exactKey=" + encodeURIComponent("' . addslashes($exactKey) . '")
                    + "&targetEntry=" + encodeURIComponent(\'' . addslashes(json_encode($targetEntry, JSON_UNESCAPED_UNICODE)) . '\')
            }).then(function() {
                setTimeout(pollVerify, 300);
            }).catch(function() {
                statusEl.innerHTML = "⚠️ Ошибка запуска обновления";
            });
        })();
        </script>';
        
        // НЕ возвращаемся — продолжаем и показываем кэш
        // но НЕ запускаем FullSearchLauncher ниже
        $skipLive = true;
    } else {
        $skipLive = false;
    }
}

// === ШАГ 2: LIVE-поиск (если кэш пустой) ===
if (!$skipLive) {
    $launcher   = new FullSearchLauncher(getSupplierFactory());
    $allResults = $launcher->launch($displayBrand, $displayArticle, $cachedBrandMap, $exactKey, $targetEntry, 10.0);
    
    // Сохраняем результаты в кэш (если есть что сохранять)
    if (!empty($allResults)) {
        try {
            $cache = new InstantSearcher();
            $saved = $cache->saveResults($allResults);
        } catch (\Throwable $ex) {
            // Тихо пропускаем ошибки кэша
        }
    }
    
    $aggregator  = new OfferAggregator();
    $offerGroups = $aggregator->aggregate($allResults);
    $builder     = new ResultBuilder();
    $result      = $builder->build(
        $offerGroups, $exactKey, $normTargetBrand, $normTargetArt,
        $displayBrand, $displayArticle, $cachedBrandMap,
        [
            'price_min' => (int)($filterPriceMin ?? 0),
            'price_max' => (int)($filterPriceMax ?? 0),
            'brand' => (string)($filterBrand ?? ''),
        ],
        (string)$sortExact, (string)$sortAnalog
    );
    
    $exactGroups = $result['exactGroups'] ?? [];
    $analogGroups = $result['analogGroups'] ?? [];
    $allBrands = $result['allBrands'] ?? [];
    $totalGroups = $result['totalGroups'] ?? 0;
    $totalWarehouses = $result['totalWarehouses'] ?? 0;
    $searchNumber = $displayArticle;
}
