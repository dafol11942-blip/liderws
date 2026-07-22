<?
// require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
// <?
define('STOP_STATISTICS', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$GLOBALS['APPLICATION']->RestartBuffer();

// $APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");
// $APPLICATION->SetTitle("test");
include 'main_api/PHP/Functions.Blocks.php';
include 'main_api/PHP/Functions.Common.php';
include 'main_api/function.php';

?>

<head>
    
<?
    global $apiHttpCatalogsPath;
    $apiHttpCatalogsPath='';
    // if (!empty(apiHttpCatalogsPath)) $apiHttpCatalogsPath='/'.apiHttpCatalogsPath;

    //if (apiLoadJquery) echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQuery-3.1.0.min.js'></script>";

    // echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.min.js'></script>
    //   <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.css'>
    //   <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.scrollTo.190301.min.js'></script>
    //   <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.pep.js'></script>
    //   <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/fonts/ProximaNova/Font.css'>
     
    //   <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/Common2.200410.js'></script>";

      //  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/CSS/Template2.191230.css'>
?>


</head> 

<?php


$st = getApiData($apiRequestUrl, $functionParameters, $clientParameters);


$array_main = json_decode($st, true);


?>


<?


function get_url_by_param($array){

	$url = '?';

	foreach ($array as $key => $value) {
		 $url .= $key . '=' . $value . '&';
	}

	return $url;
}


// NEW CReATE 26/01/22



foreach ($array_main['data'] as $main_value => $main_key) {

	if($array_main['data'][$main_value]['format'] == 'ifTable'){


	    foreach($array_main['data'][$main_value]['values'] as $main_key => $item){


	        $item['url'] = '?';


	        foreach ($item['urlParams'] as $key => $value) {

	            $item['url'] .= $key . '=' . $value . '&';


	        }

	        $array_main['data'][$main_value]['values'][$main_key]['url'] = $item['url'];

	        // array_push($item['urlParams'], $item['url']);


	    }

	}



}


// FUCK  ifMultiList !=  ifMultilist
if ($array_main['data'][0]['format'] == 'ifMultiList') {


    foreach($array_main['data'][0]['values'] as $main_key => $sub_items){


        foreach ($sub_items['values'] as $sub_key => $item) {

            $item['url'] = '?';


            // debug($item['urlParams']);
            
            foreach ($item['urlParams'] as $key => $value) {



                $item['url'] .= $key . '=' . $value . '&';


            }


            $array_main['data'][0]['values'][$main_key]['values'][$sub_key]['url'] = $item['url'];


        }


    }

}


	// debug($array_main);

        
if($array_main['data'][0]['format'] == 'ifList'){


    foreach($array_main['data'][0]['values'] as $main_key => $item){


        $item['url'] = '?';


        foreach ($item['urlParams'] as $key => $value) {

            $item['url'] .= $key . '=' . $value . '&';


        }

        $array_main['data'][0]['values'][$main_key]['url'] = $item['url'];

        // array_push($item['urlParams'], $item['url']);


    }

}elseif ($array_main['data'][0]['format'] == 'ifMultilist') {


    foreach($array_main['data'][0]['values'] as $main_key => $sub_items){


        foreach ($sub_items['values'] as $sub_key => $item) {

            $item['url'] = '?';


            // debug($item['urlParams']);
            
            foreach ($item['urlParams'] as $key => $value) {



                $item['url'] .= $key . '=' . $value . '&';


            }


            $array_main['data'][0]['values'][$main_key]['values'][$sub_key]['url'] = $item['url'];


        }


    }


}elseif ($array_main['data'][0]['format'] == 'ifTile'){

    foreach($array_main['data'] as $Smain_key => $Sitem){

        foreach($Sitem['values'] as $main_key => $item){


            $item['url'] = '?';


            foreach ($item['urlParams'] as $key => $value) {

                $item['url'] .= $key . '=' . $value . '&';


            }


            $array_main['data'][$Smain_key]['values'][$main_key]['url'] = $item['url'];
        }

        // array_push($item['urlParams'], $item['url']);


    }

}
        
?>





<?
	$page = $APPLICATION->GetCurPage();

?>














<?


if($_GET['function'] == 'getBrands'){

	main_data_change($array_main, "/avtocatalog/");

}

?>























<? if ($array_main['data'][0]['format'] == 'ifMultilist') { ?>


    <? foreach($array_main['data'][0]['values'] as $item):?>

     


        <? foreach($item['values'] as $sub_item):?>

         

        <? endforeach;?>


    <? endforeach;?>


<? } ?>


