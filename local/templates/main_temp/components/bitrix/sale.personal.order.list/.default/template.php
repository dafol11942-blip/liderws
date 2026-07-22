<?

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







<!-- 	 <section class="kabinet-orders">
        <div class="container kabinet-container">
            <div class="kabinet__menu">
                <nav>
                    <ul class="kabinet__menu-ul">
                        <li class="">
                            <a href="#">Личные данные</a>
                        </li>
                        <li class="">
                            <a href="#">История поиска</a>
                        </li>
                        <li class="active" >
                            <a href="#">Заказы</a>
                        </li>
                        <li>
                            <a href="#">Автомобили</a>
                        </li>
                        <li>
                            <a href="#">Личные данные</a>
                        </li>
                    </ul>
                    <div class="filter__box mr0">
                        <div class="fz14 fw700 consult__tit">Консультация</div>
                        <div class="fz14 mt10">Получите профессианальную консультацию у наших специалистов. </div>
                        <div class="footer__icons kabinet__icons mt10">
                            <a href="#">
                                <svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.3347 15.4277C14.3831 15.8864 14.7763 14.9253 14.7763 14.9253L17.5503 0.989739C17.5285 0.050508 16.2616 0.618415 16.2616 0.618415L0.731508 6.71249C0.731508 6.71249 -0.0111401 6.97461 0.0543876 7.4333C0.119915 7.89199 0.709665 8.11042 0.709665 8.11042L4.61949 9.42097C4.61949 9.42097 5.79899 13.2871 6.03925 14.0298C6.25768 14.7506 6.45426 14.7724 6.45426 14.7724C6.67269 14.8598 6.86927 14.7069 6.86927 14.7069L9.40301 12.4134L13.3347 15.4277ZM14.0118 3.45793C14.0118 3.45793 14.5579 3.13029 14.536 3.45793C14.536 3.45793 14.6234 3.50162 14.3394 3.80741C14.0773 4.06952 7.89588 9.61754 7.06586 10.3602C7.00033 10.4039 6.95665 10.4694 6.95665 10.5568L6.71638 12.61C6.6727 12.8284 6.38874 12.8502 6.32321 12.6537L5.29661 9.2899C5.25293 9.15884 5.29661 9.00595 5.42767 8.91858L14.0118 3.45793Z" fill="white"/>
                                </svg>                                
                            </a>
                            <a href="#">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13.6894 2.33514C12.2071 0.821622 10.158 0 8.0654 0C3.61853 0 0.0435967 3.58919 0.0871935 7.95676C0.0871935 9.34054 0.479564 10.6811 1.13351 11.8919L0 16L4.22888 14.9189C5.40599 15.5676 6.7139 15.8703 8.0218 15.8703C12.4251 15.8703 16 12.2811 16 7.91351C16 5.79459 15.1717 3.80541 13.6894 2.33514ZM8.0654 14.5297C6.88828 14.5297 5.71117 14.227 4.70845 13.6216L4.44687 13.4919L1.91826 14.1405L2.57221 11.6757L2.39782 11.4162C0.479564 8.34595 1.3951 4.28108 4.53406 2.37838C7.67302 0.475676 11.7275 1.38378 13.6458 4.4973C15.564 7.61081 14.6485 11.6324 11.5095 13.5351C10.5068 14.1838 9.2861 14.5297 8.0654 14.5297ZM11.9019 9.72973L11.4223 9.51351C11.4223 9.51351 10.7248 9.21081 10.2888 8.99459C10.2452 8.99459 10.2016 8.95135 10.158 8.95135C10.0272 8.95135 9.94005 8.99459 9.85286 9.03784C9.85286 9.03784 9.80926 9.08108 9.19891 9.77297C9.15531 9.85946 9.06812 9.9027 8.98093 9.9027H8.93733C8.89373 9.9027 8.80654 9.85946 8.76294 9.81622L8.54496 9.72973C8.0654 9.51351 7.62943 9.25405 7.28065 8.90811C7.19346 8.82162 7.06267 8.73514 6.97548 8.64865C6.6703 8.34595 6.36512 8 6.14714 7.61081L6.10354 7.52432C6.05995 7.48108 6.05995 7.43784 6.01635 7.35135C6.01635 7.26486 6.01635 7.17838 6.05995 7.13514C6.05995 7.13514 6.23433 6.91892 6.36512 6.78919C6.45232 6.7027 6.49591 6.57297 6.58311 6.48649C6.6703 6.35676 6.7139 6.18378 6.6703 6.05405C6.6267 5.83784 6.10354 4.67027 5.97275 4.41081C5.88556 4.28108 5.79837 4.23784 5.66757 4.19459H5.53678C5.44959 4.19459 5.3188 4.19459 5.18801 4.19459C5.10082 4.19459 5.01362 4.23784 4.92643 4.23784L4.88283 4.28108C4.79564 4.32432 4.70845 4.41081 4.62125 4.45405C4.53406 4.54054 4.49046 4.62703 4.40327 4.71351C4.09809 5.1027 3.92371 5.57838 3.92371 6.05405C3.92371 6.4 4.0109 6.74595 4.14169 7.04865L4.18529 7.17838C4.57766 8 5.10082 8.73514 5.79837 9.38378L5.97275 9.55676C6.10354 9.68649 6.23433 9.77297 6.32153 9.9027C7.23706 10.6811 8.28338 11.2432 9.46049 11.5459C9.59128 11.5892 9.76567 11.5892 9.89646 11.6324C10.0272 11.6324 10.2016 11.6324 10.3324 11.6324C10.5504 11.6324 10.812 11.5459 10.9864 11.4595C11.1172 11.373 11.2044 11.373 11.2916 11.2865L11.3787 11.2C11.4659 11.1135 11.5531 11.0703 11.6403 10.9838C11.7275 10.8973 11.8147 10.8108 11.8583 10.7243C11.9455 10.5514 11.9891 10.3351 12.0327 10.1189C12.0327 10.0324 12.0327 9.9027 12.0327 9.81622C12.0327 9.81622 11.9891 9.77297 11.9019 9.72973Z" fill="white"/>
                                </svg>                                
                            </a>
                            <a href="#">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.66732 2.66675H13.334C14.0673 2.66675 14.6673 3.26675 14.6673 4.00008V12.0001C14.6673 12.7334 14.0673 13.3334 13.334 13.3334H2.66732C1.93398 13.3334 1.33398 12.7334 1.33398 12.0001V4.00008C1.33398 3.26675 1.93398 2.66675 2.66732 2.66675Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M14.6673 4L8.00065 8.66667L1.33398 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>                                                        
                            </a>
                        </div>  
                    </div>
                    <a href="#" class="filter__reset btn bg-red mr0" >Выход</a>
                </nav>
            </div> -->
            <div class="kabinet__orders">
                <div class="kabinet__change-tit">
                    <div>Заказы</div>
                </div>
                <div style="max-width: 70px;" class="iphone__chekbox  orders__chekbox">
                    <div class="active">2022</div>
                   <!--  <div class="">2021</div>
                    <div class="">2020</div>
                    <div>2019</div>
                    <div>2018</div> -->
                    <!-- <div>2017</div> -->
                </div>
                <div class="table__orders">
                    <div class="orders__item-main-start">
                        <div>Номер заказов</div>
                        <div>Дата</div>
                        <div class="">Адресс доставки</div>
                        <div>Итого</div>
                        <div>Распечатать</div>
                        <div>Повторить</div>
                        <div></div>
                    </div>

            <? foreach($arResult['ORDERS'] as $order):?>

                        <?

                        // $order = $order['ORDER'];

                        debug($order);

                        ?>


                    <div class="orders__item">
                        <div><?= $order['ORDER']['ACCOUNT_NUMBER']?>    </div>
                        <div><?= $order['ORDER']['DATE_INSERT_FORMATED']?></div>
                        <div><?= $order['SHIPMENT'][0]['DELIVERY_NAME']?></div>
                        <div class="order__total-price">
                            <div><?= $order['ORDER']['FORMATED_PRICE']?></div>
                           <? if($order['ORDER']['OLD_PRICE']):?> <div>2 390.0</div><? else:?><div></div><?endif;?>
                        </div>
                        <div class="printer">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.99935 15H3.33268C2.89065 15 2.46673 14.8244 2.15417 14.5118C1.84161 14.1993 1.66602 13.7754 1.66602 13.3333V9.16667C1.66602 8.72464 1.84161 8.30072 2.15417 7.98816C2.46673 7.6756 2.89065 7.5 3.33268 7.5H16.666C17.108 7.5 17.532 7.6756 17.8445 7.98816C18.1571 8.30072 18.3327 8.72464 18.3327 9.16667V13.3333C18.3327 13.7754 18.1571 14.1993 17.8445 14.5118C17.532 14.8244 17.108 15 16.666 15H14.9993" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M15 11.6667H5V18.3334H15V11.6667Z" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5 7.50008V1.66675H15V7.50008" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <a href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>">
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
                                    // debug($order['ORDER']);


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


                                <?

                                // debug($order);

                                ?>


                            <? foreach($order['BASKET_ITEMS'] as $item): ?>

                                <?

                                // debug($item);

                                ?>


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

            <?endforeach?>
                </div>
            </div>
        </div>
    </section>
















<div style="display: none">
	
	<div class="row col-md-12 col-sm-12">
		<?
		$nothing = !isset($_REQUEST["filter_history"]) && !isset($_REQUEST["show_all"]);
		$clearFromLink = array("filter_history","filter_status","show_all", "show_canceled");

		if ($nothing || $_REQUEST["filter_history"] == 'N')
		{
			?>
			<a class="sale-order-history-link" href="<?=$APPLICATION->GetCurPageParam("filter_history=Y", $clearFromLink, false)?>">
				<?echo Loc::getMessage("SPOL_TPL_VIEW_ORDERS_HISTORY")?>
			</a>
			<?
		}
		if ($_REQUEST["filter_history"] == 'Y')
		{
			?>
			<a class="sale-order-history-link" href="<?=$APPLICATION->GetCurPageParam("", $clearFromLink, false)?>">
				<?echo Loc::getMessage("SPOL_TPL_CUR_ORDERS")?>
			</a>
			<?
			if ($_REQUEST["show_canceled"] == 'Y')
			{
				?>
				<a class="sale-order-history-link" href="<?=$APPLICATION->GetCurPageParam("filter_history=Y", $clearFromLink, false)?>">
					<?echo Loc::getMessage("SPOL_TPL_VIEW_ORDERS_HISTORY")?>
				</a>
				<?
			}
			else
			{
				?>
				<a class="sale-order-history-link" href="<?=$APPLICATION->GetCurPageParam("filter_history=Y&show_canceled=Y", $clearFromLink, false)?>">
					<?echo Loc::getMessage("SPOL_TPL_VIEW_ORDERS_CANCELED")?>
				</a>
				<?
			}
		}
		?>
	</div>
	<?
	if (!count($arResult['ORDERS']))
	{
		?>
		<div class="row col-md-12 col-sm-12">
			<a href="<?=htmlspecialcharsbx($arParams['PATH_TO_CATALOG'])?>" class="sale-order-history-link">
				<?=Loc::getMessage('SPOL_TPL_LINK_TO_CATALOG')?>
			</a>
		</div>
		<?
	}

	if ($_REQUEST["filter_history"] !== 'Y')
	{
		$paymentChangeData = array();
		$orderHeaderStatus = null;

		foreach ($arResult['ORDERS'] as $key => $order)
		{
			if ($orderHeaderStatus !== $order['ORDER']['STATUS_ID'] && $arResult['SORT_TYPE'] == 'STATUS')
			{
				$orderHeaderStatus = $order['ORDER']['STATUS_ID'];

				?>
				<h1 class="sale-order-title">
					<?= Loc::getMessage('SPOL_TPL_ORDER_IN_STATUSES') ?> &laquo;<?=htmlspecialcharsbx($arResult['INFO']['STATUS'][$orderHeaderStatus]['NAME'])?>&raquo;
				</h1>
				<?
			}
			?>
			<div class="col-md-12 col-sm-12 sale-order-list-container">
				<div class="row">
					<div class="col-md-12 col-sm-12 col-xs-12 sale-order-list-title-container">
						<h2 class="sale-order-list-title">
							<?=Loc::getMessage('SPOL_TPL_ORDER')?>
							<?=Loc::getMessage('SPOL_TPL_NUMBER_SIGN').$order['ORDER']['ACCOUNT_NUMBER']?>
							<?=Loc::getMessage('SPOL_TPL_FROM_DATE')?>
							<?=$order['ORDER']['DATE_INSERT_FORMATED']?>,
							<?=count($order['BASKET_ITEMS']);?>
							<?
							$count = count($order['BASKET_ITEMS']) % 10;
							if ($count == '1')
							{
								echo Loc::getMessage('SPOL_TPL_GOOD');
							}
							elseif ($count >= '2' && $count <= '4')
							{
								echo Loc::getMessage('SPOL_TPL_TWO_GOODS');
							}
							else
							{
								echo Loc::getMessage('SPOL_TPL_GOODS');
							}
							?>
							<?=Loc::getMessage('SPOL_TPL_SUMOF')?>
							<?=$order['ORDER']['FORMATED_PRICE']?>
						</h2>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12 sale-order-list-inner-container">
						<span class="sale-order-list-inner-title-line">
							<span class="sale-order-list-inner-title-line-item"><?=Loc::getMessage('SPOL_TPL_PAYMENT')?></span>
							<span class="sale-order-list-inner-title-line-border"></span>
						</span>
						<?
						$showDelimeter = false;
						foreach ($order['PAYMENT'] as $payment)
						{
							if ($order['ORDER']['LOCK_CHANGE_PAYSYSTEM'] !== 'Y')
							{
								$paymentChangeData[$payment['ACCOUNT_NUMBER']] = array(
									"order" => htmlspecialcharsbx($order['ORDER']['ACCOUNT_NUMBER']),
									"payment" => htmlspecialcharsbx($payment['ACCOUNT_NUMBER']),
									"allow_inner" => $arParams['ALLOW_INNER'],
									"refresh_prices" => $arParams['REFRESH_PRICES'],
									"path_to_payment" => $arParams['PATH_TO_PAYMENT'],
									"only_inner_full" => $arParams['ONLY_INNER_FULL'],
									"return_url" => $arResult['RETURN_URL'],
								);
							}
							?>
							<div class="row sale-order-list-inner-row">
								<?
								if ($showDelimeter)
								{
									?>
									<div class="sale-order-list-top-border"></div>
									<?
								}
								else
								{
									$showDelimeter = true;
								}
								?>

								<div class="sale-order-list-inner-row-body">
									<div class="col-md-9 col-sm-8 col-xs-12 sale-order-list-payment">
										<div class="sale-order-list-payment-title">
											<?
											$paymentSubTitle = Loc::getMessage('SPOL_TPL_BILL')." ".Loc::getMessage('SPOL_TPL_NUMBER_SIGN').htmlspecialcharsbx($payment['ACCOUNT_NUMBER']);
											if(isset($payment['DATE_BILL']))
											{
												$paymentSubTitle .= " ".Loc::getMessage('SPOL_TPL_FROM_DATE')." ".$payment['DATE_BILL_FORMATED'];
											}
											$paymentSubTitle .=",";
											echo $paymentSubTitle;
											?>
											<span class="sale-order-list-payment-title-element"><?=$payment['PAY_SYSTEM_NAME']?></span>
											<?
											if ($payment['PAID'] === 'Y')
											{
												?>
												<span class="sale-order-list-status-success"><?=Loc::getMessage('SPOL_TPL_PAID')?></span>
												<?
											}
											elseif ($order['ORDER']['IS_ALLOW_PAY'] == 'N')
											{
												?>
												<span class="sale-order-list-status-restricted"><?=Loc::getMessage('SPOL_TPL_RESTRICTED_PAID')?></span>
												<?
											}
											else
											{
												?>
												<span class="sale-order-list-status-alert"><?=Loc::getMessage('SPOL_TPL_NOTPAID')?></span>
												<?
											}
											?>
										</div>
										<div class="sale-order-list-payment-price">
											<span class="sale-order-list-payment-element"><?=Loc::getMessage('SPOL_TPL_SUM_TO_PAID')?>:</span>

											<span class="sale-order-list-payment-number"><?=$payment['FORMATED_SUM']?></span>
										</div>
										<?
										if (!empty($payment['CHECK_DATA']))
										{
											$listCheckLinks = "";
											foreach ($payment['CHECK_DATA'] as $checkInfo)
											{
												$title = Loc::getMessage('SPOL_CHECK_NUM', array('#CHECK_NUMBER#' => $checkInfo['ID']))." - ". htmlspecialcharsbx($checkInfo['TYPE_NAME']);
												if($checkInfo['LINK'] <> '')
												{
													$link = $checkInfo['LINK'];
													$listCheckLinks .= "<div><a href='$link' target='_blank'>$title</a></div>";
												}
											}
											if ($listCheckLinks <> '')
											{
												?>
												<div class="sale-order-list-payment-check">
													<div class="sale-order-list-payment-check-left"><?= Loc::getMessage('SPOL_CHECK_TITLE')?>:</div>
													<div class="sale-order-list-payment-check-left">
														<?=$listCheckLinks?>
													</div>
												</div>
												<?
											}
										}
										if ($payment['PAID'] !== 'Y' && $order['ORDER']['LOCK_CHANGE_PAYSYSTEM'] !== 'Y')
										{
											?>
											<a href="#" class="sale-order-list-change-payment" id="<?= htmlspecialcharsbx($payment['ACCOUNT_NUMBER']) ?>">
												<?= Loc::getMessage('SPOL_TPL_CHANGE_PAY_TYPE') ?>
											</a>
											<?
										}
										if ($order['ORDER']['IS_ALLOW_PAY'] == 'N' && $payment['PAID'] !== 'Y')
										{
											?>
											<div class="sale-order-list-status-restricted-message-block">
												<span class="sale-order-list-status-restricted-message"><?=Loc::getMessage('SOPL_TPL_RESTRICTED_PAID_MESSAGE')?></span>
											</div>
											<?
										}
										?>

									</div>
									<?
									if ($payment['PAID'] === 'N' && $payment['IS_CASH'] !== 'Y' && $payment['ACTION_FILE'] !== 'cash')
									{
										if ($order['ORDER']['IS_ALLOW_PAY'] == 'N')
										{
											?>
											<div class="col-md-3 col-sm-4 col-xs-12 sale-order-list-button-container">
												<a class="sale-order-list-button inactive-button">
													<?=Loc::getMessage('SPOL_TPL_PAY')?>
												</a>
											</div>
											<?
										}
										elseif ($payment['NEW_WINDOW'] === 'Y')
										{
											?>
											<div class="col-md-3 col-sm-4 col-xs-12 sale-order-list-button-container">
												<a class="sale-order-list-button" target="_blank" href="<?=htmlspecialcharsbx($payment['PSA_ACTION_FILE'])?>">
													<?=Loc::getMessage('SPOL_TPL_PAY')?>
												</a>
											</div>
											<?
										}
										else
										{
											?>
											<div class="col-md-3 col-sm-4 col-xs-12 sale-order-list-button-container">
												<a class="sale-order-list-button ajax_reload" href="<?=htmlspecialcharsbx($payment['PSA_ACTION_FILE'])?>">
													<?=Loc::getMessage('SPOL_TPL_PAY')?>
												</a>
											</div>
											<?
										}
									}
									?>

								</div>
								<div class="col-lg-9 col-md-9 col-sm-10 col-xs-12 sale-order-list-inner-row-template">
									<a class="sale-order-list-cancel-payment">
										<i class="fa fa-long-arrow-left"></i> <?=Loc::getMessage('SPOL_CANCEL_PAYMENT')?>
									</a>
								</div>
							</div>
							<?
						}
						if (!empty($order['SHIPMENT']))
						{
							?>
							<div class="sale-order-list-inner-title-line">
								<span class="sale-order-list-inner-title-line-item"><?=Loc::getMessage('SPOL_TPL_DELIVERY')?></span>
								<span class="sale-order-list-inner-title-line-border"></span>
							</div>
							<?
						}
						$showDelimeter = false;
						foreach ($order['SHIPMENT'] as $shipment)
						{
							if (empty($shipment))
							{
								continue;
							}
							?>
							<div class="row sale-order-list-inner-row">
								<?
									if ($showDelimeter)
									{
										?>
										<div class="sale-order-list-top-border"></div>
										<?
									}
									else
									{
										$showDelimeter = true;
									}
								?>
								<div class="col-md-9 col-sm-8 col-xs-12 sale-order-list-shipment">
									<div class="sale-order-list-shipment-title">
									<span class="sale-order-list-shipment-element">
										<?=Loc::getMessage('SPOL_TPL_LOAD')?>
										<?
										$shipmentSubTitle = Loc::getMessage('SPOL_TPL_NUMBER_SIGN').htmlspecialcharsbx($shipment['ACCOUNT_NUMBER']);
										if ($shipment['DATE_DEDUCTED'])
										{
											$shipmentSubTitle .= " ".Loc::getMessage('SPOL_TPL_FROM_DATE')." ".$shipment['DATE_DEDUCTED_FORMATED'];
										}

										if ($shipment['FORMATED_DELIVERY_PRICE'])
										{
											$shipmentSubTitle .= ", ".Loc::getMessage('SPOL_TPL_DELIVERY_COST')." ".$shipment['FORMATED_DELIVERY_PRICE'];
										}
										echo $shipmentSubTitle;
										?>
									</span>
										<?
										if ($shipment['DEDUCTED'] == 'Y')
										{
											?>
											<span class="sale-order-list-status-success"><?=Loc::getMessage('SPOL_TPL_LOADED');?></span>
											<?
										}
										else
										{
											?>
											<span class="sale-order-list-status-alert"><?=Loc::getMessage('SPOL_TPL_NOTLOADED');?></span>
											<?
										}
										?>
									</div>

									<div class="sale-order-list-shipment-status">
										<span class="sale-order-list-shipment-status-item"><?=Loc::getMessage('SPOL_ORDER_SHIPMENT_STATUS');?>:</span>
										<span class="sale-order-list-shipment-status-block"><?=htmlspecialcharsbx($shipment['DELIVERY_STATUS_NAME'])?></span>
									</div>

									<?
									if (!empty($shipment['DELIVERY_ID']))
									{
										?>
										<div class="sale-order-list-shipment-item">
											<?=Loc::getMessage('SPOL_TPL_DELIVERY_SERVICE')?>:
											<?=$arResult['INFO']['DELIVERY'][$shipment['DELIVERY_ID']]['NAME']?>
										</div>
										<?
									}

									if (!empty($shipment['TRACKING_NUMBER']))
									{
										?>
										<div class="sale-order-list-shipment-item">
											<span class="sale-order-list-shipment-id-name"><?=Loc::getMessage('SPOL_TPL_POSTID')?>:</span>
											<span class="sale-order-list-shipment-id"><?=htmlspecialcharsbx($shipment['TRACKING_NUMBER'])?></span>
											<span class="sale-order-list-shipment-id-icon"></span>
										</div>
										<?
									}
									?>
								</div>
								<?
								if ($shipment['TRACKING_URL'] <> '')
								{
									?>
									<div class="col-md-2 col-md-offset-1 col-sm-12 sale-order-list-shipment-button-container">
										<a class="sale-order-list-shipment-button" target="_blank" href="<?=$shipment['TRACKING_URL']?>">
											<?=Loc::getMessage('SPOL_TPL_CHECK_POSTID')?>
										</a>
									</div>
									<?
								}
								?>
							</div>
							<?
						}
						?>
						<div class="row sale-order-list-inner-row">
							<div class="sale-order-list-top-border"></div>
							<div class="col-md-<?=($order['ORDER']['CAN_CANCEL'] !== 'N') ? 8 : 10?>  col-sm-12 sale-order-list-about-container">
								<a class="sale-order-list-about-link" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_DETAIL"])?>"><?=Loc::getMessage('SPOL_TPL_MORE_ON_ORDER')?></a>
							</div>
							<div class="col-md-2 col-sm-12 sale-order-list-repeat-container">
								<a class="sale-order-list-repeat-link" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>"><?=Loc::getMessage('SPOL_TPL_REPEAT_ORDER')?></a>
							</div>
							<?
							if ($order['ORDER']['CAN_CANCEL'] !== 'N')
							{
								?>
								<div class="col-md-2 col-sm-12 sale-order-list-cancel-container">
									<a class="sale-order-list-cancel-link" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_CANCEL"])?>"><?=Loc::getMessage('SPOL_TPL_CANCEL_ORDER')?></a>
								</div>
								<?
							}
							?>
						</div>
					</div>
				</div>
			</div>
			<?
		}
	}
	else
	{
		$orderHeaderStatus = null;

		if ($_REQUEST["show_canceled"] === 'Y' && count($arResult['ORDERS']))
		{
			?>
			<h1 class="sale-order-title">
				<?= Loc::getMessage('SPOL_TPL_ORDERS_CANCELED_HEADER') ?>
			</h1>
			<?
		}

        // debug($arParams);

		foreach ($arResult['ORDERS'] as $key => $order)
		{
			if ($orderHeaderStatus !== $order['ORDER']['STATUS_ID'] && $_REQUEST["show_canceled"] !== 'Y')
			{
				$orderHeaderStatus = $order['ORDER']['STATUS_ID'];
				?>
				<h1 class="sale-order-title">
					<?= Loc::getMessage('SPOL_TPL_ORDER_IN_STATUSES') ?> &laquo;<?=htmlspecialcharsbx($arResult['INFO']['STATUS'][$orderHeaderStatus]['NAME'])?>&raquo;
				</h1>
				<?
			}
			?>


            <?


                $dbOrderProps = CSaleOrderPropsValue::GetList(
                    array("SORT" => "ASC"),
                    array("ORDER_ID" => $order['ORDER']["ID"], "CODE"=>array("LOCATION", "ADDRESS"))
                );


                while ($arOrderProps = $dbOrderProps->GetNext()):
                        //echo "<pre>"; print_r($arOrderProps); echo "</pre>";
                        $order['ORDER']['ADDRESS'] = $arOrderProps['VALUE'];
                endwhile;

            ?>

            <div class="orders__item">
                        <div>11148514325</div>
                        <div><? echo $order['DATE_INSERT_FORMATED']?></div>
                        <div>Самовывоз в Набережные Челны ТК СДЭК</div> 
                        <div class="order__total-price">
                            <div>2 290.0</div>
                            <div>2 390.0</div>
                        </div>
                        <div class="printer">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.99935 15H3.33268C2.89065 15 2.46673 14.8244 2.15417 14.5118C1.84161 14.1993 1.66602 13.7754 1.66602 13.3333V9.16667C1.66602 8.72464 1.84161 8.30072 2.15417 7.98816C2.46673 7.6756 2.89065 7.5 3.33268 7.5H16.666C17.108 7.5 17.532 7.6756 17.8445 7.98816C18.1571 8.30072 18.3327 8.72464 18.3327 9.16667V13.3333C18.3327 13.7754 18.1571 14.1993 17.8445 14.5118C17.532 14.8244 17.108 15 16.666 15H14.9993" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M15 11.6667H5V18.3334H15V11.6667Z" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M5 7.50008V1.66675H15V7.50008" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <div class="repeat__svg">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0)">
                                <path d="M0.833984 16.6667V11.6667H5.83398" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M19.166 3.33325V8.33325H14.166" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M2.92565 7.49998C3.34829 6.30564 4.06659 5.23782 5.01354 4.39616C5.96048 3.55451 7.10521 2.96645 8.34089 2.68686C9.57657 2.40727 10.8629 2.44527 12.08 2.79729C13.297 3.14932 14.405 3.80391 15.3007 4.69998L19.1673 8.33331M0.833984 11.6666L4.70065 15.3C5.59627 16.1961 6.70429 16.8506 7.92132 17.2027C9.13835 17.5547 10.4247 17.5927 11.6604 17.3131C12.8961 17.0335 14.0408 16.4455 14.9878 15.6038C15.9347 14.7621 16.653 13.6943 17.0756 12.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </g>
                                <defs>
                                <clipPath id="clip0">
                                <rect width="20" height="20" fill="white"></rect>
                                </clipPath>
                                </defs>
                            </svg>                                
                        </div>
                        <div>
                            <div class="open-order-items">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"></path>
                                </svg>
                            </div>
                            <div class="green tae">Доставлено</div>
                        </div>
                        <div class="order__item-show" style="">
                            <div class="orders__item order__item">
                                <div>11148514325</div>
                                <div>Запчасть Volkswagen Golf 6 2009-2012 CAXA КПП-робот (РКПП) 2010</div>
                                <div class="order__total-price">
                                    <div>700.0</div>
                                </div>
                                <div class="repeat__text">обновлено</div>
                                <div class="order__item-end">
                                    <div class="green tae open-order-items">
                                        В наличии
                                    </div>
                                    <div class="">сегодня</div>
                                </div>
                            </div>
                            <div class="orders__item order__item">
                                <div>11148514325</div>
                                <div>Запчасть Volkswagen Golf 6 2009-2012 CAXA КПП-робот (РКПП) 2010</div>
                                <div class="order__total-price">
                                    <div>700.0</div>
                                </div>
                                <div class="repeat__text">обновлено</div>
                                <div class="order__item-end">
                                    <div class="green tae open-order-items">
                                        В наличии
                                    </div>
                                    <div class="">сегодня</div>
                                </div>
                            </div>
                            <div class="orders__item order__item">
                                <div>11148514325</div>
                                <div>Запчасть Volkswagen Golf 6 2009-2012 CAXA КПП-робот (РКПП) 2010</div>
                                <div class="order__total-price">
                                    <div>700.0</div>
                                </div>
                                <div class="repeat__text">обновлено</div>
                                <div class="order__item-end">
                                    <div class="green tae open-order-items">
                                        В наличии
                                    </div>
                                    <div class="">сегодня</div>
                                </div>
                            </div>
                        </div>
                    
                </div>







			<div class="col-md-12 col-sm-12 sale-order-list-container">
				<div class="row">
					<div class="col-md-12 col-sm-12 sale-order-list-accomplished-title-container">
						<div class="row">
							<div class="col-md-8 col-sm-12 sale-order-list-accomplished-title-container">
								<h2 class="sale-order-list-accomplished-title">
									<?= Loc::getMessage('SPOL_TPL_ORDER') ?>
									<?= Loc::getMessage('SPOL_TPL_NUMBER_SIGN') ?>
									<?= htmlspecialcharsbx($order['ORDER']['ACCOUNT_NUMBER'])?>
									<?= Loc::getMessage('SPOL_TPL_FROM_DATE') ?>
									<?= $order['ORDER']['DATE_INSERT'] ?>,
									<?= count($order['BASKET_ITEMS']); ?>
									<?
									$count = mb_substr(count($order['BASKET_ITEMS']), -1);
									if ($count == '1')
									{
										echo Loc::getMessage('SPOL_TPL_GOOD');
									}
									elseif ($count >= '2' || $count <= '4')
									{
										echo Loc::getMessage('SPOL_TPL_TWO_GOODS');
									}
									else
									{
										echo Loc::getMessage('SPOL_TPL_GOODS');
									}
									?>
									<?= Loc::getMessage('SPOL_TPL_SUMOF') ?>
									<?= $order['ORDER']['FORMATED_PRICE'] ?>
								</h2>
							</div>
							<div class="col-md-4 col-sm-12 sale-order-list-accomplished-date-container">
								<?
								if ($_REQUEST["show_canceled"] !== 'Y')
								{
									?>
									<span class="sale-order-list-accomplished-date">
										<?= Loc::getMessage('SPOL_TPL_ORDER_FINISHED')?>
									</span>
									<?
								}
								else
								{
									?>
									<span class="sale-order-list-accomplished-date canceled-order">
										<?= Loc::getMessage('SPOL_TPL_ORDER_CANCELED')?>
									</span>
									<?
								}
								?>
								<span class="sale-order-list-accomplished-date-number"><?= $order['ORDER']['DATE_STATUS_FORMATED'] ?></span>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12 sale-order-list-inner-accomplished">
						<div class="row sale-order-list-inner-row">
							<div class="col-md-3 col-sm-12 sale-order-list-about-accomplished">
								<a class="sale-order-list-about-link" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_DETAIL"])?>">
									<?=Loc::getMessage('SPOL_TPL_MORE_ON_ORDER')?>
								</a>
							</div>
							<div class="col-md-3 col-md-offset-6 col-sm-12 sale-order-list-repeat-accomplished">
								<a class="sale-order-list-repeat-link sale-order-link-accomplished" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>">
									<?=Loc::getMessage('SPOL_TPL_REPEAT_ORDER')?>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?
		}
	}
	?>
	<div class="clearfix"></div>
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
}
?>
</div>