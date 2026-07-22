<?php

if ($IlcatsInjections) {
	$IlcatsInjection = 'AdvertFunc';
	include('IlcatsInjections.php');
}


define('apiStaticContentHost', '//static.ilcats.ru');

function ifAdvancedForm($Data, $SiteLabels) {
	global $data;
	/*$FilterHeader=$Data['formInfo']['filteredParameters']?
		"<span class='FilterSwitch'>Включен фильтр</span> по {$Data['formInfo']['filteredParameters']} параметрам: Отобрано {$Data['formInfo']['recordCount']['filtered']} позиций из {$Data['formInfo']['recordCount']['total']}" :
		"<span class='FilterSwitch'>Фильтр</span> не используется: Всего {$Data['formInfo']['recordCount']['total']} позиций";*/

	foreach ($Data['fields'] as $Group => $FieldsGroup)
		foreach ($FieldsGroup as $Field)
			$Fields[$Group][] = AdvancedFormField($Field);
	foreach ($Fields as $Group => $Set) $Fields[$Group] = implode($Set);

	$Fields = "<div class='PopGroup Popular'>{$Fields['popular']}</div><div class='PopGroup NotPopular'>{$Fields['notPopular']}</div>";
	return "<div class='ifAdvancedForm' Data-FilterData='{$_GET['filterData']}' Data-AjaxLink='//{$_SERVER['HTTP_HOST']}" . (generateLink2(array(
			"params"   => $Data["ajaxUrlParams"],
			"linkText" => $K
		), false)) . "&Ajax=1' Data-fpEncodeMethod='{$Data['parameters']['fpEncodeMethod']}' Data-fpFormDataUrlParamName='{$Data['parameters']['fpFormDataUrlParamName']}'><div class='FilterHeader'><span class='FilterSwitch'>{$SiteLabels['accessoriesFilterLabel']}</span></div><div class='Filters'><div class='PopFieldsButtons'><span class='Popular Active'>Популярные</span><span class='NotPopular'>Все</span></div>{$Fields}</div></div>";
}

function AdvancedFormField($Field) {
	if ($Field['type'] === 'range') {
		//Show($Field);
		//if ($Field['id']==13) {$Field['values']['min']=222; $Field['values']['max']=88888;}
		//if ($Field['id']==10) {$Field['values']['min']=333; $Field['values']['max']=777;}
		$Values = array("<div class='Range'><input class='Min' value='{$Field['range']['selectedLow']}' disabled autocomplete='off'><div class='Slider' Data-Min='{$Field['range']['minValue']}' Data-Max='{$Field['range']['maxValue']}' Data-Min='{$Field['values']['min']}' Data-Max='{$Field['values']['max']}'></div><input class='Max' value='{$Field['range']['selectedHigh']}' disabled autocomplete='off'></div>");
	} else {
		foreach ($Field['values'] as $Group => $ValuesGroup)
			foreach ($ValuesGroup as $Value)
				$Values[$Group][] = AdvancedFormOption($Value);

		foreach ($Values as $Group => $Set)
			$Values[$Group] = "<div class='ValuePopGroup'>" . implode($Set) . "</div>";
		if (count($Values) > 1) $PopGroupButtons = "<div class='PopGroupButtons'><span class='Popular Active'>Популярные</span><span class='NotPopular'>Все</span></div>";
	}

	return "<div class='AdvancedFormField' Data-FieldType='{$Field['type']}' Data-FieldId='{$Field['id']}'>
				<div class='FieldHeader'><div class='Name'>{$Field['name']}</div>{$PopGroupButtons}</div>
				" . implode($Values) . "
			</div>";
}

function AdvancedFormOption($Value) {
	if ($Value['isSelected']) $Value['isSelected'] = 'checked';
	return "<label><input type='checkbox' value='{$Value['value']}' {$Value['isSelected']} autocomplete='off'>{$Value['name']}</label>";
}


function ifPageSelector($Data) {

	if ($_GET['pageCount']) $Data["parameters"]["pageCount"] = $_GET['pageCount'];
	if ($Data["parameters"]["pageCount"] > 1) {
		if ($Data["parameters"]["pageCount"] <= 10)
			for ($i = 1; $i <= $Data["parameters"]["pageCount"]; $i++)
				$Keys[$i] = $i;
		else {
			$Keys = array(1 => 1, $Data["parameters"]["pageCount"] => $Data["parameters"]["pageCount"]);
			$Step = $Data["parameters"]["pageCount"] / 8;
			for ($i = 0; $i <= 7; $i++)
				$Keys[$M = round(1 + $i * $Step)] = $M;
		}
		if ($Data["parameters"]["selectedPage"] > 1) {
			$Keys[$M = $Data["parameters"]["selectedPage"] - 1] = $M;
			$LeftArrow                                          = "<li class='Arrow Left'><a href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => array_merge($Data["parameters"]["urlParams"], array("page" => $Data["parameters"]["selectedPage"] - 1, "pageSize" => $Data["parameters"]["pageSize"])), "linkText" => $K), false) . "'>‹</a></li>";
			$LeftLeftArrow                                      = "<li class='Arrow Left'><a href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => array_merge($Data["parameters"]["urlParams"], array("page" => 1, "pageSize" => $Data["parameters"]["pageSize"])), "linkText" => $K), false) . "'>«</a></li>";
		}
		if ($Data["parameters"]["selectedPage"] < $Data["parameters"]["pageCount"]) {
			$Keys[$M = $Data["parameters"]["selectedPage"] + 1] = $M;
			$RightArrow                                         = "<li class='Arrow Right'><a href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => array_merge($Data["parameters"]["urlParams"], array("page" => $Data["parameters"]["selectedPage"] + 1, "pageSize" => $Data["parameters"]["pageSize"])), "linkText" => $K), false) . "'>›</a></li>";
			$RightRightArrow                                    = "<li class='Arrow Right'><a href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => array_merge($Data["parameters"]["urlParams"], array("page" => $Data["parameters"]["pageCount"], "pageSize" => $Data["parameters"]["pageSize"])), "linkText" => $K), false) . "'>»</a></li>";
		}
		asort($Keys);
		foreach ($Keys as $K) {
			if ($L and ($K - $L) != 1) $Pages[] = "<li class='Empty'>...</li>";
			$L         = $K;
			$Pages[$K] = "<li class='Link'><a href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => array_merge($Data["parameters"]["urlParams"], array("page" => $K, "pageSize" => $Data["parameters"]["pageSize"])), "linkText" => $K), false) . "'>{$K}</a></li>";
		}
		$Pages[$Data["parameters"]["selectedPage"]] = "<li class='Active'>{$Data["parameters"]["selectedPage"]}</li>";
		if ($Data["parameters"]["pageCount"] < 11) unset($LeftArrow, $LeftLeftArrow, $RightArrow, $RightRightArrow);
		return "<ul class='PageSelector'>{$LeftLeftArrow}{$LeftArrow}" . implode($Pages) . "{$RightArrow}{$RightRightArrow}</ul>";
	}
}


function ifForm($Data, $SiteLabels) {
	$MaxRadioCount = 5;
	$InputTypes    = array('selectable' => "type='radio'");
	$Checked       = array('' => '', 1 => "checked=1");
	$Selected      = array('' => '', 1 => "selected=1");
	foreach ($Data['fields'] as $Field) {
		unset($Inputs);
		if ($InputTypes[$Field['type']] != 'selectable' and count($Field['values']) <= $MaxRadioCount and empty($Field['isLongValueNames'])) {
			foreach ($Field['values'] as $Input)
				$Inputs[] = "<label><input name='{$Field['id']}' {$InputTypes[$Field['type']]} {$Checked[empty($Input['isSelected'])?'':$Input['isSelected']]} value='{$Input['value']}'><span>{$Input['name']}</span></label>";
			$Inputs = ImplodeIfArray($Inputs);
		} else {
			foreach ($Field['values'] as $Input)
				$Inputs[] = "<option value='{$Input['value']}' {$Selected[$Input['isSelected']]}>{$Input['name']}</option>";
			$Inputs = "<select name='{$Field['id']}'>" . ImplodeIfArray($Inputs) . "</select>";
		}
		$Fields[] = "<div class='Field'><div class='Header'>{$Field['name']}</div>{$Inputs}</div>";
		if ((!empty($Field['values'][0]['value']) and empty($Data['parameters']['fpFormDataUnknownValue'])) or (!empty($Data['parameters']['fpFormDataUnknownValue']) and $Field['values'][0]['value'] != $Data['parameters']['fpFormDataUnknownValue'])) $URLAppend[] = $Field['id'] . '=' . $Field['values'][0]['value'];
	}
	if (empty($Data['parameters']['fpFormDataUnknownValue'])) $Data['parameters']['fpFormDataUnknownValue'] = '';
	return "<div class='Form' data-FieldsDelimeter='{$Data['parameters']['fpFormFieldsDelimeter']}' data-ValuesDelimeter='{$Data['parameters']['fpFormValuesDelimeter']}' data-EncodeMethod='{$Data['parameters']['fpEncodeMethod']}' data-URL='" . ($URL = generateLink2(array(
				"params"   => $Data['urlParams'],
				"linkText" => $_GET['brand']
			), false) . "&{$Data['parameters']['fpFormDataUrlParamName']}=") . "'" . ($Data['parameters']['fpFormDataUnknownValue'] ? " data-fpFormDataUnknownValue='{$Data['parameters']['fpFormDataUnknownValue']}'" : '') . ">" . ImplodeIfArray($Fields) . "<a href='{$URL}" . base64_encode(ImplodeIfArray(empty($URLAppend) ? [] : $URLAppend, $Data['parameters']['fpFormFieldsDelimeter'])) . "'><button>{$SiteLabels['openCatalog']}</button></a></div>";
}

function ifNoScriptData($Data) {
	foreach ($Data['values'] as $URL) {
		$URLs[] = "<a alt='{$URL['name']}' href='//" . $_SERVER['HTTP_HOST'] . generateLink2(array("params" => $URL['urlParams'], "linkText" => $_GET['brand']), false) . "'>{$URL['name']}</a>";
	}
	return "<noscript>" . ImplodeIfArray($URLs, '<br>') . "</noscript>";
}

function ifTile($Data) {
	$Tiles[] = Listing($Data, $Data['tileItemFormat']);
	return "<div class='Tiles'>" . ImplodeIfArray($Tiles) . "</div>";
}

function ifAdvancedTile($Data) {
	if ($Data['ifAdvancedTileAdditionalInfo']) {
		global $ifAdvancedTileAdditionalInfo;
		$ifAdvancedTileAdditionalInfo = 1;
	}

	$Tiles[] = Listing($Data, $Data['tileItemFormat']);
	return "<div class='Tiles AdvancedTiles'>" . ImplodeIfArray($Tiles) . "</div>";
}


function ifImage($Data, $SiteLabels) {
	global $data, $IlcatsInjections;
	if ($Data['image'])
		foreach ($Data['image'] as $Key => $Val) {
			switch ($Key) {
				case 'filename':
					if (!empty($Data['image']['isStaticImage'])) {
						$ImageUrl = '//static.neoriginal.ru' . "/images/{$_GET['brand']}/{$Val}";
					} else {
						$getHashParams = array("function" => "getImageHash", "brand" => $_GET['brand'], "filename" => $Val, "apiVersion" => '2.0');
						if ($IlcatsInjections) {
							$IlcatsInjection = 'IfImage';
							include('IlcatsInjections.php');
						}


						// define ("apiKey","10d416e8c5f28439a7fdf5d2004e2b06");
						// define ("apiKey","10d416e8c5f28439a7fdf5d2004e2b06");
						define ("apiKey","10d416e8c5f28439a7fdf5d2004e2b06");
						define ("apiClientId","18111");
						define ("apiDomainName","lider.netkama.ru");
						define ("apiRequestUrl","https://api.ilcats.ru/");
						define ("apiExamlpeBrandName","audi");
						define ("apiCurrentVersion","2.0");

						$clientParameters = array( 
						        "apiKey" => apiKey, 
						        "clientId" => apiClientId,  
						        "domain" => apiDomainName,
						        "apiVersion" => apiCurrentVersion
						);

						$Image    = getApiData( 'https://api.ilcats.ru/', $getHashParams, $clientParameters);

						$Image = json_decode($Image, true);
						// ($apiRequestUrl, $functionParameters, $clientParameters);
						$ImageUrl = "//images.ilcats.ru/getImage.php?catalog={$_GET['brand']}&filename={$Val}&hash={$Image['data']['imageHash']}" . ($_SERVER['HTTP_HOST'] != 'www.ilcats.ru' ? '&domain=' . $_SERVER['HTTP_HOST'] : '');
					}
					break;
				case 'callouts':
					$ImageMap = generateImageMap($Data['image']['callouts'], $SiteLabels);
					break;
				case 'imageLinks':
					foreach ($Val as $ILs)
						if ($ILs['isActive']) $ImageLinks[] = "<a href='#' class='Disabled'>{$ILs['name']}</a>";
						else $ImageLinks[] = generateLink2(array("params" => array_merge(array("brand" => $_GET['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"]), $ILs["urlParams"]), "linkText" => $ILs['name']));
					$ImageLinks = "<div>" . ImplodeIfArray($ImageLinks) . "</div>";
					break;
			}
		}
	if (empty($ImageLinks)) $ImageLinks = '';
	if (empty($Data['image']['alt'])) $Data['image']['alt'] = '';
	return "<div class='Images' id='fixed'>
				<div id='ImagesControlPanel'>
					<div><button class='ScaleStep First' data-Direction='-1'>-</button><button class='CurrentScale' disabled>100%</button><button class='ScaleStep Last' data-Direction='1'>+</button></div>
			" .
		ImplodeIfArray($ImageLinks) .
		"</div>
				<div class='ImageArea'>
					<div class='Image 1'>
						<img src='{$ImageUrl}' alt='{$Data['image']['alt']}' usemap='#myMap'>{$ImageMap}
					</div>
				</div>
				<div class='Move'><button></button><span>{$SiteLabels['lbYouCanMoveTheImage']}<br><a href='#'>{$SiteLabels['lbCenterImage']}</a></span></div>
			</div>";
}

function Listing($Data, $ItemFormat, $ChildFormat = '', $TagsType = 'Div', $Child = '') {
	if ($ChildFormat and !$Child) {
		$WrapClass1 = 'Multilist';
		$HeaderWrap = array('Opening' => "<div class='Header'>", 'Closing' => '</div>');
	} else {
		$WrapClass1 = '';
		$HeaderWrap = array('Opening' => "", 'Closing' => '');
	}
	if ($TagsType == 'Table') $Tags = array('Strings' => array('Opening' => "<td class='open'>", 'Closing' => '</td>'), 'Return' => array('Opening' => "<tr " . (!empty($Data['values'][0]['callout']) ? "class='Active TR-{$Data['values'][0]['callout']}' data-ID='{$Data['values'][0]['callout']}'" : "") . ">", 'Closing' => '</tr>'));
	if ($TagsType == 'Div') $Tags = array('ListItems' => array('Opening' => "<div class='List'>", 'Closing' => '</div>'), 'Return' => array('Opening' => "<div class='List {$WrapClass1}'>", 'Closing' => '</div>'));
	if ($Child) {
		$Tags['Child'] = array();
	}
	if (!isset($Tags['Strings']['Opening'])) $Tags['Strings'] = array('Opening' => "", 'Closing' => '');
	if (!isset($Tags['ListItems']['Opening'])) $Tags['ListItems'] = array('Opening' => "", 'Closing' => '');
	foreach ($Data['values'] as $ListItem) {
		unset($Strings);
		$PassQnt = false;
		foreach ($ItemFormat as $Class => $StringFormat) {
			if (--$PassQnt) continue;
			if ($TagsType == 'Table' and !empty($ListItem['colspan'])) {
				$Tags['Strings']['Opening'] = "<td colspan={$ListItem['colspan']}>";
				$PassQnt                    = $ListItem['colspan'];
			} else if ($TagsType == 'Table') $Tags['Strings']['Opening'] = "<td>";
			$Strings[] = $Tags['Strings']['Opening'] . Caption($ListItem, $StringFormat, $Class) . $Tags['Strings']['Closing'];
		}
		$ListItems[] = $Tags['ListItems']['Opening'] . $HeaderWrap['Opening'] . ImplodeIfArray($Strings) . $HeaderWrap['Closing'] . ($ChildFormat ? Listing($ListItem, $ChildFormat, '', 'Div', 1) : '') . $Tags['ListItems']['Closing'];
	}

	// unset($ListItems);
	 // return $Tags['Return']['Opening'] . ImplodeIfArray($ListItems) . $Tags['Return']['Closing'];
}

function ifList($Data) {
	return Listing($Data, $Data['listItemFormat'], '', 'Div', 1);
}

function ifMultilist($Data) {
	return Listing($Data, $Data['multilistItemFormat'], $Data['multilistChildItemFormat']);
}

function ifTable($Data, $SiteLabels) {
	global $data;
	if (!empty($Data['tableColumnHeaders'])) {
		foreach ($Data['tableColumnHeaders'] as $ColHeaders) $Cols[] = "<th>{$ColHeaders}</th>";
		$RowHeaderSpan = count($Data['tableColumnHeaders']);
		$Rows[]        = "<tr>" . ImplodeIfArray($Cols) . "</tr>";
	}
	foreach ($Data['values'] as $RowData)
		if (!empty($RowData['isHeader'])) $Rows[] = "<tr " . (!empty($RowData['callout']) ? "class='Active TR-{$RowData['callout']}' data-ID='{$RowData['callout']}'" : "") . "><th colspan=" . (!empty($RowData['colspan']) ? $RowData['colspan'] : $RowHeaderSpan) . ">{$RowData['name']}</th></tr>";
		else $Rows[] = Listing(array('values' => array(0 => $RowData)), $Data['tableItemFormat'], '', 'Table');
	if ($SiteLabels['close']) {
		$Labels[] = "Data-close='{$SiteLabels['close']}'";
		$Labels[] = "Data-additionalInfo='{$SiteLabels['additionalInfo']}'";
		$Labels[] = "Data-brand='" . ucwords($_GET['brand']) . "'";
		$Labels   = ImplodeIfArray($Labels);
	}
	 // return $Table = "<table {$Labels}>" . ImplodeIfArray($Rows) . "</table>";
}

function Caption($Row, $StringFormat) {
	// if ($StringFormat[1]['caption']=='Применяемость:<br> {usage}')
    global $apiHttpCatalogsPath;
	switch (empty($StringFormat['type']) ? '' : $StringFormat['type']) {
		case '':
			foreach ($StringFormat as $PartStringFormat) {
				$PartString[] = Caption($Row, $PartStringFormat);
			}

			return ImplodeIfArray($PartString);
		case 'ifTable':

			foreach ($StringFormat['tableItemFormat'][0] as $Key => $Val) $StringFormat['tableItemFormat'][0][$Key]['caption'] = '{' . $StringFormat['tableItemFormat'][0][$Key]['caption'] . '}';
			return ifTable(array_merge($StringFormat, array('values' => $Row[$StringFormat['dataSource']])));

		default:

			if (!empty($StringFormat['image'])) $StringFormat['caption'] = $StringFormat['image'];
			preg_match_all("/\{([a-zA-Z0-9]+)\}/i", $StringFormat['caption'], $Matches);
			$Changed = 0;
			if ($Matches[1]) {
				foreach ($Matches[1] as $Match) {
					if ($Replacing = isset($Row[$Match]) ? $Row[$Match] : '') {

						$Changed++;
						switch ($StringFormat['type']) {
							case 'ifLink':
								$Replacing = generateLink2(array("params" => array_merge(array("brand" => $_GET['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"]), $Row["urlParams"]), "linkText" => $Replacing, 'urlAnchor' => empty($Row['urlAnchor']) ? '' : $Row['urlAnchor']));
								break;
							case 'ifPartAdditionalInfo':
								foreach ($Replacing as $K => $V) {
									if ($V) {
										$AddInfoLinks[] = generateLink2(array("params" => array_merge(array("brand" => $_GET['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"], 'title' => $V['name']), $V["urlParams"]), "linkText" => "<img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/{$V['urlParams']['function']}.png' 'alt'='{$V['name']}' 'title'='{$V['name']}' 'Data-Number'='{$V["urlParams"]['number']}'>"));
										$Replacing      = ImplodeIfArray($AddInfoLinks);
									}
								}
								break;
							case 'ifLinkArray':
								//Show($Row);
								if ($Replacing) foreach ($Replacing as $PartNumber) {

									$LinkArray[] = generateLink2($LinkArray1 = array("params" => array_merge(array("brand" => $PartNumber["urlParams"]['brand'] ? $PartNumber["urlParams"]['brand'] : $_GET['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"], 'urlAnchor' => $PartNumber['urlAnchor']), $PartNumber["urlParams"]), "linkText" => $PartNumber['name']));
								}
								$Replacing = ImplodeIfArray($LinkArray, $StringFormat['linkDelimeter'] ? $StringFormat['linkDelimeter'] : ', ');
								break;
							case 'ifPartLink':
								$Replacing = generateArticleUrl2($Replacing);
								break;
							case 'ifPartLinkWBrand':
								$Replacing = generateBrandUrl($Replacing);
								break;
							case 'ifPartLinkArray':
								if ($Replacing) foreach ($Replacing as $PartNumber) $PartNumbers[] = generateArticleUrl2($PartNumber);
								$Replacing = ImplodeIfArray($PartNumbers, $StringFormat['linkDelimeter'] ? $StringFormat['linkDelimeter'] : ', ');
								break;
							case 'ifHeaderText':
								$Replacing = "<div class='ifHeaderText'><b>{$Replacing}</b></div>";
								break;
							case 'ifTileImage':
								if (!empty($Replacing['magnifiedImageFilename']))
									$MagnifiedTitle = "Data-MagnifiedTitle=" . apiStaticContentHost . "/images/{$_GET['brand']}{$Replacing['magnifiedImageFilename']}";

								if (!empty($Row['imageBig']))
									$MagnifiedTitle = "Data-imageBig=" . apiStaticContentHost . "/images/{$_GET['brand']}{$Row['imageBig']['filename']}";

								//Show($Row);
								global $ifAdvancedTileAdditionalInfo;
								if (empty($MagnifiedTitle)) $MagnifiedTitle = '';
								if ($ifAdvancedTileAdditionalInfo)
									$Replacing = generateLink2(array("brand" => $_GET['brand'], "params" => array_merge(array("vin" => empty($_GET["vin"]) ? '' : $_GET["vin"], 'function' => 'getTileAdditionalInfo', 'itemId' => $Row['itemId'])), "linkText" => "<img src='" . apiStaticContentHost . "/images/{$_GET['brand']}{$Replacing['filename']}' alt='{$_GET['brand']} {$Row['id']} {$Row['name']}' {$MagnifiedTitle}>"));
								else $Replacing = generateLink2(array("brand" => $_GET['brand'], "params" => array_merge(array("vin" => empty($_GET["vin"]) ? '' : $_GET["vin"]), $Row["urlParams"]), "linkText" => "<img src='" . apiStaticContentHost . "/images/{$_GET['brand']}{$Replacing['filename']}' alt='{$_GET['brand']} {$Row['id']} {$Row['name']}' {$MagnifiedTitle}>"));
								break;
						}

					}
					$StringFormat['caption'] = str_replace("{{$Match}}", $Replacing, $StringFormat['caption']);
				}
			} else {
				switch ($StringFormat['type']) {
					case 'ifLink':
						$Changed++;
						$StringFormat['caption'] = generateLink2(array("params" => array_merge(array("brand" => $_GET['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"]), $Row["urlParams"]), "linkText" => $StringFormat['caption']));
						break;
					case 'ifPartInfoLink':
						$Changed++;
						if ($Row['partAdditionalInfo'])
							foreach ($Row['partAdditionalInfo'] as $K => $V)
								if ($V) $AddInfoLinks[] = generateLink2(array("params" => array_merge(array("brand" => $V["urlParams"]['brand'], "vin" => empty($_GET["vin"]) ? '' : $_GET["vin"], 'title' => $V['name']), $V["urlParams"]), "linkText" => "<img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/{$V['urlParams']['function']}.png' 'alt'='{$V['name']}' 'title'='{$V['name']}' 'Data-Number'='{$V["urlParams"]['number']}'>"));
						$Match                   = $StringFormat['type'];
						$StringFormat['caption'] = ImplodeIfArray($AddInfoLinks);
						break;
				}
			}
			if (!empty($StringFormat['textAlign'])) $Style = "style='text-align:{$StringFormat['textAlign']};'"; else $Style = '';

			return $Changed ? "<div class='{$Match}' {$Style}>" . $StringFormat['caption'] . "</div>" : ($Matches ? '' : $StringFormat['caption']);
	}
}

function MainMenu($Menu = array(), $stageName) {
	if ($Menu)
		foreach ($Menu as $KeyS => $SubMenu) {
			foreach ($SubMenu as $Key => $Option) {
				$Link["linkText"] = $Option['name'] . ": ";
				if (empty($Option['link'])) $Option['link'] = '';
				$Link["catRootUrl"] = $Option['link'];
				if ($KeyS == 1 && $Key == 0) unset($Option['urlParams']['function']);
				$Link["params"] = !empty($Option['urlParams']) ? $Option['urlParams'] : array();
				if (strlen($Option['label']) > 20) {
					$Label = substr($Option['label'], 0, strpos($Option['label'], ' ', 20));
					if (!$Label) $Label = $Option['label'];
					if ($Label != $Option['label']) {
						//$Label = "<span title='{$Option['label']}'>{$Label}...</span>";
					}
				} else {
					$Label = $Option['label'];
				}
				$Options[] = generateLink2($Link);

			}
		}


	if ($Options) {
		$MenuOptions = "<li>" . ImplodeIfArray($Options, "</li><li>") . "</li>";

		$MenuOptions = $MenuOptions . '<li class="bread__active"><a href="#">'. $stageName . '</a></li>';
	}
	return "<ul>{$MenuOptions}</ul>";


	
}

function Languages($Languages, $apiActiveLanguages) {
	if (count($apiActiveLanguages))
		foreach ($Languages as $K => $Language)
			if (!in_array($K, $apiActiveLanguages)) unset($Languages[$K]);

	if ($Languages and count($Languages) > 1)
		foreach ($Languages as $Language) {
			if ($Language['isSelected']) $LIs[] = "<li class='Selected'><img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/{$Language['image']}' alt='{$Language['hint']}' title='{$Language['hint']}'></li>";
			else $LIs[] = "<li data-language='{$Language["urlParams"]['language']}'>" . ($Language["urlParams"] ? generateLink2(array(
					"params"   => array_merge(array("vin" => empty($_GET["vin"]) ? '' : $_GET["vin"], 'LanguageLink' => 1), $Language["urlParams"]),
					"linkText" => "<img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/{$Language['image']}' alt='{$Language['hint']}' title='{$Language['hint']}'>"
				)) : "<img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/{$Language['image']}' title='{$Language['hint']}' alt='{$Language['hint']}'>") . "</li>";
		}
	return "<div></div><ul id='Languages'>" . ImplodeIfArray(empty($LIs) ? [] : $LIs) . "</ul>";
}

function generateImageMap($Callouts, $SiteLabels) {
	foreach ($Callouts as $ID => $Callout) {
		foreach ($Callout as $CalloutAttrs) {
			if (empty($CalloutAttrs['opacity'])) $CalloutAttrs['opacity'] = '';
			if (empty($CalloutAttrs['label'])) $CalloutAttrs['label'] = '';
			$Map[] = "<div style='background-color:rgba(255,255,255,{$CalloutAttrs['opacity']});' " . (!empty($CalloutAttrs['isNotApplicable']) ? "class='NotUsable' data-NotUsableAlert='{$SiteLabels['notApplicable']}' data-NotUsableTitle='{$SiteLabels['notApplicable']}'" : "class='Reg-{$CalloutAttrs['callout']} Opacity{$CalloutAttrs['opacity']}'") . " data-ID='{$CalloutAttrs['callout']}' data-Coords='" . json_encode([
					$CalloutAttrs['x'],
					$CalloutAttrs['y'],
					$CalloutAttrs['w'],
					$CalloutAttrs['h']
				]) . "'>{$CalloutAttrs['label']}</div>";
		}
	}
	return "<map name='myMap' id='myMap'>" . ImplodeIfArray(empty($Map) ? [] : $Map) . "</map>";
}

function VinForm($VinData,$vinSearchParameters) {
	// echo "11111111";

	if($_GET['q']){

		$_GET['vin'] = $_GET['q'];

	}else{
		$_GET['vin'] = $_GET['vin'];
	}

	if ($vinSearchParameters['examples']) {
		foreach ($vinSearchParameters['examples'] as $Example) {
			$Examples[] = generateLink2(array("params" => array("brand" => $_GET['brand'], "vin" => $Example, "VinAction" => 'Search'), "linkText" => $Example));
		}
		$LinkTemplate = generateLink2(array("params" => array("brand" => $_GET['brand'], "vin" => 'vinValue', "VinAction" => 'Search')), false);
		if (!empty($_GET['vin'])) {
			$additionalParameters = array();
			if (empty($_GET['VinAction'])) $_GET['VinAction']='';
			if ($_GET['VinAction'] != 'Search' and !empty($vinSearchParameters['additionalParameters'])) foreach ($vinSearchParameters['additionalParameters'] as $additionalParameter) $additionalParameters[$additionalParameter] = $_GET[$additionalParameter];
			// $VinData = getApiData(array_merge(array("function" => "getVin", "brand" => $_GET['brand'], "vin" => $_GET['vin'], "apiVersion" => '2.0', "language" => $_GET['language']), $additionalParameters));
			// $VinData 
			//Show($VinData);
			if ($VinData['data']['vins']) {
				$CurrentVinInfo = "";
				foreach ($VinData['data']['vins'] as $Vin) {
					unset($TRs, $LIs);
					foreach ($Vin['description'] as $Label => $Val) $TRs[] = "<tr><td class='Left'>{$Label}</td><td>{$Val}</td></tr>";
					if ($Vin['options']) foreach ($Vin['options'] as $Label => $Val) $LIs[] = "<li><span>{$Label}</span> {$Val}</li>";
					if ($_GET['VinAction'] == 'Search' and !empty($Vin['selectableValues']))
						foreach ($Vin['selectableValues'] as $Label => $Select) {
							unset($Options);
							$Selected[1] = 'selected';
							foreach ($Select['values'] as $Option)
								$Options[] = "<option value='{$Option['id']}' {$Selected[$Option['isSelected']]}>{$Option['name']}</option>";
							$TRs[] = "<tr><td class='Left'>{$Select['name']}</td><td><select data-Name='{$Select['urlParamName']}'><option value=''>{$Select['label']}</option>" . ImplodeIfArray($Options) . "</select></td></tr>";
						}
					$Vins[] = "<div class='VinCard'>
								<table>
									<tr><th colspan=2>{$Vin['shortDescription']}</th></tr>" .
						ImplodeIfArray($TRs) .
						"<tr class='" . ($_GET['VinAction'] == 'Search' ? '' : '') . "'><td colspan=2 class='Center'>" . generateLink2(array("params" => array_merge(array("brand" => $Vin["urlParams"]['brand'] ? $Vin["urlParams"]['brand'] : $_GET['brand'], "VinAction" => 'Choose'), $Vin["urlParams"]), "linkText" => $vinSearchParameters['openCatalogLabel'])) . "</td></tr>" .
						"</table>" .
						($LIs ? "
								<div class='Options'>
									<div class='Header'><span>{$vinSearchParameters['optionsListLabel']}</span><span class='Hide'>{$vinSearchParameters['hideLabel']}</span></div>
									<ul>" . ImplodeIfArray($LIs) . "</ul>
								</div>" : "") .
						"</div>";
				}
				if ($_GET['VinAction'] != 'Search' and count($VinData['data']['vins']) == 1) {
					$CurrentVin = "<span>{$VinData['data']['vins'][0]['shortDescription']}</span><span class='Hide'>{$vinSearchParameters['hideLabel']}</span>";
					if ($_GET['VinAction'] == 'Choose') {
						if ($_COOKIE['Vins']) $VinCookie = json_decode($_COOKIE['Vins'], true);
						$VinCookie[$_GET['brand']] = array_merge(array('vin' => $_GET['vin']), $additionalParameters);
						setcookie('Vins', json_encode($VinCookie), time() + 31536000, '/');
					}
				}
				$Vins = "<div class='VinInfo " . (!empty($CurrentVin) ? '' : "") . "'>" . ImplodeIfArray($Vins) . "</div>";

			} else {
				$SearchMessage = $VinData['errors']['errorVinNotFound'];
			}
		} else {
			if ($Examples) $SearchMessage = $vinSearchParameters['exampleLabel'] . ", " . ImplodeIfArray($Examples, ', ');
		}
	} else {
		$NoVinClass = 'NoVin';
	}
	if (empty($NoVinClass)) $NoVinClass = '';
	if (empty($_GET['vin'])) $_GET['vin'] = '';
	if (empty($CurrentVin)) $CurrentVin = '';
	if (empty($Vins)) $Vins = '';
	if (empty($SearchMessage)) $SearchMessage = '';
	return "<div id='Vins' class='{$NoVinClass}'>
				<div id='VinSearchForm'>
					<form data-Link='/avtocatalog{$LinkTemplate}'>
						<input name='vin' value='{$_GET['vin']}'><button>{$vinSearchParameters['searchButtonCaption']}</button>
						<div class='SearchMessage" . ($SearchMessage ? '' : ' ') . "'>{$SearchMessage}</div>
					</form>
					<div class='CurrentVin'>{$CurrentVin}</div>
				</div>
				{$Vins}
			</div>";
}

function ifButtonsSet($Catalogs) {
	global $data;
	if (!empty($_GET['CSSManager']) and !empty($_GET['cssdomain']))
		$UrlAppend = "&CSSManager={$_GET['CSSManager']}&cssdomain={$_GET['cssdomain']}";
	else $UrlAppend = '';
	foreach ($Catalogs['values'] as $Catalog) {
	    //Show($Catalog);
		if (substr($_SERVER['HTTP_HOST'], -9) == 'ilcats.ru') $Catalog['url'] = substr($Catalog['url'], 7) . '/';
		$CatalogGroup[] = "<a href='{$Catalog['url']}{$UrlAppend}'><img src='{$Catalog['image']}' alt='{$data['stageName']} {$Catalog['name']}'>{$Catalog['name']}</a>";
	}
	return "<div class='CatalogGroup'>" . ImplodeIfArray($CatalogGroup) . "</div>";
}


?>