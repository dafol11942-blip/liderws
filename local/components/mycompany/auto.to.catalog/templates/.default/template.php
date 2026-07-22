<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\CJSCore::Init(['ajax']);
\Bitrix\Main\Loader::includeModule('highloadblock');

use Bitrix\Highloadblock\HighloadBlockTable;

$mode       = $arResult['PAGE_MODE'] ?? 'embed';
$detailPage = $arResult['DETAIL_PAGE'] ?? '/service-parts/';

$urlBrand = isset($_GET['brand'])   ? (int)$_GET['brand']   : 0;
$urlModel = isset($_GET['model'])   ? (int)$_GET['model']   : 0;
$urlMod   = isset($_GET['modification']) ? (int)$_GET['modification'] : 0;

function hlGet(string $name, array $filter = [], array $order = [], int $limit = 0): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $hl = HighloadBlockTable::getRow(['filter' => ['=NAME' => $name]]);
        $cache[$name] = $hl ? HighloadBlockTable::compileEntity($hl)->getDataClass() : null;
    }
    if (!$cache[$name]) return [];
    $q = $cache[$name]::query()->setSelect(['*']);
    if ($filter) $q->setFilter($filter);
    if ($order)  $q->setOrder($order);
    if ($limit)  $q->setLimit($limit);
    return $q->exec()->fetchAll();
}

// Функция поиска картинки (ищет по коду, затем по ID, любое расширение)
function findImage(string $dir, string $code, int $id): string
{
    foreach (['png','jpg','jpeg','svg','webp'] as $ext) {
        $test = $dir . strtolower($code) . '.' . $ext;
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $test)) return $test;
    }
    foreach (['png','jpg','jpeg','svg','webp'] as $ext) {
        $test = $dir . $id . '.' . $ext;
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $test)) return $test;
    }
    return '';
}

if ($urlMod && !$urlBrand && !$urlModel) {
    $modInfo = hlGet('AutoModifications', ['UF_MODIFICATION_ID' => $urlMod], [], 1);
    if (!empty($modInfo)) {
        $urlModel = (int)$modInfo[0]['UF_MODEL_ID'];
        $modelInfo = hlGet('AutoModels', ['UF_MODEL_ID' => $urlModel], [], 1);
        $urlBrand = (int)($modelInfo[0]['UF_BRAND_ID'] ?? 0);
    }
}
?>
<?php if ($mode === 'embed'): ?>
<div class="auto-finder">
    <h2 class="auto-finder__title">🔧 Подбор запчастей по автомобилю</h2>
    <p class="auto-finder__subtitle">Выберите марку, модель и модификацию — покажем точный список запчастей для вашего авто</p>
    <div class="auto-finder__form">
        <select class="auto-finder__select" id="brandSelect"><option value="">— Марка —</option></select>
        <select class="auto-finder__select" id="modelSelect" disabled><option value="">— Модель —</option></select>
        <select class="auto-finder__select" id="modSelect" disabled><option value="">— Модификация —</option></select>
        <button class="btn btn--primary" id="showPartsBtn" disabled>Показать запчасти</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
var C='mycompany:auto.to.catalog',U='<?= CUtil::JSEscape($detailPage) ?>';
var b=document.getElementById('brandSelect'),m=document.getElementById('modelSelect'),o=document.getElementById('modSelect'),t=document.getElementById('showPartsBtn');
BX.ajax.runComponentAction(C,'getBrands',{mode:'class',data:{}}).then(function(r){r.data.brands.forEach(function(x){var y=document.createElement('option');y.value=x.UF_BRAND_ID;y.textContent=x.UF_NAME;b.appendChild(y);});});
b.onchange=function(){var i=this.value;m.innerHTML='<option value="">— Модель —</option>';o.innerHTML='<option value="">— Модификация —</option>';m.disabled=o.disabled=t.disabled=true;if(!i)return;BX.ajax.runComponentAction(C,'getModels',{mode:'class',data:{brandId:parseInt(i)}}).then(function(r){r.data.models.forEach(function(x){var y=document.createElement('option');y.value=x.UF_MODEL_ID;y.textContent=x.UF_NAME+(x.UF_YEAR_FROM?' ('+x.UF_YEAR_FROM+'\u2013'+(x.UF_YEAR_TO||'н.в.')+')':'');m.appendChild(y);});m.disabled=false;});};
m.onchange=function(){var i=this.value;o.innerHTML='<option value="">— Модификация —</option>';o.disabled=t.disabled=true;if(!i)return;BX.ajax.runComponentAction(C,'getModifications',{mode:'class',data:{modelId:parseInt(i)}}).then(function(r){r.data.modifications.forEach(function(x){var y=document.createElement('option');y.value=x.UF_MODIFICATION_ID;y.textContent=x.UF_FULL_NAME+' | '+(x.UF_ENGINE_CAPACITY||'?')+' л | '+(x.UF_HORSE_POWER||'?')+' л.с. | '+(x.UF_FUEL||'');o.appendChild(y);});o.disabled=false;});};
o.onchange=function(){t.disabled=!this.value;};
t.onclick=function(){if(o.value)window.location.href=U+'?modification='+o.value;};
});
</script>

<?php else: ?>
<div class="to-full-catalog">

<?php
// ===== ЭТАП 4: РЕЗУЛЬТАТ =====
if ($urlMod):
    $mod   = hlGet('AutoModifications', ['UF_MODIFICATION_ID' => $urlMod], [], 1)[0] ?? null;
    $model = hlGet('AutoModels', ['UF_MODEL_ID' => $urlModel], [], 1)[0] ?? null;
    $brand = hlGet('AutoBrands', ['UF_BRAND_ID' => $urlBrand], [], 1)[0] ?? null;
    $parts = hlGet('AutoParts', ['UF_MODIFICATION_ID' => $urlMod], ['UF_CATEGORY_ID' => 'ASC']);
    $oils  = hlGet('AutoOils', ['UF_MODIFICATION_ID' => $urlMod], ['UF_ORDER_POSITION' => 'ASC']);
    $specs = hlGet('AutoSpecifications', ['UF_MODIFICATION_ID' => $urlMod], ['UF_NAME' => 'ASC']);

    $groups = ['Фильтры'=>[1,2,3,4],'Тормозная система'=>[5,6],'Зажигание и прочее'=>[7,8]];
    $grouped = ['Фильтры'=>[],'Тормозная система'=>[],'Зажигание и прочее'=>[]];
    foreach ($parts as $p) {
        $c = (int)$p['UF_CATEGORY_ID'];
        foreach ($groups as $gn => $cats) { if (in_array($c, $cats)) { $grouped[$gn][] = $p; break; } }
    }
    $modSpecs = [];
    if ($mod) {
        if ($mod['UF_ENGINE_CODE']) $modSpecs[] = 'Двигатель: ' . htmlspecialchars($mod['UF_ENGINE_CODE']);
        $modSpecs[] = 'Объём: ' . ($mod['UF_ENGINE_CAPACITY'] ?: '?') . ' л · Мощность: ' . ($mod['UF_HORSE_POWER'] ?: '?') . ' л.с.';
        if ($mod['UF_FUEL']) $modSpecs[] = 'Топливо: ' . htmlspecialchars($mod['UF_FUEL']);
        if ($mod['UF_CONSTRUCTION_TYPE']) $modSpecs[] = 'Кузов: ' . htmlspecialchars($mod['UF_CONSTRUCTION_TYPE']);
    }
    $retUrl = urlencode('/service-parts/?brand='.$urlBrand.'&model='.$urlModel.'&modification='.$urlMod);
?>
<div style="margin-bottom:18px;font-size:14px;color:var(--gray);">
    <a href="/service-parts/" style="color:var(--blue);font-weight:700;">Марки</a>
    <?php if ($brand): ?> → <a href="/service-parts/?brand=<?= $urlBrand ?>" style="color:var(--blue);font-weight:700;"><?= htmlspecialchars($brand['UF_NAME']) ?></a><?php endif; ?>
    <?php if ($model): ?> → <a href="/service-parts/?brand=<?= $urlBrand ?>&model=<?= $urlModel ?>" style="color:var(--blue);font-weight:700;"><?= htmlspecialchars($model['UF_NAME']) ?></a><?php endif; ?>
    <?php if ($mod): ?> → <strong><?= htmlspecialchars($mod['UF_FULL_NAME']) ?></strong><?php endif; ?>
</div>

<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:20px 24px;margin-bottom:20px;box-shadow:var(--shadow-sm);">
    <h2 style="font-size:20px;font-weight:800;margin-bottom:8px;"><?= htmlspecialchars($mod['UF_FULL_NAME'] ?? '') ?></h2>
    <div style="display:flex;flex-wrap:wrap;gap:6px 18px;font-size:13px;color:var(--gray);">
        <?php foreach ($modSpecs as $s): ?><span><?= $s ?></span><?php endforeach; ?>
    </div>
</div>

<?php $hasParts = !empty($grouped['Фильтры']) || !empty($grouped['Тормозная система']) || !empty($grouped['Зажигание и прочее']); ?>
<?php if ($hasParts): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">🔧 Запчасти для ТО</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px;">
    <?php foreach ($grouped as $gName => $items): ?>
        <?php if (empty($items)) continue; ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;">
            <div style="background:var(--bg);padding:10px 16px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.03em;color:var(--black);border-bottom:1px solid var(--border);"><?= htmlspecialchars($gName) ?></div>
            <table style="width:100%;border-collapse:collapse;border:none;margin:0;">
                <thead><tr><th style="padding:10px 16px;text-align:left;font-weight:700;font-size:11px;text-transform:uppercase;color:var(--gray);border-bottom:1px solid var(--border);">Наименование</th><th style="padding:10px 16px;text-align:right;font-weight:700;font-size:11px;text-transform:uppercase;color:var(--gray);border-bottom:1px solid var(--border);">Артикул</th></tr></thead>
                <tbody>
                <?php foreach ($items as $p): ?>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="padding:10px 16px;">
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['UF_ITEM_NAME']) ?></div>
                            <?php if ($p['UF_COMMENT']): ?><div style="font-size:11px;color:var(--gray);margin-top:1px;"><?= htmlspecialchars($p['UF_COMMENT']) ?></div><?php endif; ?>
                        </td>
                        <td style="padding:10px 16px;text-align:right;">
                            <a href="/search/?q=<?= urlencode($p['UF_PART_NUMBER']) ?>&return_url=<?= $retUrl ?>" style="display:inline-block;background:var(--bg);color:var(--blue-dark);padding:4px 10px;border-radius:var(--radius);font-weight:700;font-size:12px;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.background='var(--blue)';this.style.color='#fff';" onmouseout="this.style.background='var(--bg)';this.style.color='var(--blue-dark)';"><?= htmlspecialchars($p['UF_PART_NUMBER']) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($oils): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">🛢️ Масла и жидкости</h3>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;border:none;margin:0;">
            <thead><tr><th style="padding:10px 16px;text-align:left;">Тип</th><th style="padding:10px 16px;text-align:left;">Продукт</th><th style="padding:10px 16px;text-align:left;">Артикул</th><th style="padding:10px 16px;text-align:center;">Объём, л</th></tr></thead>
            <tbody>
            <?php foreach ($oils as $o): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:10px 16px;"><span style="background:var(--bg);padding:2px 10px;border-radius:var(--radius);font-size:11px;font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($o['UF_TYPE_NAME']) ?></span></td>
                    <td style="padding:10px 16px;font-weight:600;"><?= htmlspecialchars($o['UF_GROUP_NAME']) ?></td>
                    <td style="padding:10px 16px;"><a href="/search/?q=<?= urlencode($o['UF_ART_NUMBER']) ?>&return_url=<?= $retUrl ?>" style="color:var(--blue-dark);font-weight:700;font-size:12px;"><?= htmlspecialchars($o['UF_ART_NUMBER']) ?></a></td>
                    <td style="padding:10px 16px;text-align:center;"><?= $o['UF_VOLUME'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($specs): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">⚙️ Спецификации и объёмы заправки</h3>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;border:none;margin:0;">
            <thead><tr><th style="padding:10px 16px;text-align:left;">Жидкость</th><th style="padding:10px 16px;text-align:center;">Объём</th><th style="padding:10px 16px;text-align:left;">Допуски / примечание</th></tr></thead>
            <tbody>
            <?php foreach ($specs as $s): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:10px 16px;font-weight:600;"><?= htmlspecialchars($s['UF_NAME']) ?></td>
                    <td style="padding:10px 16px;text-align:center;"><?= $s['UF_VOLUME'] ?></td>
                    <td style="padding:10px 16px;font-size:12px;color:var(--gray);"><?= htmlspecialchars($s['UF_PROPERTIES'] ?? $s['UF_COMMENT'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
// ===== ЭТАП 3: МОДИФИКАЦИИ =====
elseif ($urlModel):
    $model = hlGet('AutoModels', ['UF_MODEL_ID' => $urlModel], [], 1)[0] ?? null;
    $brand = hlGet('AutoBrands', ['UF_BRAND_ID' => $urlBrand], [], 1)[0] ?? null;
    $mods  = hlGet('AutoModifications', ['UF_MODEL_ID' => $urlModel], ['UF_FULL_NAME' => 'ASC']);
?>
<div style="margin-bottom:18px;font-size:14px;color:var(--gray);">
    <a href="/service-parts/" style="color:var(--blue);font-weight:700;">Марки</a>
    <?php if ($brand): ?> → <a href="/service-parts/?brand=<?= $urlBrand ?>" style="color:var(--blue);font-weight:700;"><?= htmlspecialchars($brand['UF_NAME']) ?></a><?php endif; ?>
    <?php if ($model): ?> → <strong><?= htmlspecialchars($model['UF_NAME']) ?></strong><?php endif; ?>
</div>
<h2 class="section-title" style="margin-bottom:16px;">Выберите модификацию <?= htmlspecialchars($model['UF_NAME'] ?? '') ?></h2>
<div style="display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($mods as $m): ?>
        <a href="?brand=<?= $urlBrand ?>&model=<?= $urlModel ?>&modification=<?= $m['UF_MODIFICATION_ID'] ?>"
           style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow-sm);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;text-decoration:none;transition:all var(--transition);">
            <div>
                <strong style="font-size:14px;color:var(--black);"><?= htmlspecialchars($m['UF_FULL_NAME']) ?></strong>
                <div style="font-size:12px;color:var(--gray);margin-top:2px;">
                    <?php if ($m['UF_ENGINE_CODE']): ?>Код: <?= htmlspecialchars($m['UF_ENGINE_CODE']) ?> · <?php endif; ?>
                    <?= $m['UF_ENGINE_CAPACITY'] ?> л · <?= $m['UF_HORSE_POWER'] ?> л.с. · <?= htmlspecialchars($m['UF_FUEL'] ?? '') ?> · <?= htmlspecialchars($m['UF_CONSTRUCTION_TYPE'] ?? '') ?>
                </div>
            </div>
            <span style="color:var(--blue);font-weight:700;font-size:13px;">Выбрать →</span>
        </a>
    <?php endforeach; ?>
</div>

<?php
// ===== ЭТАП 2: МОДЕЛИ =====
elseif ($urlBrand):
    $brand  = hlGet('AutoBrands', ['UF_BRAND_ID' => $urlBrand], [], 1)[0] ?? null;
    $models = hlGet('AutoModels', ['UF_BRAND_ID' => $urlBrand], ['UF_NAME' => 'ASC']);
    $brandCode = strtolower($brand['UF_CODE'] ?? '');
?>
<div style="margin-bottom:18px;font-size:14px;color:var(--gray);">
    <a href="/service-parts/" style="color:var(--blue);font-weight:700;">Марки</a>
    <?php if ($brand): ?> → <strong><?= htmlspecialchars($brand['UF_NAME']) ?></strong><?php endif; ?>
</div>
<h2 class="section-title" style="margin-bottom:16px;">Выберите модель <?= htmlspecialchars($brand['UF_NAME'] ?? '') ?></h2>
<input type="text" placeholder="🔍 Быстрый поиск модели..." style="width:100%;max-width:400px;padding:10px 14px;border:2px solid var(--border);border-radius:var(--radius);font-size:14px;font-family:var(--font);margin-bottom:16px;box-shadow:var(--shadow-sm);" oninput="var q=this.value.toLowerCase();document.querySelectorAll('.to-brand-card').forEach(function(c){c.style.display=c.querySelector('.to-brand-name').textContent.toLowerCase().includes(q)?'':'none';});">
<div class="to-brands-grid">
    <?php foreach ($models as $m):
        $modelCode = strtolower(preg_replace('/[^a-z0-9]+/', '_', \CUtil::translit($m['UF_NAME'], 'ru', ['replace_space'=>'_','replace_other'=>''])));
        $imgPath = findImage('/upload/models/', $brandCode . '_' . $modelCode, $m['UF_MODEL_ID']);
        $hasImg = $imgPath !== '';
    ?>
        <a href="?brand=<?= $urlBrand ?>&model=<?= $m['UF_MODEL_ID'] ?>" class="to-brand-card" style="text-decoration:none;">
            <?php if ($hasImg): ?>
                <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($m['UF_NAME']) ?>" style="height:48px;width:auto;max-width:100px;object-fit:contain;margin-bottom:6px;">
            <?php else: ?>
                <div style="width:80px;height:48px;background:var(--bg);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--blue-dark);margin-bottom:6px;">🚗</div>
            <?php endif; ?>
            <span class="to-brand-name"><?= htmlspecialchars($m['UF_NAME']) ?></span>
            <?php if ($m['UF_YEAR_FROM']): ?><span style="font-size:11px;color:var(--gray);display:block;"><?= $m['UF_YEAR_FROM'] ?>–<?= $m['UF_YEAR_TO'] ?: 'н.в.' ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
// ===== ЭТАП 1: МАРКИ =====
else:
    $brands = hlGet('AutoBrands', [], ['UF_NAME' => 'ASC']);
?>
<h2 class="section-title" style="margin-bottom:16px;">Выберите марку автомобиля</h2>
<input type="text" placeholder="🔍 Быстрый поиск марки..." style="width:100%;max-width:400px;padding:10px 14px;border:2px solid var(--border);border-radius:var(--radius);font-size:14px;font-family:var(--font);margin-bottom:16px;box-shadow:var(--shadow-sm);" oninput="var q=this.value.toLowerCase();document.querySelectorAll('.to-brand-card').forEach(function(c){c.style.display=c.querySelector('.to-brand-name').textContent.toLowerCase().includes(q)?'':'none';});">
<div class="to-brands-grid">
    <?php foreach ($brands as $b):
        $imgPath = findImage('/upload/brands/', $b['UF_CODE'], $b['UF_BRAND_ID']);
        $hasImg = $imgPath !== '';
    ?>
        <a href="?brand=<?= $b['UF_BRAND_ID'] ?>" class="to-brand-card" style="text-decoration:none;">
            <?php if ($hasImg): ?>
                <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($b['UF_NAME']) ?>" style="height:36px;width:auto;max-width:80px;object-fit:contain;margin-bottom:6px;">
            <?php else: ?>
                <div style="width:80px;height:36px;background:var(--bg);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:var(--blue);margin-bottom:6px;"><?= mb_substr($b['UF_NAME'], 0, 2) ?></div>
            <?php endif; ?>
            <span class="to-brand-name"><?= htmlspecialchars($b['UF_NAME']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?php endif; ?>
