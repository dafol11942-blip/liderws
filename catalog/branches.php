<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
// Хаб-страница /catalog/ — раньше был листинг одного инфоблока (42), теперь
// три параллельных инфоблока-каталога без общего дерева разделов (см.
// CATALOG_BRANCHES в init.php), поэтому корень каталога — просто ссылки
// на три ветки, а не список товаров.
$APPLICATION->SetPageProperty('title', 'Каталог автозапчастей в Елабуге — ВАЗ, иномарки, масла | ЛИДЕР');
$APPLICATION->SetPageProperty('description', 'Каталог магазина автозапчастей ЛИДЕР в Елабуге: запчасти для ВАЗ (LADA) и иномарок, моторные масла и технические жидкости. Цены и наличие в двух магазинах, подбор по VIN.');

// Фоновые картинки плиток — по slug ветки. Без картинки плитка остаётся
// просто крупной карточкой с иконкой (как было, только шире).
$branchImages = [
    'vaz'      => SITE_TEMPLATE_PATH . '/assets/images/catalog-vaz.jpg',
    'inomarki' => SITE_TEMPLATE_PATH . '/assets/images/catalog-inomarki.jpg',
    'maslo'    => SITE_TEMPLATE_PATH . '/assets/images/catalog-maslo.jpg',
];

$branchesHtml = '';
foreach (CATALOG_BRANCHES as $slug => $info) {
    $img = $branchImages[$slug] ?? '';
    $style = $img ? ' style="background-image:url(\'' . htmlspecialchars($img) . '\')"' : '';
    $branchesHtml .= '
    <a href="/catalog/' . $slug . '/" class="catalog-branch-card' . ($img ? ' catalog-branch-card--has-image' : '') . '"' . $style . '>
        <span class="catalog-branch-card__icon"><svg class="icon"><use href="#icon-folder"></use></svg></span>
        <span class="catalog-branch-card__name">' . htmlspecialchars($info['name']) . '</span>
    </a>';
}

$mainHtml = '
<style>
.catalog-branches-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.catalog-branch-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 16px;
    width: 100%;
    min-height: 140px;
    padding: 24px 28px;
    border-radius: 16px;
    background-color: #f3f5f9;
    background-size: cover;
    background-position: center;
    overflow: hidden;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease;
}
.catalog-branch-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
}
.catalog-branch-card--has-image::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(10,20,40,.72) 0%, rgba(10,20,40,.35) 55%, rgba(10,20,40,.1) 100%);
}
.catalog-branch-card__icon {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    border-radius: 50%;
    background: rgba(255,255,255,.9);
}
.catalog-branch-card--has-image .catalog-branch-card__icon {
    background: rgba(255,255,255,.18);
}
.catalog-branch-card--has-image .catalog-branch-card__icon svg {
    color: #fff;
}
.catalog-branch-card__name {
    position: relative;
    z-index: 1;
    font-size: 24px;
    font-weight: 700;
}
.catalog-branch-card--has-image .catalog-branch-card__name {
    color: #fff;
}
</style>
' . \Lider\Seo\Seo::breadcrumbs([['NAME' => 'Главная', 'LINK' => '/'], ['NAME' => 'Каталог автозапчастей', 'LINK' => '']]) . '
<div class="container">
    <div class="section-header">
        <h1 class="section-title"><svg class="icon"><use href="#icon-box"></use></svg> Каталог автозапчастей</h1>
    </div>
    <div class="catalog-branches-grid">' . $branchesHtml . '</div>
</div>';

if ($isAjax) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['filter' => '', 'results' => $mainHtml], JSON_UNESCAPED_UNICODE);
    exit;
}
echo $mainHtml;
