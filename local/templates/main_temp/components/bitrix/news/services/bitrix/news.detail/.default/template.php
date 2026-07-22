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

// debug($arResult);

?>

<section class="">
        <div class="container df ac ">
            <a class="product__back" href="<?echo $arResult["SECTION_URL"];?>">
                <div class="df ac">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.8867 11.3332C10.0109 11.2083 10.0806 11.0393 10.0806 10.8632C10.0806 10.6871 10.0109 10.5181 9.8867 10.3932L7.5267 7.99988L9.8867 5.63988C10.0109 5.51498 10.0806 5.34601 10.0806 5.16988C10.0806 4.99376 10.0109 4.82479 9.8867 4.69988C9.82473 4.6374 9.751 4.5878 9.66976 4.55396C9.58852 4.52011 9.50138 4.50269 9.41337 4.50269C9.32536 4.50269 9.23823 4.52011 9.15699 4.55396C9.07575 4.5878 9.00201 4.6374 8.94004 4.69988L6.11337 7.52655C6.05088 7.58853 6.00129 7.66226 5.96744 7.7435C5.9336 7.82474 5.91617 7.91188 5.91617 7.99988C5.91617 8.08789 5.9336 8.17503 5.96744 8.25627C6.00129 8.33751 6.05088 8.41124 6.11337 8.47322L8.94004 11.3332C9.00201 11.3957 9.07575 11.4453 9.15699 11.4791C9.23823 11.513 9.32536 11.5304 9.41337 11.5304C9.50138 11.5304 9.58852 11.513 9.66976 11.4791C9.751 11.4453 9.82473 11.3957 9.8867 11.3332Z" fill="#668BEA"></path>
                    </svg>
                </div>
                <div>Назад</div>
            </a>
            <div class="title"><?echo $arResult['NAME']?></div> 
        </div>
    </section>

<div class="container df fw">
    <div class="right__inner">
        <div class="image__container-full w580">
          	<img
			class="detail_picture"
			border="0"
			src="<?=$arResult["DETAIL_PICTURE"]["SRC"]?>"
			width="<?=$arResult["DETAIL_PICTURE"]["WIDTH"]?>"
			height="<?=$arResult["DETAIL_PICTURE"]["HEIGHT"]?>"
			alt="<?=$arResult["DETAIL_PICTURE"]["ALT"]?>"
			title="<?=$arResult["DETAIL_PICTURE"]["TITLE"]?>"
			/>
            <!-- <img src="<?//= SITE_TEMPLATE_PATH?>/images/maslo-dvs.png" alt=""> -->

            <? if($arResult["DETAIL_PICTURE"]["SRC"]):?>

            <div class="lypa__svg">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M17.5 17.5L13.875 13.875" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M9.14062 11.2828L9.14062 7" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M7.2973 9.00073L10.9863 9.00073" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>                                
            </div>
            
            <?endif;?>

        </div>
        <?echo $arResult["DETAIL_TEXT"];?>
        </div>
    <div class="right__main-dvs">
        <div class="contact__item">
			<div class="detail_map">
               
            </div>
            <div class="contact__tit">Автосервис, ремонт автомобилей</div>
            <div class="two__item">
                <div>Адрес</div>
                <div>РТ, Елабуга, пр-т Нефтяников, 4</div>
            </div>
            <div class="two__item">
                <div></div>
                <a class="show_city_in_map" data-name="<?=$arResult['ID'];?>" data-modal='Y' href="#">Показать на карте</a>
            </div>
            <div class="two__item">
                <div>Телефон</div>
                <div>8 987 262 45 85</div>
            </div>
            <div class="two__item">
                <div>Режим</div>
                <div>Ежедневно с 8:00 до 19:00</div>
            </div>
            <a href="#" class="btn bg-red">Записаться</a>
        </div>
    </div>
</div>

<div style="display: none;">
            <div style="width: 100%" class="box-modal <?echo $arResult['ID']?>1">
                <div class="box-modal_close arcticmodal-close">закрыть</div>
                <div class="<?echo $arResult['ID']?>"></div>
            </div>
        </div>

<script>
$('.show_city_in_map').click(function(e){

		let name = $(this).data('name');
    	let id = <?echo $arResult['ID']?>;
		let iblock_id = <?echo $arResult['IBLOCK_ID']?>;

    	$.ajax({
	        type: "GET",
	        url: '/include/show_property_by_id.php',
	        data: {
	        	ajax:1,
	        	id : id,
				iblock_id: iblock_id,
	        },
	        dataType: 'html',
	        success: function(result) {

                // result.arcticmodal()
;	        	 $('.' + name).replaceWith(result)


	        }
    	});

       	e.preventDefault();
        $('.' + name+'1').arcticmodal();


    });
</script>