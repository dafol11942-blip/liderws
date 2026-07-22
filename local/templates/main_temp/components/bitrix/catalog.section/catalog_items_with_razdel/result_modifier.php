<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogSectionComponent $component
 */

$component = $this->getComponent();

$arParams = $component->applyTemplateModifications();




$itemArticle = $arParams['FilterByItem'];




foreach ($arResult['ITEMS'] as $key => $value) {
	if($value['PROPERTIES']['CML2_ARTICLE']['VALUE'] == $itemArticle){
		

		$buf = $value;

		$arResult['ITEMS'][] = $arResult['ITEMS'][0]; 

		$arResult['ITEMS'][0] = $buf;

		unset($arResult['ITEMS'][$key]);


	}
}

