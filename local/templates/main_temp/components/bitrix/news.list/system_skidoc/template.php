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


<section class="main-catalog system__discounts">
        <div class="container">
            <div class="catalog__inner">

            	<?foreach($arResult["ITEMS"] as $arItem):?>

            		<?
						$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
						$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
					?>




            		<? if($arItem['ID'] == 40):?>

            			<div class="catalog__item catalog__item-first" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
	                    <div class="catalog__text"><?echo $arItem["NAME"]?></div>
	                    <div class="catalog__ltext"><?echo $arItem["PREVIEW_TEXT"];?></div>
	                    <div class="catalog__bottom-auto">
	                        <div>
	                            <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
	                                <g clip-path="url(#clip0)">
	                                <path d="M3.49996 0H35.3618L52.2499 16.8226V56.25C52.2499 58.3218 50.57 60 48.4999 60H3.49996C1.42991 60 -0.25 58.3218 -0.25 56.25V3.74996C-0.25 1.67816 1.4301 0 3.49996 0Z" fill="#E2E2E2"></path>
	                                <path d="M52.1951 16.8749H39.1245C37.0544 16.8749 35.3745 15.1949 35.3745 13.1249V0.0373535L52.1951 16.8749Z" fill="#C4C4C4"></path>
	                                <path d="M38.1831 28.4306C38.8112 28.4306 39.1187 27.8831 39.1187 27.3525C39.1187 26.8031 38.7981 26.2725 38.1831 26.2725H34.6056C33.9062 26.2725 33.5162 26.8518 33.5162 27.4912V36.283C33.5162 37.0668 33.9624 37.5018 34.5662 37.5018C35.1662 37.5018 35.6144 37.0668 35.6144 36.283V33.87H37.7782C38.4494 33.87 38.7851 33.3205 38.7851 32.775C38.7851 32.2407 38.4494 31.7099 37.7782 31.7099H35.6144V28.4306H38.1831ZM26.0912 26.2725H23.4736C22.763 26.2725 22.2586 26.76 22.2586 27.4836V36.2906C22.2586 37.1887 22.9841 37.47 23.5035 37.47H26.2505C29.5016 37.47 31.6485 35.3307 31.6485 32.0287C31.6468 28.5375 29.6256 26.2725 26.0912 26.2725ZM26.2169 35.2988H24.6212V28.4438H26.0594C28.2363 28.4438 29.1831 29.9045 29.1831 31.92C29.1831 33.8063 28.253 35.2988 26.2169 35.2988ZM16.6281 26.2725H14.035C13.3018 26.2725 12.8931 26.7561 12.8931 27.4912V36.283C12.8931 37.0668 13.3618 37.5018 13.9917 37.5018C14.6217 37.5018 15.0904 37.0668 15.0904 36.283V33.7161H16.716C18.7222 33.7161 20.3779 32.2948 20.3779 30.0092C20.3781 27.7725 18.7806 26.2725 16.6281 26.2725ZM16.585 31.6538H15.0906V28.3369H16.585C17.5075 28.3369 18.0944 29.0569 18.0944 29.9962C18.0925 30.9339 17.5075 31.6538 16.585 31.6538Z" fill="white"></path>
	                                </g>
	                                <defs>
	                                <clipPath id="clip0">
	                                <rect width="60" height="60" fill="white"></rect>
	                                </clipPath>
	                                </defs>
	                                </svg>                            
	                        </div>
	                        <a download="" href="<?echo $arItem['DISPLAY_PROPERTIES']['DOWNLOAD']['FILE_VALUE']['SRC'];?>" class="btn bg-red">Скачать прайс-лист</a>
	                    </div>
	                    <!-- <div class="catalog__img">
	                        <img src="images/catalog__item_1.png" alt="">
	                    </div> -->
	                </div>

            		<? endif;?>

            	<? endforeach; ?>

                


                <div class="catalog-wrapper catalog-wrapper_3">

                



					<?foreach($arResult["ITEMS"] as $arItem):?>
						<?
						$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
						$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
						?>

						<? if($arItem['ID'] != 40):?>

						<?//download="" href="<?echo $arItem['DISPLAY_PROPERTIES']['DOWNLOAD']['FILE_VALUE']['SRC'];?>

						<div id="<?=$this->GetEditAreaId($arItem['ID']);?>"  class="catalog__item">
		                    <div class="catalog__text"><?echo $arItem["NAME"]?> </div>
		                    <div class="catalog__ltext"><?echo $arItem["PREVIEW_TEXT"];?></div>
		                </div>

		            	<? endif;?>


						
					<?endforeach;?>
                   
                   
                </div>
            </div>
        </div>
    </section>