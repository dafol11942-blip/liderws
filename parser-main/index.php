<?


define('STOP_STATISTICS', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$GLOBALS['APPLICATION']->RestartBuffer();

?>



 <section class="voprosi">
    <div class="container df jsb">
        <div class="services__item">
            <div class="services__title">остались вопросы ?</div>
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

           	<form action="/form/" class="ajax-form">

	            <div class="have__question-order df">
	            	<input class="hidden" type="hidden" name="modal-name" value="остались вопросы ?">
	                <input type="number" name='phone' class="require" required laceholder="+ 7 900 123 90 00">
	                <button class="services__btn btn bg-red">
	                    <div>Заказать звонок</div>
	                </button>
	                <div class="error-text">Поле телефона не заполнено !</div>
	            </div>
	            <div class="soglasie-na-obrabotky df">
	                <input name="soglasie" required class="checkbox_iphone require-checkbox" id="checkbox1" type="checkbox">
	                <label required for="checkbox1"></label>
	                <div class="sogl__text">Согласие на обработку <a class="blue" href="personal.html">персональных данных</a></div>
	                <div class="error-text">Поле согласия не заполнено !</div>
	            </div>

            </form>

        </div>
    </div>
</section>





<style>
	.error-text {
		display: none;
	}
	.voprosi .error-text {
		color: #ff220b;
    	margin: 0 1rem;
    	font-size: 14px;
	}
	
</style>





<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>