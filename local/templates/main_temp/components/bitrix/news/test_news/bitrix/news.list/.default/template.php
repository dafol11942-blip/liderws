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




<section class="novosti_section">
    <div class="container">


        <div class="novosti">





            



<div class="news-list">
<?if($arParams["DISPLAY_TOP_PAGER"]):?>
	<?=$arResult["NAV_STRING"]?><br />
<?endif;?>
<?foreach($arResult["ITEMS"] as $arItem):?>
	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>





	 <div class="novosti_item">
			<div class="item_image">
				<img
					class="preview_picture"
					border="0"
					src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
					width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
					height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
					alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
					title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>"
					style="float:left"
					/>
			</div>
			<div class="item_text">
				<div class="item_name"><?echo $arItem["NAME"]?></div>
				<div class="text">			<?echo $arItem["PREVIEW_TEXT"];?></div>
				<a class="item_link" href="<?=$arItem["DETAIL_PAGE_URL"]?>">Подробнее</a>
			</div>
		</div>







<?endforeach;?>
<?if($arParams["DISPLAY_BOTTOM_PAGER"]):?>
	<br /><?=$arResult["NAV_STRING"]?>
<?endif;?>
 </div>




    </div>
</section>



<style>



.novosti_item {
  display: flex;
  margin-top: 3.5rem;
}

.item_name {
  /* margin-bottom: 2rem; */
  font-weight: 600;
  /* margin-top: 3.5rem; */
}

.text { 
  margin-top: 1.5rem;
}

.item_link {
  text-decoration: none;
  margin-top: 0.5rem;
  color: blue;
}
</style>
