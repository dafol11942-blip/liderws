<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";

// Маршрутизация — как в /catalog/index.php: один физический файл на все
// адреса вида /shop/<id>/ (см. urlrewrite.php), код магазина — последний
// сегмент URL.
$path = trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = $path !== '' ? explode('/', $path) : [];
$shopId = $segments[1] ?? '';

$shop = null;
foreach (getShopLocations() as $s) {
    if ($s['id'] === $shopId) { $shop = $s; break; }
}

if (!$shop) {
    $APPLICATION->SetPageProperty("title", "Магазин не найден | ЛИДЕР");
    $APPLICATION->SetTitle("Магазин не найден");
    ?>
    <div class="breadcrumbs container">
        <ul>
            <li><a href="/">Главная</a></li>
            <li><a href="/contacts/">Контакты</a></li>
            <li>Магазин не найден</li>
        </ul>
    </div>
    <div class="container" style="padding:40px 0 60px;text-align:center;">
        <h1>Такого магазина нет</h1>
        <p style="color:var(--gray);margin:12px 0 20px;">Возможно, ссылка устарела.</p>
        <a href="/contacts/" class="btn btn--primary">Все магазины и карта →</a>
    </div>
    <?php
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
    return;
}

$APPLICATION->SetPageProperty("description", "Магазин автозапчастей ЛИДЕР: " . $shop['address'] . ". Телефоны, график работы, схема проезда.");
$APPLICATION->SetPageProperty("title", "Магазин " . $shop['short'] . " — автозапчасти ЛИДЕР Елабуга");
$APPLICATION->SetTitle("Магазин: " . $shop['short']);

$yandexMapsApiKey = function_exists('getYandexMapsApiKey') ? getYandexMapsApiKey() : '';
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/contacts/">Контакты</a></li>
        <li><?= htmlspecialchars($shop['short']) ?></li>
    </ul>
</div>

<div class="container">
    <div class="hero" style="margin-bottom:24px;">
        <div class="hero__content">
            <h1 class="hero__title"><svg class="icon"><use href="#icon-store"></use></svg> Магазин <span><?= htmlspecialchars($shop['short']) ?></span></h1>
            <p class="hero__subtitle"><?= htmlspecialchars($shop['address']) ?></p>
            <div class="hero__buttons">
                <?php foreach ($shop['phones'] as $phone): ?>
                <a href="tel:<?= htmlspecialchars($phone['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($phone['display']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hero__image" style="background:var(--bg-dark);display:flex;align-items:center;justify-content:center;color:var(--blue);">
            <svg class="icon" style="width:100px;height:100px;"><use href="#icon-store"></use></svg>
        </div>
    </div>

    <div class="contacts-layout">
        <div class="contacts-sidebar">
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
            </div>
        </div>

        <div class="contacts-map-wrap">
            <?php if ($yandexMapsApiKey !== ''): ?>
            <div class="contacts-map" id="shop-map"
                 data-coords="<?= htmlspecialchars(json_encode($shop['coords']), ENT_QUOTES) ?>"
                 data-name="<?= htmlspecialchars($shop['short']) ?>"
                 data-address="<?= htmlspecialchars($shop['address']) ?>"></div>
            <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= urlencode($yandexMapsApiKey) ?>&lang=ru_RU"></script>
            <script>
            (function () {
                var mapEl = document.getElementById('shop-map');
                if (!mapEl || typeof ymaps === 'undefined') return;
                var coords = [];
                try { coords = JSON.parse(mapEl.getAttribute('data-coords') || '[]'); } catch (e) {}
                if (!coords.length) return;

                ymaps.ready(function () {
                    var map = new ymaps.Map(mapEl, { center: coords, zoom: 16 });
                    var placemark = new ymaps.Placemark(coords, {
                        balloonContentHeader: mapEl.getAttribute('data-name'),
                        balloonContentBody: mapEl.getAttribute('data-address')
                    }, { preset: 'islands#blueDotIcon' });
                    map.geoObjects.add(placemark);
                    placemark.balloon.open();
                });
            })();
            </script>
            <?php else: ?>
            <div class="contacts-map-fallback">
                <a href="https://yandex.ru/maps/?text=<?= urlencode($shop['address']) ?>" target="_blank" rel="noopener" class="contacts-map-fallback__link">
                    <svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['short']) ?> — открыть на карте
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="service-detail">
        <h2 class="section-title" style="margin-bottom:16px;"><svg class="icon"><use href="#icon-list"></use></svg> О магазине</h2>
        <div class="service-detail__text">
            <p style="color:var(--gray);">Описание магазина скоро появится здесь.</p>
        </div>
    </div>

    <div style="margin:24px 0 40px;">
        <h2 class="section-title" style="margin-bottom:16px;"><svg class="icon"><use href="#icon-box"></use></svg> Фото магазина</h2>
        <div class="photo-gallery">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="photo-gallery__placeholder"><svg class="icon"><use href="#icon-box"></use></svg><span>Фото скоро появятся</span></div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
