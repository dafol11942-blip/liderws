
<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main;
?> 

<? //debug($item); ?>


<div class="product__item" id="<?= $item['ID']?>">
   <!--  <a href="#" class="product__img">
        <img src="images/R1.png" alt="">
    </a> -->
     <a class="product__img"  href="<?=$item['DETAIL_PAGE_URL']?>" title="<?=$imgTitle?>">
        <? $bgImage = empty($item['PREVIEW_PICTURE']['SRC']) ? '/local/templates/main_temp/components/bitrix/catalog.section/.default/images/no_photo.png' : $item['PREVIEW_PICTURE']['SRC']; ?>
        <img src="<?= $bgImage?>" alt="">
    </a>

    <div class="product__right">
        <a href="<?=$item['DETAIL_PAGE_URL']?>" class="product__title"><?= $item['NAME']?></a>
        <div class="product__list-item product__list-item-kod">155D3A</div>
        <div class="product__list-item">8</div>
        <div class="product__list-item product__list-item-mob"><div>8 шт </div><div>в наличии</div></div>
        <div class="product__price">
            <div>2 290.0</div>
            <div class="product__old-price">2 390.0</div>
        </div>
        <div class="product__sum">
            <!-- <div class=" product__count">
                <button class="item__minus">
                    <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>                                   
                </button>
                <input class="item__number" value="1" type="number">
                <button class="item__plus">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg> 
                </button>
            </div>
            <a href="#" class=" hidden product-item-btn">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a> -->
        <? if ($actualItem['CAN_BUY']):?>

            <?// debug($itemIds);?>


            <div style="position: relative;z-index: 100;" class="product-item-button-container" id="<?=$itemIds['BASKET_ACTIONS']?>">
                <a class="bx-blue btn btn-default btn-default <?=$buttonSizeClass?> add_to_basket__ajax-first" id="<?=$item['ID']?>"
                    href="#" rel="nofollow">

                     <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                </a>
            </div>

             <div class="product__count product__count-catalog__list" style="display: none;">
                <button class="item__minus">
                    <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"></line>
                    </svg>                                   
                </button>
                <input id="<?=$item['ID']?>" class="item__number" value="1" type="number">
                <button class="item__plus add_to_basket">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"></path>
                    </svg> 
                </button>
            </div>

        <? else:?>

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

        <? endif;?>


            <div class="product__status">
                <div class="product__status-have">В наличии</div>
            </div>
        </div>
    </div>
</div>  



<!-- <div class="product__item " id="<?= $item['ID']?>">
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
        <div class="hidden product__count">
            <button class="item__minus">
                <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>                                   
            </button>
            <input class="item__number" value="1" type="number">
            <button class="item__plus">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg> 
            </button>
        </div>



        <? if ($actualItem['CAN_BUY']):?>


	        <div style="position: relative;z-index: 100;" class="product-item-button-container" id="<?=$itemIds['BASKET_ACTIONS']?>">
				<a class="bx-blue btn btn-default btn-default <?=$buttonSizeClass?>" id="<?=$itemIds['BUY_LINK']?>"
					href="<?echo $itemIds["ADD_URL"]?>" rel="nofollow">

					 <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
		                <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
		                <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		            </svg>

				</a>
			</div>

		<? else:?>

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

		<? endif;?>




        

		
    
    </div>
    <div class="product__status">
        <div class="product__status-name">Статус</div>
        <div class="product__status-have">В наличии</div>
    </div>
    <div class="product__hover-container">

		<? if (!empty($item['DISPLAY_PROPERTIES'])){ ?>

	        <div class="product__hover">
	        	<? foreach ($item['DISPLAY_PROPERTIES'] as $code => $displayProperty): ?>

					<?// debug($item['DISPLAY_PROPERTIES']); ?>


					<div class="product__hover-item">
		                <div> <?= $displayProperty['NAME'] ?> </div>
		                <div><?=(is_array($displayProperty['DISPLAY_VALUE'])
									? implode(' / ', $displayProperty['DISPLAY_VALUE'])
									: $displayProperty['DISPLAY_VALUE'])?></div>
		            </div>


				<? endforeach; ?>

	        </div>

        <? } ?>

    </div>
</div> -->



