<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetPageProperty("description", "Большой выбор автозапчастей в городе Елабуга, в наличии и на заказ, ВАЗ и иномарки, доставка");
$APPLICATION->SetPageProperty("title", "ЛИДЕР магазин автозапчастей для иномарок и ВАЗ в Елабуге");
$APPLICATION->SetTitle("Главная");
$APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");

?><?$APPLICATION->IncludeComponent(
	"bitrix:catalog.section.list",
	"header_sections",
	Array(
		"ADD_SECTIONS_CHAIN" => "Y",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"COMPONENT_TEMPLATE" => "header_sections",
		"COUNT_ELEMENTS" => "Y",
		"COUNT_ELEMENTS_FILTER" => "CNT_ACTIVE",
		"FILTER_NAME" => "sectionsFilter",
		"IBLOCK_ID" => "42",
		"IBLOCK_TYPE" => "1c_catalog",
		"SECTION_CODE" => "",
		"SECTION_FIELDS" => array(0=>"",1=>"",),
		"SECTION_ID" => "0",
		"SECTION_URL" => "",
		"SECTION_USER_FIELDS" => array(0=>"",1=>"",),
		"SHOW_PARENT_NAME" => "Y",
		"TOP_DEPTH" => "2",
		"VIEW_MODE" => "TEXT"
	)
);?> <?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"slider_main",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "Y",
		"AJAX_MODE" => "Y",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "N",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"CHECK_DATES" => "Y",
		"COMPONENT_TEMPLATE" => "slider_main",
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
		"IBLOCK_ID" => "12",
		"IBLOCK_TYPE" => "sliders",
		"INCLUDE_IBLOCK_INTO_CHAIN" => "Y",
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
		"SET_BROWSER_TITLE" => "Y",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "Y",
		"SET_META_KEYWORDS" => "Y",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "Y",
		"SHOW_404" => "N",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "DESC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?> <?php

// $scriptStartTime = microtime(1);
// ob_start();
// session_start();

// header("Content-Type: text/html; charset=utf-8");

// //Значение параметра partInfo по умолчанию
// //partInfo default value
// $partInfoValue = 1;
// //
// //
// //if (file_exists('underConstruction.php')) {
// //	include('underConstruction.php');
// //}
// if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
// 	$IlcatsInjection = 'Index1';
// 	include('IlcatsInjections.php');
// }

// if (file_exists('IlcatsInjections2.php')) include_once('IlcatsInjections2.php');

// //error_reporting(E_ALL);
// include_once('settings.php');

// global $apiHttpCatalogsPath;
// $apiHttpCatalogsPath='';
// if (!empty(apiHttpCatalogsPath)) $apiHttpCatalogsPath='/'.apiHttpCatalogsPath;

// include_once('API.v2/PHP/Functions.Common.php');
// include_once('API.v2/PHP/Functions.Blocks.php');

// if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
// 	$IlcatsInjection = 'Index1.2';
// 	include('IlcatsInjections.php');
// }

// if (empty($_GET['function'])) $_GET['function'] = 'defaultFunction';
// if (empty($_GET['language'])) $_GET['language'] = $_COOKIE['language'] ? $_COOKIE['language'] : "ru";
// $vinTmp = (empty($_GET["vin"]) ? array() : array("vin" => $_GET["vin"]));

// if (empty($_GET["clid"])) $_GET["clid"] = '';
// if (empty($_GET["pid"])) $_GET["pid"] = '';
// if (empty($_GET["shopid"])) $_GET["shopid"] = '';
// if (!empty($_GET['brand'])) $data = getApiData($_GET);
// else $data = getApiData(array_merge(array("function" => "catalogsList", "brand" => 'cataloglist', "apiVersion" => '2.0', "shopClientId" => $_GET["clid"], "catalogId" => $_GET["pid"], "shopid" => $_GET["shopid"], "language" => $_GET["language"]), $vinTmp));
//  // debug($data);
// if (!empty($_GET["debughash"])) ShowApiAnswer($data, $_GET["debughash"]);


// if (!empty($_GET['Ajax']) and $_GET['Ajax'] == 1) {
// 	$_GET['filterData']     = base64_decode($_GET['filterData']);
// 	$Answer['filterData']   = $_GET['filterData'];
// 	$Answer['PageSelector'] = $data['data'][1]['format']($data['data'][1]);
// 	$Answer['Tiles']        = $data['data'][2]['format']($data['data'][2]);

// 	//print_r($Answer);
// 	exit(json_encode($Answer));
// }

// if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
// 	$IlcatsInjection = 'Index1.5';
// 	include('IlcatsInjections.php');
// }

// $SiteLabels = $data['siteLabels'];
// if (!empty($data['mainMenu'])) $Page['MainMenu'] = MainMenu($data['mainMenu']); else $Page['MainMenu'] = '';
// if (!empty($data['availableLanguages'])) $Page['Languages'] = Languages($data['availableLanguages'], $apiActiveLanguages); //else $Page['Languages']="No 'availableLanguages'";
// if ($data['data'])

// 	// $Page['Content'][] = "<div class='main_block'>";

// 	// array_push($Page['Content'], "<div class='main_block'>");

// 	foreach ($data["data"] as $key => $Data){

// 		$Page['Content'][$key] = $Data['format']($Data, $SiteLabels);

// 	}
// 		// $Page['Content'][] = (!empty($Data['caption']) ? "<h2 class='$Data[captionEng]'>{$Data['caption']}</h2>" : "") . $Data['format']($Data, $SiteLabels);
// 		// echo "1";
// else {
// 	if ($data["errors"])
// 		$Page['Content'][] = "<div class='ApiError'>" . ImplodeIfArray($data["errors"], '<br>') . "</div>";
// 	else $Page['Content'][] = "Wrong answer";
// }

// if (apiIlcatsIsPlugin) {
// 	$HtmlTags = array(
// 		'Start'   => "",
// 		'HeadEnd' => "",
// 		'Header'  => array('Start' => "<div class='PageHeader'>", 'End' => "</div>"),
// 		'Footer'  => array('Start' => "<div class='PageFooter'>", 'End' => "</div>"),
// 	);
// } else {
// 	$HtmlTags = array(
// 		'Html'    => array('Start' => "<!DOCTYPE html><html lang='ru'><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'><meta http-equiv='X-UA-Compatible' content='IE=edge'><meta name='viewport' content='width=device-width, initial-scale=0.7'>", 'End' => '</html>'),
// 		'HeadEnd' => "</head>",
// 		'Header'  => array('Start' => "<header>", 'End' => "</header>"),
// 		'Footer'  => array('Start' => "<footer>", 'End' => "</footer>"),
// 	);
// }

// echo $HtmlTags['Html']['Start'];
// echo "<meta name='description' content='{$data["metas"]["description"]}'>
//     		<meta name='keyword' content='" . ImplodeIfArray($data["metas"]["keywords"], ', ') . "'>
//     		<title>{$data["metas"]["title"]}</title>";


// if (apiLoadJquery) echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQuery-3.1.0.min.js'></script>";

// echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.min.js'></script>
// 	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.css'>
// 	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.scrollTo.190301.min.js'></script>
// 	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.pep.js'></script>
// 	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/fonts/ProximaNova/Font.css'>
// 	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/CSS/Template2.191230.css'>
// 	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/Common2.200410.js'></script>";

// $clientId = empty($_GET["clid"]) ? "clid=" . apiClientId : "clid=" . $_GET["clid"];
// $hostName = "&domain=" . (!empty($_GET['cssdomain']) ? $_GET['cssdomain'] : $_SERVER["HTTP_HOST"]);
// $TestCSS  = (empty($_SESSION['CSSManager']) or empty($_GET['CSSManager'])) ? "" : "&TestCSS=" . $_SESSION['CSSManager'];
// echo "<link type='text/css' rel='stylesheet' href='//www.ilcats.ru/getCss.php?" . $clientId . $TestCSS . "'>";
// if ($hostName) echo "<link type='text/css' rel='stylesheet' href='//www.ilcats.ru/getCss.php?" . $clientId . $hostName . $TestCSS . "'>";

// if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
// 	$IlcatsInjection = 'Index2';
// 	include('IlcatsInjections.php');
// }
// if (empty($_GET['brand'])) $_GET['brand'] = '';
// echo $HtmlTags['HeadEnd'];
// echo "<body class='" . $_GET['brand'] . "'>";
// if ($IlcatsInjections) {
// 	$IlcatsInjection = 'Counters';
// 	include('IlcatsInjections.php');
// }
// echo "{$HtmlTags['Header']['Start']}<div class='Top'>{$Page['MainMenu']}{$Page['Languages']}</div>", VinForm($data['vinSearchParameters']), $HtmlTags['Header']['End'];
// echo "<div id='Body' class='{$data['data'][0]['format']}Body'>";
// if ($IlcatsInjections) {
// 	$IlcatsInjection = 'Advert1';
// 	include('IlcatsInjections.php');
// }
// echo "<h1>{$data["stageName"]}</h1>";
// if ($data['data'][0]['format'] == 'ifImage') {
// 	$TempPageContent[0] = $Page['Content'][0];
// 	array_shift($Page['Content']);
// 	$TempPageContent[1] = "<div class='Info'>" . ImplodeIfArray($Page['Content']) . "</div>";
// 	$Page['Content']    = "<div class='ifImage'>" . ImplodeIfArray($TempPageContent) . "</div>";
// }
// echo ImplodeIfArray($Page['Content']);

// if ($IlcatsInjections) {
// 	$IlcatsInjection = 'Advert2';
// 	include('IlcatsInjections.php');
// }
// echo "</div>
// 	<div id='Dialog'></div>";


// echo $HtmlTags['Footer']['Start'];
// if ($IlcatsInjections) {
// 	$IlcatsInjection = 'Index3';
// 	include('IlcatsInjections.php');
// }
// if (empty($CatSetup))
// 	echo "<div>{$data['siteLabels']['advertLinkUrl']}</div>";
// else echo $CatSetup;

// echo $ErrorFound, $HtmlTags['Footer']['End'];
// echo "<script>console.log({$data['serverInfo']['dataGenerateTime']}, ". (microtime(1) - $scriptStartTime) . ")</script>";
// echo "</body>";

// echo $HtmlTags['Html']['End'];
// ob_end_flush();


?> <?$APPLICATION->IncludeComponent(
	"bitrix:catalog",
	"template_for_main_page",
	Array(
		"ACTION_VARIABLE" => "action",
		"ADD_ELEMENT_CHAIN" => "Y",
		"ADD_PICT_PROP" => "-",
		"ADD_PROPERTIES_TO_BASKET" => "Y",
		"ADD_SECTIONS_CHAIN" => "Y",
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"BASKET_URL" => "/personal/basket.php",
		"BIG_DATA_RCM_TYPE" => "personal",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"COMMON_ADD_TO_BASKET_ACTION" => "ADD",
		"COMMON_SHOW_CLOSE_POPUP" => "N",
		"COMPATIBLE_MODE" => "Y",
		"COMPOSITE_FRAME_MODE" => "A",
		"COMPOSITE_FRAME_TYPE" => "AUTO",
		"CONVERT_CURRENCY" => "Y",
		"CURRENCY_ID" => "RUB",
		"DETAIL_ADD_DETAIL_TO_SLIDER" => "N",
		"DETAIL_ADD_TO_BASKET_ACTION" => array("BUY"),
		"DETAIL_ADD_TO_BASKET_ACTION_PRIMARY" => array("BUY"),
		"DETAIL_BACKGROUND_IMAGE" => "-",
		"DETAIL_BRAND_USE" => "N",
		"DETAIL_BROWSER_TITLE" => "-",
		"DETAIL_CHECK_SECTION_ID_VARIABLE" => "Y",
		"DETAIL_DETAIL_PICTURE_MODE" => array("MAGNIFIER"),
		"DETAIL_DISPLAY_NAME" => "Y",
		"DETAIL_DISPLAY_PREVIEW_TEXT_MODE" => "E",
		"DETAIL_IMAGE_RESOLUTION" => "16by9",
		"DETAIL_MAIN_BLOCK_OFFERS_PROPERTY_CODE" => array(),
		"DETAIL_MAIN_BLOCK_PROPERTY_CODE" => array("CML2_BAR_CODE","CML2_ARTICLE"),
		"DETAIL_META_DESCRIPTION" => "-",
		"DETAIL_META_KEYWORDS" => "-",
		"DETAIL_OFFERS_FIELD_CODE" => array("",""),
		"DETAIL_PRODUCT_INFO_BLOCK_ORDER" => "sku,props",
		"DETAIL_PRODUCT_PAY_BLOCK_ORDER" => "rating,price,priceRanges,quantityLimit,quantity,buttons",
		"DETAIL_SET_CANONICAL_URL" => "N",
		"DETAIL_SET_VIEWED_IN_COMPONENT" => "N",
		"DETAIL_SHOW_BASIS_PRICE" => "Y",
		"DETAIL_SHOW_MAX_QUANTITY" => "N",
		"DETAIL_SHOW_POPULAR" => "N",
		"DETAIL_SHOW_SLIDER" => "N",
		"DETAIL_SHOW_VIEWED" => "N",
		"DETAIL_STRICT_SECTION_CHECK" => "N",
		"DETAIL_USE_COMMENTS" => "N",
		"DETAIL_USE_VOTE_RATING" => "N",
		"DISABLE_INIT_JS_IN_COMPONENT" => "N",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"DISPLAY_TOP_PAGER" => "N",
		"ELEMENT_SORT_FIELD" => $_SESSION["ELEMENT_SORT_FIELD"],
		"ELEMENT_SORT_FIELD2" => "id",
		"ELEMENT_SORT_ORDER" => $_SESSION["ELEMENT_SORT_ORDER"],
		"ELEMENT_SORT_ORDER2" => "desc",
		"FILE_404" => "",
		"FILTER_FIELD_CODE" => array(0=>"",1=>"",),
		"FILTER_HIDE_ON_MOBILE" => "N",
		"FILTER_NAME" => "arrFilter",
		"FILTER_OFFERS_FIELD_CODE" => array(0=>"ID",1=>"CODE",2=>"XML_ID",3=>"NAME",4=>"TAGS",5=>"SORT",6=>"PREVIEW_TEXT",7=>"PREVIEW_PICTURE",8=>"DETAIL_TEXT",9=>"DETAIL_PICTURE",10=>"DATE_ACTIVE_FROM",11=>"ACTIVE_FROM",12=>"DATE_ACTIVE_TO",13=>"ACTIVE_TO",14=>"SHOW_COUNTER",15=>"SHOW_COUNTER_START",16=>"IBLOCK_TYPE_ID",17=>"IBLOCK_ID",18=>"IBLOCK_CODE",19=>"IBLOCK_NAME",20=>"IBLOCK_EXTERNAL_ID",21=>"DATE_CREATE",22=>"CREATED_BY",23=>"CREATED_USER_NAME",24=>"TIMESTAMP_X",25=>"MODIFIED_BY",26=>"USER_NAME",27=>"",),
		"FILTER_OFFERS_PROPERTY_CODE" => array(0=>"CML2_ARTICLE",1=>"CML2_BASE_UNIT",2=>"CML2_MANUFACTURER",3=>"CML2_TRAITS",4=>"CML2_TAXES",5=>"CML2_ATTRIBUTES",6=>"CML2_BAR_CODE",7=>"",),
		"FILTER_PRICE_CODE" => array(0=>"Ручная розничная цена",),
		"FILTER_PROPERTY_CODE" => array(0=>"DLYA_GRUZOVOY_I_SPETSTEKHNIKI",1=>"TIP",2=>"INDEKS_DOPUSKA_VAG",3=>"TSVET",4=>"CML2_ARTICLE",5=>"CML2_BASE_UNIT",6=>"DLYA_MOTOTEKHNIKI",7=>"CML2_MANUFACTURER",8=>"CML2_TRAITS",9=>"CML2_TAXES",10=>"CML2_ATTRIBUTES",11=>"CML2_BAR_CODE",12=>"TEMPERATURA_ZAMERZANIYA",13=>"TEMPERATURA_KIPENIYA",14=>"KONTSENTRAT",15=>"OBEM",16=>"PODDERZHKA_SISTEM_ABS_ESP",17=>"TIP_1",18=>"STANDART_DOT",19=>"TIP_2",20=>"OBLAST_PRIMENENIYA",21=>"MARKA_AVTOMOBILYA",22=>"MARKA_AVTOMOBILYA_1",23=>"MARKA_AVTOMOBILYA_2",24=>"MARKA_AVTOMOBILYA_3",25=>"KOVRIK_NA_TORPEDU",26=>"MATERIAL",27=>"DOPOLNITELNYY_TSVET",28=>"DLYA_AVTOMOBILYA",29=>"DLYA_MOTOTSIKLA",30=>"PODKHODIT_DLYA_PLANSHETA",31=>"DLYA_VELOSIPEDA",32=>"AKTIVNAYA_MOSHCHNOST_V_VATTAKH",33=>"TSOKOL_LAMPY",34=>"NOMINALNOE_NAPRYAZHENIE",35=>"OTOBRAZHENIE_RASSTOYANIYA",36=>"KAMERA_ZADNEGO_VIDA",37=>"KOLICHESTVO_DATCHIKOV",38=>"TIP_FAR",39=>"MARKA",40=>"SVETODIODNAYA",41=>"STORONA_KREPLENIYA",42=>"MARKA_1",43=>"MARKA_2",44=>"MARKA_3",45=>"MARKA_4",46=>"MARKA_5",47=>"DATCHIK_IZNOSA",48=>"SPOYLER",49=>"DLINA",50=>"KOMPLEKT",51=>"MARKA_6",52=>"ZADNYAYA_SHCHETKA",53=>"SEZONNOST",54=>"TIP_KREPLENIYA",55=>"TIP_SHCHETKI",56=>"SEZONNOST_1",57=>"OBEM_1",58=>"KONTSENTRAT_1",59=>"DLYA_MOTOTEKHNIKI_1",60=>"TIP_DVIGATELYA",61=>"TIP_UPAKOVKI",62=>"KLASS_VYAZKOSTI_SAE",63=>"STANDART_API",64=>"DLYA_GRUZOVOY_I_SPETSTEKHNIKI_1",65=>"OBEM_UPAKOVKI",66=>"DLYA_DIZELNYKH_DVIGATELEY",67=>"DLYA_TURBIROVANNYKH_DVIGATELEY",68=>"DLYA_BENZINOVYKH_DVIGATELEY",69=>"DLYA_SNEGOKHODOV",70=>"TIP_3",71=>"DIAMETR",72=>"NA_ZADNEE_SIDENE",73=>"PROPORTSII_40_60_DLYA_ZADNEGO_SIDENYA",74=>"MASSAZH",75=>"PODOGREV",76=>"KOMPLEKT_1",77=>"POTREBLYAEMAYA_MOSHCHNOST",78=>"KOLICHESTVO_PREDMETOV_NABORA",79=>"PROIZVODITELNOST",80=>"MOSHCHNOST",81=>"DLINA_KABELYA_PITANIYA",82=>"DLINA_VOZDUSHNOGO_SHLANGA",83=>"VSTROENNYY_MANOMETR",84=>"PORSHNEVOY",85=>"MAKSIMALNOE_DAVLENIE",86=>"NAPRYAZHENIE_24_B",87=>"VYSOTA_PODKHVATA",88=>"GRUZOPODEMNOST",89=>"DVUKHSTUPENCHATYY_DOMKRAT",90=>"VYSOTA_PODEMA",91=>"SUMKA_CHEKHOL_V_KOMPLEKTE",92=>"MAKSIMALNAYA_GRUZOPODEMNOST",93=>"DLINA_DUG",94=>"PROVODKA_V_KOMPLEKTE",95=>"SYEMNYY_KRYUK",96=>"S_SHUMOIZOLYATSIEY",97=>"MOSHCHNOST_MOTORA",98=>"DLINA_TROSA",99=>"VES_LEBEDKI",100=>"TYAGOVOE_USILIE",101=>"TOLSHCHINA_TROSA",102=>"DLYA_KVADROTSIKLOV",103=>"DISTANTSIONNOE_UPRAVLENIE",104=>"KOMPLEKT_2",105=>"C_OBOGREVOM",106=>"UGOL_OBZORA_DIAGONAL",107=>"DATCHIK_UDARA_G_SENSOR",108=>"RADAR_DETEKTOR",109=>"EKRAN",110=>"ZAPIS_SOBYTIYA_V_OTDELNYY_FAYL",111=>"GLONASS",112=>"GPS",113=>"WI_FI",114=>"VSTROENNYY_MIKROFON",115=>"PODDERZHKA_IPOD_IPHONE",116=>"BLUETOOTH",117=>"VKHOD_AUDIO_NA_PEREDNEY_PANELI",118=>"GLONASS_1",119=>"SENSORNYY_DISPLEY",120=>"TV_TYUNER",121=>"CD_PROIGRYVATEL",122=>"DVD_PROIGRYVATEL",123=>"USB_PORT",124=>"VYKHODNAYA_MOSHCHNOST_MAKS",125=>"GPS_1",126=>"OBEM_2",127=>"KOMPLEKT_LAMP",128=>"BIKSENON",129=>"",),
		"FILTER_VIEW_MODE" => "HORIZONTAL",
		"GIFTS_DETAIL_BLOCK_TITLE" => "Выберите один из подарков",
		"GIFTS_DETAIL_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_DETAIL_PAGE_ELEMENT_COUNT" => "4",
		"GIFTS_DETAIL_TEXT_LABEL_GIFT" => "Подарок",
		"GIFTS_MAIN_PRODUCT_DETAIL_BLOCK_TITLE" => "Выберите один из товаров, чтобы получить подарок",
		"GIFTS_MAIN_PRODUCT_DETAIL_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_MAIN_PRODUCT_DETAIL_PAGE_ELEMENT_COUNT" => "4",
		"GIFTS_MESS_BTN_BUY" => "Выбрать",
		"GIFTS_SECTION_LIST_BLOCK_TITLE" => "Подарки к товарам этого раздела",
		"GIFTS_SECTION_LIST_HIDE_BLOCK_TITLE" => "N",
		"GIFTS_SECTION_LIST_PAGE_ELEMENT_COUNT" => "4",
		"GIFTS_SECTION_LIST_TEXT_LABEL_GIFT" => "Подарок",
		"GIFTS_SHOW_DISCOUNT_PERCENT" => "Y",
		"GIFTS_SHOW_IMAGE" => "Y",
		"GIFTS_SHOW_NAME" => "Y",
		"GIFTS_SHOW_OLD_PRICE" => "Y",
		"HIDE_NOT_AVAILABLE" => "Y",
		"HIDE_NOT_AVAILABLE_OFFERS" => "Y",
		"IBLOCK_ID" => "42",
		"IBLOCK_TYPE" => "1c_catalog",
		"INCLUDE_SUBSECTIONS" => "N",
		"INSTANT_RELOAD" => "N",
		"LABEL_PROP" => array("TIP_3"),
		"LABEL_PROP_MOBILE" => array(),
		"LABEL_PROP_POSITION" => "top-left",
		"LAZY_LOAD" => "N",
		"LINE_ELEMENT_COUNT" => "3",
		"LINK_ELEMENTS_URL" => "link.php?PARENT_ELEMENT_ID=#ELEMENT_ID#",
		"LINK_IBLOCK_ID" => "",
		"LINK_IBLOCK_TYPE" => "",
		"LINK_PROPERTY_SID" => "",
		"LIST_BROWSER_TITLE" => "-",
		"LIST_ENLARGE_PRODUCT" => "STRICT",
		"LIST_META_DESCRIPTION" => "-",
		"LIST_META_KEYWORDS" => "-",
		"LIST_OFFERS_FIELD_CODE" => array("",""),
		"LIST_OFFERS_LIMIT" => "5",
		"LIST_PRODUCT_BLOCKS_ORDER" => "price,quantityLimit,props,sku,quantity,buttons",
		"LIST_PRODUCT_ROW_VARIANTS" => "[{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false}]",
		"LIST_PROPERTY_CODE" => array(0=>"DLYA_GRUZOVOY_I_SPETSTEKHNIKI",1=>"TIP",2=>"INDEKS_DOPUSKA_VAG",3=>"TSVET",4=>"CML2_ARTICLE",5=>"CML2_BASE_UNIT",6=>"DLYA_MOTOTEKHNIKI",7=>"CML2_MANUFACTURER",8=>"CML2_TRAITS",9=>"CML2_TAXES",10=>"CML2_ATTRIBUTES",11=>"CML2_BAR_CODE",12=>"TEMPERATURA_ZAMERZANIYA",13=>"TEMPERATURA_KIPENIYA",14=>"KONTSENTRAT",15=>"OBEM",16=>"PODDERZHKA_SISTEM_ABS_ESP",17=>"TIP_1",18=>"STANDART_DOT",19=>"TIP_2",20=>"OBLAST_PRIMENENIYA",21=>"MARKA_AVTOMOBILYA",22=>"MARKA_AVTOMOBILYA_1",23=>"MARKA_AVTOMOBILYA_2",24=>"MARKA_AVTOMOBILYA_3",25=>"KOVRIK_NA_TORPEDU",26=>"MATERIAL",27=>"DOPOLNITELNYY_TSVET",28=>"DLYA_AVTOMOBILYA",29=>"DLYA_MOTOTSIKLA",30=>"PODKHODIT_DLYA_PLANSHETA",31=>"DLYA_VELOSIPEDA",32=>"AKTIVNAYA_MOSHCHNOST_V_VATTAKH",33=>"TSOKOL_LAMPY",34=>"NOMINALNOE_NAPRYAZHENIE",35=>"OTOBRAZHENIE_RASSTOYANIYA",36=>"KAMERA_ZADNEGO_VIDA",37=>"KOLICHESTVO_DATCHIKOV",38=>"TIP_FAR",39=>"MARKA",40=>"SVETODIODNAYA",41=>"STORONA_KREPLENIYA",42=>"MARKA_1",43=>"MARKA_2",44=>"MARKA_3",45=>"MARKA_4",46=>"MARKA_5",47=>"DATCHIK_IZNOSA",48=>"SPOYLER",49=>"DLINA",50=>"KOMPLEKT",51=>"MARKA_6",52=>"ZADNYAYA_SHCHETKA",53=>"SEZONNOST",54=>"TIP_KREPLENIYA",55=>"TIP_SHCHETKI",56=>"SEZONNOST_1",57=>"OBEM_1",58=>"KONTSENTRAT_1",59=>"DLYA_MOTOTEKHNIKI_1",60=>"TIP_DVIGATELYA",61=>"TIP_UPAKOVKI",62=>"KLASS_VYAZKOSTI_SAE",63=>"STANDART_API",64=>"DLYA_GRUZOVOY_I_SPETSTEKHNIKI_1",65=>"OBEM_UPAKOVKI",66=>"DLYA_DIZELNYKH_DVIGATELEY",67=>"DLYA_TURBIROVANNYKH_DVIGATELEY",68=>"DLYA_BENZINOVYKH_DVIGATELEY",69=>"DLYA_SNEGOKHODOV",70=>"TIP_3",71=>"DIAMETR",72=>"NA_ZADNEE_SIDENE",73=>"PROPORTSII_40_60_DLYA_ZADNEGO_SIDENYA",74=>"MASSAZH",75=>"PODOGREV",76=>"KOMPLEKT_1",77=>"POTREBLYAEMAYA_MOSHCHNOST",78=>"KOLICHESTVO_PREDMETOV_NABORA",79=>"PROIZVODITELNOST",80=>"MOSHCHNOST",81=>"DLINA_KABELYA_PITANIYA",82=>"DLINA_VOZDUSHNOGO_SHLANGA",83=>"VSTROENNYY_MANOMETR",84=>"PORSHNEVOY",85=>"MAKSIMALNOE_DAVLENIE",86=>"NAPRYAZHENIE_24_B",87=>"VYSOTA_PODKHVATA",88=>"GRUZOPODEMNOST",89=>"DVUKHSTUPENCHATYY_DOMKRAT",90=>"VYSOTA_PODEMA",91=>"SUMKA_CHEKHOL_V_KOMPLEKTE",92=>"MAKSIMALNAYA_GRUZOPODEMNOST",93=>"DLINA_DUG",94=>"PROVODKA_V_KOMPLEKTE",95=>"SYEMNYY_KRYUK",96=>"S_SHUMOIZOLYATSIEY",97=>"MOSHCHNOST_MOTORA",98=>"DLINA_TROSA",99=>"VES_LEBEDKI",100=>"TYAGOVOE_USILIE",101=>"TOLSHCHINA_TROSA",102=>"DLYA_KVADROTSIKLOV",103=>"DISTANTSIONNOE_UPRAVLENIE",104=>"KOMPLEKT_2",105=>"C_OBOGREVOM",106=>"UGOL_OBZORA_DIAGONAL",107=>"DATCHIK_UDARA_G_SENSOR",108=>"RADAR_DETEKTOR",109=>"EKRAN",110=>"ZAPIS_SOBYTIYA_V_OTDELNYY_FAYL",111=>"GLONASS",112=>"GPS",113=>"WI_FI",114=>"VSTROENNYY_MIKROFON",115=>"PODDERZHKA_IPOD_IPHONE",116=>"BLUETOOTH",117=>"VKHOD_AUDIO_NA_PEREDNEY_PANELI",118=>"GLONASS_1",119=>"SENSORNYY_DISPLEY",120=>"TV_TYUNER",121=>"CD_PROIGRYVATEL",122=>"DVD_PROIGRYVATEL",123=>"USB_PORT",124=>"VYKHODNAYA_MOSHCHNOST_MAKS",125=>"GPS_1",126=>"OBEM_2",127=>"KOMPLEKT_LAMP",128=>"BIKSENON",129=>"",),
		"LIST_PROPERTY_CODE_MOBILE" => array(),
		"LIST_SHOW_SLIDER" => "Y",
		"LIST_SLIDER_INTERVAL" => "3000",
		"LIST_SLIDER_PROGRESS" => "N",
		"LOAD_ON_SCROLL" => "N",
		"MESSAGE_404" => "",
		"MESS_BTN_ADD_TO_BASKET" => "В корзину",
		"MESS_BTN_BUY" => "Купить",
		"MESS_BTN_COMPARE" => "Сравнение",
		"MESS_BTN_DETAIL" => "Подробнее",
		"MESS_BTN_LAZY_LOAD" => "Показать ещё",
		"MESS_BTN_SUBSCRIBE" => "Подписаться",
		"MESS_COMMENTS_TAB" => "Комментарии",
		"MESS_DESCRIPTION_TAB" => "Описание",
		"MESS_NOT_AVAILABLE" => "Нет в наличии",
		"MESS_PRICE_RANGES_TITLE" => "Цены",
		"MESS_PROPERTIES_TAB" => "Характеристики",
		"MESS_SHOW_MAX_QUANTITY" => "Наличие",
		"OFFERS_SORT_FIELD" => "sort",
		"OFFERS_SORT_FIELD2" => "id",
		"OFFERS_SORT_ORDER" => "asc",
		"OFFERS_SORT_ORDER2" => "desc",
		"OFFER_ADD_PICT_PROP" => "-",
		"OFFER_TREE_PROPS" => "",
		"PAGER_BASE_LINK_ENABLE" => "N",
		"PAGER_DESC_NUMBERING" => "N",
		"PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
		"PAGER_SHOW_ALL" => "N",
		"PAGER_SHOW_ALWAYS" => "N",
		"PAGER_TEMPLATE" => "catalog",
		"PAGER_TITLE" => "Товары",
		"PAGE_ELEMENT_COUNT" => "30",
		"PARTIAL_PRODUCT_PROPERTIES" => "Y",
		"PRICE_CODE" => array("Ручная розничная цена"),
		"PRICE_VAT_INCLUDE" => "Y",
		"PRICE_VAT_SHOW_VALUE" => "N",
		"PRODUCT_DISPLAY_MODE" => "Y",
		"PRODUCT_ID_VARIABLE" => "id",
		"PRODUCT_PROPS_VARIABLE" => "prop",
		"PRODUCT_QUANTITY_VARIABLE" => "quantity",
		"PRODUCT_SUBSCRIPTION" => "Y",
		"SEARCH_CHECK_DATES" => "Y",
		"SEARCH_NO_WORD_LOGIC" => "Y",
		"SEARCH_PAGE_RESULT_COUNT" => "50",
		"SEARCH_RESTART" => "N",
		"SEARCH_USE_LANGUAGE_GUESS" => "Y",
		"SEARCH_USE_SEARCH_RESULT_ORDER" => "N",
		"SECTIONS_HIDE_SECTION_NAME" => "N",
		"SECTIONS_SHOW_PARENT_NAME" => "Y",
		"SECTIONS_VIEW_MODE" => "TILE",
		"SECTION_ADD_TO_BASKET_ACTION" => "ADD",
		"SECTION_BACKGROUND_IMAGE" => "-",
		"SECTION_COUNT_ELEMENTS" => "N",
		"SECTION_ID_VARIABLE" => "SECTION_ID",
		"SECTION_TOP_DEPTH" => "2",
		"SEF_FOLDER" => "/catalog/",
		"SEF_MODE" => "Y",
		"SEF_URL_TEMPLATES" => Array("compare"=>"compare.php?action=#ACTION_CODE#","element"=>"#SECTION_CODE_PATH#/#ELEMENT_CODE#/","section"=>"#SECTION_CODE_PATH#/","sections"=>"","smart_filter"=>"#SECTION_CODE_PATH#/#SMART_FILTER_PATH#"),
		"SET_LAST_MODIFIED" => "N",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "Y",
		"SHOW_404" => "N",
		"SHOW_DEACTIVATED" => "N",
		"SHOW_DISCOUNT_PERCENT" => "N",
		"SHOW_MAX_QUANTITY" => "Y",
		"SHOW_OLD_PRICE" => "N",
		"SHOW_PRICE_COUNT" => "1",
		"SHOW_SKU_DESCRIPTION" => "N",
		"SHOW_TOP_ELEMENTS" => "N",
		"SIDEBAR_DETAIL_POSITION" => "right",
		"SIDEBAR_DETAIL_SHOW" => "N",
		"SIDEBAR_PATH" => "",
		"SIDEBAR_SECTION_POSITION" => "right",
		"SIDEBAR_SECTION_SHOW" => "Y",
		"TEMPLATE_THEME" => "blue",
		"TOP_ADD_TO_BASKET_ACTION" => "ADD",
		"TOP_ELEMENT_COUNT" => "9",
		"TOP_ELEMENT_SORT_FIELD" => "sort",
		"TOP_ELEMENT_SORT_FIELD2" => "id",
		"TOP_ELEMENT_SORT_ORDER" => "asc",
		"TOP_ELEMENT_SORT_ORDER2" => "desc",
		"TOP_ENLARGE_PRODUCT" => "STRICT",
		"TOP_LINE_ELEMENT_COUNT" => "3",
		"TOP_OFFERS_FIELD_CODE" => array(0=>"",1=>"",),
		"TOP_OFFERS_LIMIT" => "5",
		"TOP_PRODUCT_BLOCKS_ORDER" => "price,props,sku,quantityLimit,quantity,buttons",
		"TOP_PRODUCT_ROW_VARIANTS" => "[{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false}]",
		"TOP_SHOW_SLIDER" => "Y",
		"TOP_SLIDER_INTERVAL" => "3000",
		"TOP_SLIDER_PROGRESS" => "N",
		"TOP_VIEW_MODE" => "SECTION",
		"USER_CONSENT" => "N",
		"USER_CONSENT_ID" => "0",
		"USER_CONSENT_IS_CHECKED" => "Y",
		"USER_CONSENT_IS_LOADED" => "N",
		"USE_ALSO_BUY" => "N",
		"USE_BIG_DATA" => "N",
		"USE_COMMON_SETTINGS_BASKET_POPUP" => "N",
		"USE_COMPARE" => "N",
		"USE_ELEMENT_COUNTER" => "Y",
		"USE_ENHANCED_ECOMMERCE" => "N",
		"USE_FILTER" => "N",
		"USE_GIFTS_DETAIL" => "Y",
		"USE_GIFTS_MAIN_PR_SECTION_LIST" => "Y",
		"USE_GIFTS_SECTION" => "Y",
		"USE_MAIN_ELEMENT_SECTION" => "N",
		"USE_PRICE_COUNT" => "N",
		"USE_PRODUCT_QUANTITY" => "Y",
		"USE_REVIEW" => "N",
		"USE_SALE_BESTSELLERS" => "Y",
		"USE_STORE" => "N",
		"VARIABLE_ALIASES" => array("compare"=>array("ACTION_CODE"=>"action",),),
		"VIEWTYPE" => $_SESSION["viewtype"]
	)
);?>
<div id="avtovacatalog_lazy">
	<div class="preload_ajax">
	</div>
</div>
<?

// include 'avtocatalog/index_for_main.php';

?><?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"main-page-services",
	Array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"ADD_SECTIONS_CHAIN" => "Y",
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
		"COMPONENT_TEMPLATE" => "main-page-services",
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
		"IBLOCK_ID" => "14",
		"IBLOCK_TYPE" => "services",
		"INCLUDE_IBLOCK_INTO_CHAIN" => "Y",
		"INCLUDE_SUBSECTIONS" => "Y",
		"MESSAGE_404" => "",
		"NEWS_COUNT" => "6",
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
		"PROPERTY_CODE" => array(0=>"text",1=>"",),
		"SET_BROWSER_TITLE" => "Y",
		"SET_LAST_MODIFIED" => "N",
		"SET_META_DESCRIPTION" => "Y",
		"SET_META_KEYWORDS" => "Y",
		"SET_STATUS_404" => "N",
		"SET_TITLE" => "Y",
		"SHOW_404" => "N",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_BY2" => "SORT",
		"SORT_ORDER1" => "DESC",
		"SORT_ORDER2" => "ASC",
		"STRICT_SECTION_CHECK" => "N"
	)
);?><?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>