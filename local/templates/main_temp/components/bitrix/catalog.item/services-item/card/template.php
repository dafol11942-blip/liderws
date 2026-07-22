<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Localization\Loc;

/**
 * @global CMain $APPLICATION
 * @var array $arParams
 * @var array $item
 * @var array $actualItem
 * @var array $minOffer
 * @var array $itemIds
 * @var array $price
 * @var array $measureRatio
 * @var bool $haveOffers
 * @var bool $showSubscribe
 * @var array $morePhoto
 * @var bool $showSlider
 * @var bool $itemHasDetailUrl
 * @var string $imgTitle
 * @var string $productTitle
 * @var string $buttonSizeClass
 * @var CatalogSectionComponent $component
 */
?>



<!-- <div class="news__item" id="<?=$this->GetEditAreaId($arItem['ID']);?>"> -->
    <div class="news__left">
    	<a href="<?=$item["DETAIL_PAGE_URL"]?>">
    		<img
			class=""
			border="0"
			src="<?=$item['PREVIEW_PICTURE']['SRC']?>"
			width="<?=$item["PREVIEW_PICTURE"]["WIDTH"]?>"
			height="<?=$item["PREVIEW_PICTURE"]["HEIGHT"]?>"
			alt="<?=$item["PREVIEW_PICTURE"]["ALT"]?>"
			title="<?=$item["PREVIEW_PICTURE"]["TITLE"]?>"
			style="float:left"
			/>
		</a>
        <!-- <img src="images/news__item3.png" alt=""> -->
    </div>
    <div class="news__right">
        <div class="df jsb">
            <div class="news__tit"><?echo $item["NAME"]?></div>
            <div class="news__data"><?echo $item["DISPLAY_ACTIVE_FROM"]?></div>
        </div>
        <div class="news__text"><?echo $item["PREVIEW_TEXT"];?>…</div>
        <a href="<?=$item["DETAIL_PAGE_URL"]?>" class="news__btn">Подробнее</a>
    </div>
<!-- </div> -->

