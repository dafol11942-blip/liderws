<?php
use Bitrix\Main\Page\Asset;

$url = $APPLICATION->GetCurPage();

$urls_false_main = array('/auth/', '/auth/registration.php');

$idRandCheck1 = rand(1, 9999);
$idRandCheck2 = rand(1, 9999);
$idRandCheck3 = rand(1, 9999);

?>


<? if(!in_array($url, $urls_false_main)):?>
   

</div>
</div>
</div>
</div>
</section>

<div class="modal modal-map" id="modal-map" tabindex="-1" aria-labelledby="modal-map" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <!-- <form class="modal__form form-query">

               <input type="hidden" name="modal-name" value="Напишите нам">
               <input class="input-service-name" type="hidden" name="service-name" value="">

               <div class="modal__title">
                  Напишите нам
               </div>

               <div class="modal__field">

                    <div class="form__label mt30">
                        <input type="text" required name="name" placeholder="Иван Караченский" value="">
                    </div>

                    <div class="form__label mt30">
                        <input type="text" required name="phone" placeholder="+7(___)-___-__-__" value="">
                    </div>

                     <div class="form__label mt30">
                        <input type="text" required name="email" placeholder="lider@mail.ru" value="">
                    </div>

                    <textarea class="input textarea" name="comment" id="" cols="30" rows="10" placeholder="Сообщение"></textarea>

                
               </div>


               <div class="modal__btn">
                  <button class="btn btn-blue" type="submit">Отправить</button>
               </div>
            </form> -->
            
         </div>

         <div class="modal__thanks" style="display: none;">
                <img src="/images/form-fanks.png" class="modal__thanks-img">
                <div class="modal__thanks-title">Благодарим за заявку!</div>
                <div class="modal__thanks-text">
                   В течение 15 минут с Вами свяжется наш менеджер и уточнит все детали покупки данного товара!
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>

<div class="modal modal-write-our" id="modal-write-our" tabindex="-1" aria-labelledby="write-our" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">
            <form class="modal__form form-query">

               <input type="hidden" name="modal-name" value="Напишите нам">
               <input class="input-service-name" type="hidden" name="service-name" value="">

               <div class="modal__title">
                  Напишите нам
               </div>
               <div class="modal__field">

                    <div class="form__label mt30">
                        <input type="text" required name="name" placeholder="Иван Караченский" value="">
                    </div>

                    <div class="form__label mt30">
                        <input type="text" required name="phone" placeholder="+7(___)-___-__-__" value="">
                    </div>

                     <div class="form__label mt30">
                        <input type="text" required name="email" placeholder="lider@mail.ru" value="">
                    </div>

                    <textarea class="input textarea" name="comment" id="" cols="30" rows="10" placeholder="Сообщение"></textarea>

                    <div class="soglasie-na-obrabotky df">
                        <input name="soglasie" required="" class="checkbox_iphone require-checkbox" id="checkbox<?=$idRandCheck1?>" type="checkbox"> 
                        <label required="" for="checkbox<?=$idRandCheck1?>"></label>
                        <div class="sogl__text">
                            Согласие на обработку <a class="blue" href="/soglasie/" target="_blank">персональных данных</a>
                        </div>
                        <div class="error-text">
                            Поле согласия не заполнено !
                        </div>
                     </div>

                
               </div>
               <div class="modal__btn">
                  <button class="btn btn-blue" type="submit">Отправить</button>
               </div>
            </form>
         </div>

         <div class="modal__thanks" style="display: none;">
                <img src="/images/form-fanks.png" class="modal__thanks-img">
                <div class="modal__thanks-title">Благодарим за заявку!</div>
                <div class="modal__thanks-text">
                   В течение 15 минут с Вами свяжется наш менеджер и уточнит все детали покупки данного товара!
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>

<div class="modal modal-service" id="modal-service" tabindex="-1" aria-labelledby="modal-service" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">
            <form class="modal__form form-query">

               <input type="hidden" name="modal-name" value="Заказ услуги">
               <input class="input-service-name" type="hidden" name="service-name" value="">

               <div class="modal__title">
                  Заказать услугу
               </div>
               <div class="modal__field">

                    <div class="form__label mt30">
                        <input type="text" name="name" placeholder="Иван Караченский" value="">
                    </div>

                    <div class="form__label mt30">
                        <input type="text" name="phone" placeholder="+7(___)-___-__-__" value="">
                    </div>

                    <textarea class="input textarea" name="comment" id="" cols="30" rows="10" placeholder="Сообщение"></textarea>

                     <div class="soglasie-na-obrabotky df">
                        <input name="soglasie" required="" class="checkbox_iphone require-checkbox" id="checkbox<?=$idRandCheck2?>" type="checkbox"> 
                        <label required="" for="checkbox<?=$idRandCheck2?>"></label>
                        <div class="sogl__text">
                            Согласие на обработку <a class="blue" href="/soglasie/" target="_blank">персональных данных</a>
                        </div>
                        <div class="error-text">
                            Поле согласия не заполнено !
                        </div>
                     </div>
                
               </div>
               <div class="modal__btn">
                  <button class="btn btn-blue" type="submit">Отправить</button>
               </div>
            </form>
         </div>

         <div class="modal__thanks" style="display: none;">
                <img src="/images/form-fanks.png" class="modal__thanks-img">
                <div class="modal__thanks-title">Благодарим за заявку!</div>
                <div class="modal__thanks-text">
                   В течение 15 минут с Вами свяжется наш менеджер и уточнит все детали покупки данного товара!
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>


<div class="modal modal-question" id="modal-question" tabindex="-1" aria-labelledby="modal-question" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__thanks" style="display: block;">
                <img src="/images/form-fanks.png" class="modal__thanks-img">
                <div class="modal__thanks-title">Благодарим за заявку!</div>
                <div class="modal__thanks-text">
                   В течение 15 минут с Вами свяжется наш менеджер и ответит на все вопросы !
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>


<div class="modal modal-select-city" id="modal-select-city" tabindex="-1" aria-labelledby="modal-select-city" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <div class="modal__title">
                Выбрать пункт выдачи заказов
            </div>

            <div class="modal__field">

                <div></div>


            </div>

        </div>

      </div>
   </div>
</div>


<div class="modal modal-avtocatalog-item" id="modal-avtocatalog-item" tabindex="-1" aria-labelledby="modal-avtocatalog-item" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#000000" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">
            <form class="modal__form form-query">

               <input type="hidden" name="modal-name" value="Заказ товара Автокаталог">
               <input class="input-product-name" type="hidden" name="product-name" value="">
               <input class="input-product-kod" type="hidden" name="product-kod" value="">

               <div class="modal__title">
                  Купить в 1 клик
               </div>
               <div class="modal__field">

                    <div class="form__label mt30">
                        <input type="text" name="name" placeholder="Иван Караченский" value="" required>
                    </div>

                    <div class="form__label mt30">
                        <input type="text" name="phone" placeholder="+7(___)-___-__-__" value="" required>
                    </div>

                    <textarea class="input textarea" name="comment" id="" cols="30" rows="10" placeholder="Сообщение"></textarea>

                    <div class="soglasie-na-obrabotky df">
                        <input name="soglasie" required="" class="checkbox_iphone require-checkbox" id="checkbox<?=$idRandCheck3?>" type="checkbox"> 
                        <label required="" for="checkbox<?=$idRandCheck3?>"></label>
                        <div class="sogl__text">
                            Согласие на обработку <a class="blue" href="/soglasie/" target="_blank">персональных данных</a>
                        </div>
                        <div class="error-text">
                            Поле согласия не заполнено !
                        </div>
                     </div>

                
               </div>
               <div class="modal__btn">
                  <button class="btn btn-blue" type="submit">Отправить</button>
               </div>
            </form>
         </div>

         <div class="modal__thanks" style="display: none;">
                <img src="/images/form-fanks.png" class="modal__thanks-img">
                <div class="modal__thanks-title">Благодарим за заявку!</div>
                <div class="modal__thanks-text">
                   В течение 15 минут с Вами свяжется наш менеджер и уточнит все детали покупки данного товара!
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>



<div class="modal modal-select-city" id="modal-select-city" tabindex="-1" aria-labelledby="modal-select-city" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <div class="modal__title">
                Выбрать пункт выдачи заказов
            </div>

            <div class="modal__field">

                <div></div>


            </div>

        </div>

      </div>
   </div>
</div>



<div class="modal modal-select-main-city" id="modal-select-main-city" tabindex="-1" aria-labelledby="modal-select-main-city" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <div class="modal__title">
                Выберите город
            </div>

            <div class="modal__field">

                <div></div>


            </div>

        </div>

      </div>
   </div>
</div>


<div class="modal modal-applicability" id="modal-applicability" tabindex="-1" aria-labelledby="modal-applicability" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <div class="modal__title">
                Выбрать пункт выдачи заказов
            </div>

            <div class="modal__field">

                <div></div>


            </div>

        </div>

      </div>
   </div>
</div>



<div class="modal modal-analogs" id="modal-analogs" tabindex="-1" aria-labelledby="modal-analogs" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">

            <div class="modal__title">
                Выбрать пункт выдачи заказов
            </div>

            <div class="modal__field">

                <div></div>


            </div>

        </div>

      </div>
   </div>
</div>



<!-- modal-analogs -->

<!-- ДОБАВЛЕНИЯ БЛОКА ОСТАЛИСЬ ВОПРОСЫ -->

<?

// $url = $APPLICATION->GetCurPage();

$urls_false = array('/personal/','/personal/zakazy/','/', '/auth/','/auth/registration.php');

?>
<? if(!in_array($url, $urls_false)):?>
   
     <?$APPLICATION->IncludeComponent(
        "bitrix:main.include",
        ".default",
        Array(
            "AREA_FILE_SHOW" => "file",
            "AREA_FILE_SUFFIX" => "inc",
            "COMPONENT_TEMPLATE" => ".default",
            "EDIT_TEMPLATE" => "",
            "PATH" => SITE_TEMPLATE_PATH ."/include/inc_footer__have__question.php"
        )
    );?>
    
<?endif;?>

<!-- ДОБАВЛЕНИЯ БЛОКА ОСТАЛИСЬ ВОПРОСЫ -->



<footer>
        <div class="footer__wrapper">
            <div class="container">
                <div class="footer__top">
                    <div class="footer__nav">
                        <nav>
                            <div class="footer__catalog">
                                

                                <?$APPLICATION->IncludeComponent(
	"bitrix:menu", 
	"footer_menu-catalog", 
	array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "top",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(
		),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "footer_menu",
		"USE_EXT" => "N",
		"COMPONENT_TEMPLATE" => "footer_menu-catalog"
	),
	false
);?>

                                
                            </div>
                             <?$APPLICATION->IncludeComponent(
	"bitrix:menu", 
	"footer_menu-services", 
	array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "top",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(
		),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "footer_menu__services",
		"USE_EXT" => "N",
		"COMPONENT_TEMPLATE" => "footer_menu-services"
	),
	false
);?>

<?$APPLICATION->IncludeComponent(
	"bitrix:menu", 
	"footer_menu-about", 
	array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "top",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(
		),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "footer_menu__about",
		"USE_EXT" => "N",
		"COMPONENT_TEMPLATE" => "footer_menu-about"
	),
	false
);?>
                    <!-- footer_menu__about -->
                            
                           <!--  <ul>
                                <li class="footer-nav__title">
                                    <a href="#">О нас</a>
                                </li>
                                <li class="footer-nav__title">
                                    <a href="#">Клиентам</a>
                                </li>
                                <li class="footer-nav__title">
                                    <a href="#">Поставщикам</a>
                                </li>
                                <li class="footer-nav__title">
                                    <a href="#">Оптовым клиентам</a>
                                </li>
                            </ul> -->
                        </nav>
                    </div>
                    <div class="footer__contacts">
                        <div class="footer-contant footer-nav__title">Контакты</div>
                        <div class="footer__items">
                            <div class="footer__item">
                                <div class="footer__address">
                                    <?$APPLICATION->IncludeComponent(
                                        "bitrix:main.include",
                                        ".default",
                                        Array(
                                            "AREA_FILE_SHOW" => "file",
                                            "AREA_FILE_SUFFIX" => "inc",
                                            "COMPONENT_TEMPLATE" => ".default",
                                            "EDIT_TEMPLATE" => "",
                                            "PATH" => "/include/footer/inc_addres.php"
                                        )
                                    );?>
                                </div>
                                <a href='#' class="email">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.66659 2.66666H13.3333C14.0666 2.66666 14.6666 3.26666 14.6666 3.99999V12C14.6666 12.7333 14.0666 13.3333 13.3333 13.3333H2.66659C1.93325 13.3333 1.33325 12.7333 1.33325 12V3.99999C1.33325 3.26666 1.93325 2.66666 2.66659 2.66666Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14.6666 4L7.99992 8.66667L1.33325 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>                                        
                                </a>
                                <div class="footer__select">

                                    <?$APPLICATION->IncludeComponent(
                                        "bitrix:main.include",
                                        "",
                                        Array(
                                            "AREA_FILE_SHOW" => "file",
                                            "AREA_FILE_SUFFIX" => "inc",
                                            "EDIT_TEMPLATE" => "",
                                            "PATH" => "/include/footer/inc_footer-phone-select.php"
                                        )
                                    );?>

                                   <!--  <select name="" id="">
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                    </select> -->
                                </div>
                                
                            </div>
                            <div class="footer__item">
                                <div class="footer__address">
                                    <?$APPLICATION->IncludeComponent(
                                        "bitrix:main.include",
                                        ".default",
                                        Array(
                                            "AREA_FILE_SHOW" => "file",
                                            "AREA_FILE_SUFFIX" => "inc",
                                            "COMPONENT_TEMPLATE" => ".default",
                                            "EDIT_TEMPLATE" => "",
                                            "PATH" => "/include/footer/inc_addres2.php"
                                        )
                                    );?>
                                </div>
                                <a href='#' class="email">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.66659 2.66666H13.3333C14.0666 2.66666 14.6666 3.26666 14.6666 3.99999V12C14.6666 12.7333 14.0666 13.3333 13.3333 13.3333H2.66659C1.93325 13.3333 1.33325 12.7333 1.33325 12V3.99999C1.33325 3.26666 1.93325 2.66666 2.66659 2.66666Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14.6666 4L7.99992 8.66667L1.33325 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>                                        
                                </a>
                                <div class="footer__select">

                                    <?$APPLICATION->IncludeComponent(
                                        "bitrix:main.include",
                                        "",
                                        Array(
                                            "AREA_FILE_SHOW" => "file",
                                            "AREA_FILE_SUFFIX" => "inc",
                                            "EDIT_TEMPLATE" => "",
                                            "PATH" => "/include/footer/inc_footer-phone-select2.php"
                                        )
                                    );?>



                                   <!--  <select name="" id="">
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                    </select> -->
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <div class="footer__center">

                    <?$APPLICATION->IncludeComponent(
	"bitrix:sender.subscribe", 
	"podpiska", 
	array(
		"AJAX_MODE" => "Y",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"CACHE_TIME" => "3600",
		"CACHE_TYPE" => "A",
		"CONFIRMATION" => "Y",
		"HIDE_MAILINGS" => "Y",
		"SET_TITLE" => "N",
		"SHOW_HIDDEN" => "Y",
		"USER_CONSENT" => "N",
		"USER_CONSENT_ID" => "0",
		"USER_CONSENT_IS_CHECKED" => "Y",
		"USER_CONSENT_IS_LOADED" => "N",
		"USE_PERSONALIZATION" => "Y",
		"COMPONENT_TEMPLATE" => "podpiska"
	),
	false
);?>
                    
                   <!--  <form action="" class="mailing">
                        <div class="mailing__title">Подписка на новости и акции компании</div>
                        <div class="mailing__right">
                            <input placeholder="Электронная почта" type="text" class="mailing__input">
                            <button class="btn bg-red">Подписаться</button>
                        </div>
                    </form> -->
                </div>
                <div class="footer__bottom">
                    <div class="footer__personal">
                        <div>© <?=date('Y')?> Лидер</div>
                        <a href="/soglasie/">Правила обработки персональных данных</a>
                    </div>
<iframe src="https://yandex.ru/sprav/widget/rating-badge/201645850284?type=rating" width="150" height="50" frameborder="0"></iframe>
                    <?$APPLICATION->IncludeComponent(
	"bitrix:menu", 
	"footer_menu-icons", 
	array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "top",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(
		),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "footer_menu__icons",
		"USE_EXT" => "N",
		"COMPONENT_TEMPLATE" => "footer_menu-icons"
	),
	false
);?>

                    
                    <div class="footer__netkam">
                        <div>Создание сайтов —</div>
                        <a href="https://netkam.ru">Неткам</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

<div class="cookie__wrapper js-cookie-wrapper">
   <button class="cookie__wrapper-close js-cookie-close">×</button>
   <div class="cookie__wrapper-title">Согласие на обработку файлов cookie</div>
   <p class="cookie__wrapper-text">
      Мы используем файлы cookie, разработанные нашими специалистами и третьими лицами, для анализа событий на нашем веб-сайте, что позволяет нам улучшать взаимодействие с пользователями и обслуживание. Продолжая просмотр страниц нашего сайта, вы принимаете условия его использования
   </p>
   <button class="cookie__wrapper-send js-cookie-send">Принять</button>
</div>


    <script src="https://api-maps.yandex.ru/2.1/?apikey=199a4307-6c32-4fda-b2a2-a907c4638cce
&lang=ru_RU&load=package.full,Geolink"
 type="text/javascript"></script>
<!-- Yandex.Metrika counter --> <script type="text/javascript" > (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)}; m[i].l=1*new Date(); for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }} k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)}) (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym"); ym(94151783, "init", { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true }); </script> <noscript><div><img src="https://mc.yandex.ru/watch/94151783" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->
</body>
</html>    

<? endif;?>