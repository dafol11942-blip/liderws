<? require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
if(!CModule::IncludeModule("iblock"))
return; ?>


<?
$arSelect = Array('ID','NAME','PROPERTY_ADDRESS');
$arFilter = Array("IBLOCK_ID"=>9, "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y");

$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>50), $arSelect);
while($ob = $res->GetNextElement())
{
 $arFields = $ob->GetFields();
 // $arProps = $ob->GetProperties();
 $addres[] = $arFields['PROPERTY_ADDRESS_VALUE'];
 
}


echo json_encode($addres);

?> 