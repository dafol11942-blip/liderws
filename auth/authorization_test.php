<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Вход email+паролем на публичном сайте отключён — только /auth/ (телефон+SMS).
LocalRedirect('/auth/');
