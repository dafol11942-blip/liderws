<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Контакты автотехцентра ЛИДЕР в Елабуге: адреса, телефоны отделов ВАЗ и Иномарки, график работы магазинов и карта проезда.");
$APPLICATION->SetPageProperty("title", "Контакты — автотехцентр ЛИДЕР в Елабуге");
$APPLICATION->SetTitle("Контакты");

require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$shops = getShopLocations();
$yandexMapsApiKey = function_exists('getYandexMapsApiKey') ? getYandexMapsApiKey() : '';
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li>Контакты</li>
    </ul>
</div>

<div class="container">
    <div class="section-header">
        <h1 class="section-title"><svg class="icon"><use href="#icon-pin"></use></svg> Контакты</h1>
    </div>

    <div class="contacts-grid">
        <?php foreach ($shops as $shop): ?>
        <div class="contacts-card" data-coords="<?= htmlspecialchars(json_encode($shop['coords']), ENT_QUOTES) ?>">
            <div class="contacts-card__title"><svg class="icon"><use href="#icon-store"></use></svg> Магазин: <?= htmlspecialchars($shop['short']) ?></div>
            <div class="contacts-card__address"><svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['address']) ?></div>
            <?php if (!empty($shop['hours'])): ?>
            <div class="contacts-card__hours"><svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?></div>
            <?php endif; ?>
            <div class="contacts-card__phones">
                <?php foreach ($shop['phones'] as $phone): ?>
                <a href="tel:<?= htmlspecialchars($phone['tel']) ?>" class="contacts-card__phone">
                    <svg class="icon"><use href="#icon-phone"></use></svg>
                    <span><b><?= htmlspecialchars($phone['display']) ?></b> — <?= htmlspecialchars($phone['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-header">
        <h2 class="section-title"><svg class="icon"><use href="#icon-pin"></use></svg> Как нас найти</h2>
    </div>

    <?php if ($yandexMapsApiKey !== ''): ?>
    <div class="contacts-map" id="contacts-map"
         data-points='<?= htmlspecialchars(json_encode(array_map(function ($shop) {
             return ['name' => $shop['short'], 'address' => $shop['address'], 'coords' => $shop['coords']];
         }, $shops)), ENT_QUOTES) ?>'></div>
    <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= urlencode($yandexMapsApiKey) ?>&lang=ru_RU"></script>
    <script>
    (function () {
        var mapEl = document.getElementById('contacts-map');
        if (!mapEl || typeof ymaps === 'undefined') return;
        var points = JSON.parse(mapEl.getAttribute('data-points') || '[]');
        ymaps.ready(function () {
            var map = new ymaps.Map(mapEl, { center: [55.76, 52.02], zoom: 12 });
            var placemarks = points.map(function (point) {
                var placemark = new ymaps.Placemark(point.coords, {
                    balloonContentHeader: point.name,
                    balloonContentBody: point.address
                }, { preset: 'islands#blueDotIcon' });
                map.geoObjects.add(placemark);
                return placemark;
            });
            if (placemarks.length) map.setBounds(map.geoObjects.getBounds(), { checkZoomRange: true });

            document.querySelectorAll('.contacts-card[data-coords]').forEach(function (card, i) {
                card.addEventListener('click', function (e) {
                    if (e.target.closest('a')) return;
                    var placemark = placemarks[i];
                    if (!placemark) return;
                    map.setCenter(placemark.geometry.getCoordinates(), 16, { duration: 300 })
                        .then(function () { placemark.balloon.open(); });
                });
            });
        });
    })();
    </script>
    <?php else: ?>
    <div class="contacts-map-fallback">
        <?php foreach ($shops as $shop): ?>
        <a href="https://yandex.ru/maps/?text=<?= urlencode($shop['address']) ?>" target="_blank" rel="noopener" class="contacts-map-fallback__link">
            <svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['short']) ?> — открыть на карте
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
