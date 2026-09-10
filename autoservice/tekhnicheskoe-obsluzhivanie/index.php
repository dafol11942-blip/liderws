<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Техническое обслуживание и мелкосрочный ремонт автомобиля в Елабуге — автосервис ЛИДЕР.");
$APPLICATION->SetPageProperty("title", "Техническое обслуживание и мелкосрочный ремонт в Елабуге | ЛИДЕР");
$APPLICATION->SetTitle("Техническое обслуживание и мелкосрочный ремонт");
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$stoPhones = getAutoservicePhones();
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/autoservice/">Автосервис</a></li>
        <li>Техническое обслуживание и мелкосрочный ремонт</li>
    </ul>
</div>

<div class="container">
    <a href="/autoservice/" class="service-detail__back">← Ко всем услугам</a>

    <div class="service-detail">
        <img src="/upload/iblock/ee9/6bndc11srzenoudh5249dlrzxbas7saa.png" alt="Техническое обслуживание и мелкосрочный ремонт" class="service-detail__img">
        <h1 class="service-detail__title">Техническое обслуживание и мелкосрочный ремонт</h1>
        <div class="service-detail__text">
            <p>Помимо замены масла, регулярно необходимо проводить техническое обслуживание автомобиля. От исправной и бесперебойной работы всех узлов и агрегатов в автомобиле зависит ваша безопасность и комфорт при его эксплуатации. Поэтому, проводя своевременное техническое обслуживание, вы избавите себя от непредвиденного ремонта.</p>
            <p>Периодической замены требуют как фильтры — воздушный, салонный, топливный, так и технические жидкости — тормозная, антифриз, жидкость в гидроусилителе руля, в редукторах мостов и раздаточной коробке, а также свечи зажигания, высоковольтные провода, лампы наружного освещения, щётки стеклоочистителя. Если лампы и дворники меняются по мере износа, то заменой всего остального пренебрегать не стоит — лучше придерживаться рекомендаций завода-изготовителя.</p>
            <p>Все необходимые детали и расходные материалы, как правило, в наличии в нашем магазине, а специалисты нашего автосервиса всегда готовы провести техническое обслуживание с соблюдением всех норм.</p>
        </div>
        <div class="service-detail__cta">
            <?php foreach ($stoPhones as $p): ?><a href="tel:<?= htmlspecialchars($p['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($p['display']) ?></a> <?php endforeach; ?>
            <span style="color:var(--gray);font-size:13px;">Елабуга, пр-т Нефтяников, 4 · Пн-Вс: 8:00–19:00</span>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
