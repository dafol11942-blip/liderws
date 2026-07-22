<?
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
// require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$APPLICATION->SetTitle("");
GLOBAL $USER;

//debug($_REQUEST);
//if (isset($_REQUEST["login"]) && strlen($_REQUEST["backurl"])>0){ 
	// debug($arResult);
	
	//LocalRedirect($_REQUEST["backurl"]);
////echo "234234234";

?><?$APPLICATION->IncludeComponent("bitrix:main.auth.forgotpasswd", "", Array(
	"AUTH_AUTH_URL" => "",	// Страница для авторизации
		"AUTH_REGISTER_URL" => "",	// Страница для регистрации
	),
	false
);?>

<?

//	debug($USER);

?>
<?//if($USER->IsAuthorized()):?>
	<?


	$APPLICATION->SetTitle("Авторизация");
	?>
	<?

	// $APPLICATION->IncludeComponent(
	// 	"bitrix:system.auth.confirmation",
	// 	"form_auth_podtverjdenie",
	// 	Array(
	// 		"CONFIRM_CODE" => "confirm_code",
	// 		"LOGIN" => "login",
	// 		"USER_ID" => "confirm_user_id"
	// 	)
	// );


	?>
	 <?

	//  $APPLICATION->IncludeComponent(
	// 	"bitrix:main.auth.form",
	// 	"template1",
	// 	Array(
	// 		"AUTH_FORGOT_PASSWORD_URL" => "forget.php",
	// 		"AUTH_REGISTER_URL" => "",
	// 		"USER_PROPERTY" => array("UF_SOGLASIE_FORM"),
	// 		"AUTH_SUCCESS_URL" => "/personal/"
	// 	)
	// );

	?> 
<?// else:?>
	<? //	LocalRedirect(' /catalog/'); ?>
<? //endif;?><?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>