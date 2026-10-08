<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Доставка и самовывоз — автозапчасти ЛИДЕР в Елабуге");
$APPLICATION->SetPageProperty("description", "Самовывоз из магазинов автозапчастей ЛИДЕР в Елабуге и курьерская доставка Яндекс Доставкой (Экспресс) по городу: стоимость, сроки, оплата, ограничения для товаров под заказ.");
$APPLICATION->SetTitle("Доставка");

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/shop_locations.php';
$shops = getShopLocations();

// Логотип Яндекс Доставки — из настроек службы доставки (файл загружен модулем
// twinpx.yaexpress), а не нарисован вручную: чужой товарный знак "на глаз"
// не воспроизводим. Нет логотипа — подпись текстом.
$yandexLogoSrc = '';
if (CModule::IncludeModule('sale') && function_exists('getYandexExpressDeliveryId')) {
    $yandexDeliveryId = getYandexExpressDeliveryId();
    $yandexDelivery = $yandexDeliveryId > 0 ? \Bitrix\Sale\Delivery\Services\Manager::getById($yandexDeliveryId) : null;
    $yandexLogo = !empty($yandexDelivery['LOGOTIP']) ? CFile::GetFileArray((int)$yandexDelivery['LOGOTIP']) : null;
    $yandexLogoSrc = is_array($yandexLogo) ? (string)($yandexLogo['SRC'] ?? '') : '';
}
?><?= \Lider\Seo\Seo::breadcrumbs([['NAME' => 'Главная', 'LINK' => '/'], ['NAME' => 'Доставка', 'LINK' => '']]) ?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:8px 0 20px;">Доставка и самовывоз</h1>
</div>
<div class="container">
<div class="pay-info delivery-info">

	<p class="pay-info__lead">
		Заказ из интернет-магазина «Лидер» можно забрать самостоятельно в одном из наших магазинов в Елабуге или получить с курьером Яндекс Доставки в день заказа. Способ получения вы выбираете при оформлении заказа; для заказов с товарами под заказ у поставщика доставку курьером можно оформить позже — в личном кабинете, когда товар поступит в магазин.
	</p>

	<div class="pay-info__methods">
		<div class="pay-info__method">
			<div class="delivery-info__logo delivery-info__logo--icon"><svg class="icon"><use href="#icon-store"></use></svg></div>
			<div class="pay-info__method-title">Самовывоз из магазина</div>
			<p><b>Бесплатно.</b> Два магазина в Елабуге, ежедневно с 8:00 до 19:00. Товар из наличия — в день заказа.</p>
		</div>
		<div class="pay-info__method">
			<div class="delivery-info__logo">
				<?php if ($yandexLogoSrc !== ''): ?>
				<img src="<?= htmlspecialchars($yandexLogoSrc) ?>" alt="Яндекс Доставка" title="Яндекс Доставка" loading="lazy">
				<?php else: ?>
				<span class="delivery-info__logo-text">Яндекс Доставка</span>
				<?php endif; ?>
			</div>
			<div class="pay-info__method-title">Курьер Яндекс Доставки</div>
			<p>Экспресс-доставка по Елабуге в день заказа, до двери. Стоимость — по тарифу Яндекс Доставки, рассчитывается по адресу. Только с оплатой картой онлайн.</p>
		</div>
		<div class="pay-info__method">
			<div class="delivery-info__logo delivery-info__logo--icon"><svg class="icon"><use href="#icon-truck"></use></svg></div>
			<div class="pay-info__method-title">В другие города</div>
			<p>Отправка транспортной компанией — по согласованию с менеджером. Стоимость и сроки рассчитываются индивидуально.</p>
		</div>
	</div>

	<div class="delivery-info__shops">
		<?php foreach ($shops as $shop): ?>
		<div class="delivery-info__shop">
			<div class="delivery-info__shop-title"><svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['address']) ?></div>
			<div class="delivery-info__shop-hours"><svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?></div>
			<ul class="delivery-info__shop-phones">
				<?php foreach ($shop['phones'] as $phone): ?>
				<li><a href="tel:<?= htmlspecialchars($phone['tel']) ?>"><?= htmlspecialchars($phone['display']) ?></a> <span><?= htmlspecialchars($phone['label']) ?></span></li>
				<?php endforeach; ?>
			</ul>
			<a class="delivery-info__shop-link" href="/shop/<?= htmlspecialchars($shop['id']) ?>/">Подробнее о магазине и схема проезда</a>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="legal-text pay-info__text">

		<h3>1. Самовывоз</h3>
		<ul>
			<li>Самовывоз бесплатный. Магазин для получения вы выбираете при оформлении заказа: пр-т Нефтяников, 4 или ул. Баки Урманче, 17а. Оба магазина работают ежедневно с 8:00 до 19:00.</li>
			<li><b>Товар из наличия</b> можно забрать в день оформления заказа в часы работы выбранного магазина.</li>
			<li><b>Товар под заказ у поставщика</b> поступает в магазин в срок, указанный в корзине и на странице оформления. Когда заказ будет готов, его статус изменится на «Товар готов к выдаче», и мы пришлём SMS. Приходите после этого сообщения.</li>
			<li>При получении назовите номер заказа. Оплатить заказ из товаров в наличии можно на месте — наличными или картой; заказы с товарами под заказ оплачиваются заранее, картой на сайте (см. <a href="/klientam/oplata/">«Оплата»</a>).</li>
			<li>Пожалуйста, проверьте товар при получении: комплектность, внешний вид, соответствие заказу.</li>
		</ul>

		<h3>2. Курьерская доставка Яндекс Доставкой</h3>
		<ul>
			<li><b>Где работает:</b> по городу Елабуге. Курьер забирает заказ из нашего магазина на пр-те Нефтяников, 4 и везёт его по вашему адресу на легковом автомобиле — до двери.</li>
			<li><b>Стоимость</b> рассчитывается автоматически по тарифу Яндекс Доставки в зависимости от адреса и показывается при оформлении заказа — сразу после того, как вы укажете адрес. Стоимость доставки добавляется к сумме заказа и видна до оплаты.</li>
			<li><b>Сроки:</b> экспресс-доставка в день заказа. Ориентировочное время доставки показывается рядом со стоимостью. Заказ передаётся курьеру после поступления оплаты, в часы работы магазина — с 8:00 до 19:00.</li>
			<li><b>Оплата — только картой онлайн на сайте</b>, вместе со стоимостью доставки. Оплата наличными или картой курьеру при получении для этого способа недоступна. Если при выборе доставки отмечена оплата при получении, оформить заказ не получится — выберите оплату картой.</li>
			<li><b>Адрес:</b> укажите улицу и дом, а также подъезд, этаж и квартиру или офис — так курьер быстрее вас найдёт. Подсказки при вводе помогают указать адрес так, как его понимает Яндекс Доставка.</li>
			<li><b>Получение:</b> курьер свяжется с вами по телефону, указанному в заказе. Проверьте товар при получении.</li>
			<li>Если стоимость по вашему адресу не рассчитывается (адрес не найден или находится вне зоны доставки), проверьте адрес или выберите самовывоз и свяжитесь с нами — подскажем варианты.</li>
		</ul>

		<h3>3. Доставка заказов с товарами под заказ у поставщика</h3>
		<p>Если в заказе есть хотя бы одна позиция под заказ у поставщика, при оформлении доступен только самовывоз: точная дата поступления такого товара заранее неизвестна, и курьера можно вызвать только тогда, когда товар уже в магазине.</p>
		<p>Когда заказ перейдёт в статус «Товар готов к выдаче», доставку курьером можно оформить самостоятельно — см. раздел 4.</p>

		<h3>4. Как оформить доставку для уже оформленного заказа</h3>
		<p>Доставку Яндекс Доставкой можно оформить в <a href="/personal/orders/">личном кабинете</a>:</p>
		<ul>
			<li>для заказа с товарами под заказ — когда заказ в статусе «Товар готов к выдаче»;</li>
			<li>для заказа из товаров в наличии, оформленного с самовывозом, — после полной оплаты заказа.</li>
		</ul>
		<ol>
			<li>Откройте <a href="/personal/orders/">«Мои заказы»</a> и нажмите «Оформить доставку» у нужного заказа (или откройте заказ — блок «Доставка курьером»).</li>
			<li>Укажите адрес — стоимость доставки рассчитается автоматически.</li>
			<li>Нажмите «Оформить и оплатить доставку» и оплатите доставку картой на той же странице. Доставка оплачивается отдельным платежом.</li>
			<li>После оплаты заказ передаётся курьеру Яндекс Доставки.</li>
		</ol>
		<p>Пока доставку оформить нельзя, в карточке заказа написано, когда она станет доступна.</p>

		<h3>5. Где следить за заказом</h3>
		<p>Статус заказа и статус доставки отображаются в <a href="/personal/orders/">личном кабинете</a>. О готовности заказа с товарами под заказ мы сообщаем по SMS.</p>

		<h3>6. Доставка в другие города</h3>
		<p>Отправка в другие населённые пункты выполняется транспортной компанией по согласованию с менеджером. Стоимость заказа складывается из цены товаров и стоимости доставки; предварительную стоимость доставки менеджер сообщит до передачи заказа в работу, точная стоимость определяется по тарифам транспортной компании. Свяжитесь с нами по телефонам магазинов, указанным выше.</p>

		<h3>7. Отмена и возврат</h3>
		<p>Порядок отмены заказа и возврата денег, в том числе за доставку, описан на страницах <a href="/klientam/oplata/">«Оплата»</a> и <a href="/klientam/garantiya-i-vozvrat/">«Гарантия и возврат»</a>.</p>

		<p class="pay-info__note">Оформляя заказ, вы подтверждаете, что ознакомились с условиями доставки, <a href="/klientam/oplata/">оплаты</a> и <a href="/klientam/garantiya-i-vozvrat/">гарантии и возврата</a>.</p>
	</div>
</div>
</div>

<style>
/* Карточки и текст — общие стили страницы «Оплата» (те же классы pay-info). */
.pay-info { width: 100%; margin-bottom: 40px; }
.pay-info__lead { font-size: 15px; line-height: 1.6; color: var(--black); margin: 0 0 20px; max-width: none; }
.pay-info__methods { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
.pay-info__method {
	background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg, 16px);
	padding: 22px 24px; box-shadow: var(--shadow-sm); min-width: 0;
}
.pay-info__method-title { font-size: 16px; font-weight: 800; color: var(--black); margin-bottom: 10px; }
.pay-info__method p { margin: 0; font-size: 13px; line-height: 1.55; color: var(--gray); }
.pay-info__method p b { color: var(--black); }
.pay-info .legal-text.pay-info__text { max-width: none; width: 100%; box-sizing: border-box; padding: 32px 40px 40px; }
.pay-info__text ol, .pay-info__text ul { margin: 0 0 14px; padding-left: 24px; }
.pay-info__text ol { list-style: decimal; }
.pay-info__text ul { list-style: disc; }
.pay-info__text li { margin-bottom: 8px; padding-left: 4px; }
.pay-info__text li::marker { color: var(--blue); font-weight: 700; }
.pay-info__text p { margin: 0 0 12px; }
.pay-info__note { font-size: 13px; color: var(--gray); margin-top: 16px !important; }

.delivery-info__logo { height: 40px; display: flex; align-items: center; margin-bottom: 12px; }
.delivery-info__logo img { max-height: 40px; max-width: 100%; width: auto; display: block; object-fit: contain; }
.delivery-info__logo--icon .icon { width: 32px; height: 32px; color: var(--blue); }
.delivery-info__logo-text { font-weight: 800; font-size: 15px; color: var(--black); }

.delivery-info__shops { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
.delivery-info__shop {
	background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg, 16px);
	padding: 20px 24px; box-shadow: var(--shadow-sm); min-width: 0; font-size: 13px; color: var(--gray);
}
.delivery-info__shop .icon { width: 15px; height: 15px; flex-shrink: 0; color: var(--blue); }
.delivery-info__shop-title { display: flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 800; color: var(--black); margin-bottom: 8px; }
.delivery-info__shop-hours { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
.delivery-info__shop-phones { list-style: none; margin: 0 0 12px; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.delivery-info__shop-phones a { color: var(--black); font-weight: 700; white-space: nowrap; }
.delivery-info__shop-link { color: var(--blue); text-decoration: underline; }

@media (max-width: 1024px) {
	.pay-info__methods { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.pay-info__methods .pay-info__method:first-child { grid-column: 1 / -1; }
	.pay-info .legal-text.pay-info__text { padding: 28px 28px 32px; }
}
@media (max-width: 640px) {
	.pay-info__lead { font-size: 14px; }
	.pay-info__methods, .delivery-info__shops { grid-template-columns: 1fr; gap: 10px; }
	.pay-info__method, .delivery-info__shop { padding: 18px; }
	.pay-info .legal-text.pay-info__text { padding: 20px 16px 24px; font-size: 14px; line-height: 1.6; border-radius: var(--radius, 12px); }
	.pay-info__text h3 { font-size: 15px; line-height: 1.35; }
	.pay-info__text ol, .pay-info__text ul { padding-left: 20px; }
	.pay-info__text a { word-break: break-word; }
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
