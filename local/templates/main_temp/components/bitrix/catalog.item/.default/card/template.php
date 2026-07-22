
<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main;
?> 

<?

// debug($_SESSION);
 //debug($item); ?>





<!-- <div class="product__item with_filter" id="<?= $item['ID']?>"> -->
    <a class="product__img"  href="<?=$item['DETAIL_PAGE_URL']?>" title="<?=$imgTitle?>">
        <? $bgImage = empty($item['PREVIEW_PICTURE']['SRC']) ? '/local/templates/main_temp/components/bitrix/catalog.section/.default/images/no_photo.png' : $item['PREVIEW_PICTURE']['SRC']; ?>
        <img src="<?= $bgImage?>" alt="">
    </a>

       
    <a href="<?=$item['DETAIL_PAGE_URL']?>" class="product__title" title="<?= $item['NAME']?>">
    <?= $item['NAME']?>
    </a>
       

    <div class="product__sum">
        <div class="product__price">
        	<?
					if (!empty($price))
					{
						if ($arParams['PRODUCT_DISPLAY_MODE'] === 'N' && $haveOffers)
						{
							echo Loc::getMessage(
								'CT_BCI_TPL_MESS_PRICE_SIMPLE_MODE',
								array(
									'#PRICE#' => $price['PRINT_RATIO_PRICE'],
									'#VALUE#' => $measureRatio,
									'#UNIT#' => $minOffer['ITEM_MEASURE']['TITLE']
								)
							);
						}
						else
						{
							echo $price['PRINT_RATIO_PRICE'];
						}
					}
			?>
								
		</div>



        <? if(!empty($actualItem['PRODUCT']['QUANTITY_IN_CART'])):?>

	      
			 <div class="product__count product__count-catalog__list" style="">
                <button class="item__minus">
                    <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"></line>
                    </svg>                                   
                </button>
                <input id="<?=$item['ID']?>" class="item__number" value="<?echo $actualItem['PRODUCT']['QUANTITY_IN_CART']?>" type="number">
                <button class="item__plus add_to_basket">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"></path>
                    </svg> 
                </button>
            </div>


            <div style="position: relative;z-index: 100;display: none;" class="product-item-button-container" id="<?=$itemIds['BASKET_ACTIONS']?>">
				<a class="bx-blue btn btn-default btn-default <?=$buttonSizeClass?> add_to_basket__ajax-first" id="<?=$item['ID']?>"
					href="<?echo $itemIds["ADD_URL"]?>" rel="nofollow">

					 <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
		                <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
		                <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		            </svg>

				</a>
			</div>


        <? else:?>


        	<div style="position: relative;z-index: 100;" class="product-item-button-container" id="<?=$itemIds['BASKET_ACTIONS']?>">
				<a class="bx-blue btn btn-default btn-default <?=$buttonSizeClass?> add_to_basket__ajax-first" id="<?=$item['ID']?>"
					href="<?echo $itemIds["ADD_URL"]?>" rel="nofollow">

					 <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
		                <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
		                <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		            </svg>

				</a>
			</div>

        	<div class="product__count product__count-catalog__list" style="display: none">

                <button class="item__minus">
                    <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"></line>
                    </svg>                                   
                </button>
                <input id="<?=$item['ID']?>" class="item__number" value="<?echo $actualItem['PRODUCT']['QUANTITY_IN_CART']?>" type="number">
                <button class="item__plus add_to_basket">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"></path>
                    </svg> 
                </button>

            </div>


        <? endif;?>


		         
         <? if(!empty($actualItem['PRODUCT']['QUANTITY_IN_CART'])):?>
         	<a class="in_cart" href="/cart/">в Корзину</a>
         <? else:?>
         	<a class="in_cart" style="display: none" href="/cart/">в Корзину</a>
         <? endif;?>

























	        <?

			if ($showSubscribe)
			{
				$APPLICATION->IncludeComponent(
					'bitrix:catalog.product.subscribe',
					'',
					array(
						'PRODUCT_ID' => $actualItem['ID'],
						'BUTTON_ID' => $itemIds['SUBSCRIBE_LINK'],
						'BUTTON_CLASS' => 'btn btn-default '.$buttonSizeClass,
						'DEFAULT_DISPLAY' => true,
						'MESS_BTN_SUBSCRIBE' => $arParams['~MESS_BTN_SUBSCRIBE'],
					),
					$component,
					array('HIDE_ICONS' => 'Y')
				);
			}

			?>


        

		
    
    </div>
    <div class="product__status">
    	<?

    	//debug($item['PRODUCT']['QUANTITY']);

    	?>
        <div class="product__status-name">Статус</div>
        <div class="product__status-have">
        	<? if ($item['PRODUCT']['QUANTITY'] >= 1):?>
        		В наличии
        	<? else: ?>
        		под заказ
        	<? endif;?>

    </div>

    </div>
    <div class="product__hover-container">

    	<? //debug($item) ?>


	        <div class="product__hover">

		<? if (!empty($item['DISPLAY_PROPERTIES'])){ ?>

	        	<? foreach ($item['DISPLAY_PROPERTIES'] as $code => $displayProperty): ?>

					<?// debug($item['DISPLAY_PROPERTIES']); ?>


					<div class="product__hover-item">
		                <div> <?= $displayProperty['NAME'] ?> </div>
		                <div><?=(is_array($displayProperty['DISPLAY_VALUE'])
									? implode(' / ', $displayProperty['DISPLAY_VALUE'])
									: $displayProperty['DISPLAY_VALUE'])?></div>
		            </div>


				<? endforeach; ?>

        <? } ?>

       <!--  <?
        if ($item['LABEL'])
		{
			?>
			<div class="product-item-label-text <?=$labelPositionClass?>" id="<?=$itemIds['STICKER_ID']?>">
				<?
				if (!empty($item['LABEL_ARRAY_VALUE']))
				{
					foreach ($item['LABEL_ARRAY_VALUE'] as $code => $value)
					{
						?>
						<div<?=(!isset($item['LABEL_PROP_MOBILE'][$code]) ? ' class="hidden-xs"' : '')?>>
							<span title="<?=$value?>"><?=$value?></span>
						</div>
						<?
					}
				}
				?>
			</div>
			<?
		}
        ?> -->


        <? if (!empty($item['PRODUCT_PROPERTIES']))
								{
									?>
									
										<?
										foreach ($item['PRODUCT_PROPERTIES'] as $propID => $propInfo)
										{
											?>
											
											<div class="product__hover-item">


												<div><?=$item['PROPERTIES'][$propID]['NAME']?></div>



												
													<?
													if (
														$item['PROPERTIES'][$propID]['PROPERTY_TYPE'] === 'L'
														&& $item['PROPERTIES'][$propID]['LIST_TYPE'] === 'C'
													)
													{
														foreach ($propInfo['VALUES'] as $valueID => $value)
														{
															?>
															<label>
																<? $checked = $valueID === $propInfo['SELECTED'] ? 'checked' : ''; ?>
																<input type="radio" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']?>[<?=$propID?>]"
																	value="<?=$valueID?>" <?=$checked?>>
																<?=$value?>
															</label>
															<br />
															<?
														}
													}
													else
													{
														?>



														<!-- <select name="<?=$arParams['PRODUCT_PROPS_VARIABLE']?>[<?=$propID?>]"> -->
															<?
															foreach ($propInfo['VALUES'] as $valueID => $value)
															{
																$selected = $valueID === $propInfo['SELECTED'] ? 'selected' : '';
																?>
																<!-- <option value="<?=$valueID?>" <?=$selected?>> -->
																	<div><?=$value?></div>
																<!-- </option> -->
																<?
															}
															?>
														<!-- </select> -->




														<?
													}
													?>
												<!-- </td> -->
											<!-- </tr> -->
											</div>
											<?
										}
										?>
									<!-- </table> -->
									<?
								} ?>
 
	    

	    </div>






    </div>



    

