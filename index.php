<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге"); ?>

<!-- HERO -->
<div class="hero">
    <div class="hero__content">
        <h1 class="hero__title">Автозапчасти<br><span>для иномарок и ВАЗ</span><br>в Елабуге</h1>
        <p class="hero__subtitle">Оригинальные запчасти, масла, фильтры, шины и диски от ведущих производителей. Собственный автосервис и шиномонтаж. Доставка по городу.</p>
        <div class="hero__buttons">
            <a href="/catalog/" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-box"></use></svg> Перейти в каталог</a>
            <a href="/about/" class="btn btn--white btn--lg">О магазине</a>
        </div>
    </div>
    <div class="hero__image" style="background:var(--bg-dark);display:flex;align-items:center;justify-content:center;color:var(--blue);">
        <svg class="icon" style="width:120px;height:120px;"><use href="#icon-car"></use></svg>
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
    <h2 class="section-title"><svg class="icon"><use href="#icon-box"></use></svg> Популярные категории</h2>
    <a href="/catalog/" class="section-link">Весь каталог →</a>
</div>
<div class="categories-grid">
    <a href="/catalog/masla/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-droplet"></use></svg></span><span class="category-card__name">Масла и жидкости</span></a>
    <a href="/catalog/filtry/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-filter"></use></svg></span><span class="category-card__name">Фильтры</span></a>
    <a href="/catalog/tormoznye-kolodki/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-disc"></use></svg></span><span class="category-card__name">Тормозные колодки</span></a>
    <a href="/catalog/grm/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-settings"></use></svg></span><span class="category-card__name">ГРМ и привод</span></a>
    <a href="/catalog/shiny/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-tire"></use></svg></span><span class="category-card__name">Шины и диски</span></a>
    <a href="/catalog/akkumulyatory/" class="category-card"><span class="category-card__icon"><svg class="icon"><use href="#icon-battery"></use></svg></span><span class="category-card__name">Аккумуляторы</span></a>
</div>

<!-- ПОПУЛЯРНЫЕ ТОВАРЫ -->
<div class="section-header">
    <h2 class="section-title"><svg class="icon"><use href="#icon-star"></use></svg> Популярные товары</h2>
    <a href="/catalog/" class="section-link">Все товары →</a>
</div>
<?php $APPLICATION->IncludeComponent("bitrix:catalog.section", "lider_style", [
    "IBLOCK_TYPE" => "1c_catalog", "IBLOCK_ID" => "42", "SECTION_ID" => "",
    "ELEMENT_SORT_FIELD" => "sort", "ELEMENT_SORT_ORDER" => "asc",
    "INCLUDE_SUBSECTIONS" => "Y", "SHOW_ALL_WO_SECTION" => "Y",
    "PAGE_ELEMENT_COUNT" => "8", "LINE_ELEMENT_COUNT" => "4",
    "PRICE_CODE" => ["Ручная розничная цена"],
    "PROPERTY_CODE" => ["CML2_ARTICLE", "CML2_MANUFACTURER", "IN_STOCK"],
    "HIDE_NOT_AVAILABLE" => "Y",
    "BASKET_URL" => "/cart/",
    "CACHE_TYPE" => "A", "CACHE_TIME" => "36000000",
    "SET_TITLE" => "N",
], false); ?>

<!-- АВТОСЕРВИС -->
<div class="hero mt-20">
    <div class="hero__content">
        <h2 class="hero__title"><svg class="icon"><use href="#icon-wrench"></use></svg> Автосервис <span>и шиномонтаж</span></h2>
        <p class="hero__subtitle">Замена масла, ремонт ходовой, диагностика, шиномонтаж. Купил — поставил с гарантией. Собственный шинный центр «Колеса Даром».</p>
        <div class="hero__buttons"><a href="/autoservice/" class="btn btn--primary">Подробнее об услугах</a></div>
    </div>
    <div class="hero__image" style="background:var(--bg-dark);display:flex;align-items:center;justify-content:center;gap:16px;color:var(--blue);">
        <svg class="icon" style="width:64px;height:64px;"><use href="#icon-wrench"></use></svg>
        <svg class="icon" style="width:64px;height:64px;"><use href="#icon-tire"></use></svg>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
