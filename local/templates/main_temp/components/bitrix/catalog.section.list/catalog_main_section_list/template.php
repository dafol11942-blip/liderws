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

//echo "string TEST";


$arViewModeList = $arResult['VIEW_MODE_LIST'];

$arViewStyles = array(
	'LIST' => array(
		'CONT' => 'bx_sitemap',
		'TITLE' => 'bx_sitemap_title',
		'LIST' => 'bx_sitemap_ul',
	),
	'LINE' => array(
		'CONT' => 'bx_catalog_line',
		'TITLE' => 'bx_catalog_line_category_title',
		'LIST' => 'bx_catalog_line_ul',
		'EMPTY_IMG' => $this->GetFolder().'/images/line-empty.png'
	),
	'TEXT' => array(
		'CONT' => 'bx_catalog_text',
		'TITLE' => 'bx_catalog_text_category_title',
		'LIST' => 'bx_catalog_text_ul'
	),
	'TILE' => array(
		'CONT' => 'bx_catalog_tile',
		'TITLE' => 'bx_catalog_tile_category_title',
		'LIST' => 'bx_catalog_tile_ul',
		'EMPTY_IMG' => $this->GetFolder().'/images/tile-empty.png'
	)
);
$arCurView = $arViewStyles[$arParams['VIEW_MODE']];

$strSectionEdit = CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "SECTION_EDIT");
$strSectionDelete = CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "SECTION_DELETE");
$arSectionDeleteParams = array("CONFIRM" => GetMessage('CT_BCSL_ELEMENT_DELETE_CONFIRM'));

?>
<!-- 211111111111111111111111111111111111111 -->

<div class="<? echo $arCurView['CONT']; ?>">


 <section class="main-catalog">
        <div class="container">

        	<?
				if ('Y' == $arParams['SHOW_PARENT_NAME'] && 0 < $arResult['SECTION']['ID'])
				{
					$this->AddEditAction($arResult['SECTION']['ID'], $arResult['SECTION']['EDIT_LINK'], $strSectionEdit);
					$this->AddDeleteAction($arResult['SECTION']['ID'], $arResult['SECTION']['DELETE_LINK'], $strSectionDelete, $arSectionDeleteParams);

					?><h1
						class="<? //echo $arCurView['TITLE']; ?>"
						id="<? echo $this->GetEditAreaId($arResult['SECTION']['ID']); ?>"
					><a href="<? echo $arResult['SECTION']['SECTION_PAGE_URL']; ?>"><?
						echo (
							isset($arResult['SECTION']["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"]) && $arResult['SECTION']["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"] != ""
							? $arResult['SECTION']["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"]
							: $arResult['SECTION']['NAME']
						);
					?></a></h1><?
				}
				if (0 < $arResult["SECTIONS_COUNT"])
				{

			?>



            <div class="catalog__inner <? echo $arCurView['LIST']; ?>" >

                <a href="#" class="catalog__item catalog__item-first">
                    <div class="catalog__text">Запчасти 
                        для технического обслуживания</div>
                    <div class="catalog__img">
                        <img src="images/catalog__item_1.png" alt="">
                    </div>
                </a>
                <div class="catalog-wrapper catalog-wrapper_3">

                	<?
                	foreach ($arResult['SECTIONS'] as &$arSection){

						$this->AddEditAction($arSection['ID'], $arSection['EDIT_LINK'], $strSectionEdit);
						$this->AddDeleteAction($arSection['ID'], $arSection['DELETE_LINK'], $strSectionDelete, $arSectionDeleteParams);

						if (false === $arSection['PICTURE'])
							$arSection['PICTURE'] = array(
								'SRC' => $arCurView['EMPTY_IMG'],
								'ALT' => (
									'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
									? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
									: $arSection["NAME"]
								),
								'TITLE' => (
									'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
									? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
									: $arSection["NAME"]
								)
							);
						?>

						<a href='<? echo $arSection['SECTION_PAGE_URL']; ?>' id="<? echo $this->GetEditAreaId($arSection['ID']); ?>" class="catalog__item">
	                        <div class="catalog__text"><? echo $arSection['NAME']; ?></div>
	                        <div class="catalog__img">
	                            <img src="<? echo $arSection['PICTURE']['SRC']; ?>" alt="<? echo $arSection['PICTURE']['TITLE']; ?>">
	                        </div>
	                    </a>

						<?
							if ($arParams["COUNT_ELEMENTS"] && $arSection['ELEMENT_CNT'] !== null)
							{
								?> <span>(<? echo $arSection['ELEMENT_CNT']; ?>)</span><?
							}

						?>
						<?
					}
						unset($arSection);

                	?>
                </div>
            </div>
        </div>
    </section>









<?
	echo ('LINE' != $arParams['VIEW_MODE'] ? '<div style="clear: both;"></div>' : '');
}
?></div>