<?php
/**
 * Polling: проверка готовности Phase 2.
 * GET ?hash=xxx
 * Возвращает: {ready: true, html: "..."} или {ready: false}
 */
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$hash = trim($_GET['hash'] ?? '');
if (empty($hash)) { echo json_encode(['ready'=>false,'error'=>'no hash']); exit; }

$p2File = '/var/www/u3564357/data/www/liderws.ru/upload/cache/search/p2/' . $hash . '.json';
if (!file_exists($p2File)) { echo json_encode(['ready'=>false]); exit; }

$data = json_decode(file_get_contents($p2File), true);
if (empty($data['done'])) { echo json_encode(['ready'=>false]); exit; }

// Phase 2 готов — читаем P1 из кэша, мёржим с P2, рендерим HTML
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/BrandNormalizer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/SearchResultItem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/Stage2/OfferAggregator.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/Stage2/ResultBuilder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/SearchCacheManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/Search/InstantSearcher.php';

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;

try {
    $cacheKey = $data['cacheKey'] ?? '';
    $normTargetBrand = $data['normTargetBrand'] ?? '';
    $normTargetArt = $data['normTargetArt'] ?? '';
    $displayBrand = $data['brand'] ?? '';
    $displayArticle = $data['article'] ?? '';
    $cachedBrandMap = $data['cachedBrandMap'] ?? [];

    // Читаем P1 из файлового кэша
    $cache = new SearchCacheManager('/search/ajax_analog', 300);
    $cached = $cache->get($cacheKey);
    if (!is_array($cached) || empty($cached['fast_html'])) {
        echo json_encode(['ready'=>true, 'html'=>'', 'note'=>'cache expired']);
        exit;
    }

    // Восстанавливаем P1 результаты из InstantSearcher
    $instantSearcher = new \Lider\Search\InstantSearcher();
    $p1Results = $instantSearcher->search($normTargetArt, $normTargetBrand);

    // Добавляем P2 результаты
    $allResults = $p1Results;
    if (!empty($data['p2_results'])) {
        foreach ($data['p2_results'] as $r) {
            $item = new \Lider\Search\SearchResultItem();
            foreach ($r as $k => $v) { $item->$k = $v; }
            $allResults[] = $item;
        }
    }

    // Агрегация + рендер
    $aggregator = new OfferAggregator(200, 1000);
    $groupedItems = $aggregator->aggregate($allResults);
    $builder = new ResultBuilder(800, 200, 1000);
    $result = $builder->build(
        $groupedItems, $data['exactKey'], $normTargetBrand, $normTargetArt,
        $displayBrand, $displayArticle, $cachedBrandMap,
        [], 'default', 'default'
    );

    $analogGroups = $result['analogGroups'];

    // Рендерим HTML (упрощённо — только группы аналогов)
    ob_start();
    $ri = 0;
    foreach ($analogGroups as $group):
        $ri++;
        $inStock = $group['has_instock'];
        $rc = $inStock ? 'sl-row--instock' : 'sl-row--order';
        $pl = $group['min_price'] == $group['max_price']
            ? number_format($group['min_price'], 2, ',', ' ')
            : 'от ' . number_format($group['min_price'], 2, ',', ' ');
        $dq = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
        $ql = $dq > 4 ? 'Достаточно' : $dq . ' шт.';
        $dl = $group['min_delivery']['days'] == 0 ? 'Сегодня' : ($group['min_delivery']['days'] . ' дн.');
    ?>
<div class="supplier-list__group">
<div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)" data-group="lazy-<?=$ri?>">
<div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div>
<div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div>
<div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div>
<div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div>
<div class="sl-cell sl-cell--stock"><?=$inStock?'<span class="sl-badge sl-badge--green">'.$ql.'</span>':'<span class="sl-badge sl-badge--yellow">'.$ql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$dl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div>
<div class="sl-cell sl-cell--order"></div>
</div>
<div class="sl-warehouses" id="wh-group-lazy-<?=$ri?>" style="display:none;">
<?php foreach ($group['warehouses'] as $wh):
    $priceBase = round((float)($wh['price_base'] ?? $wh['price']), 2);
    $priceDisplay = function_exists('getDisplayPrice') ? getDisplayPrice($priceBase) : $priceBase;
    $stockDisplay = $wh['stock'] ?? '—';
    $retIcon = ($wh['returnable'] ?? true) ? '↻' : '✕';
    $wq = (int)$wh['qty'];
    $wql = $wq > 4 ? 'Достаточно' : $wq . ' шт.';
    $wdl = ($wh['delivery']['days'] ?? 0) == 0 ? 'Сегодня' : ($wh['delivery']['days'] . ' дн.');
    $sourceTag = $wh['supplier'] ?? $wh['source'] ?? '';
    $priceShow = $priceDisplay;
    $priceHtml = number_format($priceShow, 2, ',', ' ') . ' ₽';
?>
<div class="sl-warehouse-row <?=($wh['is_sched']??false)?'sl-wh--order':'sl-wh--instock'?>">
<div class="sl-cell sl-cell--expand"><?=$retIcon?></div>
<div class="sl-cell sl-cell--brand"><span class="source-tag"><?=htmlspecialchars($sourceTag)?></span></div>
<div class="sl-cell sl-cell--desc">📍 <?=htmlspecialchars($stockDisplay)?></div>
<div class="sl-cell sl-cell--stock"><?=$wql?></div>
<div class="sl-cell sl-cell--delivery"><?=$wdl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$priceHtml?></strong></div>
<div class="sl-cell sl-cell--order"><button class="btn btn--order-supplier btn--order-supplier-sm" onclick="event.stopPropagation();orderFromSupplier(this,'<?=htmlspecialchars($group['article'])?>','<?=htmlspecialchars($group['brand'])?>')">🛒</button></div>
</div>
<?php endforeach; ?>
</div></div>
<?php endforeach;
    $html = ob_get_clean();

    echo json_encode([
        'ready' => true,
        'html' => $html,
        'totalGroups' => count($analogGroups),
        'totalWarehouses' => $result['totalWarehouses'],
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['ready'=>false, 'error'=>$e->getMessage()]);
}