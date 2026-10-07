<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Страница возврата из банка после неуспешной онлайн-оплаты — адрес указан в настройках
// платёжной системы Альфа-Банка. Без require_phone_auth: сюда может вернуться и гость.
$APPLICATION->SetPageProperty("title", "Оплата не прошла — ЛИДЕР");
$APPLICATION->SetTitle("Оплата не прошла");

$paymentReturnMode = 'fail';
require $_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/include/payment_return_page.php";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
