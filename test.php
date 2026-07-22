<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("test");
?><?$APPLICATION->IncludeComponent(
	"ipol:ipol.sdekPickup",
	"",
	Array(
		"CITIES" => array(),
		"CNT_BASKET" => "N",
		"CNT_DELIV" => "N",
		"COUNTRIES" => array(),
		"FORBIDDEN" => array(),
		"MODE" => "both",
		"NOMAPS" => "Y",
		"PAYER" => "1",
		"PAYSYSTEM" => "",
		"SEARCH_ADDRESS" => "N"
	)
);?><br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>