<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Условия гарантии и возврата автозапчастей в магазине ЛИДЕР в Елабуге: возврат товара надлежащего качества в течение 14 рабочих дней.");
$APPLICATION->SetPageProperty("title", "Гарантия и возврат товара — автозапчасти ЛИДЕР");
$APPLICATION->SetTitle("Гарантия и возврат");

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/shop_locations.php';
$shops = getShopLocations();
?><?= \Lider\Seo\Seo::breadcrumbs([['NAME' => 'Главная', 'LINK' => '/'], ['NAME' => 'Гарантия и возврат', 'LINK' => '']]) ?>
<div class="container">
	<h1 class="warranty-info__title">Гарантия и возврат</h1>
	<div class="warranty-info">
		<p class="warranty-info__lead">Если запчасть не подошла или вы обнаружили недостаток, мы поможем оформить возврат. Здесь собраны условия возврата, необходимые документы и сроки возврата денег.</p>

		<div class="warranty-info__highlights">
			<div class="warranty-info__highlight">
				<div class="warranty-info__highlight-icon"><svg class="icon"><use href="#icon-refresh"></use></svg></div>
				<div>
					<div class="warranty-info__highlight-title">14 рабочих дней</div>
					<p>на возврат товара надлежащего качества после получения</p>
				</div>
			</div>
			<div class="warranty-info__highlight">
				<div class="warranty-info__highlight-icon"><svg class="icon"><use href="#icon-check-circle"></use></svg></div>
				<div>
					<div class="warranty-info__highlight-title">Гарантия производителя</div>
					<p>действует со дня передачи товара покупателю</p>
				</div>
			</div>
			<div class="warranty-info__highlight">
				<div class="warranty-info__highlight-icon"><svg class="icon"><use href="#icon-card"></use></svg></div>
				<div>
					<div class="warranty-info__highlight-title">Не позднее 10 дней</div>
					<p>на возврат денег со дня предъявления требования</p>
				</div>
			</div>
		</div>

		<nav class="warranty-info__nav" aria-label="Содержание страницы">
			<a href="#return">Возврат товара</a>
			<a href="#warranty">Гарантия и недостатки</a>
			<a href="#refund">Возврат денег</a>
			<a href="#contacts">Куда обратиться</a>
		</nav>

		<div class="legal-text warranty-info__text">
			<section class="warranty-info__section" id="return">
				<h2>1. Возврат товара надлежащего качества</h2>
				<p>Вы вправе отказаться от товара в любое время до его передачи, а после получения — <b>в течение 14 рабочих дней</b>. Этот срок больше установленного законом минимального срока в 7 дней для товаров, приобретённых дистанционно.</p>
				<p>Возврат возможен при соблюдении следующих условий:</p>
				<ul>
					<li>сохранены товарный вид и потребительские свойства товара;</li>
					<li>сохранены заводская упаковка, пломбы и ярлыки;</li>
					<li>запчасть не устанавливалась на автомобиль и не имеет следов эксплуатации;</li>
					<li>есть документ, подтверждающий покупку. Если документ не сохранился, покупку можно подтвердить другими сведениями.</li>
				</ul>
				<div class="warranty-info__notice">
					<svg class="icon"><use href="#icon-alert"></use></svg>
					<p>Товар с индивидуально-определёнными свойствами, который может использоваться только конкретным покупателем, возврату не подлежит. Такой товар отмечается на сайте как невозвратный, а при оформлении заказа запрашивается отдельное согласие.</p>
				</div>

				<h3>Как оформить возврат</h3>
				<div class="warranty-info__steps">
					<div class="warranty-info__step">
						<span class="warranty-info__step-number">1</span>
						<h4>Свяжитесь с нами</h4>
						<p>Позвоните или напишите нам. Сообщите номер заказа, наименование товара и причину возврата.</p>
					</div>
					<div class="warranty-info__step">
						<span class="warranty-info__step-number">2</span>
						<h4>Передайте товар</h4>
						<p>Привезите товар в полной комплектации в один из наших магазинов вместе с документом о покупке.</p>
					</div>
					<div class="warranty-info__step">
						<span class="warranty-info__step-number">3</span>
						<h4>Получите деньги</h4>
						<p>После оформления возврата мы вернём деньги в срок и способом, указанными в разделе 3.</p>
					</div>
				</div>
			</section>

			<section class="warranty-info__section" id="warranty">
				<h2>2. Гарантия и товар с недостатком</h2>
				<p>Гарантийный срок устанавливает изготовитель. Он исчисляется с момента передачи товара покупателю. Если изготовитель не установил гарантийный срок, требования, связанные с недостатками товара, могут быть предъявлены в пределах двух лет с момента передачи.</p>
				<p>При обнаружении недостатка свяжитесь с нами и передайте товар в магазин. Вы вправе предъявить требования, предусмотренные статьёй 18 Закона РФ «О защите прав потребителей». Мы примем товар и при необходимости проведём проверку качества. Если возникнет спор о причинах недостатка, экспертизу оплачивает продавец; покупатель вправе присутствовать при проверке и экспертизе.</p>
				<p>Для быстрого рассмотрения претензии рекомендуем приложить:</p>
				<ul>
					<li>описание недостатка и обстоятельств его обнаружения;</li>
					<li>заказ-наряд или акт работ организации, установившей деталь;</li>
					<li>акт дефектовки или диагностики, если они проводились.</li>
				</ul>
				<p>Отсутствие документов об установке и диагностике не является основанием для отказа в принятии претензии.</p>

				<details class="warranty-info__details">
					<summary>Когда недостаток может быть негарантийным</summary>
					<div class="warranty-info__details-content">
						<p>Продавец не отвечает за недостатки, если докажет, что они возникли после передачи товара из-за нарушения правил использования, хранения, транспортировки или установки, действий третьих лиц либо непреодолимой силы. Например:</p>
						<ul>
							<li>повреждения в результате ДТП, перегрузки автомобиля или небрежной эксплуатации;</li>
							<li>неквалифицированный монтаж или установка с нарушением требований изготовителя;</li>
							<li>неисправности из-за некачественного топлива, повреждения электропроводки или неправильного подключения;</li>
							<li>естественный износ расходных деталей и материалов в пределах нормального ресурса.</li>
						</ul>
						<p>Производственные недостатки расходных деталей рассматриваются на общих основаниях. Полный перечень условий приведён в <a href="/klientam/soglashenie-o-postavke/">Соглашении на поставку</a>.</p>
					</div>
				</details>
			</section>

			<section class="warranty-info__section" id="refund">
				<h2>3. Сроки и способ возврата денег</h2>
				<ul>
					<li>Деньги возвращаются <b>не позднее 10 дней со дня предъявления требования</b>, тем же способом, которым была произведена оплата, если стороны не договорились об ином.</li>
					<li>При оплате картой на сайте деньги возвращаются только на ту же банковскую карту. После отправки возврата магазином срок зачисления зависит от банка и может составлять до 30 дней.</li>
					<li>При возврате товара надлежащего качества из суммы могут быть вычтены расходы на его обратную доставку от покупателя, если они были.</li>
					<li>При возврате денег формируется кассовый чек возврата прихода.</li>
				</ul>
				<div class="warranty-info__notice warranty-info__notice--neutral">
					<svg class="icon"><use href="#icon-box"></use></svg>
					<p><b>Отмена товара, заказанного у поставщика.</b> Если заказ отменяется не по вине магазина и товар удаётся снять с заказа или вернуть поставщику, из возвращаемой суммы удерживается 25% стоимости отменённого товара в счёт компенсации транспортных и иных расходов. Подробнее — в пункте 4.5 <a href="/klientam/soglashenie-o-postavke/">Соглашения на поставку</a>.</p>
				</div>
			</section>

			<section class="warranty-info__section" id="contacts">
				<h2>4. Куда обратиться</h2>
				<p>Перед визитом свяжитесь с нами: мы проверим информацию о заказе и подскажем, какие документы взять с собой.</p>
				<div class="warranty-info__shops">
					<?php foreach ($shops as $shop): ?>
					<div class="warranty-info__shop">
						<div class="warranty-info__shop-title"><svg class="icon"><use href="#icon-pin"></use></svg> <?= htmlspecialchars($shop['address']) ?></div>
						<div class="warranty-info__shop-hours"><svg class="icon"><use href="#icon-clock"></use></svg> <?= htmlspecialchars($shop['hours']) ?></div>
						<a href="/shop/<?= htmlspecialchars($shop['id']) ?>/">Адрес и схема проезда</a>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="warranty-info__contact-actions">
					<a class="btn btn--primary" href="tel:+78555732050"><svg class="icon"><use href="#icon-phone"></use></svg> +7 (85557) 3-20-50</a>
					<a class="btn btn--white" href="mailto:lider-16@bk.ru"><svg class="icon"><use href="#icon-mail"></use></svg> lider-16@bk.ru</a>
				</div>
			</section>
		</div>

		<p class="warranty-info__footer-note">Условия на этой странице дополняют <a href="/klientam/soglashenie-o-postavke/">Соглашение на поставку</a> и не ограничивают права покупателя, предусмотренные законодательством РФ.</p>
	</div>
</div>

<style>
.warranty-info { width: 100%; margin-bottom: 40px; }
.warranty-info__title { font-size: 26px; font-weight: 800; margin: 8px 0 20px; }
.warranty-info__lead { font-size: 15px; line-height: 1.6; color: var(--black); margin: 0 0 20px; max-width: 900px; }
.warranty-info__highlights { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
.warranty-info__highlight { display: flex; align-items: flex-start; gap: 14px; min-width: 0; padding: 22px 24px; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg, 16px); box-shadow: var(--shadow-sm); }
.warranty-info__highlight-icon { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 42px; height: 42px; color: var(--blue); background: var(--blue-soft); border-radius: 12px; font-size: 22px; }
.warranty-info__highlight-title { margin: 1px 0 6px; font-size: 16px; line-height: 1.35; font-weight: 800; color: var(--black); }
.warranty-info__highlight p { margin: 0; font-size: 13px; line-height: 1.55; color: var(--gray); }
.warranty-info__nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.warranty-info__nav a { padding: 8px 14px; color: var(--blue-dark); background: var(--blue-soft); border-radius: var(--radius-pill); font-size: 13px; font-weight: 700; }
.warranty-info__nav a:hover { color: #fff; background: var(--blue); }
.warranty-info .legal-text.warranty-info__text { max-width: none; width: 100%; box-sizing: border-box; padding: 8px 40px; margin-bottom: 16px; }
.warranty-info__section { padding: 28px 0; border-bottom: 1px solid var(--border); scroll-margin-top: 20px; }
.warranty-info__section:last-child { padding-bottom: 32px; border-bottom: 0; }
.warranty-info__section h2 { margin: 0 0 16px; font-size: 20px; line-height: 1.35; font-weight: 800; }
.warranty-info__section h3 { margin: 24px 0 14px; font-size: 16px; }
.warranty-info__text p { margin: 0 0 12px; }
.warranty-info__text ol, .warranty-info__text ul { margin: 0 0 14px; padding-left: 24px; }
.warranty-info__text ol { list-style: decimal; }
.warranty-info__text ul { list-style: disc; }
.warranty-info__text li { margin-bottom: 8px; padding-left: 4px; }
.warranty-info__text li::marker { color: var(--blue); font-weight: 700; }
.warranty-info__notice { display: flex; align-items: flex-start; gap: 12px; margin: 18px 0; padding: 16px 18px; color: #7A4A00; background: #FFF8E9; border: 1px solid #F3E2B9; border-radius: var(--radius); }
.warranty-info__notice > .icon { width: 20px; height: 20px; margin-top: 2px; color: #B8790A; }
.warranty-info__notice p { margin: 0; font-size: 13px; line-height: 1.6; }
.warranty-info__notice--neutral { color: var(--black); background: var(--bg); border-color: var(--border); }
.warranty-info__notice--neutral > .icon { color: var(--blue); }
.warranty-info__steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.warranty-info__step { position: relative; min-width: 0; padding: 20px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); }
.warranty-info__step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; margin-bottom: 12px; color: #fff; background: var(--blue); border-radius: 50%; font-size: 13px; font-weight: 800; }
.warranty-info__step h4 { margin: 0 0 8px; font-size: 14px; font-weight: 800; }
.warranty-info__step p { margin: 0; color: var(--gray); font-size: 13px; line-height: 1.55; }
.warranty-info__details { margin-top: 18px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); }
.warranty-info__details summary { position: relative; padding: 16px 48px 16px 18px; color: var(--black); font-weight: 700; cursor: pointer; list-style: none; }
.warranty-info__details summary::-webkit-details-marker { display: none; }
.warranty-info__details summary::after { content: "+"; position: absolute; top: 50%; right: 18px; transform: translateY(-50%); color: var(--blue); font-size: 22px; font-weight: 400; transition: transform var(--transition); }
.warranty-info__details[open] summary::after { transform: translateY(-50%) rotate(45deg); }
.warranty-info__details-content { padding: 0 18px 16px; color: var(--gray); }
.warranty-info__details-content p:last-child, .warranty-info__details-content ul:last-child { margin-bottom: 0; }
.warranty-info__shops { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 18px 0; }
.warranty-info__shop { min-width: 0; padding: 18px 20px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); font-size: 13px; }
.warranty-info__shop .icon { width: 16px; height: 16px; color: var(--blue); }
.warranty-info__shop-title { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; color: var(--black); font-size: 15px; font-weight: 800; }
.warranty-info__shop-hours { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; color: var(--gray); }
.warranty-info__contact-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.warranty-info__contact-actions .btn--primary, .warranty-info__contact-actions .btn--primary:hover { color: #fff; }
.warranty-info__footer-note { margin: 0; color: var(--gray); font-size: 13px; line-height: 1.6; }
.warranty-info__footer-note a { color: var(--blue); text-decoration: underline; }

@media (max-width: 900px) {
	.warranty-info__highlights, .warranty-info__steps { grid-template-columns: 1fr; gap: 10px; }
	.warranty-info__highlight { align-items: center; padding: 18px 20px; }
	.warranty-info .legal-text.warranty-info__text { padding-right: 28px; padding-left: 28px; }
}
@media (max-width: 640px) {
	.warranty-info__title { font-size: 22px; line-height: 1.3; }
	.warranty-info__lead { font-size: 14px; }
	.warranty-info__highlight { padding: 16px; border-radius: var(--radius); }
	.warranty-info__highlight-title { font-size: 15px; }
	.warranty-info__nav { gap: 6px; }
	.warranty-info__nav a { padding: 7px 11px; font-size: 12px; }
	.warranty-info .legal-text.warranty-info__text { padding: 0 16px; font-size: 14px; line-height: 1.6; border-radius: var(--radius); }
	.warranty-info__section { padding: 22px 0; }
	.warranty-info__section:last-child { padding-bottom: 24px; }
	.warranty-info__section h2 { font-size: 18px; }
	.warranty-info__text ol, .warranty-info__text ul { padding-left: 20px; }
	.warranty-info__text a { word-break: break-word; }
	.warranty-info__notice { padding: 14px; }
	.warranty-info__shops { grid-template-columns: 1fr; gap: 10px; }
	.warranty-info__contact-actions { flex-direction: column; }
	.warranty-info__contact-actions .btn { width: 100%; box-sizing: border-box; white-space: normal; }
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
