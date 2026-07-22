<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:sale.personal.order.list",
    "modern",
    array(
        "PATH_TO_DETAIL" => $arResult["PATH_TO_DETAIL"],
        "PATH_TO_CANCEL" => $arResult["PATH_TO_CANCEL"],
        "PATH_TO_COPY" => $arResult["PATH_TO_LIST"].'?ID=#ID#',
        "PATH_TO_BASKET" => $arParams["PATH_TO_BASKET"],
        "PATH_TO_PAYMENT" => $arParams["PATH_TO_PAYMENT"],
        "SAVE_IN_SESSION" => $arParams["SAVE_IN_SESSION"],
        "ORDERS_PER_PAGE" => $arParams["ORDERS_PER_PAGE"] ?: 10,
        "SET_TITLE" => $arParams["SET_TITLE"],
        "ID" => $arResult["VARIABLES"]["ID"],
        "NAV_TEMPLATE" => "modern",
        "ACTIVE_DATE_FORMAT" => $arParams["ACTIVE_DATE_FORMAT"],
        "HISTORIC_STATUSES" => array("___SHOW_ALL___"),
        "ALLOW_INNER" => $arParams["ALLOW_INNER"],
        "ONLY_INNER_FULL" => $arParams["ONLY_INNER_FULL"],
        "CACHE_TYPE" => "N",
        "CACHE_TIME" => "0",
        "CACHE_GROUPS" => "N",
        "DEFAULT_SORT" => "DATE_INSERT",
        "RESTRICT_CHANGE_PAYSYSTEM" => $arParams["RESTRICT_CHANGE_PAYSYSTEM"] ?? array("0"),
        "REFRESH_PRICES" => $arParams["REFRESH_PRICES"] ?? "N",
        "DISALLOW_CANCEL" => $arParams["DISALLOW_CANCEL"] ?? "N",
    ),
    $component
);
?>
