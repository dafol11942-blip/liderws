<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Реквизиты");
$APPLICATION->SetTitle("реквизиты");
?><?$APPLICATION->IncludeComponent(
	"bitrix:menu",
	"Left_menu",
	Array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "left",
		"COMPONENT_TEMPLATE" => "Left_menu",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "left",
		"USE_EXT" => "N"
	)
);?>
 ИП Винокуров Сергей Владимирович<br>
 ИНН 164604616640<br>
 ОГРНИП 314167418800021<br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>