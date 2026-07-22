<? 
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("iblock");

// echo "234234";
// if(!CModule::IncludeModule("iblok"))
// return; 
if($_GET['id'] && $_GET['iblock_id']){
	$id = $_GET['id'];
	$iblock_id = $_GET['iblock_id'];
}

$VALUES = array();

$res = CIBlockElement::GetProperty($iblock_id, $id, "sort", "asc");

while ($ob = $res->GetNext())
{

    if($ob['NAME'] == "Показать на карте ссылка"){

    	$VALUES = $ob['~VALUE'];

    }

    
}


if(!empty($VALUES)){
	echo $VALUES;
}else{

	$VALUES = array();

	$res = CIBlockElement::GetProperty($iblock_id, 54674, "sort", "asc");

	while ($ob = $res->GetNext())
	{

	    if($ob['NAME'] == "Показать на карте ссылка"){

	    	$VALUES = $ob['~VALUE'];

	    }

	    
	}

	echo $VALUES;

}


	
?>