<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Шиномонтаж в Елабуге от «Колёса Даром»: легковой и мотошиномонтаж, шиномонтаж на лёгких грузовиках, сезонное хранение, ремонт шин и дисков, балансировка, ошиповка.");
$APPLICATION->SetPageProperty("title", "Шиномонтаж в Елабуге — «Колёса Даром» | ЛИДЕР");
$APPLICATION->SetTitle("Шиномонтаж");

$stoPhone = ['tel' => '+79872385111', 'display' => '+7 (987) 238-51-11'];
$bookingUrl = 'https://elabuga.kolesa-darom.ru/service/shinomontazh/?utm_referrer=https%3A%2F%2Fwww.kolesa-darom.ru%2F';
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li>Шиномонтаж</li>
    </ul>
</div>

<div class="container">
    <div class="hero" style="margin-bottom:24px;">
        <div class="hero__content">
            <h1 class="hero__title"><svg class="icon"><use href="#icon-tire"></use></svg> Шиномонтаж <span>в Елабуге</span></h1>
            <p class="hero__subtitle">Подбор и покупка шин и дисков по лучшей цене, а также полный комплекс шиномонтажных услуг от «Колёса Даром».</p>
            <div class="hero__buttons">
                <a href="tel:<?= htmlspecialchars($stoPhone['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($stoPhone['display']) ?></a>
                <a href="<?= htmlspecialchars($bookingUrl) ?>" class="btn btn--white btn--lg" target="_blank" rel="noopener">Записаться онлайн</a>
            </div>
        </div>
        <div class="hero__image" style="background:var(--bg-dark);display:flex;align-items:center;justify-content:center;color:var(--blue);">
            <svg class="icon" style="width:100px;height:100px;"><use href="#icon-tire"></use></svg>
        </div>
    </div>

    <div class="service-detail">
        <div class="service-detail__text">
            <p>В магазине «Колёса Даром» вы не только подберёте и купите шины и диски по лучшей цене, но и получите комплекс шиномонтажных услуг. Мы предлагаем:</p>
            <ul>
                <li>легковой шиномонтаж;</li>
                <li>мотошиномонтаж;</li>
                <li>шиномонтаж на лёгких грузовиках;</li>
                <li>сезонное хранение шин;</li>
                <li>ремонт шин и дисков;</li>
                <li>балансировку дисков;</li>
                <li>ошиповку зимних шин.</li>
            </ul>
            <p>Для записи позвоните нам или запишитесь онлайн — кнопки выше. Вы можете связаться с нами любым другим удобным для вас способом: в социальных сетях или по телефону.</p>
            <p class="service-detail__note">Перечень услуг, оказываемых в шинных центрах вашего города, можно уточнить в разделе «Сервис», либо через онлайн-чат на сайте.</p>

            <h2>Когда нужно проводить шиномонтажные работы</h2>
            <p>Точного срока для проведения сезонного шиномонтажа нет, так как работы по смене шин зависят от климатических особенностей каждого региона. Ориентироваться лучше на температурный показатель: летнюю резину ставят, когда температура воздуха в среднем держится не ниже +5°С. Зимнюю — когда перестаёт подниматься выше этого показателя.</p>
            <p>Шиномонтаж может понадобиться и в случае:</p>
            <ul>
                <li>ремонта проколов или порезов на шинах;</li>
                <li>нарушения геометрии дисков;</li>
                <li>нарушения баланса диска;</li>
                <li>снижения давления в шинах;</li>
                <li>потери шипов в шипованных шинах.</li>
            </ul>
            <p>В «Колёса Даром» вы получаете всё в одном месте по выгодной для вас цене.</p>
        </div>
        <div class="service-detail__cta">
            <a href="tel:<?= htmlspecialchars($stoPhone['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($stoPhone['display']) ?></a>
            <a href="<?= htmlspecialchars($bookingUrl) ?>" class="btn btn--outline btn--lg" target="_blank" rel="noopener">Онлайн-запись на шиномонтаж →</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
