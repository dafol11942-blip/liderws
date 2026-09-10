<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Замена фильтров (воздушного, масляного, топливного, салонного) в Елабуге — автосервис ЛИДЕР.");
$APPLICATION->SetPageProperty("title", "Замена фильтров в Елабуге | ЛИДЕР");
$APPLICATION->SetTitle("Замена фильтров");
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$stoPhones = getAutoservicePhones();
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/autoservice/">Автосервис</a></li>
        <li>Замена фильтров</li>
    </ul>
</div>

<div class="container">
    <a href="/autoservice/" class="service-detail__back">← Ко всем услугам</a>

    <div class="service-detail">
        <img src="/upload/iblock/0a0/amku9ea0hd4cvoy15vdu73ja0l0zok2u.jpg" alt="Замена фильтров" class="service-detail__img">
        <h1 class="service-detail__title">Замена фильтров</h1>
        <div class="service-detail__text">
            <p>Замена фильтров в городе Елабуга. Регулярная замена фильтров важна для стабильной работы автомобиля и увеличения срока его эксплуатации. Периодичность замены зависит от типа устройства и эксплуатационных условий.</p>
            <p>Воздушный фильтр следует менять каждые 10 000 км пробега.</p>
            <p>Установка нового масляного фильтра проводится вместе с заменой масла каждые 15 000 км. При регулярной эксплуатации авто в городских условиях эта цифра снижается до 10 000 км.</p>
            <p>Смена топливного фильтра проводится один раз на 20 000 км. Это число может быть сокращено до 8 или 10 тысяч км, в зависимости от условий эксплуатации авто.</p>
        </div>
        <div class="service-detail__cta">
            <?php foreach ($stoPhones as $p): ?><a href="tel:<?= htmlspecialchars($p['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($p['display']) ?></a> <?php endforeach; ?>
            <span style="color:var(--gray);font-size:13px;">Елабуга, пр-т Нефтяников, 4 · Пн-Вс: 8:00–19:00</span>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
