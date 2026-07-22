<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Доставка");
if(!CModule::IncludeModule("iblock"))
return; 
?><?$APPLICATION->IncludeComponent(
	"bitrix:menu",
	"Left_menu",
	Array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "left",
		"COMPONENT_TEMPLATE" => "Left_menu",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "left",
		"USE_EXT" => "N"
	)
);?>
<div class="delivery">
	<div class="delivery__block">
		<div class="delivery__left">
			<div class="delivery__tit">
				 Самовывоз
			</div>
			<div class="mt10 w160">
				 РТ, Елабуга, ул. Баки Урманче 17а
			</div>
 <a href="#" data-id="28" class="show_in_map border__bottom">Показать на карте</a>
		</div>
		<div class="delivery__right">
			<ul>
				<li>Из магазина, указанного при оформление заказа.</li>
				<li>Самовывоз осуществляется бесплатно.</li>
			</ul>
		</div>
	</div>
	<div class="delivery__block">
		<div class="delivery__left">
			<div class="delivery__tit">
				 Курьерская доставка
			</div>
		</div>
		<div class="delivery__right">
			<ul>
				<li>Вы можете заказать курьерскую доставку, предварительно уточнив условия и наличие данной услуги у менеджера Вашего магазина.</li>
			</ul>
		</div>
	</div>
	<div class="delivery__block">
		<div class="delivery__left">
			<div class="delivery__tit">
				 Служба доставки по России
			</div>
		</div>
		<div class="delivery__right">
			<ul>
				<li>Расчет стоимости заказа состоит из цены на товар и стоимости доставки от сортировочного склада до конечного клиента. Расчет цены товара происходит при помощи инструмента «Корзина», исходя из данных онлайн прайс-листа на момент составления заявки. Расчет предварительной стоимости доставки осуществляется при помощи персонального менеджера до отправки заказа в работу, а точная стоимость определяется по факту прихода товара на сортировочный склад отдела Службы доставки по России.</li>
			</ul>
		</div>
	</div>
</div>
 <br>
 <style>
 	ymaps {
 		margin: 0 auto;
 	}
 </style>
<div style="display: none;">
	<div style="width: 100%" class="box-modal" id="show_in_map">
		 <!-- <div class="box-modal_close arcticmodal-close">закрыть</div> -->
		<div class="replace">
		</div>
	</div>
</div>
 <script>
 	$(document).ready(function() {

	$.ajax({
	    type: "GET",
	    url: '/include/show_in_map.php',
	    data: {
	     ajax:1,
	     // city_id : value
	    },
	    dataType: 'html',
	    success: function(result) {


                    $('#modal-map .modal__open').html(result);

	      $('.replace').replaceWith(result)

	       

	    }
	});



    $('.show_in_map').click(function(e){

       	e.preventDefault();
$('#modal-map').show();
        //$('#show_in_map').arcticmodal();


    });



});
 </script><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>