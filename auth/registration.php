<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Регистрация email+паролем отключена: аккаунт создаётся автоматически
// при первом входе по телефону через /auth/ (см. Lider\Auth\PhoneUserService).
LocalRedirect('/auth/');
