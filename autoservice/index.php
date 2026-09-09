<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Автосервис в Елабуге: диагностика и ремонт подвески, замена масла, ТО, ремонт тормозной системы, замена ГРМ и фильтров. Автотехцентр ЛИДЕР.");
$APPLICATION->SetPageProperty("title", "Автосервис в Елабуге — ремонт и обслуживание автомобилей | ЛИДЕР");
$APPLICATION->SetTitle("Автосервис");

$services = [
    [
        'code' => 'diagnostika-i-remont-podveski',
        'name' => 'Диагностика и ремонт подвески',
        'preview' => 'Диагностика и ремонт ходовой (подвески) в Елабуге. Как только выходят из строя детали подвески, возникает дискомфорт при управлении автомобилем, снижается безопасность, появляются посторонние скрипы и стуки при движении.',
        'img' => '/upload/iblock/c15/55o9kfmns6hpose114ubyvfx6elciado.png',
    ],
    [
        'code' => 'zamena-masla-v-dvigatele',
        'name' => 'Замена масла в двигателе',
        'preview' => 'Вовремя произведённая замена масла продлит срок службы двигателя вашего автомобиля. Качественное масло снижает трение деталей и обладает набором присадок, уменьшающим износ.',
        'img' => '/upload/iblock/987/gcbo0arm0me1zrw2qmt7wuxrxl7zwbe1.png',
    ],
    [
        'code' => 'tekhnicheskoe-obsluzhivanie',
        'name' => 'Техническое обслуживание и мелкосрочный ремонт',
        'preview' => 'Помимо замены масла, регулярно необходимо проводить техническое обслуживание автомобиля. От исправной и бесперебойной работы всех узлов и агрегатов зависит ваша безопасность и комфорт при эксплуатации.',
        'img' => '/upload/iblock/c54/2gx1l9wna51tgn52w5uhzymu69a8mki0.png',
    ],
    [
        'code' => 'remont-tormoznoy-sistemy',
        'name' => 'Ремонт и обслуживание тормозной системы',
        'preview' => 'Замена колодок и обслуживание тормозной системы в Елабуге. Тормозная система — одна из систем автомобиля, обеспечивающая вашу безопасность при эксплуатации транспортного средства.',
        'img' => '/upload/iblock/514/isyk0567knbp1iundr99v5es0ua5qe2k.png',
    ],
    [
        'code' => 'zamena-tsepi-remnya-grm',
        'name' => 'Замена цепи/ремня ГРМ',
        'preview' => 'Газораспределительный механизм в современных автомобилях приводится в движение либо посредством цепи, либо за счёт зубчатого ремня. Рекомендуется менять ремень ГРМ через каждые 60 000 – 80 000 км пробега.',
        'img' => '/upload/iblock/595/9nvqdxbfyqs1ctwetm8hxq57qi8b3d87.png',
    ],
    [
        'code' => 'zamena-masla-v-transmissii',
        'name' => 'Замена масла в автоматической и механической трансмиссиях',
        'preview' => 'Замена масла в механике (МКПП) и автомате (АКПП) в Елабуге. Масло в коробке передач служит для снижения трения между шестернями и уменьшения износа. Рекомендуется менять каждые 60 000 – 70 000 км.',
        'img' => '/upload/iblock/ca1/ntx0a5e49hzkxikpn91evb5fclkciftk.jpg',
    ],
    [
        'code' => 'zamena-filtrov',
        'name' => 'Замена фильтров',
        'preview' => 'Замена фильтров в городе Елабуга. Регулярная замена фильтров важна для стабильной работы автомобиля и увеличения срока его эксплуатации. Периодичность замены зависит от типа устройства и эксплуатационных условий.',
        'img' => '/upload/iblock/2e5/aftngparramevv3hhyn19k29wie1402y.jpg',
    ],
];
?>
<div class="breadcrumbs container">
    <ul>
        <li><a href="/">Главная</a></li>
        <li>Автосервис</li>
    </ul>
</div>

<div class="container">
    <div class="hero" style="margin-bottom:24px;">
        <div class="hero__content">
            <h1 class="hero__title"><svg class="icon"><use href="#icon-wrench"></use></svg> Автосервис <span>в Елабуге</span></h1>
            <p class="hero__subtitle">Диагностика, ремонт и техническое обслуживание автомобилей любых марок. Работаем на своём оборудовании, запчасти и расходники — из соседнего магазина ЛИДЕР, без ожидания доставки.</p>
            <div class="hero__buttons">
                <a href="tel:+78000000000" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> 8-800-000-00-00</a>
                <a href="#services" class="btn btn--white btn--lg">Все услуги</a>
            </div>
        </div>
        <div class="hero__image" style="background:var(--bg-dark);display:flex;align-items:center;justify-content:center;color:var(--blue);">
            <svg class="icon" style="width:100px;height:100px;"><use href="#icon-wrench"></use></svg>
        </div>
    </div>

    <div class="section-header" id="services">
        <h2 class="section-title"><svg class="icon"><use href="#icon-settings"></use></svg> Услуги автосервиса</h2>
    </div>

    <div class="service-list">
        <?php foreach ($services as $s): ?>
        <div class="service-item">
            <a href="/autoservice/<?= $s['code'] ?>/" class="service-item__img">
                <img src="<?= $s['img'] ?>" alt="<?= htmlspecialchars($s['name']) ?>" loading="lazy">
            </a>
            <div class="service-item__body">
                <h3 class="service-item__title"><a href="/autoservice/<?= $s['code'] ?>/"><?= htmlspecialchars($s['name']) ?></a></h3>
                <p class="service-item__text"><?= htmlspecialchars($s['preview']) ?></p>
                <a href="/autoservice/<?= $s['code'] ?>/" class="service-item__more">Подробнее →</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="auto-finder" style="text-align:left;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;">
            <div>
                <div class="auto-finder__title">Запишитесь на автосервис</div>
                <div class="auto-finder__subtitle">Елабуга, пр-т Нефтяников, 4 · Пн-Вс: 9:00–20:00</div>
            </div>
            <a href="tel:+78000000000" class="btn btn--primary btn--lg"><svg class="icon"><use href="#icon-phone"></use></svg> Позвонить: 8-800-000-00-00</a>
        </div>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
