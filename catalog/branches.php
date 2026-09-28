<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
// Хаб-страница /catalog/ — раньше был листинг одного инфоблока (42), теперь
// три параллельных инфоблока-каталога без общего дерева разделов (см.
// CATALOG_BRANCHES в index.php), поэтому корень каталога — просто ссылки
// на три ветки, а не список товаров.
$APPLICATION->SetPageProperty('title', 'Каталог автозапчастей в Елабуге — ЛИДЕР');

$branchesHtml = '';
foreach (CATALOG_BRANCHES as $slug => $info) {
    $branchesHtml .= '
    <a href="/catalog/' . $slug . '/" class="category-card">
        <span class="category-card__icon"><svg class="icon"><use href="#icon-folder"></use></svg></span>
        <span class="category-card__name">' . htmlspecialchars($info['name']) . '</span>
    </a>';
}

$mainHtml = '
<div class="breadcrumbs container">
    <ul><li>Каталог автозапчастей</li></ul>
</div>
<div class="container">
    <div class="section-header">
        <h2 class="section-title"><svg class="icon"><use href="#icon-box"></use></svg> Каталог товаров</h2>
    </div>
    <div class="categories-grid">' . $branchesHtml . '</div>
</div>';

if ($isAjax) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['filter' => '', 'results' => $mainHtml], JSON_UNESCAPED_UNICODE);
    exit;
}
echo $mainHtml;
