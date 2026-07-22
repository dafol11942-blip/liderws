<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

/**
 * @global CMain $APPLICATION
 * @var CBitrixComponent $component
 * @var array $arParams
 * @var array $arResult
 * @var array $arCurSection
 */

if (isset($arParams['USE_COMMON_SETTINGS_BASKET_POPUP']) && $arParams['USE_COMMON_SETTINGS_BASKET_POPUP'] === 'Y')
{
	$basketAction = isset($arParams['COMMON_ADD_TO_BASKET_ACTION']) ? $arParams['COMMON_ADD_TO_BASKET_ACTION'] : '';
}
else
{
	$basketAction = isset($arParams['SECTION_ADD_TO_BASKET_ACTION']) ? $arParams['SECTION_ADD_TO_BASKET_ACTION'] : '';
}
?>
	
	

	
			
<?
$sectionListParams = array(
	"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
	"IBLOCK_ID" => $arParams["IBLOCK_ID"],
	"SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
	"SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
	"CACHE_TYPE" => $arParams["CACHE_TYPE"],
	"CACHE_TIME" => $arParams["CACHE_TIME"],
	"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
	"COUNT_ELEMENTS" => $arParams["SECTION_COUNT_ELEMENTS"],
	"TOP_DEPTH" => $arParams["SECTION_TOP_DEPTH"],
	"SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
	"VIEW_MODE" => $arParams["SECTIONS_VIEW_MODE"],
	"SHOW_PARENT_NAME" => $arParams["SECTIONS_SHOW_PARENT_NAME"],
	"HIDE_SECTION_NAME" => (isset($arParams["SECTIONS_HIDE_SECTION_NAME"]) ? $arParams["SECTIONS_HIDE_SECTION_NAME"] : "N"),
	"ADD_SECTIONS_CHAIN" => (isset($arParams["ADD_SECTIONS_CHAIN"]) ? $arParams["ADD_SECTIONS_CHAIN"] : '')
);
if ($sectionListParams["COUNT_ELEMENTS"] === "Y")
{
	$sectionListParams["COUNT_ELEMENTS_FILTER"] = "CNT_ACTIVE";
	if ($arParams["HIDE_NOT_AVAILABLE"] == "Y")
	{
		$sectionListParams["COUNT_ELEMENTS_FILTER"] = "CNT_AVAILABLE";
	}
}

if(!$_REQUEST['is_ajax']){


	$show_radel = $APPLICATION->IncludeComponent(
		"bitrix:catalog.section.list",
		"",
		$sectionListParams,
		$component,
		array("HIDE_ICONS" => "Y")
	);

}

$intCount_Sections = CIBlockSection::GetCount(array('IBLOCK_ID' => $sectionListParams['IBLOCK_ID'],'SECTION_ID' => $sectionListParams['SECTION_ID']));



// debug($arResult);

unset($sectionListParams);

//  СОРТИРОВКА 


//debug($arParams);



if (isset($_GET["sort"]) && isset($_GET["method"])){

   $arParams["ELEMENT_SORT_FIELD"] = $_GET["sort"]; // name, property_PRODUCT_TYPE
   $arParams["ELEMENT_SORT_ORDER"] = $_GET["method"]; //method=asc , method= desc

}










if($intCount_Sections == 0){ ?>


<? if(!$_REQUEST['is_ajax']):?>



	<section class="main_catalog_section">
	    <div class="container df ac jsb">
	        <div class="title">

			<?$APPLICATION->ShowTitle(false)?>

			</div>
	        <div class="product__sortirovka df ac jsb">
	            <div class="product__sort">
	                <select name="sort sort_by_catalog" id="sort_by_catalog" href='<?=$arResult["SECTION_PAGE_URL"]?>'>
	                    <option class="viewtype" data-sort='name' data-method='asc' value="">По название</option>
	                    <option class="viewtype" data-sort='SCALED_PRICE_1' data-method='asc' value="">По цене</option>
	                    <option class="viewtype" data-sort='timestamp_x' data-method='asc' value="">По дате поступления</option>
	                    <option class="viewtype" data-sort='property_PRODUCT_TYPE' data-method='asc' value=""> Лидер продаж</option>
	                </select>
	            </div>
	            <div class="sort__catalog active sort__block viewtype" data-viewtypes='grid' href='<?= $arResult["SECTION_PAGE_URL"]?>'>
	                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
	                <path d="M18 12H12V17H18V12Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                <path d="M8 12H2V17H8V12Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                <path d="M18 3H12V8H18V3Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                <path d="M8 3H2V8H8V3Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                </svg>
	            </div>
	            <div class="sort__catalog sort__list viewtype" data-viewtypes='list' href='<?= $arResult["SECTION_PAGE_URL"]?>'>
	                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
	                    <path d="M16.4 12H3.6C2.71634 12 2 12.5596 2 13.25V15.75C2 16.4404 2.71634 17 3.6 17H16.4C17.2837 17 18 16.4404 18 15.75V13.25C18 12.5596 17.2837 12 16.4 12Z" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                    <path d="M16.4 3H3.6C2.71634 3 2 3.55964 2 4.25V6.75C2 7.44036 2.71634 8 3.6 8H16.4C17.2837 8 18 7.44036 18 6.75V4.25C18 3.55964 17.2837 3 16.4 3Z" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	                </svg>                        
	            </div>
	        </div>
	    </div>
	</section>




<?

$arResult["SECTION_PAGE_URL"] = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
//echo $url;

?>

  


 	<!-- <div>	
		<p class="sort">Сортировка:

		    <a data-sort='name' data-method='asc' class="viewtype" <?if ($_GET["sort"] == "name"):?> class="active" <?endif;?>
		       href="<?=$arResult["SECTION_PAGE_URL"]?>">название</a> 

		    <a data-sort='SCALED_PRICE_1'  class="viewtype" <?if ($_GET["sort"] == "SCALED_PRICE_1"):?> class="active" <?endif;?>
		       href="<?=$arResult["SECTION_PAGE_URL"]?>">цена возврстатание</a> 

	       <a data-sort='grid' data-method='asc'  class="viewtype" <?if ($_GET["sort"] == "SCALED_PRICE_1"):?> class="active" <?endif;?>
	       href="<?=$arResult["SECTION_PAGE_URL"]?>">цена убывание</a> 

		    <a data-sort='property_PRODUCT_TYPE' data-method='asc' class="viewtype" <?if ($_GET["sort"] == "property_PRODUCT_TYPE"):?> class="active" <?endif;?>
		       href="<?=$arResult["SECTION_PAGE_URL"]?>">лидер продаж</a> 

		    <a data-sort='d' data-method='asc' class="viewtype" <?if ($_GET["sort"] == "timestamp_x"):?> class="active" <?endif;?>
		       href="<?=$arResult["SECTION_PAGE_URL"]?>">дата поступления</a>

		</p>

		вид сориторовки:

		<a class="viewtype" data-viewtypes='grid' href='<?= $arResult["SECTION_PAGE_URL"]?>'>viewtype=grid - СТАНДАРТ</a>
		<a class="viewtype" data-viewtypes='list' href='<?= $arResult["SECTION_PAGE_URL"]?>'>viewtype=list - новый вид</a>

	</div>	 -->








	

	<div class="product">
		<div class="container df" style="align-items: flex-start;">




			<? if ($isFilter): ?>
				<div class="product__fulter <?=(isset($arParams['FILTER_HIDE_ON_MOBILE']) && $arParams['FILTER_HIDE_ON_MOBILE'] === 'Y' ? ' hidden-xs' : '')?>">
					<?
					$APPLICATION->IncludeComponent(
						"bitrix:catalog.smart.filter",
						"",
						array(
							"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
							"IBLOCK_ID" => $arParams["IBLOCK_ID"],
							"SECTION_ID" => $arCurSection['ID'],
							"FILTER_NAME" => $arParams["FILTER_NAME"],
							"PRICE_CODE" => $arParams["~PRICE_CODE"],
							"CACHE_TYPE" => $arParams["CACHE_TYPE"],
							"CACHE_TIME" => $arParams["CACHE_TIME"],
							"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
							"SAVE_IN_SESSION" => "N",
							"FILTER_VIEW_MODE" => $arParams["FILTER_VIEW_MODE"],
							"XML_EXPORT" => "N",
							"SECTION_TITLE" => "NAME",
							"SECTION_DESCRIPTION" => "DESCRIPTION",
							'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
							"TEMPLATE_THEME" => $arParams["TEMPLATE_THEME"],
							'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
							'CURRENCY_ID' => $arParams['CURRENCY_ID'],
							"SEF_MODE" => $arParams["SEF_MODE"],
							"SEF_RULE" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["smart_filter"],
							"SMART_FILTER_PATH" => $arResult["VARIABLES"]["SMART_FILTER_PATH"],
							"PAGER_PARAMS_NAME" => $arParams["PAGER_PARAMS_NAME"],
							"INSTANT_RELOAD" => $arParams["INSTANT_RELOAD"],
						),
						$component,
						array('HIDE_ICONS' => 'Y')
					);
					?>
				</div>
			<? endif; ?>





<!--  IS AJAX END  -->
		<? endif;?> 
<!--  IS AJAX END  -->





			<?

				 $APPLICATION->IncludeComponent(
					"bitrix:catalog.section",
					"with_filter_cat_items",
					array(
						"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
						"IBLOCK_ID" => $arParams["IBLOCK_ID"],
						"ELEMENT_SORT_FIELD" => $arParams["ELEMENT_SORT_FIELD"],
						"ELEMENT_SORT_ORDER" => $arParams["ELEMENT_SORT_ORDER"],
						"ELEMENT_SORT_FIELD2" => $arParams["ELEMENT_SORT_FIELD2"],
						"ELEMENT_SORT_ORDER2" => $arParams["ELEMENT_SORT_ORDER2"],
						"PROPERTY_CODE" => $arParams["LIST_PROPERTY_CODE"],
						"PROPERTY_CODE_MOBILE" => $arParams["LIST_PROPERTY_CODE_MOBILE"],
						"META_KEYWORDS" => $arParams["LIST_META_KEYWORDS"],
						"META_DESCRIPTION" => $arParams["LIST_META_DESCRIPTION"],
						"BROWSER_TITLE" => $arParams["LIST_BROWSER_TITLE"],
						"SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
						"INCLUDE_SUBSECTIONS" => $arParams["INCLUDE_SUBSECTIONS"],
						"BASKET_URL" => $arParams["BASKET_URL"],
						"ACTION_VARIABLE" => $arParams["ACTION_VARIABLE"],
						"PRODUCT_ID_VARIABLE" => $arParams["PRODUCT_ID_VARIABLE"],
						"SECTION_ID_VARIABLE" => $arParams["SECTION_ID_VARIABLE"],
						"PRODUCT_QUANTITY_VARIABLE" => $arParams["PRODUCT_QUANTITY_VARIABLE"],
						"PRODUCT_PROPS_VARIABLE" => $arParams["PRODUCT_PROPS_VARIABLE"],
						"FILTER_NAME" => $arParams["FILTER_NAME"],
						"CACHE_TYPE" => $arParams["CACHE_TYPE"],
						"CACHE_TIME" => $arParams["CACHE_TIME"],
						"CACHE_FILTER" => $arParams["CACHE_FILTER"],
						"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
						"SET_TITLE" => $arParams["SET_TITLE"],
						"MESSAGE_404" => $arParams["~MESSAGE_404"],
						"SET_STATUS_404" => $arParams["SET_STATUS_404"],
						"SHOW_404" => $arParams["SHOW_404"],
						"FILE_404" => $arParams["FILE_404"],
						"DISPLAY_COMPARE" => $arParams["USE_COMPARE"],
						"PAGE_ELEMENT_COUNT" => $arParams["PAGE_ELEMENT_COUNT"],
						"LINE_ELEMENT_COUNT" => $arParams["LINE_ELEMENT_COUNT"],
						"PRICE_CODE" => $arParams["~PRICE_CODE"],
						"USE_PRICE_COUNT" => $arParams["USE_PRICE_COUNT"],
						"SHOW_PRICE_COUNT" => $arParams["SHOW_PRICE_COUNT"],

						"PRICE_VAT_INCLUDE" => $arParams["PRICE_VAT_INCLUDE"],
						"USE_PRODUCT_QUANTITY" => $arParams['USE_PRODUCT_QUANTITY'],
						"ADD_PROPERTIES_TO_BASKET" => (isset($arParams["ADD_PROPERTIES_TO_BASKET"]) ? $arParams["ADD_PROPERTIES_TO_BASKET"] : ''),
						"PARTIAL_PRODUCT_PROPERTIES" => (isset($arParams["PARTIAL_PRODUCT_PROPERTIES"]) ? $arParams["PARTIAL_PRODUCT_PROPERTIES"] : ''),
						"PRODUCT_PROPERTIES" => (isset($arParams["PRODUCT_PROPERTIES"]) ? $arParams["PRODUCT_PROPERTIES"] : []),

						"DISPLAY_TOP_PAGER" => $arParams["DISPLAY_TOP_PAGER"],
						"DISPLAY_BOTTOM_PAGER" => $arParams["DISPLAY_BOTTOM_PAGER"],
						"PAGER_TITLE" => $arParams["PAGER_TITLE"],
						"PAGER_SHOW_ALWAYS" => $arParams["PAGER_SHOW_ALWAYS"],
						"PAGER_TEMPLATE" => $arParams["PAGER_TEMPLATE"],
						"PAGER_DESC_NUMBERING" => $arParams["PAGER_DESC_NUMBERING"],
						"PAGER_DESC_NUMBERING_CACHE_TIME" => $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"],
						"PAGER_SHOW_ALL" => $arParams["PAGER_SHOW_ALL"],
						"PAGER_BASE_LINK_ENABLE" => $arParams["PAGER_BASE_LINK_ENABLE"],
						"PAGER_BASE_LINK" => $arParams["PAGER_BASE_LINK"],
						"PAGER_PARAMS_NAME" => $arParams["PAGER_PARAMS_NAME"],
						"LAZY_LOAD" => $arParams["LAZY_LOAD"],
						"MESS_BTN_LAZY_LOAD" => $arParams["~MESS_BTN_LAZY_LOAD"],
						"LOAD_ON_SCROLL" => $arParams["LOAD_ON_SCROLL"],

						"OFFERS_CART_PROPERTIES" => (isset($arParams["OFFERS_CART_PROPERTIES"]) ? $arParams["OFFERS_CART_PROPERTIES"] : []),
						"OFFERS_FIELD_CODE" => $arParams["LIST_OFFERS_FIELD_CODE"],
						"OFFERS_PROPERTY_CODE" => (isset($arParams["LIST_OFFERS_PROPERTY_CODE"]) ? $arParams["LIST_OFFERS_PROPERTY_CODE"] : []),
						"OFFERS_SORT_FIELD" => $arParams["OFFERS_SORT_FIELD"],
						"OFFERS_SORT_ORDER" => $arParams["OFFERS_SORT_ORDER"],
						"OFFERS_SORT_FIELD2" => $arParams["OFFERS_SORT_FIELD2"],
						"OFFERS_SORT_ORDER2" => $arParams["OFFERS_SORT_ORDER2"],
						"OFFERS_LIMIT" => (isset($arParams["LIST_OFFERS_LIMIT"]) ? $arParams["LIST_OFFERS_LIMIT"] : 0),

						"SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
						"SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
						"SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
						"DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["element"],
						"USE_MAIN_ELEMENT_SECTION" => $arParams["USE_MAIN_ELEMENT_SECTION"],
						'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
						'CURRENCY_ID' => $arParams['CURRENCY_ID'],
						'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
						'HIDE_NOT_AVAILABLE_OFFERS' => $arParams["HIDE_NOT_AVAILABLE_OFFERS"],

						'LABEL_PROP' => $arParams['LABEL_PROP'],
						'LABEL_PROP_MOBILE' => $arParams['LABEL_PROP_MOBILE'],
						'LABEL_PROP_POSITION' => $arParams['LABEL_PROP_POSITION'],
						'ADD_PICT_PROP' => $arParams['ADD_PICT_PROP'],
						'PRODUCT_DISPLAY_MODE' => $arParams['PRODUCT_DISPLAY_MODE'],
						'PRODUCT_BLOCKS_ORDER' => $arParams['LIST_PRODUCT_BLOCKS_ORDER'],
						'PRODUCT_ROW_VARIANTS' => $arParams['LIST_PRODUCT_ROW_VARIANTS'],
						'ENLARGE_PRODUCT' => $arParams['LIST_ENLARGE_PRODUCT'],
						'ENLARGE_PROP' => isset($arParams['LIST_ENLARGE_PROP']) ? $arParams['LIST_ENLARGE_PROP'] : '',
						'SHOW_SLIDER' => $arParams['LIST_SHOW_SLIDER'],
						'SLIDER_INTERVAL' => isset($arParams['LIST_SLIDER_INTERVAL']) ? $arParams['LIST_SLIDER_INTERVAL'] : '',
						'SLIDER_PROGRESS' => isset($arParams['LIST_SLIDER_PROGRESS']) ? $arParams['LIST_SLIDER_PROGRESS'] : '',

						'OFFER_ADD_PICT_PROP' => $arParams['OFFER_ADD_PICT_PROP'],
						'OFFER_TREE_PROPS' => (isset($arParams['OFFER_TREE_PROPS']) ? $arParams['OFFER_TREE_PROPS'] : []),
						'PRODUCT_SUBSCRIPTION' => $arParams['PRODUCT_SUBSCRIPTION'],
						'SHOW_DISCOUNT_PERCENT' => $arParams['SHOW_DISCOUNT_PERCENT'],
						'DISCOUNT_PERCENT_POSITION' => $arParams['DISCOUNT_PERCENT_POSITION'],
						'SHOW_OLD_PRICE' => $arParams['SHOW_OLD_PRICE'],
						'SHOW_MAX_QUANTITY' => $arParams['SHOW_MAX_QUANTITY'],
						'MESS_SHOW_MAX_QUANTITY' => (isset($arParams['~MESS_SHOW_MAX_QUANTITY']) ? $arParams['~MESS_SHOW_MAX_QUANTITY'] : ''),
						'RELATIVE_QUANTITY_FACTOR' => (isset($arParams['RELATIVE_QUANTITY_FACTOR']) ? $arParams['RELATIVE_QUANTITY_FACTOR'] : ''),
						'MESS_RELATIVE_QUANTITY_MANY' => (isset($arParams['~MESS_RELATIVE_QUANTITY_MANY']) ? $arParams['~MESS_RELATIVE_QUANTITY_MANY'] : ''),
						'MESS_RELATIVE_QUANTITY_FEW' => (isset($arParams['~MESS_RELATIVE_QUANTITY_FEW']) ? $arParams['~MESS_RELATIVE_QUANTITY_FEW'] : ''),
						'MESS_BTN_BUY' => (isset($arParams['~MESS_BTN_BUY']) ? $arParams['~MESS_BTN_BUY'] : ''),
						'MESS_BTN_ADD_TO_BASKET' => (isset($arParams['~MESS_BTN_ADD_TO_BASKET']) ? $arParams['~MESS_BTN_ADD_TO_BASKET'] : ''),
						'MESS_BTN_SUBSCRIBE' => (isset($arParams['~MESS_BTN_SUBSCRIBE']) ? $arParams['~MESS_BTN_SUBSCRIBE'] : ''),
						'MESS_BTN_DETAIL' => (isset($arParams['~MESS_BTN_DETAIL']) ? $arParams['~MESS_BTN_DETAIL'] : ''),
						'MESS_NOT_AVAILABLE' => (isset($arParams['~MESS_NOT_AVAILABLE']) ? $arParams['~MESS_NOT_AVAILABLE'] : ''),
						'MESS_BTN_COMPARE' => (isset($arParams['~MESS_BTN_COMPARE']) ? $arParams['~MESS_BTN_COMPARE'] : ''),

						'USE_ENHANCED_ECOMMERCE' => (isset($arParams['USE_ENHANCED_ECOMMERCE']) ? $arParams['USE_ENHANCED_ECOMMERCE'] : ''),
						'DATA_LAYER_NAME' => (isset($arParams['DATA_LAYER_NAME']) ? $arParams['DATA_LAYER_NAME'] : ''),
						'BRAND_PROPERTY' => (isset($arParams['BRAND_PROPERTY']) ? $arParams['BRAND_PROPERTY'] : ''),

						'TEMPLATE_THEME' => (isset($arParams['TEMPLATE_THEME']) ? $arParams['TEMPLATE_THEME'] : ''),
						"ADD_SECTIONS_CHAIN" => "N",
						'ADD_TO_BASKET_ACTION' => $basketAction,
						'SHOW_CLOSE_POPUP' => isset($arParams['COMMON_SHOW_CLOSE_POPUP']) ? $arParams['COMMON_SHOW_CLOSE_POPUP'] : '',
						'COMPARE_PATH' => $arResult['FOLDER'].$arResult['URL_TEMPLATES']['compare'],
						'COMPARE_NAME' => $arParams['COMPARE_NAME'],
						'USE_COMPARE_LIST' => 'Y',
						'BACKGROUND_IMAGE' => (isset($arParams['SECTION_BACKGROUND_IMAGE']) ? $arParams['SECTION_BACKGROUND_IMAGE'] : ''),
						'COMPATIBLE_MODE' => (isset($arParams['COMPATIBLE_MODE']) ? $arParams['COMPATIBLE_MODE'] : ''),
						'DISABLE_INIT_JS_IN_COMPONENT' => (isset($arParams['DISABLE_INIT_JS_IN_COMPONENT']) ? $arParams['DISABLE_INIT_JS_IN_COMPONENT'] : ''),
						'WITHOUT_SECTIONS' =>  ($intCount_Sections == '0') ? 1 : 0, 
						'VIEWTYPE' => $arParams['VIEWTYPE'],
					),
					$component
				);

			?>


<? } ?>



</div>	
</div>

<?  if($intCount_Sections > 0): ?>

	<div class="container">

	<? 


		 $APPLICATION->IncludeComponent(
			"bitrix:catalog.section",
			"",
			array(
				"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
				"IBLOCK_ID" => $arParams["IBLOCK_ID"],
				"ELEMENT_SORT_FIELD" => $arParams["ELEMENT_SORT_FIELD"],
				"ELEMENT_SORT_ORDER" => $arParams["ELEMENT_SORT_ORDER"],
				"ELEMENT_SORT_FIELD2" => $arParams["ELEMENT_SORT_FIELD2"],
				"ELEMENT_SORT_ORDER2" => $arParams["ELEMENT_SORT_ORDER2"],
				"PROPERTY_CODE" => $arParams["LIST_PROPERTY_CODE"],
				"PROPERTY_CODE_MOBILE" => $arParams["LIST_PROPERTY_CODE_MOBILE"],
				"META_KEYWORDS" => $arParams["LIST_META_KEYWORDS"],
				"META_DESCRIPTION" => $arParams["LIST_META_DESCRIPTION"],
				"BROWSER_TITLE" => $arParams["LIST_BROWSER_TITLE"],
				"SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
				"INCLUDE_SUBSECTIONS" => $arParams["INCLUDE_SUBSECTIONS"],
				"BASKET_URL" => $arParams["BASKET_URL"],
				"ACTION_VARIABLE" => $arParams["ACTION_VARIABLE"],
				"PRODUCT_ID_VARIABLE" => $arParams["PRODUCT_ID_VARIABLE"],
				"SECTION_ID_VARIABLE" => $arParams["SECTION_ID_VARIABLE"],
				"PRODUCT_QUANTITY_VARIABLE" => $arParams["PRODUCT_QUANTITY_VARIABLE"],
				"PRODUCT_PROPS_VARIABLE" => $arParams["PRODUCT_PROPS_VARIABLE"],
				"FILTER_NAME" => $arParams["FILTER_NAME"],
				"CACHE_TYPE" => $arParams["CACHE_TYPE"],
				"CACHE_TIME" => $arParams["CACHE_TIME"],
				"CACHE_FILTER" => $arParams["CACHE_FILTER"],
				"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
				"SET_TITLE" => $arParams["SET_TITLE"],
				"MESSAGE_404" => $arParams["~MESSAGE_404"],
				"SET_STATUS_404" => $arParams["SET_STATUS_404"],
				"SHOW_404" => $arParams["SHOW_404"],
				"FILE_404" => $arParams["FILE_404"],
				"DISPLAY_COMPARE" => $arParams["USE_COMPARE"],
				"PAGE_ELEMENT_COUNT" => $arParams["PAGE_ELEMENT_COUNT"],
				"LINE_ELEMENT_COUNT" => $arParams["LINE_ELEMENT_COUNT"],
				"PRICE_CODE" => $arParams["~PRICE_CODE"],
				"USE_PRICE_COUNT" => $arParams["USE_PRICE_COUNT"],
				"SHOW_PRICE_COUNT" => $arParams["SHOW_PRICE_COUNT"],

				"PRICE_VAT_INCLUDE" => $arParams["PRICE_VAT_INCLUDE"],
				"USE_PRODUCT_QUANTITY" => $arParams['USE_PRODUCT_QUANTITY'],
				"ADD_PROPERTIES_TO_BASKET" => (isset($arParams["ADD_PROPERTIES_TO_BASKET"]) ? $arParams["ADD_PROPERTIES_TO_BASKET"] : ''),
				"PARTIAL_PRODUCT_PROPERTIES" => (isset($arParams["PARTIAL_PRODUCT_PROPERTIES"]) ? $arParams["PARTIAL_PRODUCT_PROPERTIES"] : ''),
				"PRODUCT_PROPERTIES" => (isset($arParams["PRODUCT_PROPERTIES"]) ? $arParams["PRODUCT_PROPERTIES"] : []),

				"DISPLAY_TOP_PAGER" => $arParams["DISPLAY_TOP_PAGER"],
				"DISPLAY_BOTTOM_PAGER" => $arParams["DISPLAY_BOTTOM_PAGER"],
				"PAGER_TITLE" => $arParams["PAGER_TITLE"],
				"PAGER_SHOW_ALWAYS" => $arParams["PAGER_SHOW_ALWAYS"],
				"PAGER_TEMPLATE" => $arParams["PAGER_TEMPLATE"],
				"PAGER_DESC_NUMBERING" => $arParams["PAGER_DESC_NUMBERING"],
				"PAGER_DESC_NUMBERING_CACHE_TIME" => $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"],
				"PAGER_SHOW_ALL" => $arParams["PAGER_SHOW_ALL"],
				"PAGER_BASE_LINK_ENABLE" => $arParams["PAGER_BASE_LINK_ENABLE"],
				"PAGER_BASE_LINK" => $arParams["PAGER_BASE_LINK"],
				"PAGER_PARAMS_NAME" => $arParams["PAGER_PARAMS_NAME"],
				"LAZY_LOAD" => $arParams["LAZY_LOAD"],
				"MESS_BTN_LAZY_LOAD" => $arParams["~MESS_BTN_LAZY_LOAD"],
				"LOAD_ON_SCROLL" => $arParams["LOAD_ON_SCROLL"],

				"OFFERS_CART_PROPERTIES" => (isset($arParams["OFFERS_CART_PROPERTIES"]) ? $arParams["OFFERS_CART_PROPERTIES"] : []),
				"OFFERS_FIELD_CODE" => $arParams["LIST_OFFERS_FIELD_CODE"],
				"OFFERS_PROPERTY_CODE" => (isset($arParams["LIST_OFFERS_PROPERTY_CODE"]) ? $arParams["LIST_OFFERS_PROPERTY_CODE"] : []),
				"OFFERS_SORT_FIELD" => $arParams["OFFERS_SORT_FIELD"],
				"OFFERS_SORT_ORDER" => $arParams["OFFERS_SORT_ORDER"],
				"OFFERS_SORT_FIELD2" => $arParams["OFFERS_SORT_FIELD2"],
				"OFFERS_SORT_ORDER2" => $arParams["OFFERS_SORT_ORDER2"],
				"OFFERS_LIMIT" => (isset($arParams["LIST_OFFERS_LIMIT"]) ? $arParams["LIST_OFFERS_LIMIT"] : 0),

				"SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
				"SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
				"SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
				"DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["element"],
				"USE_MAIN_ELEMENT_SECTION" => $arParams["USE_MAIN_ELEMENT_SECTION"],
				'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
				'CURRENCY_ID' => $arParams['CURRENCY_ID'],
				'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
				'HIDE_NOT_AVAILABLE_OFFERS' => $arParams["HIDE_NOT_AVAILABLE_OFFERS"],

				'LABEL_PROP' => $arParams['LABEL_PROP'],
				'LABEL_PROP_MOBILE' => $arParams['LABEL_PROP_MOBILE'],
				'LABEL_PROP_POSITION' => $arParams['LABEL_PROP_POSITION'],
				'ADD_PICT_PROP' => $arParams['ADD_PICT_PROP'],
				'PRODUCT_DISPLAY_MODE' => $arParams['PRODUCT_DISPLAY_MODE'],
				'PRODUCT_BLOCKS_ORDER' => $arParams['LIST_PRODUCT_BLOCKS_ORDER'],
				'PRODUCT_ROW_VARIANTS' => $arParams['LIST_PRODUCT_ROW_VARIANTS'],
				'ENLARGE_PRODUCT' => $arParams['LIST_ENLARGE_PRODUCT'],
				'ENLARGE_PROP' => isset($arParams['LIST_ENLARGE_PROP']) ? $arParams['LIST_ENLARGE_PROP'] : '',
				'SHOW_SLIDER' => $arParams['LIST_SHOW_SLIDER'],
				'SLIDER_INTERVAL' => isset($arParams['LIST_SLIDER_INTERVAL']) ? $arParams['LIST_SLIDER_INTERVAL'] : '',
				'SLIDER_PROGRESS' => isset($arParams['LIST_SLIDER_PROGRESS']) ? $arParams['LIST_SLIDER_PROGRESS'] : '',

				'OFFER_ADD_PICT_PROP' => $arParams['OFFER_ADD_PICT_PROP'],
				'OFFER_TREE_PROPS' => (isset($arParams['OFFER_TREE_PROPS']) ? $arParams['OFFER_TREE_PROPS'] : []),
				'PRODUCT_SUBSCRIPTION' => $arParams['PRODUCT_SUBSCRIPTION'],
				'SHOW_DISCOUNT_PERCENT' => $arParams['SHOW_DISCOUNT_PERCENT'],
				'DISCOUNT_PERCENT_POSITION' => $arParams['DISCOUNT_PERCENT_POSITION'],
				'SHOW_OLD_PRICE' => $arParams['SHOW_OLD_PRICE'],
				'SHOW_MAX_QUANTITY' => $arParams['SHOW_MAX_QUANTITY'],
				'MESS_SHOW_MAX_QUANTITY' => (isset($arParams['~MESS_SHOW_MAX_QUANTITY']) ? $arParams['~MESS_SHOW_MAX_QUANTITY'] : ''),
				'RELATIVE_QUANTITY_FACTOR' => (isset($arParams['RELATIVE_QUANTITY_FACTOR']) ? $arParams['RELATIVE_QUANTITY_FACTOR'] : ''),
				'MESS_RELATIVE_QUANTITY_MANY' => (isset($arParams['~MESS_RELATIVE_QUANTITY_MANY']) ? $arParams['~MESS_RELATIVE_QUANTITY_MANY'] : ''),
				'MESS_RELATIVE_QUANTITY_FEW' => (isset($arParams['~MESS_RELATIVE_QUANTITY_FEW']) ? $arParams['~MESS_RELATIVE_QUANTITY_FEW'] : ''),
				'MESS_BTN_BUY' => (isset($arParams['~MESS_BTN_BUY']) ? $arParams['~MESS_BTN_BUY'] : ''),
				'MESS_BTN_ADD_TO_BASKET' => (isset($arParams['~MESS_BTN_ADD_TO_BASKET']) ? $arParams['~MESS_BTN_ADD_TO_BASKET'] : ''),
				'MESS_BTN_SUBSCRIBE' => (isset($arParams['~MESS_BTN_SUBSCRIBE']) ? $arParams['~MESS_BTN_SUBSCRIBE'] : ''),
				'MESS_BTN_DETAIL' => (isset($arParams['~MESS_BTN_DETAIL']) ? $arParams['~MESS_BTN_DETAIL'] : ''),
				'MESS_NOT_AVAILABLE' => (isset($arParams['~MESS_NOT_AVAILABLE']) ? $arParams['~MESS_NOT_AVAILABLE'] : ''),
				'MESS_BTN_COMPARE' => (isset($arParams['~MESS_BTN_COMPARE']) ? $arParams['~MESS_BTN_COMPARE'] : ''),

				'USE_ENHANCED_ECOMMERCE' => (isset($arParams['USE_ENHANCED_ECOMMERCE']) ? $arParams['USE_ENHANCED_ECOMMERCE'] : ''),
				'DATA_LAYER_NAME' => (isset($arParams['DATA_LAYER_NAME']) ? $arParams['DATA_LAYER_NAME'] : ''),
				'BRAND_PROPERTY' => (isset($arParams['BRAND_PROPERTY']) ? $arParams['BRAND_PROPERTY'] : ''),

				'TEMPLATE_THEME' => (isset($arParams['TEMPLATE_THEME']) ? $arParams['TEMPLATE_THEME'] : ''),
				"ADD_SECTIONS_CHAIN" => "N",
				'ADD_TO_BASKET_ACTION' => $basketAction,
				'SHOW_CLOSE_POPUP' => isset($arParams['COMMON_SHOW_CLOSE_POPUP']) ? $arParams['COMMON_SHOW_CLOSE_POPUP'] : '',
				'COMPARE_PATH' => $arResult['FOLDER'].$arResult['URL_TEMPLATES']['compare'],
				'COMPARE_NAME' => $arParams['COMPARE_NAME'],
				'USE_COMPARE_LIST' => 'Y',
				'BACKGROUND_IMAGE' => (isset($arParams['SECTION_BACKGROUND_IMAGE']) ? $arParams['SECTION_BACKGROUND_IMAGE'] : ''),
				'COMPATIBLE_MODE' => (isset($arParams['COMPATIBLE_MODE']) ? $arParams['COMPATIBLE_MODE'] : ''),
				'DISABLE_INIT_JS_IN_COMPONENT' => (isset($arParams['DISABLE_INIT_JS_IN_COMPONENT']) ? $arParams['DISABLE_INIT_JS_IN_COMPONENT'] : ''),
				'WITHOUT_SECTIONS' =>  ($intCount_Sections == '0') ? 1 : 0, 
			),
			$component
		);
	?>

	</div>


<? endif; ?>




