<? require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
if(!CModule::IncludeModule("iblock"))
return; ?>


<?
$arSelect = Array('ID','NAME','PROPERTY_ADDRESS');
$arFilter = Array("IBLOCK_ID"=>49, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");

$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
while($ob = $res->GetNextElement())
{
 $arFields = $ob->GetFields();
 // $arProps = $ob->GetProperties();
 $addres[] = array(
 	"NAME" => $arFields['NAME'],
 	"ID" => $arFields['ID'],
 );

 	if($arFields['ID'] == $_REQUEST['set_main-city-id']) {
 		$_SESSION['city'] = $arFields['NAME'];
 	}

 
}


// debug($addres);

if($_REQUEST['set_main-city-id']){

	echo json_encode('Город установлен');

}else{

	echo json_encode($addres);

}


?> 