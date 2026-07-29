<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/php_errors.log');
error_log("=== analog_search.php START ===");
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '90');
@ini_set('display_errors', 0);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init_pricing.php';

$base = '/var/www/u3564357/data/www/liderws.ru/local/php_interface/lib';
require_once $base . '/Search/BrandNormalizer.php';
require_once $base . '/Search/SearchResultItem.php';
require_once $base . '/Search/SearchCacheManager.php';
require_once $base . '/Search/Common/MultiCurlExecutor.php';
require_once $base . '/Search/Stage2/FullSearchLauncher.php';
require_once $base . '/Search/Stage2/OfferAggregator.php';
require_once $base . '/Search/Stage2/ResultBuilder.php';
require_once $base . '/Search/UmapiClient.php';
require_once $base . '/Supplier/SupplierInterface.php';
require_once $base . '/Supplier/SupplierFactory.php';
require_once $base . '/Supplier/MoskvorechieConnector.php';
require_once $base . '/Supplier/RosskoConnector.php';
require_once $base . '/Supplier/PartKomConnector.php';
require_once $base . '/Supplier/AutoeuroConnector.php';
require_once $base . '/Supplier/BergConnector.php';
require_once $base . '/Supplier/IxoraConnector.php';
require_once $base . '/Supplier/ShateMConnector.php';
require_once $base . '/Supplier/TatpartsConnector.php';
error_log("All requires loaded, starting main logic");

use Lider\Search\BrandNormalizer;
use Lider\Search\Stage2\FullSearchLauncher;
use Lider\Search\Stage2\OfferAggregator;
use Lider\Search\Stage2\ResultBuilder;
use Lider\Search\SearchCacheManager;
use Lider\Search\UmapiClient;

header('Content-Type: application/json; charset=utf-8');

function getAjaxFactory(): \Lider\Supplier\SupplierFactory {
    $f = new \Lider\Supplier\SupplierFactory();
    $f->register(new \Lider\Supplier\MoskvorechieConnector(['API_KEY'=>'2Ek7PUswoRDK:x1W5M70Y3KF8vZ52ETr2zi53d6SUOoPf']));
    $f->register(new \Lider\Supplier\RosskoConnector(['KEY1'=>'d6907f0f857524815255b74cda86fe9b','KEY2'=>'a514b4c11299686d7cfe8fd3563d1c58','DELIVERY_ID'=>'000000002','ADDRESS_ID'=>'71520']));
    $f->register(new \Lider\Supplier\BergConnector(['API_KEY'=>'9e1cc5aea546e263e54c8ba687757a6515de9c78f52c5a9b435bd7ad8303ef36','ADDRESS_ID'=>31173]));
    $f->register(new \Lider\Supplier\AutoeuroConnector(['API_KEY'=>'wK435HUkjTAbJL4RF4F5z9NBXWYqpFhSorfpVkRLFNYI60T21ksYvVQNawkX','DELIVERY_KEY'=>'q53qrkblKN8GviqxHAUlgA0vlUZgRhN04SG01sixtCpoTjC99FJ165xxzGta89mwhLNonRBxH1vlOg8rjL2xPxAdurElATA']));
    $f->register(new \Lider\Supplier\PartKomConnector(['LOGIN'=>'lider16','PASSWORD'=>'LidGates16']));
    $f->register(new \Lider\Supplier\IxoraConnector(['AUTH_CODE'=>'460880B0988C8C204B2DD392EC81116D','TIMEOUT'=>8]));
    $f->register(new \Lider\Supplier\TatpartsConnector());
    $f->register(new \Lider\Supplier\AutorussConnector(['LOGIN' => 'Lider-16@bk.ru','PASSWORD_MD5' => '00fd3781d2cfdf0d971b57fa7397cfac']));
    $f->register(new \Lider\Supplier\AutopiterConnector(['USER_ID' => '165286','PASSWORD' => 'LidGates16']));
    return $f;
}

function fmtDeliveryHtml(array $del): string {
    $days = $del['days'] ?? 0;
    $approx = $del['is_approx'] ?? false;
    if (!empty($del['date_from'])) {
        $from = $del['date_from']; $to = $del['date_to'] ?? null; $deadline = $del['deadline'] ?? null;
        $today = date('Y-m-d'); $fromDate = date('Y-m-d', $from);
        $dayLabel = $fromDate === $today ? '<span class="sl-text-green">Сегодня</span>' : ($fromDate === date('Y-m-d', strtotime('+1 day')) ? '<span class="sl-text-amber">Завтра</span>' : date('d.m', $from));
        $timeStr = date('H:i', $from); if ($to) $timeStr .= '–' . date('H:i', $to);
        $html = $dayLabel . ' <span class="sl-delivery-time">' . $timeStr . '</span>';
        if ($deadline && $deadline > time()) $html .= ' <span class="sl-deadline">заказ до ' . date('H:i', $deadline) . '</span>';
        return $html;
    }
    if ($days === 0) return '<span class="sl-text-green">Сегодня</span>';
    if ($days === 1 && !$approx) return '<span class="sl-text-amber">Завтра</span>';
    $date = date('d.m', strtotime("+{$days} days"));
    $label = ($approx ? '≈ ' : '') . $date;
    return $approx ? '<span class="sl-text-muted">' . $label . '</span>' : $label;
}

function fmtQty(int $qty): string {
    if (isManager()) return $qty . ' шт.';
    if ($qty > 4) return 'Достаточно';
    return $qty . ' шт.';
}

function calcDelivery(\Lider\Search\SearchResultItem $item): array {
    $isSched = $item->isSched;
    $hours   = $item->deliveryPeriod ?? 0;
    $days    = $item->deliveryDays ?? 0;
    if ($isSched) {
        $d = $days > 0 ? $days : max(1, (int)ceil($hours / 24));
        return ['days' => $d, 'is_approx' => true];
    }
    if ($item->source === 'moskvorechie') {
        $flags   = (array)($item->raw['flags'] ?? []);
        $stockId = (string)($item->raw['stock_id'] ?? '');
        $now = time();
        if ($hours === 0 && in_array('pickup', $flags)) {
            $hms = (int)date('Hi');
            if ($hms < 1102) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 12:00'),'date_to'=>strtotime('today 14:00'),'deadline'=>strtotime('today 11:02')];
            elseif ($hms < 1402) return ['days'=>0,'is_approx'=>false,'date_from'=>strtotime('today 15:00'),'date_to'=>strtotime('today 17:00'),'deadline'=>strtotime('today 14:02')];
            else return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 14:02')];
        }
        if ($stockId === '10') return ['days'=>1,'is_approx'=>false,'date_from'=>strtotime('tomorrow 09:00'),'date_to'=>strtotime('tomorrow 11:00'),'deadline'=>strtotime('tomorrow 07:02')];
        if ($hours > 0) {
            $ts = $now + $hours * 3600;
            $day = strtotime(date('Y-m-d', $ts));
            $h = (int)date('H', $ts);
            $wh = $h < 9 ? 7 : ($h < 12 ? 11 : 14);
            $wave = $day + $wh * 3600;
            return ['days'=>max(0,(int)ceil(($wave-strtotime('today',$now))/86400)),'is_approx'=>false,'date_from'=>$wave,'date_to'=>$wave+10800,'deadline'=>$wave+120];
        }
    }
    if ($item->source === 'berg') {
        if (!empty($item->raw['deliveryDateTo']) && !empty($item->raw['deliveryCheckout'])) {
            return ['days'=>$item->deliveryDays??0,'is_approx'=>false,'date_from'=>strtotime($item->raw['deliveryDateTo']),'date_to'=>strtotime($item->raw['deliveryDateTo'])+7200,'deadline'=>strtotime($item->raw['deliveryCheckout'])];
        }
    }
    if ($item->source === 'rossko') {
        $cutoff = $item->raw['deliveryCheckout'] ?? null;
        $from = $item->raw['deliveryDateFrom'] ?? null;
        $to = $item->raw['deliveryDateTo'] ?? null;
        if ($from && $to) {
            $r = ['days'=>$item->deliveryDays??0,'is_approx'=>false,'date_from'=>strtotime($from),'date_to'=>strtotime($to)];
            if ($cutoff) $r['deadline'] = strtotime($cutoff);
            return $r;
        }
    }
    if ($item->deliveryDays !== null) {
        $r = ['days' => $item->deliveryDays, 'is_approx' => false];
        if (!empty($item->raw['deliveryDateFrom'])) $r['date_from'] = strtotime($item->raw['deliveryDateFrom']);
        if (!empty($item->raw['deliveryDateTo']))   $r['date_to']   = strtotime($item->raw['deliveryDateTo']);
        if (!empty($item->raw['deliveryCheckout'])) $r['deadline']   = strtotime($item->raw['deliveryCheckout']);
        return $r;
    }
    if ($hours > 0) return ['days' => (int)ceil($hours / 24), 'is_approx' => false];
    return ['days' => 0, 'is_approx' => false];
}

function maskWarehouse(\Lider\Search\SearchResultItem $item): string {
    $realName = $item->warehouse ?: '—';
    if ($realName === '' || $realName === '—') return '—';
    if (isManager()) return $item->supplierName . ': ' . $realName;
    $factory = getAjaxFactory();
    $connector = $factory->get($item->source);
    if ($connector && method_exists($connector, 'maskWarehouseName')) {
        return $connector->maskWarehouseName($realName);
    }
    return $realName;
}

try {
    $q = trim((string)($_REQUEST['q'] ?? ''));
    $brand = trim((string)($_REQUEST['brand'] ?? ''));
    $number = trim((string)($_REQUEST['number'] ?? ''));
    if ($number === '' && $q !== '') {
        $number = $q;
    }
    $normQ = \Lider\Search\BrandNormalizer::normalizeArticle($q);
    $normBrand = \Lider\Search\BrandNormalizer::normalize($brand);
    $token = trim((string)($_REQUEST['token'] ?? ''));
    $filterBrand = trim((string)($_REQUEST['filter_brand'] ?? ''));
    $filterPriceMin = (int)($_REQUEST['price_min'] ?? 0);
    $filterPriceMax = (int)($_REQUEST['price_max'] ?? 0);

    $expectedToken = md5($q . '|' . $brand . '|' . $number . '|analog_v2');
    if (($token !== '' && $token !== $expectedToken) || $q === '' || $brand === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid params']);
        exit;
    }

    $cache = new SearchCacheManager('/search/ajax_analog', 300);
    $cacheKey = md5(implode('|', [$q, $brand, $number, $filterPriceMin, $filterPriceMax, $filterBrand, isManager() ? 'mgr' : 'usr']));
    $cached = $cache->get($cacheKey);
    if (is_array($cached) && !empty($cached['ok'])) {
        echo json_encode($cached);
        exit;
    }

    $normTargetBrand = BrandNormalizer::normalize($brand);
    $canonBrand = BrandNormalizer::displayBrand($brand);
    $normQArt = BrandNormalizer::normalizeArticle($number);
    $displayArticle = $number;
    $displayBrand = $canonBrand;
    $normTargetArt = $normQArt;
    $exactKey = $normTargetBrand . '|' . $normTargetArt;

    $factory = getAjaxFactory();
    $launcher = new FullSearchLauncher($factory);

    $umapi = new UmapiClient('52606cd0-b1fd-4a5e-a8e3-ad9fbef16435');
    $umapiAnalogs = $umapi->getAnalogs($displayArticle, $displayBrand);

    $phase = $_REQUEST['phase'] ?? 'full';
    $p2Hash = '';

    if ($phase === 'final' && !empty($_REQUEST['p2_hash'])) {
        $p2Hash = trim($_REQUEST['p2_hash']);
        $p2File = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/p2/' . $p2Hash . '.json';

        if (!file_exists($p2File)) {
            echo json_encode(['success'=>false, 'error'=>'State file not found']);
            exit;
        }

        $p2Data = json_decode(file_get_contents($p2File), true);

        $allResults = [];
        if (!empty($p2Data['p1_results'])) {
            foreach ($p2Data['p1_results'] as $r) {
                $item = new \Lider\Search\SearchResultItem();
                foreach ($r as $k => $v) { $item->$k = $v; }
                $allResults[] = $item;
            }
        }

        if (!empty($p2Data['p2_results'])) {
            $seenStockIds = [];
            foreach ($p2Data['p2_results'] as $r) {
                $key = ($r['source'] ?? '') . '|' . ($r['stockId'] ?? '') . '|' . ($r['warehouse'] ?? '');
                if (isset($seenStockIds[$key])) continue;
                $seenStockIds[$key] = true;
                $item = new \Lider\Search\SearchResultItem();
                $item->source       = $r['source'] ?? '';
                $item->article      = $r['article'] ?? '';
                $item->brand        = $r['brand'] ?? '';
                $item->name         = $r['name'] ?? '';
                $item->price        = (float)($r['price'] ?? 0);
                $item->quantity     = (int)($r['quantity'] ?? 0);
                $item->warehouse    = $r['warehouse'] ?? '';
                $item->stockId      = $r['stockId'] ?? '';
                $item->supplierName = $r['supplierName'] ?? '';
                $item->isSched      = (bool)($r['isSched'] ?? false);
                $item->deliveryDays = (int)($r['deliveryDays'] ?? 0);
                $item->deliveryPeriod = (int)($r['deliveryPeriod'] ?? 0);
                $item->multiplicity = (int)($r['multiplicity'] ?? 1);
                $item->unit         = $r['unit'] ?? 'шт.';
                $allResults[] = $item;
            }
        }

        $displayBrand   = $p2Data['brand'] ?? $displayBrand;
        $displayArticle = $p2Data['article'] ?? $displayArticle;
        $exactKey       = $p2Data['exactKey'] ?? $exactKey;
        $normTargetBrand = $p2Data['normTargetBrand'] ?? $normTargetBrand;
        $normTargetArt  = $p2Data['normTargetArt'] ?? $normTargetArt;

        goto finalRender;
    }

    if ($phase === 'p2_init') {
        $umapiAnalogs = $umapi->getAnalogs($displayArticle, $displayBrand);
        if (empty($umapiAnalogs)) {
            echo json_encode(['success'=>false, 'error'=>'no_analogs']);
            exit;
        }
        $p2Hash = md5($normQ . '|' . $normBrand . '|p2init|' . uniqid('', true));
        $p2Dir = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/p2';
        if (!is_dir($p2Dir)) mkdir($p2Dir, 0755, true);
        $p2File = $p2Dir . '/' . $p2Hash . '.json';
        file_put_contents($p2File, json_encode([
            'hash' => $p2Hash,
            'umapiAnalogs' => $umapiAnalogs,
            'brand' => $displayBrand,
            'article' => $displayArticle,
            'exactKey' => $exactKey,
            'normTargetBrand' => $normTargetBrand,
            'normTargetArt' => $normTargetArt,
            'cacheKey' => $cacheKey,
            'p1_count' => 0,
            'p1_results' => [],
            'created' => time(),
            'p2_results' => [],
            'running' => false,
            'done' => false,
            'p2_count' => 0,
        ], JSON_UNESCAPED_UNICODE));
        echo json_encode(['success'=>true, 'p2_hash'=>$p2Hash, 'p2_pending'=>true]);
        exit;
    }

    // ======================= p2_chunk (v9) =======================
    if ($phase === 'p2_chunk' && !empty($_REQUEST['p2_hash'])) {
        $p2Hash = trim($_REQUEST['p2_hash']);
        $p2File = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/p2/' . $p2Hash . '.json';

        if (!file_exists($p2File)) {
            echo json_encode(['success'=>false, 'error'=>'no_file']);
            exit;
        }

        $p2Data = json_decode(file_get_contents($p2File), true);
        if (!isset($p2Data['p2_results'])) $p2Data['p2_results'] = [];

        $allAnalogs = $p2Data['umapiAnalogs'] ?? [];
        $chunkSize = 10;
        $chunk = array_slice($allAnalogs, 0, $chunkSize);

        if (empty($chunk)) {
            $allResults = [];
            foreach ($p2Data['p2_results'] as $r) {
                $item = new \Lider\Search\SearchResultItem();
                foreach ($r as $k => $v) { $item->$k = $v; }
                $allResults[] = $item;
            }
            $p2Data['done'] = true; $p2Data['running'] = false;
            $p2Data['p2_count'] = count($p2Data['p2_results']);
            $p2Data['umapiAnalogs'] = [];
            file_put_contents($p2File, json_encode($p2Data, JSON_UNESCAPED_UNICODE));
            $displayBrand = $p2Data['brand'] ?? $displayBrand;
            $displayArticle = $p2Data['article'] ?? $displayArticle;
            $exactKey = $p2Data['exactKey'] ?? $exactKey;
            $normTargetBrand = $p2Data['normTargetBrand'] ?? $normTargetBrand;
            $normTargetArt = $p2Data['normTargetArt'] ?? $normTargetArt;
            $responseDone = true;
            goto finalRender;
        }

        $launcher = new FullSearchLauncher($factory);
        $p2Results = $launcher->executePhase2($chunk, 15.0);

        $allAnalogs = array_slice($allAnalogs, $chunkSize);
        $p2Data['umapiAnalogs'] = $allAnalogs;

        $seenKeys = [];
        foreach ($p2Data['p2_results'] as $r) {
            $seenKeys[($r['source']??'').'|'.($r['stockId']??'').'|'.($r['article']??'').'|'.($r['brand']??'')] = true;
        }
        foreach ($p2Results as $item) {
            $k = $item->source.'|'.$item->stockId.'|'.$item->article.'|'.$item->brand;
            if (isset($seenKeys[$k])) continue;
            $seenKeys[$k] = true;
            $p2Data['p2_results'][] = [
                'source' => $item->source, 'article' => $item->article, 'brand' => $item->brand,
                'name' => $item->name ?? '', 'price' => $item->price ?? 0, 'quantity' => $item->quantity ?? 0,
                'warehouse' => $item->warehouse ?? '', 'stockId' => $item->stockId ?? '',
                'supplierName' => $item->supplierName ?? '', 'isSched' => $item->isSched ?? false,
                'deliveryDays' => $item->deliveryDays ?? 0, 'deliveryPeriod' => $item->deliveryPeriod ?? 0,
                'multiplicity' => $item->multiplicity ?? 1, 'unit' => $item->unit ?? 'шт.',
            ];
        }

        $done = empty($allAnalogs);
        if ($done) { $p2Data['done'] = true; $p2Data['running'] = false; }
        $p2Data['p2_count'] = count($p2Data['p2_results']);
        file_put_contents($p2File, json_encode($p2Data, JSON_UNESCAPED_UNICODE));

        $allResults = [];
        foreach ($p2Data['p2_results'] as $r) {
            $item = new \Lider\Search\SearchResultItem();
            foreach ($r as $k => $v) { $item->$k = $v; }
            $allResults[] = $item;
        }
        $displayBrand = $p2Data['brand'] ?? $displayBrand;
        $displayArticle = $p2Data['article'] ?? $displayArticle;
        $exactKey = $p2Data['exactKey'] ?? $exactKey;
        $normTargetBrand = $p2Data['normTargetBrand'] ?? $normTargetBrand;
        $normTargetArt = $p2Data['normTargetArt'] ?? $normTargetArt;
        $responseDone = $done;
        goto finalRender;
    }

    if ($phase === 'fast') {
        $allResults = $launcher->launchPhase1($displayBrand, $displayArticle, 30.0);

        if (!empty($umapiAnalogs)) {
            $p2Hash = md5($normQ . '|' . $normBrand . '|fast');
            $p2Dir = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/search/p2';
            if (!is_dir($p2Dir)) mkdir($p2Dir, 0755, true);
            $p2File = $p2Dir . '/' . $p2Hash . '.json';

            $p1Serialized = [];
            foreach ($allResults as $item) {
                $p1Serialized[] = [
                    'source' => $item->source, 'article' => $item->article, 'brand' => $item->brand,
                    'name' => $item->name, 'price' => $item->price, 'quantity' => $item->quantity,
                    'warehouse' => $item->warehouse, 'stockId' => $item->stockId,
                    'supplierName' => $item->supplierName, 'isSched' => $item->isSched,
                    'deliveryDays' => $item->deliveryDays, 'deliveryPeriod' => $item->deliveryPeriod ?? 0,
                    'multiplicity' => $item->multiplicity ?? 1, 'unit' => $item->unit ?? 'шт.',
                    'raw' => $item->raw ?? [],
                ];
            }

            file_put_contents($p2File, json_encode([
                'hash' => $p2Hash,
                'umapiAnalogs' => $umapiAnalogs,
                'brand' => $displayBrand,
                'article' => $displayArticle,
                'exactKey' => $exactKey,
                'normTargetBrand' => $normTargetBrand,
                'normTargetArt' => $normTargetArt,
                'cacheKey' => $cacheKey,
                'p1_count' => count($allResults),
                'p1_results' => $p1Serialized,
                'created' => time()
            ], JSON_UNESCAPED_UNICODE));
        }
    } else {
        $allResults = $launcher->launch($displayBrand, $displayArticle, $umapiAnalogs, 30.0);
    }

    finalRender:

    $aggregator = new OfferAggregator(200, 1000);
    $groupedItems = $aggregator->aggregate($allResults);

    $builder = new ResultBuilder(800, 200, 1000);
    $result = $builder->build(
        $groupedItems, $exactKey, $normTargetBrand, $normTargetArt,
        $displayBrand, $displayArticle, [],
        ['price_min' => $filterPriceMin, 'price_max' => $filterPriceMax, 'brand' => $filterBrand],
        'default', 'default'
    );

    $analogGroups = $result['analogGroups'];

    unset($analogGroups[$exactKey]);
    foreach ($analogGroups as $_key => $_g) {
        if (
            \Lider\Search\BrandNormalizer::normalize($_g['brand']) === $normTargetBrand
            && \Lider\Search\BrandNormalizer::normalizeArticle($_g['article']) === $normTargetArt
        ) {
            unset($analogGroups[$_key]);
        }
    }
    $factoryForMask = getAjaxFactory();

    ob_start();
    $ri = 0;
    if (!empty($analogGroups)):
    ?>
    <div class="supplier-list__header">
    <div class="sl-cell sl-cell--expand"></div>
    <div class="sl-cell sl-cell--brand">Бренд</div>
    <div class="sl-cell sl-cell--desc">Описание</div>
    <div class="sl-cell sl-cell--article">Артикул</div>
    <div class="sl-cell sl-cell--mult">Кратность</div>
    <div class="sl-cell sl-cell--stock">Наличие</div>
    <div class="sl-cell sl-cell--delivery">Доставка</div>
    <div class="sl-cell sl-cell--price">Цена</div>
    <div class="sl-cell sl-cell--order"></div>
    </div>
    <?php
    endif;
    foreach ($analogGroups as $group):
        $ri++;
        $inStock = $group['has_instock'];
        $rc = $inStock ? 'sl-row--instock' : 'sl-row--order';
        $pl = $group['min_price'] == $group['max_price']
            ? number_format($group['min_price'], 2, ',', ' ')
            : 'от ' . number_format($group['min_price'], 2, ',', ' ');
        $dq = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
        $ql = fmtQty($dq);
        $dl = fmtDeliveryHtml($group['min_delivery']);
    ?>
<div class="supplier-list__group" data-analog-key="<?=htmlspecialchars(mb_strtolower($group['brand'].'|'.$group['article']))?>">
<div class="supplier-list__row <?=$rc?> sl-main-row" onclick="toggleWarehouses(this)">
<div class="sl-cell sl-cell--expand"><span class="sl-expand-icon">▶</span></div>
<div class="sl-cell sl-cell--brand"><strong><?=htmlspecialchars($group['brand'])?></strong></div>
<div class="sl-cell sl-cell--desc"><div class="sl-desc-text"><?=htmlspecialchars($group['description'])?></div></div>
<div class="sl-cell sl-cell--article"><code><?=htmlspecialchars($group['article'])?></code></div>
<div class="sl-cell sl-cell--stock"><?=$inStock?'<span class="sl-badge sl-badge--green">'.$ql.'</span>':'<span class="sl-badge sl-badge--yellow">'.$ql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$dl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$pl?> ₽</strong><div class="sl-warehouse-count"><?=count($group['warehouses'])?> складов</div></div>
<div class="sl-cell sl-cell--order"></div>
</div>
<div class="sl-warehouses" style="display:none;">
<?php foreach ($group['warehouses'] as $wh):
    $priceBase = round((float)($wh['price_base'] ?? $wh['price']), 2);
    $priceDisplay = getDisplayPrice($priceBase);
    $stockDisplay = $wh['stock'] ?? '—';
    if (!isManager()) {
        $connector = $factoryForMask->get($wh['source'] ?? '');
        if ($connector && $stockDisplay !== 'Под заказ' && $stockDisplay !== '—') {
            $stockDisplay = $connector->maskWarehouseName($stockDisplay);
        }
    } else {
        if ($stockDisplay !== 'Под заказ' && $stockDisplay !== '—') {
            $stockDisplay = ($wh['supplier'] ?? '') . ': ' . $stockDisplay;
        }
    }
    $retIcon = $wh['returnable']
        ? '<span class="ret-icon ret-icon--yes" title="Возвратный">↻</span>'
        : '<span class="ret-icon ret-icon--no" title="Невозвратный">✕</span>';
    $wq = (int)$wh['qty']; $wql = fmtQty($wq); $wdl = fmtDeliveryHtml($wh['delivery']);
    $sourceTag = isManager() ? '<span class="source-tag source-tag--' . htmlspecialchars($wh['source']) . '">' . htmlspecialchars($wh['supplier']) . '</span>' : '';
    $priceShow = isManager() ? $priceBase : $priceDisplay;
    $priceHtml = number_format($priceShow, 2, ',', ' ') . ' ₽';
    $priceBaseHtml = (isManager() && $priceDisplay !== $priceBase) ? '<div class="sl-price-base">Розница: ' . number_format($priceDisplay, 2, ',', ' ') . ' ₽</div>' : '';
?>
<div class="sl-warehouse-row <?=$wh['is_sched']?'sl-wh--order':'sl-wh--instock'?>">
<div class="sl-cell sl-cell--expand"><?=$retIcon?></div>
<div class="sl-cell sl-cell--brand"><?=$sourceTag?></div>
<div class="sl-cell sl-cell--desc"><span class="sl-wh-stock">📍 <?=htmlspecialchars($stockDisplay)?></span></div>
<div class="sl-cell sl-cell--mult"><?php if(($wh['multiplicity']??1)>1):?><span class="sl-mult-badge">×<?=$wh['multiplicity']?> <?=htmlspecialchars($wh['unit']??'шт.')?></span><?php else:?><span class="sl-mult-text"><?=htmlspecialchars($wh['unit']??'шт.')?></span><?php endif;?></div>
<div class="sl-cell sl-cell--stock"><?=$wh['is_sched']?'<span class="sl-badge sl-badge--yellow">'.$wql.'</span>':'<span class="sl-badge sl-badge--green">'.$wql.'</span>'?></div>
<div class="sl-cell sl-cell--delivery"><?=$wdl?></div>
<div class="sl-cell sl-cell--price"><strong><?=$priceHtml?></strong><?=$priceBaseHtml?></div>
<div class="sl-cell sl-cell--order"><button class="btn btn--order-supplier btn--order-supplier-sm" onclick="event.stopPropagation();orderFromSupplier(this,'<?=htmlspecialchars($group['article'])?>','<?=htmlspecialchars($group['brand'])?>')">🛒</button></div>
</div>
<?php endforeach; ?>
</div></div>
<?php endforeach;
    $html = ob_get_clean();

    $response = [
        'success' => true,
        'html' => $html,
        'totalGroups' => count($analogGroups),
        'totalWarehouses' => $result['totalWarehouses'],
        'ok' => 1
    ];

    if (isset($responseDone)) {
        $response['done'] = $responseDone;
        $response['nextChunk'] = $responseNext;
    }

    if (!empty($p2Hash)) {
        $response['p2_hash'] = $p2Hash;
        $response['p2_pending'] = true;
    }

    $cache->set($cacheKey, $response, 300);

    if ($phase === 'fast' && !empty($p2Hash)) {
        exec('/usr/bin/php /var/www/u3564357/data/www/liderws.ru/local/ajax/analog_p2_exec.php ' . escapeshellarg($number) . ' ' . escapeshellarg($brand) . ' > /dev/null 2>&1 &');
    }

    error_log("About to output JSON");
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()]);
}