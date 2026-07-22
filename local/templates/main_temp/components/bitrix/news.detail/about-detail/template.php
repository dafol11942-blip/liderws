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
<div class="news-detail">
 <b><?=$arResult["NAME"]?></b>
<div class="detial__image image__container-full">
<img

	src="<?=$arResult["DETAIL_PICTURE"]["SRC"]?>"
	width="<?//=$arResult["DETAIL_PICTURE"]["WIDTH"]?>"
	height="<?//=$arResult["DETAIL_PICTURE"]["HEIGHT"]?>"
	alt="<?=$arResult["DETAIL_PICTURE"]["ALT"]?>"
	title="<?=$arResult["DETAIL_PICTURE"]["TITLE"]?>"
/>
	<div class="lypa__svg">
	</div>
</div>
<div class="text">
<?echo $arResult["PREVIEW_TEXT"];?>
</div>

</div>