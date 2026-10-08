<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Соглашение на поставку автозапчастей и аксессуаров — ЛИДЕР, Елабуга");
$APPLICATION->SetPageProperty("description", "Условия заказа, поставки, хранения, возврата и гарантии на автозапчасти и аксессуары в интернет-магазине ЛИДЕР (ИП Винокуров С. В., Елабуга).");
$APPLICATION->SetTitle("Соглашение на поставку");
// Текст — из local/php_interface/include/supply_agreement.php (тот же, что в
// подписанном экземпляре, который уходит покупателю с письмом о заказе).
?><?= \Lider\Seo\Seo::breadcrumbs([['NAME' => 'Главная', 'LINK' => '/'], ['NAME' => 'Соглашение на поставку', 'LINK' => '']]) ?>
<div class="container">
	<div class="legal-text supply-agreement">
		<?= getSupplyAgreementTermsHtml() ?>
		<h3>Подписание</h3>
		<p>Соглашение подписывается при оформлении заказа на сайте: покупатель отмечает согласие с его условиями и нажимает «Оформить заказ» (п. 1.4). Экземпляр с данными заказа, датой и временем подписания направляется на e-mail покупателя вместе с письмом об оформленном заказе.</p>
	</div>
</div>
<style>
.supply-agreement .sa-title { font-size: 26px; font-weight: 800; margin: 8px 0 6px; }
.supply-agreement .sa-version { font-size: 13px; color: var(--gray); margin-bottom: 20px; }
.supply-agreement p { margin: 0 0 8px; }
.supply-agreement ul { margin: 0 0 10px; padding-left: 22px; }
.supply-agreement a { word-break: break-word; }
@media (max-width: 640px) {
	.supply-agreement .sa-title { font-size: 21px; line-height: 1.25; }
}
</style>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
