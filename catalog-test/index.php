<? 

if($_REQUEST['is_ajax'] == '1'){
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
}else {
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
	$APPLICATION->SetTitle("catalog-test");
}

?><?$APPLICATION->IncludeComponent(
	"bitrix:catalog.smart.filter",
	".default_old",
	Array(
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CONVERT_CURRENCY" => "N",
		"DISPLAY_ELEMENT_COUNT" => "Y",
		"FILTER_NAME" => "arrFilter",
		"FILTER_VIEW_MODE" => "vertical",
		"HIDE_NOT_AVAILABLE" => "N",
		"IBLOCK_ID" => "43",
		"IBLOCK_TYPE" => "1c_catalog",
		"PAGER_PARAMS_NAME" => "arrPager",
		"POPUP_POSITION" => "left",
		"PREFILTER_NAME" => "smartPreFilter",
		"PRICE_CODE" => array("Ручная розничная цена"),
		"SAVE_IN_SESSION" => "N",
		"SECTION_CODE" => "",
		"SECTION_DESCRIPTION" => "-",
		"SECTION_ID" => "",
		"SECTION_TITLE" => "-",
		"SEF_MODE" => "N",
		"TEMPLATE_THEME" => "blue",
		"XML_EXPORT" => "N"
	)
);?><br><?

if($_REQUEST['is_ajax'] != '1'){
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}


?>