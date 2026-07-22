<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>


<?
	// debug();
?>


Сожалеем, но ничего не найдено.

<br>

Вы можете <a href="#" class="mob-top__phone blue" data-action='{"show-modal": {"url": "/forms/callback/","name":"modal-search-item", "title": "Поисковой запрос <? echo $arResult['REQUEST']['QUERY'] ?>"}}'>оставить заявку</a> на заказ этой детали 




<div class="modal modal-search-item" id="modal-search-item" tabindex="-1" aria-labelledby="modal-avtocatalog-item" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal__close" data-dismiss="modal" aria-label="Close">
            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
               <path d="M1 1.5L15 15.5M1 15.5L15 1.5" stroke="#C2C2C2" stroke-width="2" stroke-linecap="round"></path>
            </svg>
         </div>

         <div class="modal__open">
            <form class="modal__form form-query">

               <input type="hidden" name="modal-name" value="Поиск товара">
               <input class="input-product-name" type="hidden" name="product-name" value="<? echo $arResult['REQUEST']['QUERY'] ?>">
               <input class="input-product-kod" type="hidden" name="product-kod" value="">

               <div class="modal__title">
                  Поиск товара
               </div>
               <div class="modal__field">

                    <div class="form__label mt30">
                        <input type="text" name="name" placeholder="Иван Караченский" value="" required>
                    </div>

                    <div class="form__label mt30">
                        <input type="text" name="phone" placeholder="+7(___)-___-__-__" value="" required>
                    </div>

                    <textarea class="input textarea" name="comment" id="" cols="30" rows="10" placeholder="Сообщение"></textarea>

                
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
                   В течение 15 минут с Вами свяжется наш менеджер и уточнит все детали поискового запроса!
                </div>
                <div class="modal__btn">
                   <button class="btn btn-blue" data-dismiss="modal" type="submit">Продолжить</button>
                </div>
         </div>

      </div>
   </div>
</div>