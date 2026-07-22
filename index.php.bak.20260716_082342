<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге"); ?>

<!-- HERO -->
<div class="hero">
    <div class="hero__content">
        <h1 class="hero__title">Автозапчасти<br><span>для иномарок и ВАЗ</span><br>в Елабуге</h1>
        <p class="hero__subtitle">Оригинальные запчасти, масла, фильтры, шины и диски от ведущих производителей. Собственный автосервис и шиномонтаж. Доставка по городу.</p>
        <div class="hero__buttons">
            <a href="/catalog/" class="btn btn--primary btn--lg">📦 Перейти в каталог</a>
            <a href="/about/" class="btn btn--white btn--lg">О магазине</a>
        </div>
    </div>
    <div class="hero__image">
        <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/main-banner.png" alt="Автозапчасти">
    </div>
</div>

<!-- ПОДБОР ПО АВТО -->
<?php $APPLICATION->IncludeComponent(
    "mycompany:auto.to.catalog",
    ".default",
    [
        'PAGE_MODE' => 'embed',
        'DETAIL_PAGE' => '/service-parts/',
    ],
    false
); ?>

<!-- ПОПУЛЯРНЫЕ КАТЕГОРИИ -->
<div class="section-header">
    <h2 class="section-title">📦 Популярные категории</h2>
    <a href="/catalog/" class="section-link">Весь каталог →</a>
</div>
<div class="categories-grid">
    <a href="/catalog/masla/" class="category-card"><span class="category-card__icon">🛢️</span><span class="category-card__name">Масла и жидкости</span></a>
    <a href="/catalog/filtry/" class="category-card"><span class="category-card__icon">🔍</span><span class="category-card__name">Фильтры</span></a>
    <a href="/catalog/tormoznye-kolodki/" class="category-card"><span class="category-card__icon">🛑</span><span class="category-card__name">Тормозные колодки</span></a>
    <a href="/catalog/grm/" class="category-card"><span class="category-card__icon">⚙️</span><span class="category-card__name">ГРМ и привод</span></a>
    <a href="/catalog/shiny/" class="category-card"><span class="category-card__icon">🛞</span><span class="category-card__name">Шины и диски</span></a>
    <a href="/catalog/akkumulyatory/" class="category-card"><span class="category-card__icon">🔋</span><span class="category-card__name">Аккумуляторы</span></a>
</div>

<!-- ПОПУЛЯРНЫЕ ТОВАРЫ -->
<div class="section-header">
    <h2 class="section-title">⭐ Популярные товары</h2>
    <a href="/catalog/" class="section-link">Все товары →</a>
</div>
<?php $APPLICATION->IncludeComponent("bitrix:catalog.section", ".default", [
    "IBLOCK_TYPE" => "catalog", "IBLOCK_ID" => "1", "SECTION_ID" => "",
    "ELEMENT_SORT_FIELD" => "sort", "ELEMENT_SORT_ORDER" => "asc",
    "INCLUDE_SUBSECTIONS" => "Y", "SHOW_ALL_WO_SECTION" => "Y",
    "PAGE_ELEMENT_COUNT" => "8", "LINE_ELEMENT_COUNT" => "4",
    "PROPERTY_CODE" => ["ARTICLE", "BRAND"],
    "CACHE_TYPE" => "A", "CACHE_TIME" => "36000000",
], false); ?>

<!-- БРЕНДЫ -->
<div class="section-header mt-20"><h2 class="section-title">🏭 Бренды</h2></div>
<div class="categories-grid">
    <?php foreach (['Bosch','Mann Filter','Castrol','Brembo','KYB','Gates','Denso','Febi','Lemförder','Sachs','Valeo','NTN-SNR'] as $b): ?>
        <div class="category-card"><span class="category-card__name"><?= $b ?></span></div>
    <?php endforeach; ?>
</div>

<!-- АВТОСЕРВИС -->
<div class="hero mt-20">
    <div class="hero__content">
        <h2 class="hero__title">🔧 Автосервис <span>и шиномонтаж</span></h2>
        <p class="hero__subtitle">Замена масла, ремонт ходовой, диагностика, шиномонтаж. Купил — поставил с гарантией. Собственный шинный центр «Колеса Даром».</p>
        <div class="hero__buttons"><a href="/autoservice/" class="btn btn--primary">Подробнее об услугах</a></div>
    </div>
    <div class="hero__image" style="background:#DAE2EF;">🔧🛞</div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
