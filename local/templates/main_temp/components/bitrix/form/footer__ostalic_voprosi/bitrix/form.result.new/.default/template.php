<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?>


<div class="ajax-add-answer">
<?=$arResult["FORM_NOTE"]?>
</div>


<? if(!$_REQUEST['AJAX_CALL']):?>

<section class="voprosi">
        <div class="container df jsb">
            <div class="services__item">
                <div class="services__title"><?=$arResult["FORM_TITLE"]?></div>
                <div class="services__text">Получите профессианальную консультацию 
                    у наших специалистов. </div>
                <div class="services__bottom">
                    <!-- <a href="#" id="have__question" class="services__btn btn bg-red">
                        <div>Заказать звонок</div>
                    </a> -->
                    <!-- <a href='#' class=" main__all">Подробнее</a> -->
                </div>
            </div>
            <div class="have__question-right">

                <div class="have__question-text">Укажите ваш контактный номер</div>


				<?if ($arResult["isFormErrors"] == "Y"):?>
				<?=$arResult["FORM_ERRORS_TEXT"];?>
				<?endif;?>

				<?if ($arResult["isFormNote"] != "Y"){ ?>

					<?=$arResult["FORM_HEADER"]?>

				<? } ?>


                <!-- <form action="order__call.php" class="have__question-order df"> -->




                    <input type="text" name='form_text_1' value="" placeholder="+ 7 900 123 90 00">
                    <input type="hidden" name="web_form_submit" value="Заказать звонок">
                    <button type="submit" class="services__btn btn bg-red">
                        <div>Заказать звонок</div>
                    </button>
                </form>
                
                <div class="soglasie-na-obrabotky df">
                    <input required class="checkbox_iphone" id="checkbox1" type="checkbox">

                    <label for="checkbox1"></label>
                    <div class="sogl__text">Согласие на обработку <a class="blue" href="personal.html">персональных данных</a></div>
                </div>

            </div>
        </div>
</section>

<? endif;?>



<?CUtil::InitJSCore( array('ajax' , 'popup' ));?>





<script type="text/javascript">
BX.ready(function(){


	console.log($('form'));

	$('form').on("submit", function(event) {


    	var $url = $form.attr('action');
    	BX.ajax.insertToNode($url, BX('ajax-add-answer')); // функция ajax-загрузки контента из урла в #div
     	addAnswer.show(); // появление окна
     	return false;
     	 // return false;
    	// return false

	});

	 // $('.services__btn').click(function(){
	 // 	BX.ajax.insertToNode('/test/', BX('ajax-add-answer')); // функция ajax-загрузки контента из урла в #div
  //    	addAnswer.show(); // появление окна
  //    	return false;
  // 	});


	 var addAnswer = new BX.PopupWindow(
	         "my_answer",                
	          null, 
	         {
	            content: BX( 'ajax-add-answer'),
	            closeIcon: {right: "20px", top: "10px" },
	            titleBar: {content: BX.create("span", {html: ‘<b>Это заголовок окна</b>’, 'props': {'className': 'access-title-bar'}})}, 
	            zIndex: 0,
	            offsetLeft: 0,
	            offsetTop: 0,
	            draggable: {restrict: false},
	            buttons: [
	               new BX.PopupWindowButton({
	                  text: "Сохранить" ,
	                  className: "popup-window-button-accept" ,
	                  events: {click: function(){
	                     this.popupWindow.close();
	                  }}
	               }),
	               new BX.PopupWindowButton({
	                  text: "Закрыть" ,
	                  className: "webform-button-link-cancel" ,
	                  events: {click: function(){
	                     this.popupWindow.close();
	                  }}
	               })
	            ]
	         });

	// BX.ajax.insertToNode('/test/', BX('ajax-add-answer'));
	// addAnswer.setContent(BX('ajax-add-answer'));
	// addAnswer.show();

});
</script>




