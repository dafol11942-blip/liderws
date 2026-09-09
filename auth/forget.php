<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Восстановление пароля не нужно: вход только по телефону, пароля у
// пользователя нет.
LocalRedirect('/auth/');
