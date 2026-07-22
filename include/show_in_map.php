<? 
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
if(!CModule::IncludeModule("iblock"))
return; 
$res = CIBlockElement::GetByID(72);
if($ar_res = $res->GetNext())
  echo $ar_res['PREVIEW_TEXT'];
?>