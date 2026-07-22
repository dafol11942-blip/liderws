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

 <section class="tech">
        <div class="container df">
            <div class="tech__left">
                <div class="catalog_bmw_list tech_osmotr">
                        <div class="product__inner_list product__inner">
            
                            <div class="product__desc analog__item not__after">
                                <div class="analog_num">Наименование</div>
                                <!-- <div class="anolog__kod "></div> -->
                                <div class="product__sum">Стоимость</div>
                            </div>




<?foreach($arResult["ITEMS"] as $arItem):?>
	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>


      <div class="tech_item " id="<?=$this->GetEditAreaId($arItem['ID']);?>">
            <div class="analog_num"><?echo $arItem["NAME"]?></div>
            <div class="analog__svg"></div>
            <div href="#" class="product__sum"><?echo $arItem["PREVIEW_TEXT"];?></div>
        </div>

<?endforeach;?>



                            <div class="analog__item product__item not__after">
                                <div class="analog_num text-peat w100">Постановление Кабинета Министров Республики Татарстан от 27.11.2023 № 1521</div>
                                <!-- <div class="analog__svg"></div> -->
                                <!-- <div href="#" class="product__sum">бесплатно</div> -->
                            </div>
                            
                        </div>
                </div>
            </div>
            <div class="right__main-dvs">
                <div class="contact__item">
                    <div class="contact__tit">Автосервис, ремонт автомобилей</div>
                    <div class="two__item">
                        <div>Адрес</div>
						 <?$APPLICATION->IncludeComponent(
                                "bitrix:main.include",
                                ".default",
                                Array(
                                    "AREA_FILE_SHOW" => "file",
                                    "AREA_FILE_SUFFIX" => "inc",
                                    "COMPONENT_TEMPLATE" => ".default",
                                    "EDIT_TEMPLATE" => "",
									"PATH" => SITE_TEMPLATE_PATH ."/include/tekhosmotr/inc_addres.php"
                                )
                            );?>
                        
                    </div>
                    <div class="two__item">
                        <div></div>
						<?$APPLICATION->IncludeComponent(
                                "bitrix:main.include",
                                ".default",
                                Array(
                                    "AREA_FILE_SHOW" => "file",
                                    "AREA_FILE_SUFFIX" => "inc",
                                    "COMPONENT_TEMPLATE" => ".default",
                                    "EDIT_TEMPLATE" => "",
									"PATH" => SITE_TEMPLATE_PATH ."/include/tekhosmotr/show_cart.php"
                                )
                            );?>
                        
                    </div>
					<?$APPLICATION->IncludeComponent(
                                "bitrix:main.include",
                                ".default",
                                Array(
                                    "AREA_FILE_SHOW" => "file",
                                    "AREA_FILE_SUFFIX" => "inc",
                                    "COMPONENT_TEMPLATE" => ".default",
                                    "EDIT_TEMPLATE" => "",
									"PATH" => SITE_TEMPLATE_PATH ."/include/tekhosmotr/items.php"
                                )
                            );?>
                    
                    <a href="#" data-modal-name='Автосервис, ремонт автомобилей' class="modal-tech-osmotr-btn btn bg-red">Записаться</a>
                </div>
            </div>
        </div>
    </section>