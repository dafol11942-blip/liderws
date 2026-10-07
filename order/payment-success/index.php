<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Страница возврата из банка после успешной онлайн-оплаты — адрес указан в настройках
// платёжной системы Альфа-Банка. Без require_phone_auth: сюда может вернуться и гость.
$APPLICATION->SetPageProperty("title", "Оплата прошла успешно — ЛИДЕР");
$APPLICATION->SetTitle("Оплата прошла успешно");

$paymentReturnMode = 'success';
require $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/payment_return_page.php";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
