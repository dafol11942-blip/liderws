<?php
// ============================================================
// search/index.php — НОВЫЙ поиск liderws.ru (чистовая версия)
// ============================================================
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '120');
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/init_pricing.php");

use Lider\Search\BrandNormalizer;

$APPLICATION->SetTitle("Поиск запчастей");
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

// ── Входные параметры ──
$q             = trim($_REQUEST['q'] ?? '');
$selectedBrand = trim($_REQUEST['brand'] ?? '');
$selectedNumber = trim($_REQUEST['number'] ?? '');
$brandKey      = $_REQUEST['brand_key'] ?? '';
$iblockId      = 42;                          // 1С-каталог (свои остатки)

// ── Хелперы ──
function normArt($s)  { return mb_strtolower(preg_replace('/[\s\-\+\.\/\\\*]/u', '', $s)); }
function normBr($s)   { $s = BrandNormalizer::map($s); return mb_strtolower(preg_replace("/^([^\s\-\._\/]+).*$/u", "$1", $s)); }
function fmtPrice($p) { return number_format((float)$p, 2, ',', ' '); }
function esc($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── СТАДИЯ 0: пустой поиск ──
if (!$q): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Поиск запчастей — liderws.ru</title>
    <style><?php include __DIR__ . '/style.css'; ?></style>
</head>
<body>
<div class="srch-container">
    <div class="srch-hero">
        <h1>🔍 Поиск автозапчастей</h1>
        <p>Введите артикул, название или VIN-номер</p>
        <form class="srch-form-hero" method="get">
            <input type="text" name="q" class="srch-input-hero" placeholder="Например: W7008" autofocus>
            <button type="submit" class="srch-btn-hero">Найти</button>
        </form>
    </div>
</div>
</body>
</html>
<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); exit; endif;

// ═══════════════════════════════════════════════
// СТАДИЯ 1: ввод артикула → свои остатки + выбор бренда
// ═══════════════════════════════════════════════
if (empty($selectedBrand)):

    $normQ = normArt($q);

    // ── Свои остатки (1С) ──
    global $arrFilter;
    $arrFilter = [
        ['LOGIC' => 'OR',
            ['%NAME'                     => $q],
            ['PROPERTY_CML2_ARTICLE'     => $q],
            ['%PROPERTY_CML2_ARTICLE'    => $q],
            ['%DETAIL_TEXT'              => $q],
            ['PROPERTY_CML2_MANUFACTURER' => $q],
            ['%PROPERTY_CML2_MANUFACTURER' => $q],
        ]
    ];
    $localRes   = CIBlockElement::GetList([], array_merge(['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], $arrFilter[0]), false, false, ['ID']);
    $localCount = $localRes->SelectedRowsCount();

    // ── Бренды от поставщиков ──
    $cacheKey  = 'brands_' . md5(mb_strtolower($q));
    $cache     = new \Lider\Search\SearchCacheManager();
    $allBrandsRaw = $cache->get($cacheKey);

    if ($allBrandsRaw === null) {
        $allBrandsRaw = [];
        $brandReqs    = [];
        foreach (getSupplierFactory()->allAvailable() as $supplier) {
            $req = $supplier->buildBrandsRequest($q);
            if ($req) $brandReqs[$supplier->getCode()] = ['req' => $req, 'supplier' => $supplier];
        }
        if (!empty($brandReqs)) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($brandReqs as $code => $data) {
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
                    CURLOPT_ENCODING       => '',
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
                $body = curl_multi_getcontent($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($http === 200 && !empty($body)) {
                    try {
                        $brands = $brandReqs[$code]['supplier']->parseBrandsResponse($body, $q);
                        foreach ($brands as $br) {
                            $br['source']        = $brandReqs[$code]['supplier']->getCode();
                            $br['supplier_name'] = $brandReqs[$code]['supplier']->getName();
                            $allBrandsRaw[]      = $br;
                        }
                    } catch (\Throwable $e) {}
                }
            }
            curl_multi_close($mh);
        }
        $cache->set($cacheKey, $allBrandsRaw, 900);
    }

    // ── Группировка брендов ──
    $brandMap = [];
    foreach ($allBrandsRaw as $br) {
        $brBrand = trim((string)($br["brand"] ?? ""));
        $brArt   = trim((string)($br["article_nr"] ?? ($br["article"] ?? "")));
        if ($brBrand === "" || $brArt === "") continue;
        $br["brand"]      = $brBrand;
        $br["article_nr"] = $brArt;
        $key = BrandNormalizer::groupKey($brBrand, $brArt);
        if (!isset($brandMap[$key])) {
            $brandMap[$key] = ["brands" => [], "articles" => [], "article_nr" => $br["article_nr"], "description" => $br["description"] ?: '', "sources" => []];
        }
        $src = $br["source"];
        $brandMap[$key]["brands"][$src]   = $br["brand"];
        $brandMap[$key]["articles"][$src] = $br["article_nr"];
        if (!in_array($src, $brandMap[$key]["sources"], true)) $brandMap[$key]["sources"][] = $src;
        if (mb_strlen($br["description"] ?: '') > mb_strlen($brandMap[$key]["description"]))
            $brandMap[$key]["description"] = $br["description"] ?: '';
        $brandMap[$key]["article_nr"] = BrandNormalizer::pickDisplayArticle($brandMap[$key]["articles"], $brandMap[$key]["article_nr"]);
    }

    // Разделяем точные совпадения и аналоги
    $exactBrands  = [];
    $analogBrands = [];
    foreach ($brandMap as $key => $info) {
        $isExact        = (normArt($info["article_nr"]) === $normQ);
        $displayBrand   = BrandNormalizer::displayBrand((string)(reset($info["brands"]) ?: ''));
        $displayArticle = BrandNormalizer::pickDisplayArticle($info["articles"] ?? [], $info["article_nr"] ?? '');
        $entry = ["brand" => $displayBrand, "article" => $displayArticle, "article_nr" => $displayArticle, "description" => $info["description"], "sources" => $info["sources"], "brands" => $info["brands"], "articles" => $info["articles"], "key" => $key];
        if ($isExact) $exactBrands[] = $entry;
        else $analogBrands[] = $entry;
    }
    $sortFn = fn($a, $b) => count($b['sources'] ?? []) <=> count($a['sources'] ?? []) ?: strcmp(mb_strtolower($a['brand'] ?? ''), mb_strtolower($b['brand'] ?? ''));
    usort($exactBrands, $sortFn);
    usort($analogBrands, $sortFn);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Поиск: <?=esc($q)?> — liderws.ru</title>
    <style><?php include __DIR__ . '/style.css'; ?></style>
</head>
<body>
<div class="srch-container">

    <!-- Шапка поиска -->
    <div class="srch-topbar">
        <form class="srch-form-inline" method="get">
            <input type="text" name="q" class="srch-input-inline" value="<?=esc($q)?>">
            <button type="submit" class="srch-btn-inline">🔍</button>
        </form>
        <span class="srch-badge">Поиск: <strong><?=esc($q)?></strong></span>
        <?php if (isManager()): ?><span class="mgr-badge">🔧 Менеджер</span><?php endif; ?>
    </div>

    <?php if ($localCount > 0 || !empty($exactBrands) || !empty($analogBrands)): ?>

        <!-- 🔵 СВОИ ОСТАТКИ -->
        <?php if ($localCount > 0): ?>
        <div class="section">
            <h2 class="section-title section-title--local">🔵 На нашем складе</h2>
            <?php global $arrFilter;
            $APPLICATION->IncludeComponent("bitrix:catalog.section", "lider_style", [
                "IBLOCK_TYPE"           => "1c_catalog",
                "IBLOCK_ID"             => $iblockId,
                "INCLUDE_SUBSECTIONS"   => "Y",
                "SHOW_ALL_WO_SECTION"   => "Y",
                "ELEMENT_SORT_FIELD"    => "sort",
                "ELEMENT_SORT_ORDER"    => "asc",
                "FILTER_NAME"           => "arrFilter",
                "PRICE_CODE"            => ["Ручная розничная цена"],
                "PROPERTY_CODE"         => ["CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"],
                "PAGE_ELEMENT_COUNT"    => "12",
                "HIDE_NOT_AVAILABLE"    => "Y",
                "BASKET_URL"            => "/personal/cart/",
                "CACHE_TYPE"            => "A",
                "CACHE_TIME"            => "300",
                "SET_TITLE"             => "N",
            ], false); ?>
        </div>
        <?php endif; ?>

        <!-- 🟠 ВЫБОР БРЕНДА (точные совпадения) -->
        <?php if (!empty($exactBrands)): ?>
        <div class="section">
            <h2 class="section-title section-title--brand">🟠 Выберите бренд для «<?=esc($q)?>»</h2>
            <p class="section-hint">Под этим артикулом у разных производителей могут быть разные детали. Выберите нужный бренд.</p>
            <div class="brand-table">
                <div class="bt-head">
                    <div class="bt-cell bt-cell--brand">Производитель</div>
                    <div class="bt-cell bt-cell--article">Артикул</div>
                    <div class="bt-cell bt-cell--desc">Описание</div>
                    <?php if (isManager()): ?><div class="bt-cell bt-cell--source">Источник</div><?php endif; ?>
                    <div class="bt-cell bt-cell--action"></div>
                </div>
                <?php foreach ($exactBrands as $br): ?>
                <div class="bt-row">
                    <div class="bt-cell bt-cell--brand"><strong><?=esc($br['brand'])?></strong></div>
                    <div class="bt-cell bt-cell--article"><code><?=esc($br['article'])?></code></div>
                    <div class="bt-cell bt-cell--desc"><?=esc($br['description'] ?: '—')?></div>
                    <?php if (isManager()): ?>
                    <div class="bt-cell bt-cell--source">
                        <?php foreach ($br['sources'] as $src): ?>
                        <span class="src-tag src-tag--<?=esc($src)?>"><?=esc($src)?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="bt-cell bt-cell--action">
                        <a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn-select">Выбрать →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 📋 АНАЛОГИ -->
        <?php if (!empty($analogBrands)): ?>
        <div class="section">
            <details class="analogs-toggle">
                <summary class="analogs-summary">📋 Аналоги и кросс-номера (<?=count($analogBrands)?>)</summary>
                <div class="brand-table brand-table--analogs" style="margin-top:12px">
                    <?php foreach ($analogBrands as $br): ?>
                    <div class="bt-row">
                        <div class="bt-cell bt-cell--brand"><?=esc($br['brand'])?></div>
                        <div class="bt-cell bt-cell--article"><code><?=esc($br['article'])?></code></div>
                        <div class="bt-cell bt-cell--desc"><?=esc($br['description'] ?: '—')?></div>
                        <?php if (isManager()): ?>
                        <div class="bt-cell bt-cell--source">
                            <?php foreach ($br['sources'] as $src): ?>
                            <span class="src-tag src-tag--<?=esc($src)?>"><?=esc($src)?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="bt-cell bt-cell--action">
                            <a href="?q=<?=urlencode($q)?>&brand=<?=urlencode($br['brand'])?>&number=<?=urlencode($br['article'])?>&brand_key=<?=urlencode($br['key'])?>" class="btn-select btn-select--sm">Выбрать →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <p>По артикулу «<?=esc($q)?>» ничего не найдено</p>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
<?php
// ═══════════════════════════════════════════════
// СТАДИЯ 2: выбран бренд → страница результатов
// ═══════════════════════════════════════════════
else:
    require $_SERVER["DOCUMENT_ROOT"] . "/parts-search/stage2_search_v2.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=esc($selectedBrand)?> <?=esc($selectedNumber)?> — liderws.ru</title>
    <style><?php include __DIR__ . '/style.css'; ?></style>
</head>
<body>
<div class="srch-container">

    <!-- Шапка -->
    <div class="srch-topbar">
        <form class="srch-form-inline" method="get">
            <input type="text" name="q" class="srch-input-inline" value="<?=esc($q)?>">
            <button type="submit" class="srch-btn-inline">🔍</button>
        </form>
        <span class="srch-badge"><?=esc($selectedBrand)?> <code><?=esc($selectedNumber)?></code></span>
        <a href="?q=<?=urlencode($q)?>" class="back-link">← Назад к выбору бренда</a>
        <?php if (isManager()): ?><span class="mgr-badge">🔧 Менеджер</span><?php endif; ?>
    </div>

    <?php if ($totalGroups > 0): ?>
        <p class="section-hint">
            Найдено <strong><?=$totalGroups?></strong> позиций
            (<span><?=$totalWarehouses?></span> предложений от всех поставщиков)
        </p>

        <?php
        // ── Блок: точное совпадение ──
        if (!empty($exactGroups)):
            renderResultBlock($exactGroups, 'exact', '✅ Искомый: ' . esc($selectedBrand) . ' / ' . esc($selectedNumber));
        endif;

        // ── Блок: аналоги ──
        if (!empty($analogGroups)):
            renderResultBlock($analogGroups, 'analog', '🔄 Аналоги (' . count($analogGroups) . ' поз.)');
        elseif (empty($exactGroups)):
            echo '<div class="empty-state"><p>Нет доступных предложений</p><a href="?q=' . urlencode($q) . '" class="back-link">← Назад</a></div>';
        endif;
        ?>

    <?php else: ?>
        <div class="empty-state">
            <p>Нет доступных предложений</p>
            <a href="?q=<?=urlencode($q)?>" class="back-link">← Назад</a>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleWarehouses(row) {
    var g = row.closest('.res-group');
    var w = g.querySelector('.res-warehouses');
    if (row.classList.contains('open')) {
        row.classList.remove('open');
        w.style.display = 'none';
    } else {
        row.classList.add('open');
        w.style.display = 'block';
    }
}
</script>
</body>
</html>
<?php endif; ?>

<?php
// ═════════════════════════════════════
// ФУНКЦИИ РЕНДЕРА (Стадия 2)
// ═════════════════════════════════════
function renderResultBlock(array $groups, string $cssClass, string $title): void {
    static $ri = 0;
    if (empty($groups)) return;
?>
<div class="res-block res-block--<?=$cssClass?>">
    <div class="res-block-header">
        <span class="res-block-badge res-block-badge--<?=$cssClass?>"><?=$title?></span>
        <span class="res-block-count"><?=count($groups)?> поз.</span>
    </div>
    <div class="res-table">
        <div class="res-table-head">
            <div class="res-cell res-cell--expand"></div>
            <div class="res-cell res-cell--brand">Бренд</div>
            <div class="res-cell res-cell--desc">Описание</div>
            <div class="res-cell res-cell--article">Артикул</div>
            <div class="res-cell res-cell--stock">Наличие</div>
            <div class="res-cell res-cell--delivery">Доставка</div>
            <div class="res-cell res-cell--price">Цена</div>
            <div class="res-cell res-cell--order"></div>
        </div>
        <?php foreach ($groups as $group): $ri++;
            $inStock = $group['has_instock'];
            $rowCls  = $inStock ? 'res-row--instock' : 'res-row--order';
            $prc     = $group['min_price'] == $group['max_price']
                ? fmtPrice($group['min_price'])
                : 'от ' . fmtPrice($group['min_price']);
            $dl  = formatDelivery($group['min_delivery']);
            $dq  = $group['in_stock_qty'] > 0 ? $group['in_stock_qty'] : $group['total_qty'];
            $ql  = formatQty($dq);
        ?>
        <div class="res-group">
            <div class="res-row <?=$rowCls?> res-main-row" onclick="toggleWarehouses(this)" data-group="<?=$ri?>">
                <div class="res-cell res-cell--expand"><span class="res-expand">▶</span></div>
                <div class="res-cell res-cell--brand"><strong><?=esc($group['brand'])?></strong></div>
                <div class="res-cell res-cell--desc"><div class="res-desc"><?=esc($group['description'])?></div></div>
                <div class="res-cell res-cell--article"><code><?=esc($group['article'])?></code></div>
                <div class="res-cell res-cell--stock">
                    <?php if ($inStock): ?><span class="badge badge--green"><?=$ql?></span>
                    <?php else: ?><span class="badge badge--yellow"><?=$ql?></span><?php endif; ?>
                </div>
                <div class="res-cell res-cell--delivery"><?=$dl?></div>
                <div class="res-cell res-cell--price">
                    <strong><?=$prc?> ₽</strong>
                    <div class="res-wh-count"><?=count($group['warehouses'])?> складов</div>
                </div>
                <div class="res-cell res-cell--order"></div>
            </div>
            <div class="res-warehouses" id="wh-group-<?=$ri?>" style="display:none">
                <?php foreach ($group['warehouses'] as $wh):
                    $retIcon  = $wh['returnable']
                        ? '<span class="ret-icon ret-icon--yes" title="Возвратный">↻</span>'
                        : '<span class="ret-icon ret-icon--no" title="Невозвратный">✕</span>';
                    $srcTag = isManager()
                        ? '<span class="src-tag src-tag--' . esc($wh['source']) . '">' . esc($wh['supplier']) . '</span>'
                        : '';
                    $whCls = $wh['is_sched'] ? 'res-wh--order' : 'res-wh--instock';
                ?>
                <div class="res-wh-row <?=$whCls?>">
                    <div class="res-cell res-cell--expand"><?=$retIcon?></div>
                    <div class="res-cell res-cell--brand"><?=$srcTag?></div>
                    <div class="res-cell res-cell--desc"><span class="res-wh-stock">📍 <?=esc($wh['stock'])?></span></div>
                    <div class="res-cell res-cell--stock">
                        <?php if ($wh['is_sched']): ?><span class="badge badge--yellow"><?=formatQty($wh['qty'])?></span>
                        <?php else: ?><span class="badge badge--green"><?=formatQty($wh['qty'])?></span><?php endif; ?>
                    </div>
                    <div class="res-cell res-cell--delivery"><?=formatDelivery($wh['delivery'])?></div>
                    <div class="res-cell res-cell--price">
                        <strong><?=fmtPrice($wh['price'])?> ₽</strong>
                        <?php if (isManager() && $wh['price'] !== $wh['price_base']): ?>
                        <div class="res-price-base">Закуп: <?=fmtPrice($wh['price_base'])?> ₽</div>
                        <?php endif; ?>
                    </div>
                    <div class="res-cell res-cell--order">
                        <button class="btn-order"
                            data-article="<?=esc($group['article'])?>"
                            data-brand="<?=esc($group['brand'])?>"
                            data-supplier="<?=esc($wh['source'])?>"
                            onclick="event.stopPropagation();orderFromSupplier(this)">🛒</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
}

// ── Хелперы рендера (дублированы из оригинального index.php) ──
function formatQty($qty, $exact = false) {
    if (isManager()) return $qty . " шт.";
    if ($qty > 4) return 'Достаточно';
    return $qty . ' шт.';
}

function formatDelivery(array $delivery): string {
    $days   = $delivery['days'];
    $approx = $delivery['is_approx'];
    if (!empty($delivery['date_from'])) {
        $from     = $delivery['date_from'];
        $to       = $delivery['date_to'] ?? null;
        $deadline = $delivery['deadline'] ?? null;
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', $from);
        $dayLabel = $fromDate === $today
            ? '<span class="txt-green">Сегодня</span>'
            : ($fromDate === date('Y-m-d', strtotime('+1 day'))
                ? '<span class="txt-amber">Завтра</span>'
                : date('d.m', $from));
        $timeStr = date('H:i', $from);
        if ($to) $timeStr .= '–' . date('H:i', $to);
        $html = $dayLabel . ' <span class="txt-time">' . $timeStr . '</span>';
        if ($deadline && $deadline > time())
            $html .= ' <span class="txt-deadline">заказ до ' . date('H:i', $deadline) . '</span>';
        return $html;
    }
    if ($days === 0) return '<span class="txt-green">Сегодня</span>';
    if ($days === 1 && !$approx) return '<span class="txt-amber">Завтра</span>';
    $date  = date('d.m', strtotime("+{$days} days"));
    $label = ($approx ? '≈ ' : '') . $date;
    return $approx ? '<span class="txt-muted">' . $label . '</span>' : $label;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");