<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arViewModeList = array('LIST', 'LINE', 'TEXT', 'TILE');

$arDefaultParams = array(
	'VIEW_MODE' => 'LIST',
	'SHOW_PARENT_NAME' => 'Y',
	'HIDE_SECTION_NAME' => 'N'
);

$arParams = array_merge($arDefaultParams, $arParams);

if (!in_array($arParams['VIEW_MODE'], $arViewModeList))
	$arParams['VIEW_MODE'] = 'LIST';
if ('N' != $arParams['SHOW_PARENT_NAME'])
	$arParams['SHOW_PARENT_NAME'] = 'Y';
if ('Y' != $arParams['HIDE_SECTION_NAME'])
	$arParams['HIDE_SECTION_NAME'] = 'N';

$arResult['VIEW_MODE_LIST'] = $arViewModeList;

if (0 < $arResult['SECTIONS_COUNT'])
{
	if ('LIST' != $arParams['VIEW_MODE'])
	{
		$boolClear = false;
		$arNewSections = array();
		foreach ($arResult['SECTIONS'] as &$arOneSection)
		{
			if (1 < $arOneSection['RELATIVE_DEPTH_LEVEL'])
			{
				$boolClear = true;
				continue;
			}
			$arNewSections[] = $arOneSection;
		}
		unset($arOneSection);
		if ($boolClear)
		{
			$arResult['SECTIONS'] = $arNewSections;
			$arResult['SECTIONS_COUNT'] = count($arNewSections);
		}
		unset($arNewSections);
	}
}

// debug(11111111);

if (0 < $arResult['SECTIONS_COUNT'])
{
	$boolPicture = false;
	$boolDescr = false;
	$arSelect = array('ID','UF_*');
	$arMap = array();
	if ('LINE' == $arParams['VIEW_MODE'] || 'TILE' == $arParams['VIEW_MODE'])
	{
		reset($arResult['SECTIONS']);
		$arCurrent = current($arResult['SECTIONS']);
		if (!isset($arCurrent['PICTURE']))
		{
			$boolPicture = true;
			$arSelect[] = 'PICTURE';
		}
		if ('LINE' == $arParams['VIEW_MODE'] && !array_key_exists('DESCRIPTION', $arCurrent))
		{
			$boolDescr = true;
			$arSelect[] = 'DESCRIPTION';
			$arSelect[] = 'DESCRIPTION_TYPE';

		}
	}


	// debug(22222222);
	if ($boolPicture || $boolDescr)
	{
		foreach ($arResult['SECTIONS'] as $key => $arSection)
		{
			$arMap[$arSection['ID']] = $key;
		}
		$rsSections = CIBlockSection::GetList(array(), array('ID' => array_keys($arMap)), false, $arSelect);
		while ($arSection = $rsSections->GetNext())
		{

			// debug(3333333333333333);

			// debug($arSection);

			if (!isset($arMap[$arSection['ID']]))
				continue;
			$key = $arMap[$arSection['ID']];
			if ($boolPicture)
			{
				$arSection['PICTURE'] = intval($arSection['PICTURE']);
				$arSection['PICTURE'] = (0 < $arSection['PICTURE'] ? CFile::GetFileArray($arSection['PICTURE']) : false);
				$arResult['SECTIONS'][$key]['PICTURE'] = $arSection['PICTURE'];
				$arResult['SECTIONS'][$key]['~PICTURE'] = $arSection['~PICTURE'];
			}
			if ($boolDescr)
			{
				$arResult['SECTIONS'][$key]['DESCRIPTION'] = $arSection['DESCRIPTION'];
				$arResult['SECTIONS'][$key]['~DESCRIPTION'] = $arSection['~DESCRIPTION'];
				$arResult['SECTIONS'][$key]['DESCRIPTION_TYPE'] = $arSection['DESCRIPTION_TYPE'];
				$arResult['SECTIONS'][$key]['~DESCRIPTION_TYPE'] = $arSection['~DESCRIPTION_TYPE'];
			}
		}
	}
}



//  FOR UF LINK 


foreach ($arResult['SECTIONS'] as $key => $arSection)
{
	$arMap[$arSection['ID']] = $key;
}

$rsSections = CIBlockSection::GetList(
   array(), 
   array(
   	  'ID' => array_keys($arMap),
      "IBLOCK_ID" => $arParams["IBLOCK_ID"],
      // "LEFT_MARGIN" => $arResult["LEFT_MARGIN"],
      // "RIGHT_MARGIN" => $arResult["RIGHT_MARGIN"],
   ), 
   false, 
   array('ID',"UF_*"),
);




while ($arSection = $rsSections->GetNext()){

	if (!isset($arMap[$arSection['ID']]))
		continue;
	$key = $arMap[$arSection['ID']];


	if(!empty($arSection['UF_LINK'])){
		$arResult['SECTIONS'][$key]['SECTION_PAGE_URL'] = $arSection['UF_LINK'];
	}

	$arResult['SECTIONS'][$key]['UF_LINK'] = $arSection['UF_LINK'];

}





// debug($rsSections);

// while ($arSection = $rsSections->GetNext()){
// 	// echo "123";
// 	// debug($arSection);

// 	foreach ($arResult['SECTIONS'] as $key => $arSectionMain)
// 	{
		
// 		// debug($arSectionMain['ID']['']);

// 		if($arSectionMain)

// 		$arSectionMain['ID'][$arSection['ID']]['UF_LINK'] = $arSection['UF_LINK'];

// 	}

// }


?>