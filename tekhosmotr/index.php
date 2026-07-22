<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Технический осмотр в Елабуге, получить диагностическую карту, официально");
$APPLICATION->SetPageProperty("title", "Технический осмотр в Елабуге, диагностическая карта");
$APPLICATION->SetTitle("Технический осмтор");
// $APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");
// $APPLICATION->AddChainItem("Технический осмотр", "tekhnicheskiy-osmtor.php");
?><section>
<div class="container df ac ">
 <a href="/" class="product__back">
	<div class="df ac">
	</div>
	<div>
		 Назад
	</div>
 </a>
	<h1 class="title">
	Технический осмотр </h1>
</div>
 </section>
<?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"slider_tech",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "N",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "N",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"COMPONENT_TEMPLATE" => "slider_tech",
		"DETAIL_URL" => "",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"FIELD_CODE" => array(0=>"",1=>"",),
		"FILTER_NAME" => "",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"IBLOCK_ID" => "15",
		"IBLOCK_TYPE" => "sliders",
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
		"PROPERTY_CODE" => array(0=>"show_in_map",1=>"LINK",2=>"",),
		"SET_BROWSER_TITLE" => "N",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "N",
		"SET_META_KEYWORDS" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "N",
		"SHOW_404" => "N",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "DESC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?>
<p>
	 <?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"tech_price",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "N",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "N",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"DETAIL_URL" => "",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"FIELD_CODE" => array("",""),
		"FILTER_NAME" => "",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"IBLOCK_ID" => "17",
		"IBLOCK_TYPE" => "tech",
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
		"PROPERTY_CODE" => array("",""),
		"SET_BROWSER_TITLE" => "N",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "N",
		"SET_META_KEYWORDS" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "N",
		"SHOW_404" => "N",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "DESC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?> <?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"tech_blocks",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "N",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "N",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"DETAIL_URL" => "",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"FIELD_CODE" => array("ID",""),
		"FILTER_NAME" => "",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"IBLOCK_ID" => "16",
		"IBLOCK_TYPE" => "tech",
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
		"PROPERTY_CODE" => array("",""),
		"SET_BROWSER_TITLE" => "N",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "N",
		"SET_META_KEYWORDS" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "N",
		"SHOW_404" => "N",
		"SORT_BY1" => "ID",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "ASC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?>
</p>
 <section> <section>
<div class="container">
	<div class="title">
		 Аккредитованные категории ТС
	</div>
</div>
 </section> <?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"accred_category_tc",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "N",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "N",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"COMPONENT_TEMPLATE" => "accred_category_tc",
		"DETAIL_URL" => "",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"FIELD_CODE" => array(0=>"",1=>"",),
		"FILTER_NAME" => "",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"IBLOCK_ID" => "18",
		"IBLOCK_TYPE" => "content",
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
		"PROPERTY_CODE" => array(0=>"",1=>"",),
		"SET_BROWSER_TITLE" => "N",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "N",
		"SET_META_KEYWORDS" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "N",
		"SHOW_404" => "N",
		"SORT_BY1" => "ID",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "ASC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?>
<div class="container">
	<div class="text mt20 ">
		 <!--Пройти технический осмотр и получить диагностическую карту с занесением в базу ЕАИСТО, для оформления полиса ОСАГО, вы можете в нашем автотехцентре ЛИДЕР, в городе Елабуга-->
		<p>
			 Документы
		</p>
		<ul>
			<li>Договор общий (образец) - <a href="https://drive.google.com/uc?export=download&id=16TGZXeHuQP-1MiQ40i22rLsWsAHiT7MP">Скачать</a></li>
			<li>Контракт на техосмотр по 44-фз (образец) - <a href="https://drive.google.com/uc?export=download&id=19a91cHmB_WQ9UaLJPQL6d7e41bbfRsQX">Cкачать</a></li>
			<li>Прайс-лист - <a href="https://drive.google.com/uc?export=download&id=1pLA_BV4fedRjID8ArRd0siOk5xhPRSru">Скачать</a></li>
			<li>Акт оказанных услуг по ТО (образец) - <a href="https://drive.google.com/uc?export=download&id=1oH4TKSP3qdS9E8c8tqB4YP-kMV65ID3I">Скачать</a></li>
			<li>Карта партнёра - <a href="https://drive.google.com/uc?export=download&id=1wMBeUR-TR7A2TrSyCSNZBQzIlB4Wf2UG">Cкачать</a></li>
			<li>Гарантийное письмо для прохождения техосмотра (образец) - <a href="https://drive.google.com/uc?export=download&id=1wMBeUR-TR7A2TrSyCSNZBQzIlB4Wf2UG">Скачать</a></li>
			<li>Протокол разногласий (образец) - <a href="https://drive.google.com/uc?export=download&id=1G-OYg8jjvfsYGV5Nr0qVmaWo6xupaKq_">Скачать</a></li>
		</ul>
		<p>
			 Нормативно-правовые акты
		</p>
		<ul>
			<li>170-ФЗ «О техническом осмотре транспортных средств и о внесении изменений в отдельные законодательные акты РФ» - <a href="https://drive.google.com/uc?export=download&id=18NSdjM5edMXridUnyAcPS_Nrfuk50wV-">Скачать</a></li>
			<li>Постановление Правительства РФ N 1008 «О проведении технического осмотра транспортных средств» - <a href="https://drive.google.com/uc?export=download&id=1q73wvrHtY_fdk2uU81qQRJCvUj1H9_sj">Скачать</a></li>
			<li>Приказ Министерства экономического развития РФ N 573 «Об утверждении формы типового договора о проведении технического осмотра» - <a href="https://drive.google.com/uc?export=download&id=1wI155YcHHUvXrcCFrDzHHABMgqnVNV3x">Скачать</a></li>
			<li>Приказ Министерства экономического развития N 587 «Об утверждении Порядка ведения реестра операторов технического осмотра, формирования и размещения открытого и общедоступного информационного ресурса, содержащего сведения из реестра операторов технического осмотра» - <a href="https://drive.google.com/uc?export=download&id=15eKc6FhiggrZTTzcyc5XO9SHVc_oIepc">Скачать</a></li>
			<li>Приказ Минтранса России от 21.08.2013 № 274 (ред. от 12.04.2018) «Об утверждении правил заполнения диагностической карты» - <a href="https://drive.google.com/uc?export=download&id=1N8PsGSYKs4_sQij9npz-739GxtoHQI0L">Скачать</a></li>
			<li>Приказ Минтранса России от 25.02.2014 N 46 «Об утверждении порядка учета, хранения, передачи и уничтожения диагностических карт» (Зарегистрировано в Минюсте России 03.07.2014 N 32952) - <a href="https://drive.google.com/uc?export=download&id=161e5axI0NNXr0ztCPLD4bXye35HIrsjb">Скачать</a></li>
			<li></li>
		</ul>
		<p>
			 Аттестат аккредитации оператора технического осмотра транспортных средств Номер в реестре ОТО: 10702
		</p>
 <img width="50%" src="/images/attestat.png" height="50%">
	</div>
</div>
 <br>
 </section><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>