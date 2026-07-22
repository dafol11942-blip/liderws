<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

$this->setFrameMode(true);
$this->addExternalCss("/bitrix/css/main/bootstrap.css");




global $arrFilter;

if($_REQUEST["PROPERTY_IN_STOCK"] == "Y"){
    $arrFilter[">CATALOG_QUANTITY"] = 0;
}






if (!isset($arParams['FILTER_VIEW_MODE']) || (string)$arParams['FILTER_VIEW_MODE'] == '')
	$arParams['FILTER_VIEW_MODE'] = 'VERTICAL';
$arParams['USE_FILTER'] = (isset($arParams['USE_FILTER']) && $arParams['USE_FILTER'] == 'Y' ? 'Y' : 'N');

$isVerticalFilter = ('Y' == $arParams['USE_FILTER'] && $arParams["FILTER_VIEW_MODE"] == "VERTICAL");
$isSidebar = ($arParams["SIDEBAR_SECTION_SHOW"] == "Y" && isset($arParams["SIDEBAR_PATH"]) && !empty($arParams["SIDEBAR_PATH"]));
$isFilter = ($arParams['USE_FILTER'] == 'Y');

// $arParams['main_title'] = $arResult["NAME"];


// debug($arParams);

if ($isFilter)
{
	$arFilter = array(
		"IBLOCK_ID" => $arParams["IBLOCK_ID"],
		"ACTIVE" => "Y",
		"GLOBAL_ACTIVE" => "Y",
	);
	if (0 < intval($arResult["VARIABLES"]["SECTION_ID"]))
		$arFilter["ID"] = $arResult["VARIABLES"]["SECTION_ID"];
	elseif ('' != $arResult["VARIABLES"]["SECTION_CODE"])
		$arFilter["=CODE"] = $arResult["VARIABLES"]["SECTION_CODE"];

	$obCache = new CPHPCache();
	if ($obCache->InitCache(36000, serialize($arFilter), "/iblock/catalog"))
	{
		$arCurSection = $obCache->GetVars();
	}
	elseif ($obCache->StartDataCache())
	{
		$arCurSection = array();
		if (Loader::includeModule("iblock"))
		{
			$dbRes = CIBlockSection::GetList(array(), $arFilter, false, array("ID"));

			if(defined("BX_COMP_MANAGED_CACHE"))
			{
				global $CACHE_MANAGER;
				$CACHE_MANAGER->StartTagCache("/iblock/catalog");

				if ($arCurSection = $dbRes->Fetch())
					$CACHE_MANAGER->RegisterTag("iblock_id_".$arParams["IBLOCK_ID"]);

				$CACHE_MANAGER->EndTagCache();
			}
			else
			{
				if(!$arCurSection = $dbRes->Fetch())
					$arCurSection = array();
			}
		}
		$obCache->EndDataCache($arCurSection);
	}
	if (!isset($arCurSection))
		$arCurSection = array();
}

$intCount_Sections = CIBlockSection::GetCount(array('IBLOCK_ID' => 42,'SECTION_ID' => $arResult['VARIABLES']['SECTION_ID']));

?>




<?

if($_REQUEST['viewtype']){
	$_SESSION['viewtype'] = $_REQUEST['viewtype'];
}else if(isset($_SESSION['viewtype'])){
	$_SESSION['viewtype'] = $_SESSION['viewtype'];
}else {
	$_SESSION['viewtype'] = 'list';
}





if($_REQUEST['ELEMENT_SORT_FIELD'] && $_REQUEST['ELEMENT_SORT_ORDER']){
	$_SESSION['ELEMENT_SORT_FIELD'] = $_REQUEST['ELEMENT_SORT_FIELD'];
	$_SESSION['ELEMENT_SORT_ORDER'] = $_REQUEST['ELEMENT_SORT_ORDER'];
}else {
	$_SESSION['ELEMENT_SORT_FIELD'] = "NAME";
	$_SESSION['ELEMENT_SORT_ORDER'] = "asc";
}



?>















<? 

$catalog_url =  explode('/', $APPLICATION->GetCurPage());

?>





<? if(count($catalog_url) > 4 && $intCount_Sections == '0' && $_REQUEST['is_ajax'] != 1): ?>


	<?
		$res = CIBlockSection::GetByID($arResult['VARIABLES']['SECTION_ID']);
		if($ar_res = $res->GetNext())
		  $parent_section_id = $ar_res['IBLOCK_SECTION_ID'];
	?>




  

<section class="similar-categories">
    <div class="container">
        <div class="similar-categories__inner">
            <?
            error_log('$parent_section_id: ' . $parent_section_id);

            $APPLICATION->IncludeComponent(
                "bitrix:catalog.section.list",
                "header_sections_sub",
                array(
                    "ADD_SECTIONS_CHAIN" => "N",
                    "CACHE_FILTER" => "N",
                    "CACHE_GROUPS" => "Y",
                    "CACHE_TIME" => "36000000",
                    "CACHE_TYPE" => "A",
                    "COUNT_ELEMENTS" => "Y",
                    "COUNT_ELEMENTS_FILTER" => "CNT_ACTIVE",
                    "FILTER_NAME" => "sectionsFilter",
                    "IBLOCK_ID" => "42",
                    "IBLOCK_TYPE" => "1c_catalog",
                    "SECTION_CODE" => "",
                    "SECTION_FIELDS" => array("", ""),
                    "SECTION_ID" => $parent_section_id,
                    "SECTION_URL" => "#SITE_DIR#/catalog/#SECTION_CODE_PATH#/",
                    "SECTION_USER_FIELDS" => array("UF_LINK", ""),
                    "SHOW_PARENT_NAME" => "Y",
                    "TOP_DEPTH" => "2",
                    "VIEW_MODE" => "TEXT"
                )
            );
            ?>
        </div>
    </div>
</section>

<? endif;?>







	<!-- ЕСЛИ БОЛЬШЕ НЕТУ РАЗДЕЛОВ ВЛКЮЧАЕМ ФИЛЬТР -->
	<? if($intCount_Sections == '0'):?>

		<?

		// debug($arParams);

		?>

		<? include($_SERVER["DOCUMENT_ROOT"]."/".$this->GetFolder()."/section_horizontal.php"); ?>

	<? else:?>	

		<?	// ВЫКЛЛЮЧЧАЕМ ФИЛЬТР, стр без фильтров  работает !

			$isFilter = false;
			$isSidebar = false;

		?>

		<? include($_SERVER["DOCUMENT_ROOT"]."/".$this->GetFolder()."/section_vertical.php"); ?>

	<? endif;?>

