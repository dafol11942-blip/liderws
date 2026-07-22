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
$this->setFrameMode(true);


$arParams['SECTION_ID'] = $arResult["VARIABLES"]["SECTION_ID"]; 



?>

<section class="">
    <div class="container df ac ">
        <a class="product__back" href="<?echo $arResult['URL_TEMPLATES']['news']?>">
            <div class="df ac">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.8867 11.3332C10.0109 11.2083 10.0806 11.0393 10.0806 10.8632C10.0806 10.6871 10.0109 10.5181 9.8867 10.3932L7.5267 7.99988L9.8867 5.63988C10.0109 5.51498 10.0806 5.34601 10.0806 5.16988C10.0806 4.99376 10.0109 4.82479 9.8867 4.69988C9.82473 4.6374 9.751 4.5878 9.66976 4.55396C9.58852 4.52011 9.50138 4.50269 9.41337 4.50269C9.32536 4.50269 9.23823 4.52011 9.15699 4.55396C9.07575 4.5878 9.00201 4.6374 8.94004 4.69988L6.11337 7.52655C6.05088 7.58853 6.00129 7.66226 5.96744 7.7435C5.9336 7.82474 5.91617 7.91188 5.91617 7.99988C5.91617 8.08789 5.9336 8.17503 5.96744 8.25627C6.00129 8.33751 6.05088 8.41124 6.11337 8.47322L8.94004 11.3332C9.00201 11.3957 9.07575 11.4453 9.15699 11.4791C9.23823 11.513 9.32536 11.5304 9.41337 11.5304C9.50138 11.5304 9.58852 11.513 9.66976 11.4791C9.751 11.4453 9.82473 11.3957 9.8867 11.3332Z" fill="#668BEA"></path>
                </svg>
            </div>
            <div>Назад</div>
        </a>
        <div class="title">
        	<?

        		// debug($arParams);

        	if (CModule::IncludeModule("iblock")){

        		$res = CIBlockSection::GetByID(6);
       			
       			$ar_res = $res->GetNext();

       				// debug($ar_res);
       				echo $ar_res["NAME"];

        	}

		
        	?>
        </div> 
    </div>
</section>

<? //debug($arResult);?>


<div class="container">
<?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"services",
	Array(
		"IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
		"IBLOCK_ID" => $arParams["IBLOCK_ID"],
		"SECTION_ID" => $arParams["SECTION_ID"],
		"NEWS_COUNT" => $arParams["NEWS_COUNT"],
		"SORT_BY1" => $arParams["SORT_BY1"],
		"SORT_ORDER1" => $arParams["SORT_ORDER1"],
		"SORT_BY2" => $arParams["SORT_BY2"],
		"SORT_ORDER2" => $arParams["SORT_ORDER2"],
		"FIELD_CODE" => $arParams["LIST_FIELD_CODE"],
		"PROPERTY_CODE" => $arParams["LIST_PROPERTY_CODE"],
		"DISPLAY_PANEL" => $arParams["DISPLAY_PANEL"],
		"SET_TITLE" => $arParams["SET_TITLE"],
		"SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
		"MESSAGE_404" => $arParams["MESSAGE_404"],
		"SET_STATUS_404" => $arParams["SET_STATUS_404"],
		"SHOW_404" => $arParams["SHOW_404"],
		"FILE_404" => $arParams["FILE_404"],
		"INCLUDE_IBLOCK_INTO_CHAIN" => $arParams["INCLUDE_IBLOCK_INTO_CHAIN"],
		"ADD_SECTIONS_CHAIN" => $arParams["ADD_SECTIONS_CHAIN"],
		"CACHE_TYPE" => $arParams["CACHE_TYPE"],
		"CACHE_TIME" => $arParams["CACHE_TIME"],
		"CACHE_FILTER" => $arParams["CACHE_FILTER"],
		"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
		"DISPLAY_TOP_PAGER" => $arParams["DISPLAY_TOP_PAGER"],
		"DISPLAY_BOTTOM_PAGER" => $arParams["DISPLAY_BOTTOM_PAGER"],
		"PAGER_TITLE" => $arParams["PAGER_TITLE"],
		"PAGER_TEMPLATE" => $arParams["PAGER_TEMPLATE"],
		"PAGER_SHOW_ALWAYS" => $arParams["PAGER_SHOW_ALWAYS"],
		"PAGER_DESC_NUMBERING" => $arParams["PAGER_DESC_NUMBERING"],
		"PAGER_DESC_NUMBERING_CACHE_TIME" => $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"],
		"PAGER_SHOW_ALL" => $arParams["PAGER_SHOW_ALL"],
		"PAGER_BASE_LINK_ENABLE" => $arParams["PAGER_BASE_LINK_ENABLE"],
		"PAGER_BASE_LINK" => $arParams["PAGER_BASE_LINK"],
		"PAGER_PARAMS_NAME" => $arParams["PAGER_PARAMS_NAME"],
		"DISPLAY_DATE" => $arParams["DISPLAY_DATE"],
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => $arParams["DISPLAY_PICTURE"],
		"DISPLAY_PREVIEW_TEXT" => $arParams["DISPLAY_PREVIEW_TEXT"],
		"PREVIEW_TRUNCATE_LEN" => $arParams["PREVIEW_TRUNCATE_LEN"],
		"ACTIVE_DATE_FORMAT" => $arParams["LIST_ACTIVE_DATE_FORMAT"],
		"USE_PERMISSIONS" => $arParams["USE_PERMISSIONS"],
		"GROUP_PERMISSIONS" => $arParams["GROUP_PERMISSIONS"],
		"FILTER_NAME" => $arParams["FILTER_NAME"],
		"HIDE_LINK_WHEN_NO_DETAIL" => $arParams["HIDE_LINK_WHEN_NO_DETAIL"],
		"CHECK_DATES" => $arParams["CHECK_DATES"],
		"STRICT_SECTION_CHECK" => $arParams["STRICT_SECTION_CHECK"],

		"PARENT_SECTION" => $arResult["VARIABLES"]["SECTION_ID"],
		"PARENT_SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
		"DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["detail"],
		"SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
		"IBLOCK_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["news"],
	),
	$component
);?>


</div>