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
?><style>
	.ymaps-cnst-error-cap {
			display:none !important;
	}
	.two__item {
		height:2rem;
		margin-top:1rem;
	}
</style>

<? 

	$res = CIBlockSection::GetByID($arResult["ITEMS"][0]['IBLOCK_SECTION_ID']);
	$ar_res = $res->GetNext();
?>



<?

$arSections = getSectionList(
	Array('IBLOCK_ID' => 9, 'GLOBAL_ACTIVE'=>'Y'),
	Array('NAME','SECTION_PAGE_URL','DESCRIPTION')
);


 // debug($arSections);

 
?>
<div id="pageid"></div>

<div class="replace">

<div class="select__city">
    <div class="right__tit">Город</div>
    <select name="select__sity" id="select__sity">
      

        <?foreach($arSections["CHILDS"] as $arItem):?>

          <option <?if($arItem['ID'] == $_GET['city_id']){echo "selected";} ?> value="<?= $arItem['ID'] ?>"><? echo $arItem['NAME']; ?></option>

    	<? endforeach;?>


       
    </select>
</div>

<div class="contacts__inner">

	<? //debug($arResult);?>


<?foreach($arResult["ITEMS"] as $arItem):?>


	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>

	<?// debug($arItem['PROPERTIES']);?>


	    <div class="contact__item" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
	        <div class="contact__tit"> <?= $arItem['NAME']; ?></div>
	        <div class="two__item">
	            <div>Адрес</div>
	            <div><?= $arItem['PROPERTIES']['ADDRESS']['VALUE']?></div>
	        </div>
	        <div style="height: inherit;
    margin-top: 0;" class="two__item">
	            <div></div>
	            <a class="show_city_in_map" data-id='<?= $arItem['ID']?>' data-name='<?=$this->GetEditAreaId($arItem['ID']);?>' href="#">Показать на карте</a>
	        </div>

	       <!--  <span class="ymaps-geolink">
			   Россия, Республика Татарстан, Казань, улица Маршала Чуйкова
			</span> -->


	        <div class="two__item">
	            <div>Телефон</div>
	            <div><?= $arItem['PROPERTIES']['PHONE']['~VALUE']['TEXT']?></div>
	        </div>
	        <div class="two__item">
	            <div>Режим</div>
	            <div><?= $arItem['PROPERTIES']['REJIM']['~VALUE']?></div>
	        </div>
	        <a href="#" class="modal-order-call-btn btn bg-red">Заказать звонок</a>
	    </div>

	    <div style="display: none;">
		    <div style="width: 100%" class="box-modal <?=$this->GetEditAreaId($arItem['ID']);?>1">
		        <div class="box-modal_close arcticmodal-close">закрыть</div>
		        <div class="<?=$this->GetEditAreaId($arItem['ID']);?>"></div>
		    </div>
		</div>

	  <!--   <div style="display: none;">
	    	<script type="text/javascript" charset="utf-8" async src="https://api-maps.yandex.ru/services/constructor/1.0/js/?um=constructor%3Ad74fc07e18c80cbe62bc53df3ae7275f543c63ed9479f941bc6ef77fc4ff148e&amp;width=421&amp;height=260&amp;lang=ru_RU&amp;scroll=true"></script>
	    </div> -->


<?endforeach;?>
    


    </div>


<div class="select__city-map">

	<?// debug($arSections)?>
		
    <?foreach($arSections["CHILDS"] as $arItem){

       

       if($arItem['GLOBAL_ACTIVE'] == 'Y' && !($_GET['ajax'])){

       		
	  //      	$res = CIBlockSection::GetByID(1);
			// if($ar_res = $res->GetNext())
		 	debug($arItem['ID']);

       		
       	}

       	if($arItem['ID'] == $_GET['city_id']){
			//echo $arItem['DESCRIPTION'];	
       	}	


	} ?>


</div>


</div> 

<style>
	ymaps {
		margin: 0 auto;
	}
</style>



<script>


    var value = Number($('.select__city-map').text());

    if(value){

        $.ajax({
            type: "GET",
            url: '/include/show_items_in_section_id.php',
            data: {
                ajax:1,
                city_id : value
            },
            dataType: 'html',
            success: function(result) {

                 $('.replace').replaceWith(result)
               

            }
        });


    }




    $('.show_city_in_map').click(function(e){

        let name = $(this).data('name');
        let id = $(this).data('id');

        $.ajax({
            type: "GET",
            url: '/include/show_map_by_id.php',
            data: {
                ajax:1,
                id : id
            },
            dataType: 'html',
            success: function(result) {

				//$('.'+ name).replaceWith(result)
    			$('#modal-map').show();
                $('#modal-map .modal__open').html(result);
               

            }
        });

        e.preventDefault();
		// $('.'+name+ '1').arcticmodal();


        });

        var value = $('#select__sity').val();


        $(this).attr("selected", "selected");






        


        $('#select__sity').change(function(){

        var value = $('#select__sity').val();


        $(this).attr("selected", "selected");



        $.ajax({
            type: "GET",
            url: '/include/show_items_in_section_id.php',
            data: {
                ajax:1,
                city_id : value
            },
            dataType: 'html',
            success: function(result) {

                 $('.replace').replaceWith(result)
               

            }
        });


    });
	
</script>
<style>
    iframe {
        display: none !important;
    }
    .modal iframe {
        display: block !important;
    }
</style>


	<strong>Реквизиты</strong><br>
Индивидуальный предприниматель Винокуров Сергей Владимирович<br>
ИНН	164604616640<br>
ОГРНИП	314167418800021<br>
Наименование банка	ПАО "АК БАРС" БАНК г. Казань<br>
Р/С	40802810909020000114<br>
К/С	30101810000000000805<br>
БИК	049205805<br>
Е-mail	Lider-16@bk.ru<br>