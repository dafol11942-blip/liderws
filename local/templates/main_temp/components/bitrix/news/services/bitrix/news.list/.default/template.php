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
?>

<?
	
	

	$arResult = CreateSections_AddItems($arResult);

//debug($arResult);


?>










<section class="main-catalog">
    <div class="container">
        <div class="catalog__inner">

        	<?foreach($arResult["ITEMS"] as $arItem):?>

        		<?
					$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
					$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
				?>




        		<? if($arItem['ID'] == 6):?>

        			 <a href="<?echo $arItem['SECTION_PAGE_URL']?>"  id="<?=$this->GetEditAreaId($arItem['ID']);?>" class="catalog__item catalog__item-first">
	                    <div class="catalog__text"><?echo $arItem["NAME"]?></div>
	                    <div class="catalog__img">
	                       <img
							class=""
							src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
							width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
							height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
							alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
							title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>"
							/>
	                    </div>
	                </a>


        		<? endif;?>


        	<? endforeach; ?>

            


            <div class="catalog-wrapper catalog-wrapper_3">

            

				<?foreach($arResult["SECTION"] as $arItem):?>
					<?
					$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
					$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
					?>

					<? if($arItem['ID'] != 6):?>

					<a href="<?echo $arItem['SECTION_PAGE_URL']?>"  id="<?=$this->GetEditAreaId($arItem['ID']);?>" class="catalog__item">
	                    <div class="catalog__text"><?echo $arItem["NAME"]?></div>
	                    <div class="catalog__img">
	                        <img
							class=""
							src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
							width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
							height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
							alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
							title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>"
							/>
	                    </div>
	                </a>

	            	<? endif;?>


					
				<?endforeach;?>
               
               
            </div>
        </div>
    </div>
</section>





