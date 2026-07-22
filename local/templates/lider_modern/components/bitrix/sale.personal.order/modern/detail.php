<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:sale.personal.order.detail",
    ".default",
    array(
        "PATH_TO_LIST" => $arResult["PATH_TO_LIST"],
        "PATH_TO_CANCEL" => $arResult["PATH_TO_CANCEL"],
        "PATH_TO_PAYMENT" => $arParams["PATH_TO_PAYMENT"],
        "SET_TITLE" => $arParams["SET_TITLE"],
        "ID" => $arResult["VARIABLES"]["ID"],
        "ACTIVE_DATE_FORMAT" => $arParams["ACTIVE_DATE_FORMAT"],
        "ALLOW_INNER" => $arParams["ALLOW_INNER"],
        "ONLY_INNER_FULL" => $arParams["ONLY_INNER_FULL"],
        "CACHE_TYPE" => $arParams["CACHE_TYPE"],
        "CACHE_TIME" => $arParams["CACHE_TIME"],
        "CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
        "RESTRICT_CHANGE_PAYSYSTEM" => $arParams["RESTRICT_CHANGE_PAYSYSTEM"] ?? array("0"),
        "REFRESH_PRICES" => $arParams["REFRESH_PRICES"] ?? "N",
        "DISALLOW_CANCEL" => $arParams["DISALLOW_CANCEL"] ?? "N",
        "HIDE_USER_INFO" => $arParams["DETAIL_HIDE_USER_INFO"] ?? array(),
    ),
    $component
);
?>
