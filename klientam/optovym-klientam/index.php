<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Оптовым клиентам");
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
					 Автоцентр «Лидер» предлагает магазинам автозапчастей и автосервисам, расположенным на территории России и стран СНГ, оптовые поставки автозапчастей для автомобилей российского, японского, европейского, американского, корейского и китайского производства. Работаем в режиме экспресс-заказа и оптовых поставок.
				</div>
				<div class="fz14 fw700 mt30">
					 Наши возможности
				</div>
				<ul class="our__opportunities">
					<li>Поставка оригинальных и лицензионных автозапчастей на иномарки, с необходимой сопроводительной документацией.</li>
					<li>Прямые поставки запчастей со складов ОАЭ и Европы.</li>
					<li>Самый полный ассортимент автозапчастей — от клипсы до силовых агрегатов.</li>
					<li>Ежедневные отправки груза автобусами, транспортными компаниями в регионы.</li>
					<li>Помощь в подборе запчастей, обработку запросов.</li>
					<li>Более 50 млн. позиций в наличии на складах в г. Москва, Тольятти, Н. Новгород и Ростов-На-Дону.</li>
					<li>Наличная/безналичная формы оплаты, работа с НДС.</li>
					<li>Увеличение объема работ Вашего сервиса за счет оперативного получения информации о наличии запчастей.</li>
					<li>Экономия на затратах по доставке запчастей, доставка до двери за наш счет.</li>
					<li>Экономия на зарплате сотрудника, ведущего поиск запчастей.</li>
					<li>Выделенная страница с подробным описанием услуг и прайс листом.</li>
					<li>Онлайн запись на обслуживание.</li>
					<li>Дополнительный поток клиентов.</li>
					<li>Эксклюзивные цены.</li>
				</ul>
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
	 <a href="#" data-modal-name="Оптовым клиентам" class="modal-write-our-btn services__btn btn bg-red">
						<!-- <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
	                                    <path d="M6 7.33329L8 9.33329L14.6667 2.66663" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
	                                    <path d="M14 8V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V3.33333C2 2.97971 2.14048 2.64057 2.39052 2.39052C2.64057 2.14048 2.97971 2 3.33333 2H10.6667" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
	                                </svg>                                 -->
						<div>
							 Напишите нам
						</div>
	 </a>
						<!-- <a href="#" class="main__all">Подробнее</a> -->
					</div>
				</div>
			</div>
		</div>
	</div>
</section> 
<?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"system_skidoc",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "N",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"COMPONENT_TEMPLATE" => "system_skidoc",
		"DETAIL_URL" => "DOWNLOAD",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"FIELD_CODE" => array(0=>"",1=>"",),
		"FILTER_NAME" => "",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"IBLOCK_ID" => "10",
		"IBLOCK_TYPE" => "sistem_skidoc",
		"INCLUDE_IBLOCK_INTO_CHAIN" => "N",
		"INCLUDE_SUBSECTIONS" => "Y",
		"MESSAGE_404" => "",
		"NEWS_COUNT" => "20",
		"PAGER_BASE_LINK_ENABLE" => "N",
		"PAGER_DESC_NUMBERING" => "N",
		"PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
		"PAGER_SHOW_ALL" => "N",
		"PAGER_SHOW_ALWAYS" => "N",
		"PAGER_TEMPLATE" => ".default",
		"PAGER_TITLE" => "Новости",
		"PARENT_SECTION" => "",
		"PARENT_SECTION_CODE" => "",
		"PREVIEW_TRUNCATE_LEN" => "",
		"PROPERTY_CODE" => array(0=>"",1=>"DOWNLOAD",2=>"",),
		"SET_BROWSER_TITLE" => "N",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "N",
		"SET_META_KEYWORDS" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "N",
		"SHOW_404" => "N",
		"SORT_BY1" => "SORT",
		"SORT_BY2" => "ACTIVE_FROM",
		"SORT_ORDER1" => "ASC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>