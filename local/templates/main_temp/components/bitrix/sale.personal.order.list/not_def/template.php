<?php

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\Page\Asset,
    Bitrix\Main\Loader;

// CModule::IncludeModule('sale');
 // \Bitrix\Main\Loader::includeModule('sale');
// use Bitrix\Main\Loader;

Asset::getInstance()->addJs("/bitrix/components/bitrix/sale.order.payment.change/templates/.default/script.js");
Asset::getInstance()->addCss("/bitrix/components/bitrix/sale.order.payment.change/templates/.default/style.css");
$this->addExternalCss("/bitrix/css/main/bootstrap.css");
CJSCore::Init(array('clipboard', 'fx'));

Loc::loadMessages(__FILE__);

if (!empty($arResult['ERRORS']['FATAL']))
{
	foreach($arResult['ERRORS']['FATAL'] as $error)
	{
		ShowError($error);
	}
	$component = $this->__component;
	if ($arParams['AUTH_FORM_IN_TEMPLATE'] && isset($arResult['ERRORS']['FATAL'][$component::E_NOT_AUTHORIZED]))
	{
		$APPLICATION->AuthForm('', false, false, 'N', false);
	}

}
else
{
	if (!empty($arResult['ERRORS']['NONFATAL']))
	{
		foreach($arResult['ERRORS']['NONFATAL'] as $error)
		{
			ShowError($error);
		}
	}
	if (!count($arResult['ORDERS']))
	{
		if ($_REQUEST["filter_history"] == 'Y')
		{
			if ($_REQUEST["show_canceled"] == 'Y')
			{
				?>
				<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_CANCELED_ORDER')?></h3>
				<?
			}
			else
			{
				?>
				<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_HISTORY_ORDER_LIST')?></h3>
				<?
			}
		}
		else
		{
			?>
			<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_ORDER_LIST')?></h3>
			<?
		}
	}
	?>

	<?

	  //debug($arResult);   DELIVERY_STATUS_NAME

	?>



<style>
    .red {
        color: #E64C46;
        border: 1px solid;
        padding: .5rem;
    }
    .green_pay {
        color: #4DCD71;
        border: 1px solid;
        padding: .5rem;
    }
</style>


<?

foreach($arResult['ORDERS'] as $order){


    $date = mb_substr(trim($order['ORDER']['DATE_INSERT_FORMATED']), -4, 4); 


    switch ($date) {


        case '2022':
                
            $date_2022 = 1;

            break;

        case '2023':
            
             $date_2023 = 1;

            break;
        
        default:
            # code...
            break;


    }



}


              



?>



    <div class="kabinet__orders">
        <div class="kabinet__change-tit">
            <div>Заказы</div>
        </div>
        <div style="max-width: 140px;" class="iphone__chekbox  orders__chekbox">

           

            <? if($date_2023){ ?>

                <div  data-year='2023' class="tab_link active">2023</div>

            <? } ?>


             <? if($date_2022){ ?>

                <div  data-year='2022' class="tab_link">2022</div>

            <? } ?>


        </div>


        <? if($date_2023){ ?>

        <div  data-year='2023' class="2023 tab_order table__orders">


            <div class="orders__item-main-start">
                <div>Номер заказов</div>
                <div>Дата</div>
                <div class="">Адресс доставки</div>
                <div>Итого</div>
                 <!-- <div>Статус</div> -->
                <div>Статус</div>
                <div>Повторить</div>
                <div></div>
            </div>

            <? foreach($arResult['ORDERS'] as $order):?>

                <? $date = mb_substr(trim($order['ORDER']['DATE_INSERT_FORMATED']), -4, 4); //  ?>


                <? if($date == 2023){ ?>



                    <div class="orders__item">
                        <div><?= $order['ORDER']['ACCOUNT_NUMBER']?>    </div>
                        <div><?= $order['ORDER']['DATE_INSERT_FORMATED']?></div>
                        <div><?= $order['SHIPMENT'][0]['DELIVERY_NAME']?></div>
                        <div class="order__total-price">
                            <div><?= $order['ORDER']['FORMATED_PRICE']?></div>
                           <? if($order['ORDER']['OLD_PRICE']):?> <div>2 390.0</div><? else:?><div></div><?endif;?>
                        </div>
                        <div class="printer">
                            
                            <? if($order['ORDER']['PAYED'] == 'Y'){?>

                                <div class="green_pay">Оплачен</div>

                            <? }else{ ?>

                               <a class="red" href="<?= $order['PAYMENT'][0]['PSA_ACTION_FILE'] ?>">Оплатить</a>

                            <? } ?>


                        </div>
                        <a class="repeat" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>">
                            <div class="repeat__svg">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g clip-path="url(#clip0)">
                                    <path d="M0.833984 16.6667V11.6667H5.83398" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M19.166 3.33325V8.33325H14.166" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2.92565 7.49998C3.34829 6.30564 4.06659 5.23782 5.01354 4.39616C5.96048 3.55451 7.10521 2.96645 8.34089 2.68686C9.57657 2.40727 10.8629 2.44527 12.08 2.79729C13.297 3.14932 14.405 3.80391 15.3007 4.69998L19.1673 8.33331M0.833984 11.6666L4.70065 15.3C5.59627 16.1961 6.70429 16.8506 7.92132 17.2027C9.13835 17.5547 10.4247 17.5927 11.6604 17.3131C12.8961 17.0335 14.0408 16.4455 14.9878 15.6038C15.9347 14.7621 16.653 13.6943 17.0756 12.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </g>
                                    <defs>
                                    <clipPath id="clip0">
                                    <rect width="20" height="20" fill="white"/>
                                    </clipPath>
                                    </defs>
                                </svg>                                
                            </div>
                        </a>
                        <div>
                            <div class="open-order-items">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="green tae" style="white-space: nowrap;">

                                <?
                                    

                                        if ($order['ORDER']['DEDUCTED'] == 'Y')
                                        {
                                            ?>
                                                <?=Loc::getMessage('SPOL_TPL_LOADED');?>
                                            <?
                                        }
                                        else
                                        {
                                            ?>
                                                <?=Loc::getMessage('SPOL_TPL_NOTLOADED');?>
                                            <?
                                        }
                                        ?>


                                <?//= $order['SHIPMENT'][0]['DELIVERY_STATUS_NAME'] ?>
                                    
                                </div>
                        </div>
                        <div class="order__item-show" style="display: none;">


                            <? foreach($order['BASKET_ITEMS'] as $item): ?>


                                <div class="orders__item order__item">
                                    <div><?= $item['PRODUCT_ID']?></div>
                                    <div><?= $item['NAME']?></div>
                                    <div class="order__total-price">
                                        <?  if(CModule::IncludeModule("currency")){  ?>
                                        <div><?= CurrencyFormat($item['PRICE'], "RUB");?></div>
                                        <div></div>
                                        <? } ?>
                                    </div>
                                    <div class="repeat__text">обновлено</div>
                                    <div class="order__item-end">
                                        <div class="green tae open-order-items">
                                            В наличии
                                        </div>
                                        <div class="">сегодня</div>
                                    </div>
                                </div>

                            <? endforeach?>

                        </div>
                    </div>  


                <? } ?>


                <?endforeach?>


            </div>
      

        <? } ?>


        <? if($date_2022){ ?>

        <div style="display: none" data-year='2022' class="2022 tab_order table__orders">


            <div class="orders__item-main-start">
                <div>Номер заказов</div>
                <div>Дата</div>
                <div class="">Адресс доставки</div>
                <div>Итого</div>
                 <!-- <div>Статус</div> -->
                <div>Статус</div>
                <div>Повторить</div>
                <div></div>
            </div>

            <? foreach($arResult['ORDERS'] as $order):?>


                <? $date = mb_substr(trim($order['ORDER']['DATE_INSERT_FORMATED']), -4, 4); //  ?>


                <? if($date == 2022){ ?>



                    <div class="orders__item">
                        <div><?= $order['ORDER']['ACCOUNT_NUMBER']?>    </div>
                        <div><?= $order['ORDER']['DATE_INSERT_FORMATED']?></div>
                        <div><?= $order['SHIPMENT'][0]['DELIVERY_NAME']?></div>
                        <div class="order__total-price">
                            <div><?= $order['ORDER']['FORMATED_PRICE']?></div>
                           <? if($order['ORDER']['OLD_PRICE']):?> <div>2 390.0</div><? else:?><div></div><?endif;?>
                        </div>
                        <div class="printer">
                            
                            <? if($order['ORDER']['PAYED'] == 'Y'){?>

                                <div class="green_pay">Оплачен</div>

                            <? }else{ ?>

                               <a class="red" href="<?= $order['PAYMENT'][0]['PSA_ACTION_FILE'] ?>">Оплатить</a>

                            <? } ?>


                        </div>
                        <a class="repeat" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>">
                            <div class="repeat__svg">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g clip-path="url(#clip0)">
                                    <path d="M0.833984 16.6667V11.6667H5.83398" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M19.166 3.33325V8.33325H14.166" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2.92565 7.49998C3.34829 6.30564 4.06659 5.23782 5.01354 4.39616C5.96048 3.55451 7.10521 2.96645 8.34089 2.68686C9.57657 2.40727 10.8629 2.44527 12.08 2.79729C13.297 3.14932 14.405 3.80391 15.3007 4.69998L19.1673 8.33331M0.833984 11.6666L4.70065 15.3C5.59627 16.1961 6.70429 16.8506 7.92132 17.2027C9.13835 17.5547 10.4247 17.5927 11.6604 17.3131C12.8961 17.0335 14.0408 16.4455 14.9878 15.6038C15.9347 14.7621 16.653 13.6943 17.0756 12.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </g>
                                    <defs>
                                    <clipPath id="clip0">
                                    <rect width="20" height="20" fill="white"/>
                                    </clipPath>
                                    </defs>
                                </svg>                                
                            </div>
                        </a>
                        <div>
                            <div class="open-order-items">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="green tae" style="white-space: nowrap;">

                                <?
                                    

                                        if ($order['ORDER']['DEDUCTED'] == 'Y')
                                        {
                                            ?>
                                                <?=Loc::getMessage('SPOL_TPL_LOADED');?>
                                            <?
                                        }
                                        else
                                        {
                                            ?>
                                                <?=Loc::getMessage('SPOL_TPL_NOTLOADED');?>
                                            <?
                                        }
                                        ?>


                                <?//= $order['SHIPMENT'][0]['DELIVERY_STATUS_NAME'] ?>
                                    
                                </div>
                        </div>
                        <div class="order__item-show" style="display: none;">


                            <? foreach($order['BASKET_ITEMS'] as $item): ?>


                                <div class="orders__item order__item">
                                    <div><?= $item['PRODUCT_ID']?></div>
                                    <div><?= $item['NAME']?></div>
                                    <div class="order__total-price">
                                        <?  if(CModule::IncludeModule("currency")){  ?>
                                        <div><?= CurrencyFormat($item['PRICE'], "RUB");?></div>
                                        <div></div>
                                        <? } ?>
                                    </div>
                                    <div class="repeat__text">обновлено</div>
                                    <div class="order__item-end">
                                        <div class="green tae open-order-items">
                                            В наличии
                                        </div>
                                        <div class="">сегодня</div>
                                    </div>
                                </div>

                            <? endforeach?>

                        </div>
                    </div>  


                <? } ?>


                <?endforeach?>


            </div>
        </div>

        <!-- table__orders END  -->

        <? } ?>

    </div>
</section>


















<script>
    
    $('.tab_link').click(function(e) {
        
        $('.tab_link').removeClass('active')

        $(this).addClass('active')

        let data_year = $(this).data('year');

        $('.tab_order').hide(200);

        $('.'+ data_year).show(200);



    });


</script>




<div  style="display: none"  class="container">
<?
	echo $arResult["NAV_STRING"];

	if ($_REQUEST["filter_history"] !== 'Y')
	{
		$javascriptParams = array(
			"url" => CUtil::JSEscape($this->__component->GetPath().'/ajax.php'),
			"templateFolder" => CUtil::JSEscape($templateFolder),
			"templateName" => $this->__component->GetTemplateName(),
			"paymentList" => $paymentChangeData,
			"returnUrl" => CUtil::JSEscape($arResult["RETURN_URL"]),
		);
		$javascriptParams = CUtil::PhpToJSObject($javascriptParams);
		?>
		<script>
			BX.Sale.PersonalOrderComponent.PersonalOrderList.init(<?=$javascriptParams?>);
		</script>
		<?
	}

}?>

</div>
