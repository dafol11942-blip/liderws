<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге | Елабуга");
$APPLICATION->SetPageProperty("description", "Магазин автозапчастей ЛИДЕР в Елабуге: детали для иномарок и ВАЗ, масла, фильтры, тормозные колодки, шины и диски. Собственный автосервис и шиномонтаж.");
$APPLICATION->SetTitle("ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге"); ?>

<!-- HERO -->
<div class="hero">
    <div class="hero__content hero__content--brand">
        <h1 class="hero__title">Автозапчасти<br><span>для иномарок и ВАЗ</span><br>в Елабуге</h1>
        <p class="hero__subtitle">Оригинальные запчасти, масла, фильтры, шины и диски от ведущих производителей. Собственный автосервис и шиномонтаж. Доставка по городу.</p>
        <div class="hero__buttons">
            <a href="/catalog/" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-box"></use></svg> Перейти в каталог</a>
            <a href="/about/" class="btn btn--white btn--lg">О магазине</a>
        </div>
    </div>
    <div class="hero__image hero__image--photo hero__image--split">
        <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/store-neftyanikov.webp" alt="Магазин ЛИДЕР на пр-те Нефтяников, 4 в Елабуге">
        <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/store-urmanche.webp" alt="Магазин ЛИДЕР на ул. Баки Урманче, 17а в Елабуге">
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

<!-- АВТОСЕРВИС -->
<div class="hero mt-20">
    <div class="hero__content">
        <h2 class="hero__title"><svg class="icon"><use href="#icon-wrench"></use></svg> Автосервис <span>и шиномонтаж</span></h2>
        <p class="hero__subtitle">Замена масла, ремонт ходовой, диагностика, шиномонтаж. Купил — поставил с гарантией. Собственный шинный центр «Колеса Даром».</p>
        <div class="hero__buttons"><a href="/autoservice/" class="btn btn--primary">Подробнее об услугах</a></div>
    </div>
    <div class="hero__image hero__image--bg" style="background-image:url('<?= SITE_TEMPLATE_PATH ?>/assets/images/autoservice-shop.webp');" role="img" aria-label="Автосервис и шиномонтаж ЛИДЕР в Елабуге"></div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
