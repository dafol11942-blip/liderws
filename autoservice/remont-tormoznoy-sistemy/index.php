<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Ремонт и обслуживание тормозной системы, замена колодок в Елабуге — автосервис ЛИДЕР.");
$APPLICATION->SetPageProperty("title", "Ремонт и обслуживание тормозной системы в Елабуге | ЛИДЕР");
$APPLICATION->SetTitle("Ремонт и обслуживание тормозной системы");
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$stoPhones = getAutoservicePhones();
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/autoservice/">Автосервис</a></li>
        <li>Ремонт и обслуживание тормозной системы</li>
    </ul>
</div>

<div class="container">
    <a href="/autoservice/" class="service-detail__back">← Ко всем услугам</a>

    <div class="service-detail">
        <img src="/upload/iblock/a30/ekra3wra95748nsi8501mq7xi6au0d8y.png" alt="Ремонт и обслуживание тормозной системы" class="service-detail__img">
        <h1 class="service-detail__title">Ремонт и обслуживание тормозной системы</h1>
        <div class="service-detail__text">
            <p>Замена колодок и обслуживание тормозной системы в Елабуге. Тормозная система — одна из систем автомобиля, обеспечивающая вашу безопасность при эксплуатации транспортного средства.</p>
            <p>К отказу могут привести множество факторов, чтобы этого избежать, рекомендуется регулярно (каждые 10 000–15 000 км пробега или раз в год) проверять уровень и плотность тормозной жидкости, состояние магистралей, равномерную работоспособность тормозных механизмов.</p>
            <p>Каждые 2 года рекомендуется заменять тормозную жидкость. Тормозные колодки меняются в зависимости от степени износа. При замене колодок рекомендуют провести профилактику тормозных механизмов, обработать специальной высокотемпературной смазкой, при необходимости — заменить изношенные детали.</p>
            <p><strong>Работы, проводимые при обслуживании тормозной системы автомобиля:</strong></p>
            <ul style="margin:0 0 14px 20px;list-style:disc;">
                <li>Замена тормозных колодок</li>
                <li>Замена тормозной жидкости</li>
                <li>Прокачка тормозов</li>
                <li>Профилактика и смазка тормозных механизмов</li>
                <li>Осмотр состояния тормозных трубок, шлангов и мест их соединения</li>
            </ul>
        </div>
        <div class="service-detail__cta">
            <?php foreach ($stoPhones as $p): ?><a href="tel:<?= htmlspecialchars($p['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($p['display']) ?></a> <?php endforeach; ?>
            <span style="color:var(--gray);font-size:13px;">Елабуга, пр-т Нефтяников, 4 · Пн-Вс: 8:00–19:00</span>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
