<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Общий guard для личного кабинета и оформления заказа: доступ только
 * авторизованным пользователям с заполненными Именем/Фамилией/Email (у
 * пользователей, созданных автоматически по телефону, эти поля изначально
 * пустые — см. Lider\Auth\PhoneUserService::createUserByPhone() и
 * auth/complete.php).
 */
global $USER, $APPLICATION;

$curPage = $APPLICATION->GetCurPage(true);

if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/?backurl=' . urlencode($curPage));
    die();
}

$arCurrentUser = \CUser::GetByID($USER->GetID())->Fetch();
if (trim((string)($arCurrentUser['NAME'] ?? '')) === ''
    || trim((string)($arCurrentUser['LAST_NAME'] ?? '')) === ''
    || trim((string)($arCurrentUser['EMAIL'] ?? '')) === '') {
    LocalRedirect('/auth/complete.php?backurl=' . urlencode($curPage));
    die();
}
