<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Поставщикам");
?>
<section class="optom">
	<div class="container">
		<?$APPLICATION->IncludeComponent(
			"bitrix:menu",
			"Left_menu",
			Array(
				"ALLOW_MULTI_SELECT" => "N",
				"CHILD_MENU_TYPE" => "left",
				"DELAY" => "N",
				"MAX_LEVEL" => "1",
				"MENU_CACHE_GET_VARS" => array(""),
				"MENU_CACHE_TIME" => "3600",
				"MENU_CACHE_TYPE" => "N",
				"MENU_CACHE_USE_GROUPS" => "Y",
				"ROOT_MENU_TYPE" => "left",
				"USE_EXT" => "N"
			)
		);?>
		<div class="optom__inner df">
			<div class="optom__left">
				<div class="text">
					Оказываем услуги по ремонту автомобилей, замена масла в двигателе и коробке передач. Используем современное оборудование и специальный инструмент для замены масла в автоматической трансмиссии, цепей и ремней ГРМ. Производим замену катализатора на пламегаситель, ремонт тормозной системы, замену тормозных колодок, диагностику и ремонт подвески.
				</div>
			</div>
			<div class="optom__right">
				<div class="services__item">
					<div class="services__title">
						Связать с нами
					</div>
					<div class="fz14 mt10">
						Приглашаем к сотрудничеству поставщиков. Работайте с лучшими в совем деле!
					</div>
					<div class="services__bottom">
						<a href="#" data-modal-name="Поставщикам" class="modal-write-our-btn services__btn btn bg-red">
							<div>
								Напишите нам
							</div>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>