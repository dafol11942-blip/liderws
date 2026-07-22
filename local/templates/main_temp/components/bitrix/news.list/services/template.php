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

<div class="right__inner">
                <!-- <div class="right__tit">Статьи</div> -->
                <div class="news__statie">

                <?foreach($arResult["ITEMS"] as $arItem):?>

	                <?
						$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
						$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
					?>
	                    <div class="news__item" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
	                        <div class="news__left">
	                        	<a href="<?=$arItem["DETAIL_PAGE_URL"]?>">
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
	                            <!-- <img src="images/news__item3.png" alt=""> -->
	                        </div>
	                        <div class="news__right">
	                            <div class="df jsb">
	                                <div class="news__tit"><?echo $arItem["NAME"]?></div>
	                                <div class="news__data"><?echo $arItem["DISPLAY_ACTIVE_FROM"]?></div>
	                            </div>
	                            <div class="news__text"><?echo $arItem["PREVIEW_TEXT"];?>…</div>
	                            <a href="<?=$arItem["DETAIL_PAGE_URL"]?>" class="news__btn">Подробнее</a>
	                        </div>
	                    </div>

	            <? endforeach; ?>

                </div>
                <div class="container">
                    <?if($arParams["DISPLAY_BOTTOM_PAGER"]):?>
                        <br /><?=$arResult["NAV_STRING"]?>
                    <?endif;?>
                   
                </div>
            </div>






   </div>