<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $arResult
 * @var SaleOrderAjax $component
 */

// $arResult['ORDER_PROP']['RELATED'][0] = 'lol';

$component = $this->__component;
$component::scaleImages($arResult['JS_DATA'], $arParams['SERVICES_IMAGES_SCALING']);