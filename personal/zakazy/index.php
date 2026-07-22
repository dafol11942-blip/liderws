<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Заказы");
?><section>
<div class="container df ac ">
	<div class="title">
		 Личный кабинет
	</div>
</div>
 </section>
<?$APPLICATION->IncludeComponent(
	"bitrix:menu",
	"kabinet-menu",
	Array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "left",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(""),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "lichnie_kabinet",
		"USE_EXT" => "N"
	)
);?>
<?$APPLICATION->IncludeComponent(
	"bitrix:sale.personal.order", 
	"template1", 
	array(
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "3600",
		"CACHE_TYPE" => "A",
		"CUSTOM_SELECT_PROPS" => array(
		),
		"DETAIL_HIDE_USER_INFO" => array(
			0 => "0",
		),
		"DISALLOW_CANCEL" => "N",
		"HISTORIC_STATUSES" => array(
		),
		"NAV_TEMPLATE" => "",
		"ORDERS_PER_PAGE" => "20",
		"ORDER_DEFAULT_SORT" => "STATUS",
		"PATH_TO_BASKET" => "/personal/cart",
		"PATH_TO_CATALOG" => "/catalog/",
		"PATH_TO_PAYMENT" => "/personal/order/payment.php",
		"PROP_1" => array(
		),
		"PROP_2" => array(
		),
		"REFRESH_PRICES" => "N",
		"RESTRICT_CHANGE_PAYSYSTEM" => array(
			0 => "0",
		),
		"SAVE_IN_SESSION" => "Y",
		"SEF_FOLDER" => "/personal/zakazy/",
		"SEF_MODE" => "Y",
		"SET_TITLE" => "Y",
		"STATUS_COLOR_F" => "gray",
		"STATUS_COLOR_N" => "green",
		"STATUS_COLOR_PSEUDO_CANCELLED" => "red",
		"COMPONENT_TEMPLATE" => "template1",
		"STATUS_COLOR_S" => "gray",
		"SEF_URL_TEMPLATES" => array(
			"list" => "index.php",
			"detail" => "detail/#ID#",
			"cancel" => "cancel/#ID#",
		)
	),
	false
);?>

<br>
<br>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>