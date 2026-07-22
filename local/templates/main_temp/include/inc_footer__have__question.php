<?


//define('STOP_STATISTICS', true);
//require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$APPLICATION->SetTitle("");
//$GLOBALS['APPLICATION']->RestartBuffer();

$id_checkbox = rand(1, 99999);

?><section class="voprosi">
<div class="container df jsb">
	<div class="services__item">
		<div class="services__title">
			 Остались вопросы?
		</div>
		<div class="services__text">
			 Получите профессиональную консультацию у наших специалистов.
		</div>
		<div class="services__bottom">
			 <!-- <a href="#" id="have__question" class="services__btn btn bg-red">
                    <div>Заказать звонок</div>
                </a> --> <!-- <a href='#' class=" main__all">Подробнее</a> -->
		</div>
	</div>
	<div class="have__question-right">
		<div class="have__question-text">
			 Укажите ваш контактный номер
		</div>
		<form action="/form/" class="ajax-form-modal-question">
			<div class="have__question-order df">
 <input class="hidden" type="hidden" name="modal-name" value="остались вопросы ?"> <input type="text" name="phone" class="require" required="" laceholder="+ 7 900 123 90 00"> <button class="order__btn btn bg-red">
				<div>
					 Заказать звонок
				</div>
 </button>
				<div class="error-text">
					 Поле телефона не заполнено !
				</div>
			</div>
			<div class="soglasie-na-obrabotky df">
 				<input name="soglasie" required="" class="checkbox_iphone require-checkbox" id="checkbox<?=$id_checkbox?>" type="checkbox"> 
 				<label required="" for="checkbox<?=$id_checkbox?>"></label>
				<div class="sogl__text">
					 Согласие на обработку <a class="blue" href="/soglasie/" target="_blank">персональных данных</a>
				</div>
				<div class="error-text">
					 Поле согласия не заполнено !
				</div>
			</div>
		</form>
	</div>
</div>
 </section>
<style>
	.voprosi {
		margin-top: 3rem;
	}
	.error-text {
		display: none;
	}
	.voprosi .error-text {
		color: #ff220b;
    	margin: 0 1rem;
    	font-size: 14px;
	}
	
</style><?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>