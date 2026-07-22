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

 <section class="main-banner">
        <div class="container">
            <div class="main-banner__inner swiper-container">
                <div class="swiper-wrapper">

                <?foreach($arResult["ITEMS"] as $arItem):?>

                    <?
                    $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                    $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                    ?>

                        <div id="<?=$this->GetEditAreaId($arItem['ID']);?>" class="main-banner__item swiper-slide">
                            <a class="not-active-desctop" href="<? echo $arItem['PREVIEW_TEXT'] ?>">
                                <img
                                class=""
                                border="0"
                                src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
                                width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
                                height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
                                alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
                                title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>"
                                style="float:left"
                                />
                            </a>
                            <div class="banner__text"><? echo $arItem['NAME'];?></div>
                            <a href="<? echo $arItem['PREVIEW_TEXT'] ?>" class="btn bg-red">Подробнее</a>
                        </div>


                <?endforeach;?>

                </div>
                <div class="swiper-buttons">
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
                <div class="swiper-pagination main__pagination"></div>
            </div>
        </div>
    </section>