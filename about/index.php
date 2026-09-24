<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Магазин автозапчастей «ЛИДЕР» в Елабуге: более 30 000 наименований деталей для ВАЗ и иномарок, автосервис, продажа шин и дисков «Колёса даром», шиномонтаж.");
$APPLICATION->SetPageProperty("title", "О магазине автозапчастей ЛИДЕР в Елабуге");
$APPLICATION->SetTitle("О магазине");

require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$shops = getShopLocations();
$stoPhones = function_exists('getAutoservicePhones') ? getAutoservicePhones() : [];

// Поставщики и бренды, официальным продавцом продукции которых является магазин.
$brands = ['Gates', 'Miles', 'Mann', 'Shell', 'Mobil', 'Adinol', 'Castrol', 'G-Energy', 'ZIC', 'Лукойл', 'Роснефть', 'Газпром'];

// Ассортимент для ВАЗ.
$vazLineup = ['Аккумуляторы', 'Оптика', 'Автомасла', 'Охлаждающие и тормозные жидкости', 'Автосвет', 'Автохимия и аксессуары', 'Шины и диски', 'Инструменты'];

// Почему стоит заказывать запчасти для иномарок именно здесь.
$foreignAdvantages = [
    'Более 20 поставщиков — оптимальные цены и сроки поставки',
    'Сотрудничаем только с официальными дистрибьюторами производителей — оригинальные запчасти и гарантия от производителей',
    'Используем оригинальные каталоги подбора запчастей — минимизируем возможность ошибки подбора',
    'Срок поставки запчастей на заказ — от 4 часов',
    'Квалифицированный персонал подберёт нужную деталь и проконсультирует',
    'Гарантия от производителя',
];

// Услуги автотехцентра «ЛИДЕР».
$serviceList = [
    'Плановое техническое обслуживание',
    'Замена масла в двигателе, в трансмиссии, замена фильтров',
    'Замена тормозных колодок и профилактика тормозной системы',
    'Промывка топливной системы',
    'Шиномонтаж, балансировка на современном 3D-стенде',
    'Мелкосрочный ремонт автомобиля',
    'Заправка кондиционера',
    'Замена комплектов ГРМ',
    'Ремонт подвески',
    'Ремонт стёкол',
];

// Фотографии магазина, автосервиса и шиномонтажа. Чтобы добавить свои —
// положите файлы в /local/templates/lider_modern/assets/images/about/ и
// впишите их сюда; пока список пуст, вместо фото выводятся плейсхолдеры.
$galleryPath = SITE_TEMPLATE_PATH . '/assets/images/about/';
$galleryPhotos = [
    // ['file' => 'about-1.jpg', 'caption' => 'Торговый зал магазина «ЛИДЕР»'],
];
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li>О магазине</li>
    </ul>
</div>

<div class="container">
    <div class="hero" style="margin-bottom:24px;">
        <div class="hero__content">
            <h1 class="hero__title"><svg class="icon"><use href="#icon-store"></use></svg> О магазине <span>«ЛИДЕР»</span></h1>
            <p class="hero__subtitle">Широкий выбор автозапчастей на ВАЗ и иномарки, оперативный заказ и доставка, собственный автосервис и шиномонтаж «Колёса даром» — всё в двух магазинах в Елабуге.</p>
            <div class="hero__buttons">
                <a href="tel:<?= htmlspecialchars($shops[0]['phones'][0]['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($shops[0]['phones'][0]['display']) ?></a>
                <a href="/catalog/" class="btn btn--white btn--lg">Перейти в каталог</a>
            </div>
        </div>
        <div class="hero__image hero__image--photo hero__image--split">
            <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/store-neftyanikov.webp" alt="Магазин ЛИДЕР на пр-те Нефтяников, 4 в Елабуге">
            <img src="<?= SITE_TEMPLATE_PATH ?>/assets/images/store-urmanche.webp" alt="Магазин ЛИДЕР на ул. Баки Урманче, 17а в Елабуге">
        </div>
    </div>

    <div class="service-detail">
        <div class="service-detail__text">
            <p>Магазин автозапчастей «ЛИДЕР» в Елабуге открылся 19 июля 2014 года. На сегодняшний день это крупнейшая сертифицированная точка продаж ведущих производителей автозапчастей, расходных материалов, моторных и трансмиссионных масел в городе.</p>
            <p>Мы — сертифицированная точка продаж продукции Gates, Miles, Mann, Shell, Mobil, Adinol, Castrol, G-Energy, ZIC, Лукойл, Роснефть, Газпром и других производителей. Для удобства покупателей ежедневно работают два магазина в Елабуге.</p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 24px;">
                <?php foreach ($brands as $brand): ?>
                <span style="display:inline-flex;align-items:center;padding:6px 14px;background:var(--bg);border:1px solid var(--border);border-radius:20px;font-size:13px;font-weight:700;color:var(--black);"><?= htmlspecialchars($brand) ?></span>
                <?php endforeach; ?>
            </div>

            <h2><svg class="icon"><use href="#icon-car"></use></svg> Автозапчасти для ВАЗ</h2>
            <p>Большой ассортимент автозапчастей для автомобилей ВАЗ — в наличии более 20 000 наименований деталей:</p>
            <ul>
                <?php foreach ($vazLineup as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Магазин автозапчастей «ЛИДЕР» — официальный субдилер «LADA-Деталь» и «LECAR STORE». В каждом фирменном магазине владельцы автомобилей LADA могут приобрести необходимую запчасть, не сомневаясь в её оригинальности и качестве, а также получить техническую консультацию.</p>

            <h2><svg class="icon"><use href="#icon-truck"></use></svg> Автозапчасти для иномарок</h2>
            <p>В наличии более 10 000 наименований деталей для иномарок. Ежедневно, без выходных, работает стол заказов.</p>
            <ul>
                <?php foreach ($foreignAdvantages as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Магазин автозапчастей «ЛИДЕР» активно развивается и плодотворно сотрудничает как с физическими, так и с юридическими лицами. 19 июля 2019 года открылся второй магазин — на ул. Баки Урманче, 17а. Мы обеспечиваем высокое качество обслуживания клиентов и делаем их жизнь комфортнее.</p>
        </div>
    </div>

    <div class="section-header">
        <h2 class="section-title"><svg class="icon"><use href="#icon-pin"></use></svg> Наши магазины</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:20px;">
        <?php foreach ($shops as $shop): ?>
        <div class="contacts-store">
            <div class="contacts-store__name"><svg class="icon"><use href="#icon-store"></use></svg> <?= htmlspecialchars($shop['short']) ?></div>
            <div class="contacts-store__address"><svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['address']) ?></div>
            <?php if (!empty($shop['hours'])): ?>
            <div class="contacts-store__hours"><svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?></div>
            <?php endif; ?>
            <div class="contacts-store__phones">
                <?php foreach ($shop['phones'] as $phone): ?>
                <a href="tel:<?= htmlspecialchars($phone['tel']) ?>" class="contacts-store__phone">
                    <svg class="icon"><use href="#icon-phone"></use></svg>
                    <b><?= htmlspecialchars($phone['display']) ?></b>
                    <span><?= htmlspecialchars($phone['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <a href="/shop/<?= htmlspecialchars($shop['id']) ?>/" class="contacts-store__more">Подробнее о магазине →</a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="service-detail">
        <h2 class="section-title" style="margin-bottom:16px;"><svg class="icon"><use href="#icon-wrench"></use></svg> Услуги автосервиса</h2>
        <div class="service-detail__text">
            <p>Автотехцентр «ЛИДЕР» осуществляет комплекс мероприятий по техобслуживанию, текущему и восстановительному ремонту автотранспорта, а также предлагает коммерческим организациям взаимовыгодное сотрудничество по обслуживанию и ремонту автомобилей организаций и автомобилей их сотрудников.</p>
            <ul>
                <?php foreach ($serviceList as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>На все проведённые работы предоставляется гарантия.</p>
        </div>
        <div class="service-detail__cta">
            <?php foreach ($stoPhones as $p): ?>
            <a href="tel:<?= htmlspecialchars($p['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($p['display']) ?></a>
            <?php endforeach; ?>
            <a href="/autoservice/" class="btn btn--outline btn--lg">Все услуги автосервиса →</a>
        </div>
    </div>

    <div class="service-detail">
        <h2 class="section-title" style="margin-bottom:16px;"><svg class="icon"><use href="#icon-tire"></use></svg> Шины, диски и шиномонтаж</h2>
        <div class="service-detail__text">
            <p>Собственный шинный центр компании входит в сеть «Колёса даром» — одного из ведущих дистрибьюторов автошин, мотошин и колёсных дисков в России. В ассортименте более 4 000 моделей шин и более 35 000 предложений дисков.</p>
            <p>У нас есть собственные торговые и складские площади, а также сервисная зона шиномонтажа с современным оборудованием и высококвалифицированным персоналом. Также предоставляется услуга сезонного хранения колёс.</p>
        </div>
        <div class="service-detail__cta">
            <a href="/shinomontazh/" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-tire"></use></svg> Шиномонтаж и шины →</a>
        </div>
    </div>

    <div style="margin:24px 0 40px;">
        <div class="section-header">
            <h2 class="section-title"><svg class="icon"><use href="#icon-box"></use></svg> Фото магазина</h2>
        </div>
        <div class="photo-gallery">
            <?php if ($galleryPhotos): ?>
                <?php foreach ($galleryPhotos as $photo): ?>
                <a href="<?= $galleryPath . $photo['file'] ?>" class="photo-gallery__item" data-caption="<?= htmlspecialchars($photo['caption']) ?>">
                    <img src="<?= $galleryPath . $photo['file'] ?>" alt="<?= htmlspecialchars($photo['caption']) ?>" loading="lazy">
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="photo-gallery__placeholder"><svg class="icon"><use href="#icon-box"></use></svg><span>Фото скоро появятся</span></div>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="auto-finder">
        <div class="auto-finder__title">Есть вопросы или нужна консультация?</div>
        <div class="auto-finder__subtitle">Позвоните нам или загляните в один из магазинов — поможем подобрать деталь и проконсультируем бесплатно.</div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
            <a href="tel:<?= htmlspecialchars($shops[0]['phones'][0]['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($shops[0]['phones'][0]['display']) ?></a>
            <a href="/contacts/" class="btn btn--outline btn--lg">Все контакты и как добраться →</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
