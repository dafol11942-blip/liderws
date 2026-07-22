<? 
// define('STOP_STATISTICS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("");
// $GLOBALS['APPLICATION']->RestartBuffer();

// use Bitrix\Main\Page\Asset;
// Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/style.css");
?><?$APPLICATION->IncludeComponent(
	"bitrix:main.auth.form",
	"template1",
	Array(
		"AUTH_FORGOT_PASSWORD_URL" => "",
		"AUTH_REGISTER_URL" => "",
		"AUTH_SUCCESS_URL" => "/personal/"
	)
);?><!-- <head>
	<link rel="stylesheet" src="<?= SITE_TEMPLATE_PATH  ?>/assets/css/style.css">
</head> -->
<?

//$APPLICATION->IncludeComponent(
	// "bitrix:system.auth.authorize",
	// ".default",
	// Array(
	// 	"FORGOT_PASSWORD_URL" => "/auth/forget.php",
	// 	"PROFILE_URL" => "/personal/",
	// 	"REGISTER_URL" => "/auth/registration.php",
	// 	"SHOW_ERRORS" => "N"
	// )
//);

?><?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>