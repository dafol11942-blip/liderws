<?php
define('STOP_STATISTICS', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$GLOBALS['APPLICATION']->RestartBuffer();


$addres = $_REQUEST['city_addres_name'];


// debug($_REQUEST);

global $DB;

$location_id_qr = $DB->Query("SELECT `LOCATION_ID` FROM `b_sale_loc_name` WHERE `NAME` LIKE '%" . $addres . "' LIMIT 1 ");


while ($rows = $location_id_qr->Fetch()) {

    $location_id = $rows['LOCATION_ID'];

}

// CODE

$location_code_qr = $DB->Query("SELECT `CODE` FROM `b_sale_location` WHERE `id` = " . $location_id . " LIMIT 1");

while ($rows = $location_code_qr->Fetch()) {

    $location_code = $rows['CODE'];

}

// location code for init in bitrix addmin, it is in class order

// debug($location_code);

// $GLOBALS['location_code'] = $location_code;

$_SESSION['location_code'] = $location_code;
$_SESSION['pvzItemVar'] = $_REQUEST['pvzItemVar'];

// define("CURRENT_CITY_CODE", $location_code);

// debug($_SESSION);



// \Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible( 

//     'sale', 

//     'OnSaleComponentOrderProperties', 

//     'SaleOrderEvents::fillLocation'

// ); 


// define("CURRENT_CITY_CODE", $location_code);


// class SaleOrderEvents 

// {

//     function fillLocation(&$arUserResult, $request, &$arParams, &$arResult) 

//     {

//         $registry = \Bitrix\Sale\Registry::getInstance(\Bitrix\Sale\Registry::REGISTRY_TYPE_ORDER);

//         $orderClassName = $registry->getOrderClassName();

//         $order = $orderClassName::create(\Bitrix\Main\Application::getInstance()->getContext()->getSite());

//         $propertyCollection = $order->getPropertyCollection();



//         foreach ($propertyCollection as $property)

//         {

//             if ($property->isUtil())

//                 continue;

//             $arProperty = $property->getProperty();

//             if(

//                 $arProperty['TYPE'] === 'LOCATION' 

//                 && array_key_exists($arProperty['ID'],$arUserResult["ORDER_PROP"])

//                 && !$request->getPost("ORDER_PROP_".$arProperty['ID'])

//                 && (

//                     !is_array($arOrder=$request->getPost("order"))

//                     || !$arOrder["ORDER_PROP_".$arProperty['ID']]

//                 )

//             ) {

//                 $arUserResult["ORDER_PROP"][$arProperty['ID']] = CURRENT_CITY_CODE;

//             }

//         }

//     }

// }




// $location_code

// debug($location_code);
// debug($_REQUEST);

echo json_encode($_REQUEST, 1);