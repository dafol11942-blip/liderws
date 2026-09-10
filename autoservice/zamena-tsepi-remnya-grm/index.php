<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Замена цепи и ремня ГРМ в Елабуге — автосервис ЛИДЕР.");
$APPLICATION->SetPageProperty("title", "Замена цепи/ремня ГРМ в Елабуге | ЛИДЕР");
$APPLICATION->SetTitle("Замена цепи/ремня ГРМ");
require_once $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/shop_locations.php";
$stoPhones = getAutoservicePhones();
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/autoservice/">Автосервис</a></li>
        <li>Замена цепи/ремня ГРМ</li>
    </ul>
</div>

<div class="container">
    <a href="/autoservice/" class="service-detail__back">← Ко всем услугам</a>

    <div class="service-detail">
        <img src="/upload/iblock/e11/qtqzt0981uhl1cc6zhyi3c1sonp2t7ta.png" alt="Замена цепи/ремня ГРМ" class="service-detail__img">
        <h1 class="service-detail__title">Замена цепи/ремня ГРМ</h1>
        <div class="service-detail__text">
            <p>Газораспределительный механизм в современных автомобилях в основном приводится в движение либо посредством цепи, либо за счёт зубчатого ремня. В зависимости от регламента завода-изготовителя, рекомендуется менять ремень механизма газораспределения через каждые 60 000 – 80 000 км пробега. Для цепи ГРМ регламенты не так строги, в основном всё зависит от её состояния, которое необходимо регулярно проверять ближе к 100 000 км пробега, а опытные специалисты рекомендуют не дожидаться, когда цепь окончательно растянется, и всё же заменить её при 120 000 – 150 000 км пробега.</p>
            <p>В большинстве случаев при замене привода ГРМ необходимо использовать спецынструмент для фиксации распредвалов и контроля меток. При замене также нужно помнить, что изнашиваются не только ремень, но и ролики, а в случае с цепью — натяжители, успокоители и звёздочки, их тоже рекомендуют сразу заменить.</p>
            <p>В нашем магазине можно купить не только ремни ГРМ, но и ремонтные комплекты, включающие в себя ролики и одноразовый крепёж. Автосервис оснащён всем необходимым оборудованием для замены привода ГРМ, а мастера обладают необходимым опытом для проведения столь сложной и ответственной операции.</p>
        </div>
        <div class="service-detail__cta">
            <?php foreach ($stoPhones as $p): ?><a href="tel:<?= htmlspecialchars($p['tel']) ?>" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> <?= htmlspecialchars($p['display']) ?></a> <?php endforeach; ?>
            <span style="color:var(--gray);font-size:13px;">Елабуга, пр-т Нефтяников, 4 · Пн-Вс: 8:00–19:00</span>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
