<? 
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");

// echo "234234";
// if(!CModule::IncludeModule("iblok"))
// return; 
if($_GET['id']){
	$id = $_GET['id'];
}



$res = CIBlockElement::GetByID($id);
if($ar_res = $res->GetNext())

	 // debug($ar_res);

	if($_GET['desc'] == 1){
		echo $ar_res['DESCRIPTION'];
	}else {
		echo $ar_res['PREVIEW_TEXT'];
	}
?>