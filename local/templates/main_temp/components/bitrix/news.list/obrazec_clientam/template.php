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
$arResult = CreateSections_AddItems($arResult);

// debug($arResult);

?>



<div class="right__inner">
	<?foreach($arResult["SECTION"] as $arSectionItem):?>



                <div class="right__tit"><? echo $arSectionItem['NAME']?></div>
                <div class="obras__inner">

                	<?foreach($arSectionItem["ITEMS"] as $arItem):?>

                	<?
					$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
					$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
					?>

		                <div class="obras__item"  id="<?=$this->GetEditAreaId($arItem['ID']);?>">
		                        <div>
		                            <img src="/local/templates/main_temp/images/pdg.png" alt="">
		                        </div>
		                        <a href="<? echo $arItem['DISPLAY_PROPERTIES']['DOWNLOAD']['FILE_VALUE']['SRC']?>" download class="obras__tit"><? echo $arItem['NAME'];?></a>

		                        <?

		                        $size = $arItem['DISPLAY_PROPERTIES']['DOWNLOAD']['FILE_VALUE']['FILE_SIZE'];

		                        ?>


		                        <p><? echo GetStrFileSize($size,2);?>, <?= GetFileExtension($arItem['DISPLAY_PROPERTIES']['DOWNLOAD']['FILE_VALUE']['SRC']);?></p>
		                       

		                </div>
		                    
		              

                <?endforeach;?>


                </div>

    <?endforeach;?>


</div>










		