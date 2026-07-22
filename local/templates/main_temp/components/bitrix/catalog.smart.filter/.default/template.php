<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

$templateData = array(
	'TEMPLATE_THEME' => $this->GetFolder().'/themes/'.$arParams['TEMPLATE_THEME'].'/colors.css',
	'TEMPLATE_CLASS' => 'bx_'.$arParams['TEMPLATE_THEME']
);
?>
















<!-- <div class="bx_filter <?=$templateData["TEMPLATE_CLASS"]?> bx_horizontal">
	<div class="bx_filter_section">
		<div class="bx_filter_title"><?echo GetMessage("CT_BCSF_FILTER_TITLE")?></div>
		<form name="<?echo $arResult["FILTER_NAME"]."_form"?>" action="<?echo $arResult["FORM_ACTION"]?>" method="get" class="smartfilter bx_horizontal">
			<?foreach($arResult["HIDDEN"] as $arItem):?>
			<input type="hidden" name="<?echo $arItem["CONTROL_NAME"]?>" id="<?echo $arItem["CONTROL_ID"]?>" value="<?echo $arItem["HTML_VALUE"]?>" />
			<?endforeach;
			//prices
			foreach($arResult["ITEMS"] as $key=>$arItem)
			{
				$key = $arItem["ENCODED_ID"];
				if(isset($arItem["PRICE"])):
					if ($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"] <= 0)
						continue;
					?>
					<div class="bx_filter_parameters_box active">
						<span class="bx_filter_container_modef"></span>
						<div class="bx_filter_parameters_box_title" onclick="smartFilter.hideFilterProps(this)"><?=$arItem["NAME"]?></div>
						<div class="bx_filter_block">
							<div class="bx_filter_parameters_box_container">
								<div class="bx_filter_parameters_box_container_block">
									<div class="bx_filter_input_container">
										<input
											class="min-price"
											type="text"
											name="<?echo $arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
											id="<?echo $arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
											value="<?echo $arItem["VALUES"]["MIN"]["HTML_VALUE"]?>"
											size="5"
											onkeyup="smartFilter.keyup(this)"
										/>
									</div>
								</div>
								<div class="bx_filter_parameters_box_container_block">
									<div class="bx_filter_input_container">
										<input
											class="max-price"
											type="text"
											name="<?echo $arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
											id="<?echo $arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
											value="<?echo $arItem["VALUES"]["MAX"]["HTML_VALUE"]?>"
											size="5"
											onkeyup="smartFilter.keyup(this)"
										/>
									</div>
								</div>
								<div style="clear: both;"></div>

								<div class="bx_ui_slider_track" id="drag_track_<?=$key?>">
									<?
									$price1 = $arItem["VALUES"]["MIN"]["VALUE"];
									$price2 = $arItem["VALUES"]["MIN"]["VALUE"] + round(($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])/4);
									$price3 = $arItem["VALUES"]["MIN"]["VALUE"] + round(($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])/2);
									$price4 = $arItem["VALUES"]["MIN"]["VALUE"] + round((($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])*3)/4);
									$price5 = $arItem["VALUES"]["MAX"]["VALUE"];
									?>
									<div class="bx_ui_slider_part p1"><span><?=$price1?></span></div>
									<div class="bx_ui_slider_part p2"><span><?=$price2?></span></div>
									<div class="bx_ui_slider_part p3"><span><?=$price3?></span></div>
									<div class="bx_ui_slider_part p4"><span><?=$price4?></span></div>
									<div class="bx_ui_slider_part p5"><span><?=$price5?></span></div>

									<div class="bx_ui_slider_pricebar_VD" style="left: 0;right: 0;" id="colorUnavailableActive_<?=$key?>"></div>
									<div class="bx_ui_slider_pricebar_VN" style="left: 0;right: 0;" id="colorAvailableInactive_<?=$key?>"></div>
									<div class="bx_ui_slider_pricebar_V"  style="left: 0;right: 0;" id="colorAvailableActive_<?=$key?>"></div>
									<div class="bx_ui_slider_range" id="drag_tracker_<?=$key?>"  style="left: 0%; right: 0%;">
										<a class="bx_ui_slider_handle left"  style="left:0;" href="javascript:void(0)" id="left_slider_<?=$key?>"></a>
										<a class="bx_ui_slider_handle right" style="right:0;" href="javascript:void(0)" id="right_slider_<?=$key?>"></a>
									</div>
								</div>
								<div style="opacity: 0;height: 1px;"></div>
							</div>
						</div>
					</div>
					<?
					$precision = 2;
					if (Bitrix\Main\Loader::includeModule("currency"))
					{
						$res = CCurrencyLang::GetFormatDescription($arItem["VALUES"]["MIN"]["CURRENCY"]);
						$precision = $res['DECIMALS'];
					}
					$arJsParams = array(
						"leftSlider" => 'left_slider_'.$key,
						"rightSlider" => 'right_slider_'.$key,
						"tracker" => "drag_tracker_".$key,
						"trackerWrap" => "drag_track_".$key,
						"minInputId" => $arItem["VALUES"]["MIN"]["CONTROL_ID"],
						"maxInputId" => $arItem["VALUES"]["MAX"]["CONTROL_ID"],
						"minPrice" => $arItem["VALUES"]["MIN"]["VALUE"],
						"maxPrice" => $arItem["VALUES"]["MAX"]["VALUE"],
						"curMinPrice" => $arItem["VALUES"]["MIN"]["HTML_VALUE"],
						"curMaxPrice" => $arItem["VALUES"]["MAX"]["HTML_VALUE"],
						"fltMinPrice" => intval($arItem["VALUES"]["MIN"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MIN"]["FILTERED_VALUE"] : $arItem["VALUES"]["MIN"]["VALUE"] ,
						"fltMaxPrice" => intval($arItem["VALUES"]["MAX"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MAX"]["FILTERED_VALUE"] : $arItem["VALUES"]["MAX"]["VALUE"],
						"precision" => $precision,
						"colorUnavailableActive" => 'colorUnavailableActive_'.$key,
						"colorAvailableActive" => 'colorAvailableActive_'.$key,
						"colorAvailableInactive" => 'colorAvailableInactive_'.$key,
					);
					?>
					<script type="text/javascript">
						BX.ready(function(){
							window['trackBar<?=$key?>'] = new BX.Iblock.SmartFilter(<?=CUtil::PhpToJSObject($arJsParams)?>);
						});
					</script>
				<?endif;
			}

			//not prices
			foreach($arResult["ITEMS"] as $key=>$arItem)
			{
				if(
					empty($arItem["VALUES"])
					|| isset($arItem["PRICE"])
				)
					continue;

				if (
					$arItem["DISPLAY_TYPE"] == "A"
					&& (
						$arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"] <= 0
					)
				)
					continue;
				?>
				<div class="bx_filter_parameters_box <?if ($arItem["DISPLAY_EXPANDED"]== "Y"):?>active<?endif?>">
					<span class="bx_filter_container_modef"></span>
					<div class="bx_filter_parameters_box_title" onclick="smartFilter.hideFilterProps(this)"><?=$arItem["NAME"]?></div>
					<div class="bx_filter_block">
						<div class="bx_filter_parameters_box_container">
						<?
						$arCur = current($arItem["VALUES"]);
						switch ($arItem["DISPLAY_TYPE"])
						{
							case "A"://NUMBERS_WITH_SLIDER
								?>
								<div class="bx_filter_parameters_box_container_block">
									<div class="bx_filter_input_container">
										<input
											class="min-price"
											type="text"
											name="<?echo $arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
											id="<?echo $arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
											value="<?echo $arItem["VALUES"]["MIN"]["HTML_VALUE"]?>"
											size="5"
											onkeyup="smartFilter.keyup(this)"
										/>
									</div>
								</div>
								<div class="bx_filter_parameters_box_container_block">
									<div class="bx_filter_input_container">
										<input
											class="max-price"
											type="text"
											name="<?echo $arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
											id="<?echo $arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
											value="<?echo $arItem["VALUES"]["MAX"]["HTML_VALUE"]?>"
											size="5"
											onkeyup="smartFilter.keyup(this)"
										/>
									</div>
								</div>
								<div style="clear: both;"></div>

								<div class="bx_ui_slider_track" id="drag_track_<?=$key?>">
									<?
									$value1 = $arItem["VALUES"]["MIN"]["VALUE"];
									$value2 = $arItem["VALUES"]["MIN"]["VALUE"] + round(($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])/4);
									$value3 = $arItem["VALUES"]["MIN"]["VALUE"] + round(($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])/2);
									$value4 = $arItem["VALUES"]["MIN"]["VALUE"] + round((($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"])*3)/4);
									$value5 = $arItem["VALUES"]["MAX"]["VALUE"];
									?>
									<div class="bx_ui_slider_part p1"><span><?=$value1?></span></div>
									<div class="bx_ui_slider_part p2"><span><?=$value2?></span></div>
									<div class="bx_ui_slider_part p3"><span><?=$value3?></span></div>
									<div class="bx_ui_slider_part p4"><span><?=$value4?></span></div>
									<div class="bx_ui_slider_part p5"><span><?=$value5?></span></div>

									<div class="bx_ui_slider_pricebar_VD" style="left: 0;right: 0;" id="colorUnavailableActive_<?=$key?>"></div>
									<div class="bx_ui_slider_pricebar_VN" style="left: 0;right: 0;" id="colorAvailableInactive_<?=$key?>"></div>
									<div class="bx_ui_slider_pricebar_V"  style="left: 0;right: 0;" id="colorAvailableActive_<?=$key?>"></div>
									<div class="bx_ui_slider_range" 	id="drag_tracker_<?=$key?>"  style="left: 0;right: 0;">
										<a class="bx_ui_slider_handle left"  style="left:0;" href="javascript:void(0)" id="left_slider_<?=$key?>"></a>
										<a class="bx_ui_slider_handle right" style="right:0;" href="javascript:void(0)" id="right_slider_<?=$key?>"></a>
									</div>
								</div>
								<?
								$arJsParams = array(
									"leftSlider" => 'left_slider_'.$key,
									"rightSlider" => 'right_slider_'.$key,
									"tracker" => "drag_tracker_".$key,
									"trackerWrap" => "drag_track_".$key,
									"minInputId" => $arItem["VALUES"]["MIN"]["CONTROL_ID"],
									"maxInputId" => $arItem["VALUES"]["MAX"]["CONTROL_ID"],
									"minPrice" => $arItem["VALUES"]["MIN"]["VALUE"],
									"maxPrice" => $arItem["VALUES"]["MAX"]["VALUE"],
									"curMinPrice" => $arItem["VALUES"]["MIN"]["HTML_VALUE"],
									"curMaxPrice" => $arItem["VALUES"]["MAX"]["HTML_VALUE"],
									"fltMinPrice" => intval($arItem["VALUES"]["MIN"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MIN"]["FILTERED_VALUE"] : $arItem["VALUES"]["MIN"]["VALUE"] ,
									"fltMaxPrice" => intval($arItem["VALUES"]["MAX"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MAX"]["FILTERED_VALUE"] : $arItem["VALUES"]["MAX"]["VALUE"],
									"precision" => $arItem["DECIMALS"]? $arItem["DECIMALS"]: 0,
									"colorUnavailableActive" => 'colorUnavailableActive_'.$key,
									"colorAvailableActive" => 'colorAvailableActive_'.$key,
									"colorAvailableInactive" => 'colorAvailableInactive_'.$key,
								);
								?>
								<script type="text/javascript">
									BX.ready(function(){
										window['trackBar<?=$key?>'] = new BX.Iblock.SmartFilter(<?=CUtil::PhpToJSObject($arJsParams)?>);
									});
								</script>
								<?
								break;
							case "B"://NUMBERS
								?>
								<div class="bx_filter_parameters_box_container_block"><div class="bx_filter_input_container">
									<input
										class="min-price"
										type="text"
										name="<?echo $arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
										id="<?echo $arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
										value="<?echo $arItem["VALUES"]["MIN"]["HTML_VALUE"]?>"
										size="5"
										onkeyup="smartFilter.keyup(this)"
										/>
								</div></div>
								<div class="bx_filter_parameters_box_container_block"><div class="bx_filter_input_container">
									<input
										class="max-price"
										type="text"
										name="<?echo $arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
										id="<?echo $arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
										value="<?echo $arItem["VALUES"]["MAX"]["HTML_VALUE"]?>"
										size="5"
										onkeyup="smartFilter.keyup(this)"
										/>
								</div></div>
								<?
								break;
							case "G"://CHECKBOXES_WITH_PICTURES
								?>
								<?foreach ($arItem["VALUES"] as $val => $ar):?>
									<input
										style="display: none"
										type="checkbox"
										name="<?=$ar["CONTROL_NAME"]?>"
										id="<?=$ar["CONTROL_ID"]?>"
										value="<?=$ar["HTML_VALUE"]?>"
										<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
									/>
									<?
									$class = "";
									if ($ar["CHECKED"])
										$class.= " active";
									if ($ar["DISABLED"])
										$class.= " disabled";
									?>
									<label for="<?=$ar["CONTROL_ID"]?>" data-role="label_<?=$ar["CONTROL_ID"]?>" class="labe bx_filter_param_label dib<?=$class?>" onclick="smartFilter.keyup(BX('<?=CUtil::JSEscape($ar["CONTROL_ID"])?>')); BX.toggleClass(this, 'active');">
										<span class="bx_filter_param_btn bx_color_sl">
											<?if (isset($ar["FILE"]) && !empty($ar["FILE"]["SRC"])):?>
											<span class="bx_filter_btn_color_icon" style="background-image:url('<?=$ar["FILE"]["SRC"]?>');"></span>
											<?endif?>
										</span>
									</label>
								<?endforeach?>
								<?
								break;
							case "H"://CHECKBOXES_WITH_PICTURES_AND_LABELS
								?>
								<?foreach ($arItem["VALUES"] as $val => $ar):?>
									<input
										style="display: none"
										type="checkbox"
										name="<?=$ar["CONTROL_NAME"]?>"
										id="<?=$ar["CONTROL_ID"]?>"
										value="<?=$ar["HTML_VALUE"]?>"
										<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
									/>
									<?
									$class = "";
									if ($ar["CHECKED"])
										$class.= " active";
									if ($ar["DISABLED"])
										$class.= " disabled";
									?>
									<label for="<?=$ar["CONTROL_ID"]?>" data-role="label_<?=$ar["CONTROL_ID"]?>" class="label bx_filter_param_label<?=$class?>" onclick="smartFilter.keyup(BX('<?=CUtil::JSEscape($ar["CONTROL_ID"])?>')); BX.toggleClass(this, 'active');">
										<span class="bx_filter_param_btn bx_color_sl">
											<?if (isset($ar["FILE"]) && !empty($ar["FILE"]["SRC"])):?>
												<span class="bx_filter_btn_color_icon" style="background-image:url('<?=$ar["FILE"]["SRC"]?>');"></span>
											<?endif?>
										</span>
										<span class="bx_filter_param_text" title="<?=$ar["VALUE"];?>"><?=$ar["VALUE"];?><?
										if ($arParams["DISPLAY_ELEMENT_COUNT"] !== "N" && isset($ar["ELEMENT_COUNT"])):
											?> (<span data-role="count_<?=$ar["CONTROL_ID"]?>"><? echo $ar["ELEMENT_COUNT"]; ?></span>)<?
										endif;?></span>
									</label>
								<?endforeach?>
								<?
								break;
							case "P"://DROPDOWN
								$checkedItemExist = false;
								?>
								<div class="bx_filter_select_container">
									<div class="bx_filter_select_block" onclick="smartFilter.showDropDownPopup(this, '<?=CUtil::JSEscape($key)?>')">
										<div class="bx_filter_select_text" data-role="currentOption">
											<?
											foreach ($arItem["VALUES"] as $val => $ar)
											{
												if ($ar["CHECKED"])
												{
													echo $ar["VALUE"];
													$checkedItemExist = true;
												}
											}
											if (!$checkedItemExist)
											{
												echo GetMessage("CT_BCSF_FILTER_ALL");
											}
											?>
										</div>
										<div class="bx_filter_select_arrow"></div>
										<input
											style="display: none"
											type="radio"
											name="<?=$arCur["CONTROL_NAME_ALT"]?>"
											id="<? echo "all_".$arCur["CONTROL_ID"] ?>"
											value=""
										/>
										<?foreach ($arItem["VALUES"] as $val => $ar):?>
											<input
												style="display: none"
												type="radio"
												name="<?=$ar["CONTROL_NAME_ALT"]?>"
												id="<?=$ar["CONTROL_ID"]?>"
												value="<? echo $ar["HTML_VALUE_ALT"] ?>"
												<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
											/>
										<?endforeach?>
										<div class="bx_filter_select_popup" data-role="dropdownContent" style="display: none;">
											<ul>
												<li>
													<label for="<?="all_".$arCur["CONTROL_ID"]?>" class="bx_filter_param_label" data-role="label_<?="all_".$arCur["CONTROL_ID"]?>" onclick="smartFilter.selectDropDownItem(this, '<?=CUtil::JSEscape("all_".$arCur["CONTROL_ID"])?>')">
														<? echo GetMessage("CT_BCSF_FILTER_ALL"); ?>
													</label>
												</li>
											<?
											foreach ($arItem["VALUES"] as $val => $ar):
												$class = "";
												if ($ar["CHECKED"])
													$class.= " selected";
												if ($ar["DISABLED"])
													$class.= " disabled";
											?>
												<li>
													<label for="<?=$ar["CONTROL_ID"]?>" class="bx_filter_param_label<?=$class?>" data-role="label_<?=$ar["CONTROL_ID"]?>" onclick="smartFilter.selectDropDownItem(this, '<?=CUtil::JSEscape($ar["CONTROL_ID"])?>')"><?=$ar["VALUE"]?></label>
												</li>
											<?endforeach?>
											</ul>
										</div>
									</div>
								</div>
								<?
								break;
							case "R"://DROPDOWN_WITH_PICTURES_AND_LABELS
								?>
								<div class="bx_filter_select_container">
									<div class="bx_filter_select_block" onclick="smartFilter.showDropDownPopup(this, '<?=CUtil::JSEscape($key)?>')">
										<div class="bx_filter_select_text" data-role="currentOption">
											<?
											$checkedItemExist = false;
											foreach ($arItem["VALUES"] as $val => $ar):
												if ($ar["CHECKED"])
												{
												?>
													<?if (isset($ar["FILE"]) && !empty($ar["FILE"]["SRC"])):?>
														<span class="bx_filter_btn_color_icon" style="background-image:url('<?=$ar["FILE"]["SRC"]?>');"></span>
													<?endif?>
													<span class="bx_filter_param_text">
														<?=$ar["VALUE"]?>
													</span>
												<?
													$checkedItemExist = true;
												}
											endforeach;
											if (!$checkedItemExist)
											{
												?><span class="bx_filter_btn_color_icon all"></span> <?
												echo GetMessage("CT_BCSF_FILTER_ALL");
											}
											?>
										</div>
										<div class="bx_filter_select_arrow"></div>
										<input
											style="display: none"
											type="radio"
											name="<?=$arCur["CONTROL_NAME_ALT"]?>"
											id="<? echo "all_".$arCur["CONTROL_ID"] ?>"
											value=""
										/>
										<?foreach ($arItem["VALUES"] as $val => $ar):?>
											<input
												style="display: none"
												type="radio"
												name="<?=$ar["CONTROL_NAME_ALT"]?>"
												id="<?=$ar["CONTROL_ID"]?>"
												value="<?=$ar["HTML_VALUE_ALT"]?>"
												<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
											/>
										<?endforeach?>
										<div class="bx_filter_select_popup" data-role="dropdownContent" style="display: none">
											<ul>
												<li style="border-bottom: 1px solid #e5e5e5;padding-bottom: 5px;margin-bottom: 5px;">
													<label for="<?="all_".$arCur["CONTROL_ID"]?>" class="bx_filter_param_label" data-role="label_<?="all_".$arCur["CONTROL_ID"]?>" onclick="smartFilter.selectDropDownItem(this, '<?=CUtil::JSEscape("all_".$arCur["CONTROL_ID"])?>')">
														<span class="bx_filter_btn_color_icon all"></span>
														<? echo GetMessage("CT_BCSF_FILTER_ALL"); ?>
													</label>
												</li>
											<?
											foreach ($arItem["VALUES"] as $val => $ar):
												$class = "";
												if ($ar["CHECKED"])
													$class.= " selected";
												if ($ar["DISABLED"])
													$class.= " disabled";
											?>
												<li>
													<label for="<?=$ar["CONTROL_ID"]?>" data-role="label_<?=$ar["CONTROL_ID"]?>" class="bx_filter_param_label<?=$class?>" onclick="smartFilter.selectDropDownItem(this, '<?=CUtil::JSEscape($ar["CONTROL_ID"])?>')">
														<?if (isset($ar["FILE"]) && !empty($ar["FILE"]["SRC"])):?>
															<span class="bx_filter_btn_color_icon" style="background-image:url('<?=$ar["FILE"]["SRC"]?>');"></span>
														<?endif?>
														<span class="bx_filter_param_text">
															<?=$ar["VALUE"]?>
														</span>
													</label>
												</li>
											<?endforeach?>
											</ul>
										</div>
									</div>
								</div>
								<?
								break;
							case "K"://RADIO_BUTTONS
								?>
								<label class="radio_btns bx_filter_param_label" for="<? echo "all_".$arCur["CONTROL_ID"] ?>">
									<span class="bx_filter_input_checkbox">
										<input
											type="radio"
											value=""
											name="<? echo $arCur["CONTROL_NAME_ALT"] ?>"
											id="<? echo "all_".$arCur["CONTROL_ID"] ?>"
											onclick="smartFilter.click(this)"
										/>
										<span class="bx_filter_param_text"><? echo GetMessage("CT_BCSF_FILTER_ALL"); ?></span>
									</span>
								</label>
								<?foreach($arItem["VALUES"] as $val => $ar):?>
									<label data-role="label_<?=$ar["CONTROL_ID"]?>" class="radio_btns bx_filter_param_label" for="<? echo $ar["CONTROL_ID"] ?>">
										<span class="bx_filter_input_checkbox <? echo $ar["DISABLED"] ? 'disabled': '' ?>">
											<input
												type="radio"
												value="<? echo $ar["HTML_VALUE_ALT"] ?>"
												name="<? echo $ar["CONTROL_NAME_ALT"] ?>"
												id="<? echo $ar["CONTROL_ID"] ?>"
												<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
												onclick="smartFilter.click(this)"
											/>
											<span class="bx_filter_param_text" title="<?=$ar["VALUE"];?>"><?=$ar["VALUE"];?><?
											if ($arParams["DISPLAY_ELEMENT_COUNT"] !== "N" && isset($ar["ELEMENT_COUNT"])):
												?> (<span data-role="count_<?=$ar["CONTROL_ID"]?>"><? echo $ar["ELEMENT_COUNT"]; ?></span>)<?
											endif;?></span>
										</span>
									</label>
								<?endforeach;?>
								<?
								break;
							case "U"://CALENDAR
								?>
								<div class="bx_filter_parameters_box_container_block"><div class="bx_filter_input_container bx_filter_calendar_container">
									<?$APPLICATION->IncludeComponent(
										'bitrix:main.calendar',
										'',
										array(
											'FORM_NAME' => $arResult["FILTER_NAME"]."_form",
											'SHOW_INPUT' => 'Y',
											'INPUT_ADDITIONAL_ATTR' => 'class="calendar" placeholder="'.FormatDate("SHORT", $arItem["VALUES"]["MIN"]["VALUE"]).'" onkeyup="smartFilter.keyup(this)" onchange="smartFilter.keyup(this)"',
											'INPUT_NAME' => $arItem["VALUES"]["MIN"]["CONTROL_NAME"],
											'INPUT_VALUE' => $arItem["VALUES"]["MIN"]["HTML_VALUE"],
											'SHOW_TIME' => 'N',
											'HIDE_TIMEBAR' => 'Y',
										),
										null,
										array('HIDE_ICONS' => 'Y')
									);?>
								</div></div>
								<div class="bx_filter_parameters_box_container_block"><div class="bx_filter_input_container bx_filter_calendar_container">
									<?$APPLICATION->IncludeComponent(
										'bitrix:main.calendar',
										'',
										array(
											'FORM_NAME' => $arResult["FILTER_NAME"]."_form",
											'SHOW_INPUT' => 'Y',
											'INPUT_ADDITIONAL_ATTR' => 'class="calendar" placeholder="'.FormatDate("SHORT", $arItem["VALUES"]["MAX"]["VALUE"]).'" onkeyup="smartFilter.keyup(this)" onchange="smartFilter.keyup(this)"',
											'INPUT_NAME' => $arItem["VALUES"]["MAX"]["CONTROL_NAME"],
											'INPUT_VALUE' => $arItem["VALUES"]["MAX"]["HTML_VALUE"],
											'SHOW_TIME' => 'N',
											'HIDE_TIMEBAR' => 'Y',
										),
										null,
										array('HIDE_ICONS' => 'Y')
									);?>
								</div></div>
								<?
								break;
							default://CHECKBOXES
								?>
								<?foreach($arItem["VALUES"] as $val => $ar):?>
									<label data-role="label_<?=$ar["CONTROL_ID"]?>" class="check_box bx_filter_param_label <? echo $ar["DISABLED"] ? 'disabled': '' ?>" for="<? echo $ar["CONTROL_ID"] ?>">
										<span class="bx_filter_input_checkbox">
											<input
												type="checkbox"
												value="<? echo $ar["HTML_VALUE"] ?>"
												name="<? echo $ar["CONTROL_NAME"] ?>"
												id="<? echo $ar["CONTROL_ID"] ?>"
												<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
												onclick="smartFilter.click(this)"
											/>
											<span class="bx_filter_param_text" title="<?=$ar["VALUE"];?>"><?=$ar["VALUE"];?><?
											if ($arParams["DISPLAY_ELEMENT_COUNT"] !== "N" && isset($ar["ELEMENT_COUNT"])):
												?> (<span data-role="count_<?=$ar["CONTROL_ID"]?>"><? echo $ar["ELEMENT_COUNT"]; ?></span>)<?
											endif;?></span>
										</span>
									</label>


									<div class="box__content df jsb ac">
	                                    <div>Синтетические</div>
	                                    <div class="checkbox_phone">
	                                        <input class="checkbox_iphone" id="checkbox1" type="checkbox">
	                                        <input
	                                        	class="checkbox_iphone"
												type="checkbox"
												value="<? echo $ar["HTML_VALUE"] ?>"
												name="<? echo $ar["CONTROL_NAME"] ?>"
												id="<? echo $ar["CONTROL_ID"] ?>"
												<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
												onclick="smartFilter.click(this)"
											/>
	                                        <label for="checkbox1"></label>
	                                    </div>
	                                </div>




								<?endforeach;?>
						<?
						}
						?>
						</div>
						<div class="clb"></div>
					</div>
				</div>

				
                   

			<?
			}
			?>
			<div class="clb"></div>
			<div class="bx_filter_button_box active">
				<div class="bx_filter_block">
					<div class="bx_filter_parameters_box_container">
						<input class="bx_filter_search_button" type="submit" id="set_filter" name="set_filter" value="<?=GetMessage("CT_BCSF_SET_FILTER")?>" />
						<input class="bx_filter_search_reset" type="submit" id="del_filter" name="del_filter" value="<?=GetMessage("CT_BCSF_DEL_FILTER")?>" />

						<div class="bx_filter_popup_result left" id="modef" <?if(!isset($arResult["ELEMENT_COUNT"])) echo 'style="display:none"';?> style="display: inline-block;">
							<?echo GetMessage("CT_BCSF_FILTER_COUNT", array("#ELEMENT_COUNT#" => '<span id="modef_num">'.intval($arResult["ELEMENT_COUNT"]).'</span>'));?>
							<span class="arrow"></span>
							<a href="<?echo $arResult["FILTER_URL"]?>"><?echo GetMessage("CT_BCSF_FILTER_SHOW")?></a>
						</div>
					</div>
				</div>
			</div>
		</form>
		<div style="clear: both;"></div>
	</div>
</div> -->

<?

// debug($arResult);


?>

<? if($_REQUEST['is_ajax'] != 1): ?>

 <div class=" filter__box filter__box-mob-open">
                <div class="box__table df jsb">
                    <div>Фильтры</div>
                    <div class="filter__minus df ac">
                            <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"></path>
                            </svg>
                    </div>
                    <div class="filter__plus ">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"></path>
                            </svg>
                    </div>
                </div>
                <div class="box__tab">
                        <div class="box__content df jsb ac">
                            <div>Синтетические</div>
                            <div class="checkbox_phone">
                                <input class="checkbox_iphone" id="checkbox1" type="checkbox">
                                <label for="checkbox1"></label>
                            </div>
                        </div>
                        <div class="box__content df jsb ac">
                        <div>Синтетические</div>
                        <div class="checkbox_phone">
                            <input class="checkbox_iphone" id="checkbox2" type="checkbox">
                            <label for="checkbox2"></label>
                        </div>
                        </div>
                        <div class="box__content df jsb ac">
                            <div>Синтетические</div>
                            <div class="checkbox_phone">
                                <input class="checkbox_iphone" id="checkbox3" type="checkbox">
                                <label for="checkbox3"></label>
                            </div>
                        </div>
                </div>
            </div>
            <?

            // debug($arResult);

            ?>
            <!-- <form name="<?echo $arResult["FILTER_NAME"]."_form"?>" action="<?echo $arResult["FORM_ACTION"]?>" method="get" class="smartfilter bx_horizontal"> -->
            <form id="filter" name="<?echo $arResult["FILTER_NAME"]."_form"?>" class="product__fulter " action="<?echo $arResult["FORM_ACTION"]?>" method="get">
                <div class="filter__box">
                        <div class="filter__tit df ac jsb filter__box-mob-close">
                            <div>Фильтры</div>
                            <div>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11 8H15" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M1 8L5 8" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M7.99992 10.6666C9.47268 10.6666 10.6666 9.47268 10.6666 7.99992C10.6666 6.52716 9.47268 5.33325 7.99992 5.33325C6.52716 5.33325 5.33325 6.52716 5.33325 7.99992C5.33325 9.47268 6.52716 10.6666 7.99992 10.6666Z" stroke="#668BEA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>





                        <?foreach($arResult["HIDDEN"] as $arItem):?>
							<input type="hidden" name="<?echo $arItem["CONTROL_NAME"]?>" id="<?echo $arItem["CONTROL_ID"]?>" value="<?echo $arItem["HTML_VALUE"]?>" />
						<?endforeach; ?>
							
						























		           		 <div class="bx_filter">
			                <? foreach($arResult["ITEMS"] as $key=>$arItem){
								$key = $arItem["ENCODED_ID"];
								if(isset($arItem["PRICE"])):
									if ($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"] <= 0)
										continue;
									?>

										<div class="bx_filter_parameters_box active box__table box-price">
											<!-- <span class="bx_filter_container_modef"></span> -->

											<div class="mb10 mt20" onclick="//smartFilter.hideFilterProps(this)"><?=$arItem["NAME"]?></div>
											<div class="bx_filter_block">
												<div class="bx_filter_parameters_box_container">

													<div class="bx_filter_parameters_box_container_block">
														<div class="bx_filter_input_container">

															<?

															if(!empty($arItem["VALUES"]["MIN"]["HTML_VALUE"])){
																$min_price_p = $arItem["VALUES"]["MIN"]["HTML_VALUE"];
															}else{
																$min_price_p = $arItem["VALUES"]["MIN"]["VALUE"];
															}

															?>

															<input
																class="min-price"
																type="text"
																name="<?echo $arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
																id="<?echo $arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
																value="<?echo $min_price_p;?>"
																size="5"
																onkeyup="smartFilter.keyup(this)"
																/>

														</div>
													</div>

													<div class="bx_filter_parameters_box_container_block">
														<div class="bx_filter_input_container">

															<?

															if(!empty($arItem["VALUES"]["MAX"]["HTML_VALUE"])){
																$max_price_p = $arItem["VALUES"]["MAX"]["HTML_VALUE"];
															}else{
																$max_price_p = $arItem["VALUES"]["MAX"]["VALUE"];
															}

															?>

															<input
																class="max-price"
																type="text"
																name="<?echo $arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
																id="<?echo $arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
																value="<?echo $max_price_p;?>"
																size="5"
																onkeyup="smartFilter.keyup(this)"
																/>

														</div>
													</div>


													<div style="clear: both;"></div>

													<div class="bx_ui_slider_track" id="drag_track_<?=$key?>">
														<?
														// $precision = $arItem["DECIMALS"]? $arItem["DECIMALS"]: 0;
														// $step = ($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"]) / 4;
														// $price1 = number_format($arItem["VALUES"]["MIN"]["VALUE"], $precision, ".", "");
														// $price2 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step, $precision, ".", "");
														// $price3 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step * 2, $precision, ".", "");
														// $price4 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step * 3, $precision, ".", "");
														// $price5 = number_format($arItem["VALUES"]["MAX"]["VALUE"], $precision, ".", "");







														$precision = $arItem["DECIMALS"]? $arItem["DECIMALS"]: 0;
														$step = ($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"]) / 4;
														$price1 = number_format($arItem["VALUES"]["MIN"]["VALUE"], $precision, ".", "");
														$price2 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step, $precision, ".", "");
														$price3 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step * 2, $precision, ".", "");
														$price4 = number_format($arItem["VALUES"]["MIN"]["VALUE"] + $step * 3, $precision, ".", "");
														$price5 = number_format($arItem["VALUES"]["MAX"]["VALUE"], $precision, ".", "");




														?>
														<!-- <div class="bx_ui_slider_part p1"><span><?=$price1?></span></div>
														<div class="bx_ui_slider_part p2"><span><?=$price2?></span></div>
														<div class="bx_ui_slider_part p3"><span><?=$price3?></span></div>
														<div class="bx_ui_slider_part p4"><span><?=$price4?></span></div>
														<div class="bx_ui_slider_part p5"><span><?=$price5?></span></div>
								 -->
														<div class="bx_ui_slider_pricebar_VD" style="left: 0;right: 0;background: #668BEA;z-index: 100;border: none" id="colorUnavailableActive_<?=$key?>"></div>
														<div class="bx_ui_slider_pricebar_VN" style="left: 0;right: 0;" id="colorAvailableInactive_<?=$key?>"></div>
														<div class="bx_ui_slider_pricebar_V"  style="left: 0;right: 0;" id="colorAvailableActive_<?=$key?>"></div>
														<div class="bx_ui_slider_range" id="drag_tracker_<?=$key?>"  style="left: 0%; right: 0%;">
															<a class="bx_ui_slider_handle left"  style="left:0;" href="javascript:void(0)" id="left_slider_<?=$key?>"></a>
															<a class="bx_ui_slider_handle right" style="right:0;" href="javascript:void(0)" id="right_slider_<?=$key?>"></a>
														</div>
													</div>
													<div style="opacity: 0;height: 1px;"></div>
												</div>
											</div>
										</div>




										<style>
											.mt20 {
												margin-top: 20px;
											}
											.mb10 {
												margin-bottom: 10px;
											}
											.bx_filter .bx_ui_slider_track {
												margin: 18px 10px;
												margin-bottom: 0px !important;
											}

											.box-price {
												margin-bottom: 0 !important;
												padding: 0 0 !important;
											}
											.bx_filter .bx_filter_parameters_box_container .bx_filter_input_container {
												min-width: 115px;
											}
											.bx_filter .bx_filter_parameters_box_container .bx_filter_input_container {
												background: inherit;
												border: none;
												width: initial; 
											    height: initial; 
											    padding: initial;
											    border: 1px solid white;
												border-radius: 6px;
												background-color: white;
											}
											#arrFilter_P1_MIN, #arrFilter_P1_MAX {
												outline: 0 !important;
												border: 1px solid white;
												border-radius: 6px;
												box-shadow: none;
												width: 65%;
												margin: 0 auto;
											}
											.bx_ui_slider_pricebar_VN, .bx_ui_slider_pricebar_V {
												background: #E2E2E2 !important;
												border: none !important;
											}

											.bx_ui_slider_pricebar_V,.bx_ui_slider_pricebar_VN,.bx_ui_slider_pricebar_VD{
												border: none;
											}

											.bx_filter .bx_ui_slider_track {
												background: none;
												border: none;
												box-shadow: none;
											}
											
											.bx_filter .bx_ui_slider_track {

												height: 2px;
											}

											.bx_filter .bx_ui_slider_handle {
												top: -7px;
												width: 16px;
												height: 14px;
												z-index: 110;	
											}

											.bx_filter .bx_ui_slider_range {
												z-index: 110;
											}

											.bx_filter .bx_ui_slider_handle.left {
												margin-left:-12px;
											}

											.bx_filter .bx_ui_slider_handle.right {
												margin-right: -12px;
											}
											.bx_filter .bx_filter_parameters_box_container_block {
												width: 47%;
											}

											/*.bx_filter .bx_ui_slider_pricebar_VN {*/
												/*background: inherit;
												border: none;
											}
										</style>	



									
									<?
									$precision = 2;
									if (Bitrix\Main\Loader::includeModule("currency"))
									{
										$res = CCurrencyLang::GetFormatDescription($arItem["VALUES"]["MIN"]["CURRENCY"]);
										$precision = $res['DECIMALS'];
									}
									$arJsParams = array(
										"leftSlider" => 'slider-left-ui',
										"rightSlider" => 'slider-right-ui',
										"tracker" => "drag_tracker_".$key,
										"trackerWrap" => "drag_track_".$key,
										"minInputId" => $arItem["VALUES"]["MIN"]["CONTROL_ID"],
										"maxInputId" => $arItem["VALUES"]["MAX"]["CONTROL_ID"],
										"minPrice" => $arItem["VALUES"]["MIN"]["VALUE"],
										"maxPrice" => $arItem["VALUES"]["MAX"]["VALUE"],
										"curMinPrice" => $arItem["VALUES"]["MIN"]["HTML_VALUE"],
										"curMaxPrice" => $arItem["VALUES"]["MAX"]["HTML_VALUE"],
										"fltMinPrice" => intval($arItem["VALUES"]["MIN"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MIN"]["FILTERED_VALUE"] : $arItem["VALUES"]["MIN"]["VALUE"] ,
										"fltMaxPrice" => intval($arItem["VALUES"]["MAX"]["FILTERED_VALUE"]) ? $arItem["VALUES"]["MAX"]["FILTERED_VALUE"] : $arItem["VALUES"]["MAX"]["VALUE"],
										"precision" => $precision,
										"colorUnavailableActive" => 'colorUnavailableActive_'.$key,
										"colorAvailableActive" => 'colorAvailableActive_'.$key,
										"colorAvailableInactive" => 'colorAvailableInactive_'.$key,
									);
									?>


									<!-- <script>

											let price_placeholder_min = $('#arrFilter_P1_MIN').val();

											let price_placeholder_max = $('#arrFilter_P1_MAX').val();


											let slider_range = $('#slider-range');


											if(slider_range) {

											  $("#slider-range").slider({
											    range: true,
											    min: parseInt(price_placeholder_min),
											    max: parseInt(price_placeholder_max),
											    step: 10,
											    slide: function (event, ui) {

											    	// let href = "https://lider.netkama.ru/catalog/inomarki/filtry/vozdushnye_filtry/";


													// $.ajax({
												 //        type: "POST",
												 //        url: href,
												 //        data: {
												 //            'ajax': "y",
												 //            'arrFilter_P1_MIN': parseInt(ui.values[0]),
												 //            'arrFilter_P1_MAX': parseInt(ui.values[1]),
												 //          // PRODUCT_ID: ID,
												 //          // QUANTITY: 1,
												 //        },
												 //        success: function (msg) {
												 //          alert(msg);
												 //          // $('.product__inner').replaceWith(msg)
												         
												 //        },
											  //  		});







											        // console.log($(event))
											        // ui.values[0].style.color = 'red';
											  
											        $("#rub-left").text(ui.values[0] + ''); // текст левого span
											        $("#rub-right").text(ui.values[1] + ''); // текст правого span
											  
											        $("#arrFilter_P1_MIN").val(parseInt(ui.values[0]));
											        $("#arrFilter_P1_MAX").val(parseInt(ui.values[1]));


											        // console.log(ui.values);
											  
											        if (ui.handleIndex === 0) {
											            // потянули левый ползунок - переместим левый span
											            // $("#rub-left").css('margin-left', ui.handle.style.left);
											        } else {
											            // потянули правый ползунок - переместим правый span
											            // $("#rub-right").css('margin-left', ui.handle.style.left);
											        }
											    }
											  });

											}



									</script> -->


									<script type="text/javascript">
										BX.ready(function(){
											window['trackBar<?=$key?>'] = new BX.Iblock.SmartFilter(<?=CUtil::PhpToJSObject($arJsParams)?>);
										});
									</script>
								<?endif;
							} ?>

						</div>





                        <!-- <div class="catalog-price">
                            <div class="catalog-price-items ui-slider ui-corner-all ui-slider-horizontal ui-widget ui-widget-content" id="slider-range">
        
                            <div>
                                <span id="rub-left" style="position: absolute;bottom: 20px">99</span>
                                <span id="rub-right" style="position: absolute; margin-left: 50%; bottom: 20px">6 990</span>
                            </div>
        
                            <div class="catalog-price-item hidden">
                                <input placeholder="0" type="text" value="<?echo $arItem["VALUES"]["MIN"]["HTML_VALUE"]?>" name="price[from]" id="price_from">
                            </div>
                            <div class="catalog-price-item hidden">
                                <input placeholder="10000" type="text" value="<?echo $arItem["VALUES"]["MAX"]["HTML_VALUE"]?>" name="price[to]" id="price_to">

                            </div>
                        
                            <div class="ui-slider-range ui-corner-all ui-widget-header bg-blue"></div>
                            <span tabindex="0" class="ui-slider-handle ui-corner-all ui-state-default " style="left: 0%;"></span>
                            <span tabindex="0" class="ui-slider-handle ui-corner-all ui-state-default" style="left: 100%;"></span>
                            <div class="ui-slider-range ui-corner-all ui-widget-header" style="background-color:#E2E2E2; left: 0%; width: 100%; "></div></div>
                        </div> -->
                </div>  
                <!-- 11111111 -->
                <? foreach($arResult['ITEMS'] as $item):?>

                	<?

                		debug($item);	

                	?>


                	<? if(
						empty($item["VALUES"])
						|| isset($item["PRICE"])
					)
						continue; ?>







                	<? if($item['VALUES']):?>
                
		                <div class="filter__box">
			                <div class="box__table df jsb">
			                    <div><? echo $item['NAME'] ?></div>
			                    <div class="filter__minus df ac filter__btns">
			                         <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
			                             <path d="M1 1H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
			                         </svg>
			                    </div>
			                    <div class="filter__plus filter__btns" >
			                         <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
			                         <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
			                         </svg>
			                    </div>
			                </div>
			               <div class="box__tab">



			               		<? if($item['DISPLAY_TYPE'] == 'F' && isset($item['DISPLAY_TYPE'])): ?>
			               


								

				               		<? foreach($item['VALUES'] as $ar):?>

					                     <div class="box__content df jsb ac">
					                         <div><? echo $ar['VALUE']?></div>
					                         <div class="checkbox_phone">
					                             <input
					                             	class="checkbox_iphone"
													type="checkbox"
													value="<? echo $ar["HTML_VALUE"] ?>"
													name="<? echo $ar["CONTROL_NAME"] ?>"
													id="<? echo $ar["CONTROL_ID"] ?>"
													<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
													onclick="smartFilter.click(this)"
												/>
					                             <label for="<? echo $ar["CONTROL_ID"] ?>"></label>
					                         </div>
					                     </div>

					                <? endforeach ?>




					           	<? endif;?>
					       
			                    
			               </div>
		                </div>

		            <? endif;?>

            	<? endforeach?>


            	 <div class="filter__box">
			                <div class="box__table df jsb">
			                    <div>В наличии</div>
			                    <div class="filter__minus df ac filter__btns">
			                         <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
			                             <path d="M1 1H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
			                         </svg>
			                    </div>
			                    <div class="filter__plus filter__btns" >
			                         <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
			                         <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
			                         </svg>
			                    </div>
			                </div>
			               <div class="box__tab">
			                     <div class="box__content df jsb ac">
			                         <div>Да</div>
			                         <div class="checkbox_phone">
			                             <input
			                             	class="checkbox_iphone"
											type="checkbox"
											value="Y"
											name="PROPERTY_IN_STOCK"
											id="PROPERTY_IN_STOCK"
											<? echo $ar["CHECKED"]? 'checked="checked"': '' ?>
											onclick="smartFilter.click(this)"
										/>
			                             <label for="PROPERTY_IN_STOCK"></label>
			                         </div>
			                     </div>
			               </div>
		                </div>





            	<!-- <div class="clb">
					<div class="bx_filter_button_box active">
						<div class="bx_filter_block">
							<div class="bx_filter_parameters_box_container">
								<input class="bx_filter_search_button" type="submit" id="set_filter" name="set_filter" value="<?=GetMessage("CT_BCSF_SET_FILTER")?>" />
								<input class="bx_filter_search_reset" type="submit" id="del_filter" name="del_filter" value="<?=GetMessage("CT_BCSF_DEL_FILTER")?>" /> -->


								<!-- FORM_ACTION
								1	FILTER_URL
								
11111111111

								 -->

								 <?

								 //debug($arResult);

								 ?>

								 <!-- <input class="bx_filter_search_button bx_filter_popup_result left bg-red btn_show" type="submit" id="set_filter" name="set_filter" value="" /> -->

									<div  class="bx_filter_popup_result left bg-red btn_show" id="modef" <?if(!isset($arResult["ELEMENT_COUNT"])) echo 'style="display:none"';?> style="display: inline-block;">
										<a class="btn_show main_link" href="<?echo $arResult["FORM_ACTION"]?>">
											<?echo GetMessage("CT_BCSF_FILTER_COUNT", array("#ELEMENT_COUNT#" => '<span id="modef_num">'.intval($arResult["ELEMENT_COUNT"]).'</span>'));?>
											<?echo GetMessage("CT_BCSF_FILTER_SHOW")?>
										</a>
									</div>

								
						<!-- 	</div>
						</div>
					</div>
                </div> -->

                <!-- <input class="bx_filter_search_button" type="submit" id="set_filter" name="set_filter" value="<?=GetMessage("CT_BCSF_SET_FILTER")?>" />
				<input class="bx_filter_search_reset" type="submit" id="del_filter" name="del_filter" value="<?=GetMessage("CT_BCSF_DEL_FILTER")?>" /> -->

				<!-- <div class="filter__reset btn bg-red" >
					<input type="text" class="hidden" id="set_filter" name="set_filter">
					показать
				</div> -->

				<!-- <button name="set_filter" value="Показать" id='set_filter'>показать</button> -->
				<!-- <input type="submit" value="gjrfpfnm "> -->

				<? 

				//debug($arResult);

				?>

				<!-- <input class="bx_filter_search_reset" type="submit" id="del_filter" name="del_filter" value="<?=GetMessage("CT_BCSF_DEL_FILTER")?>" /> -->

                <a href='<? echo $arResult["SEF_DEL_FILTER_URL"]?>' class="filter__reset btn bg-red" >
                	<!-- <input type="text" class="hidden" name="del_filter"> -->
                    <div>Сбросить все параметры</div>
                    <div>
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.53542 1L4.99989 4.53553M4.99989 4.53553L1.46436 8.07107M4.99989 4.53553L1.46436 1M4.99989 4.53553L8.53542 8.07107" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </a>
            </form>


<? endif; ?>



<style>
	.btn_show {
		/*padding: 1rem;*/
	    /*width: 245px;*/
	    display: block;
	    border-radius: 6px;
	    margin: 0.25rem 0;
	    color: white;
	}
	.main_link {
		width: 250px;
    	padding: 0.5rem 0.5rem 0.5rem  1rem;

	}
</style>



<script>
	var smartFilter = new JCSmartFilter('<?echo CUtil::JSEscape($arResult["FORM_ACTION"])?>', 'horizontal');
</script>