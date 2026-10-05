<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге | Елабуга");
$APPLICATION->SetPageProperty("description", "Магазин автозапчастей ЛИДЕР в Елабуге: детали для иномарок и ВАЗ, масла, фильтры, тормозные колодки, шины и диски. Собственный автосервис и шиномонтаж.");
$APPLICATION->SetTitle("ЛИДЕР — автозапчасти для иномарок и ВАЗ в Елабуге"); ?>
<div class="container">

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

<?php
// Ключевые факты и FAQ — короткие проверяемые утверждения о магазине: их
// цитируют быстрые ответы поисковиков и генеративные модели (см. также /llms.txt).
// Цифры и условия — те же, что на страницах «О магазине», «Оплата», «Доставка».
\Lider\Seo\Seo::addShops();
$homeShops = getShopLocations();
$homeShopList = implode(' и ', array_map(function ($s) {
    return '<a href="/shop/' . htmlspecialchars($s['id']) . '/">' . htmlspecialchars($s['short']) . '</a>';
}, $homeShops));
?>
<div class="facts">
    <div class="facts__item"><div class="facts__num">с 2014</div><div class="facts__label">года продаём автозапчасти в Елабуге</div></div>
    <div class="facts__item"><div class="facts__num">30 000+</div><div class="facts__label">наименований: более 20 000 для ВАЗ и 10 000 для иномарок</div></div>
    <div class="facts__item"><div class="facts__num">от 4 часов</div><div class="facts__label">срок поставки запчастей под заказ от 20+ поставщиков</div></div>
    <div class="facts__item"><div class="facts__num">2 магазина</div><div class="facts__label">ежедневно 8:00–19:00, без выходных</div></div>
</div>

<section class="home-about">
    <h2 class="section-title">Магазин автозапчастей ЛИДЕР в Елабуге</h2>
    <p>ЛИДЕР — магазин автозапчастей и автотехцентр в Елабуге (Республика Татарстан), работает с 19 июля 2014 года. В двух магазинах — на <?= $homeShopList ?> — в наличии запчасти для ВАЗ (LADA) и иномарок, моторные и трансмиссионные масла, фильтры, тормозные колодки, аккумуляторы, автохимия и аксессуары.</p>
    <p>Магазин — официальный субдилер «LADA-Деталь» и «LECAR STORE» и сертифицированная точка продаж Gates, Miles, Mann, Shell, Mobil, Castrol, ZIC, G-Energy, Лукойл, Роснефть и Газпром. Запчасти подбираем по VIN по оригинальным каталогам, рядом с магазином на пр-те Нефтяников, 4 работает собственный автосервис, а шиномонтаж и продажа шин — в шинном центре «Колёса Даром».</p>
</section>

<?= \Lider\Seo\Seo::faq([
    ['Где находятся магазины ЛИДЕР и как они работают?',
        'Два магазина в Елабуге: ' . $homeShopList . '. Оба работают ежедневно с 8:00 до 19:00. Телефоны отделов ВАЗ и иномарок — на странице <a href="/contacts/">«Контакты»</a>.'],
    ['Можно ли подобрать запчасти по VIN-номеру?',
        'Да. На странице <a href="/podbor-po-vin/">«Подбор по VIN»</a> можно найти деталь в оригинальном каталоге по VIN или Frame-номеру, а запчасти для планового ТО — по марке и модели в разделе <a href="/service-parts/">«Запчасти для ТО»</a>. Продавцы также подберут деталь по телефону или в магазине.'],
    ['Сколько ждать запчасти под заказ?',
        'Запчасти, которых нет в наличии, заказываем у более чем 20 поставщиков — официальных дистрибьюторов производителей. Срок поставки — от 4 часов.'],
    ['Продаёте ли вы оригинальные запчасти LADA?',
        'Да. ЛИДЕР — официальный субдилер «LADA-Деталь» и «LECAR STORE», в наличии более 20 000 наименований деталей для автомобилей ВАЗ.'],
    ['Какие способы оплаты доступны?',
        'Наличными в магазине или курьеру, банковской картой МИР, Visa, Mastercard и JCB (онлайн через платёжный шлюз ПАО Сбербанк), для юридических лиц — безналичный расчёт по счёту. Подробнее — на странице <a href="/klientam/oplata/">«Оплата»</a>.'],
    ['Есть ли доставка?',
        'Самовывоз из магазина бесплатный. Курьерскую доставку по Елабуге и отправку по России через службу доставки согласовывает менеджер магазина. Подробнее — на странице <a href="/klientam/delivery/">«Доставка»</a>.'],
    ['Можно ли вернуть товар?',
        'Товар надлежащего качества можно вернуть в течение 30 дней с момента получения. Условия гарантии и возврата — на странице <a href="/klientam/garantiya-i-vozvrat/">«Гарантия и возврат»</a>.'],
    ['Можно ли установить купленные запчасти у вас?',
        'Да. Автосервис ЛИДЕР на пр-те Нефтяников, 4 выполняет ТО, замену масла и фильтров, ремонт тормозной системы и подвески, замену ремня и цепи ГРМ; на все работы даётся гарантия. Подробнее — в разделе <a href="/autoservice/">«Автосервис»</a>.'],
]) ?>
</div><!-- /.container -->

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
