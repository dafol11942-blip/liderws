<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle(""); ?>

<!-- 13123 -->

<?$APPLICATION->IncludeComponent("bitrix:main.auth.forgotpasswd", "", Array(
	"AUTH_AUTH_URL" => "/auth/",	// Страница для авторизации
		"AUTH_REGISTER_URL" => "/auth/registration.php",	// Страница для регистрации
	),
	false
);?>

<?

// $APPLICATION->IncludeComponent(
// 	"bitrix:system.auth.forgotpasswd",
// 	"",
// 	Array(
// 		"FORGOT_PASSWORD_URL" => "",
// 		"PROFILE_URL" => "",
// 		"REGISTER_URL" => "",
// 		"SHOW_ERRORS" => "N"
// 	)
// );

?>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>