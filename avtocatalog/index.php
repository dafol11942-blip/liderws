<?



// if($_GET['function'] != 'getVin'){

	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// }else{

// 	define('STOP_STATISTICS', true);
// 	require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// }




$APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");
// $APPLICATION->SetTitle("test");
include 'main_api/PHP/Functions.Blocks.php';
include 'main_api/PHP/Functions.Common.php';

include 'main_api/function.php';


include 'main_api/templates/main.php';

?>



<head>
    
<?
    global $apiHttpCatalogsPath;
    $apiHttpCatalogsPath='';
    // if (!empty(apiHttpCatalogsPath)) $apiHttpCatalogsPath='/'.apiHttpCatalogsPath;

    if (apiLoadJquery) echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQuery-3.1.0.min.js'></script>";

    echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.min.js'></script>
      <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.css'>
      <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.scrollTo.190301.min.js'></script>
      <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.pep.js'></script>
      <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/fonts/ProximaNova/Font.css'>
      <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/CSS/Template2.191230.css'>
      <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/Common2.200410.js'></script>";
?>


</head> 

<?php


$st = getApiData($apiRequestUrl, $functionParameters, $clientParameters);


$array_main = json_decode($st, true);




// debug($array_main);


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




// function MainMenu($Menu = array()) {
// 	if ($Menu)
// 		foreach ($Menu as $KeyS => $SubMenu) {
// 			foreach ($SubMenu as $Key => $Option) {
// 				$Link["linkText"] = $Option['name'] . ": ";
// 				if (empty($Option['link'])) $Option['link'] = '';
// 				$Link["catRootUrl"] = $Option['link'];
// 				if ($KeyS == 1 && $Key == 0) unset($Option['urlParams']['function']);
// 				$Link["params"] = !empty($Option['urlParams']) ? $Option['urlParams'] : array();
// 				if (strlen($Option['label']) > 20) {
// 					$Label = substr($Option['label'], 0, strpos($Option['label'], ' ', 20));
// 					if (!$Label) $Label = $Option['label'];
// 					if ($Label != $Option['label']) {
// 						$Label = "<span title='{$Option['label']}'>{$Label}...</span>";
// 					}
// 				} else {
// 					$Label = $Option['label'];
// 				}
// 				$Options[] = generateLink2($Link) . $Label;
// 			}
// 		}
// 	if ($Options) {
// 		$MenuOptions = "<li>" . ImplodeIfArray($Options, "</li><li>") . "</li>";
// 	}
// 	return "<ul id='MainMenu'><li class='Image'><img src='" . $apiHttpCatalogsPath . "/API.v2/Icons/Menu.png' alt='Menu'></li>{$MenuOptions}</ul>";
// }

?>

<?
	
	$page = $APPLICATION->GetCurPage();

	// debug(__DIR__);

?>

<?
	if($page == '/http://lider.netkama.ru//'):
?>

<section class="bread">
    <div class="container">
 		
 		<? echo MainMenu($array_main['mainMenu'], $array_main['stageName']); ?>
        
    </div>
</section>

<?
	
	endif;

?>


<section class="catalog_title">
<div class="container">
    <div class="product__detail-head df ac">
        <a class="product__back" href="#">
            <div class="df ac">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.8867 11.3332C10.0109 11.2083 10.0806 11.0393 10.0806 10.8632C10.0806 10.6871 10.0109 10.5181 9.8867 10.3932L7.5267 7.99988L9.8867 5.63988C10.0109 5.51498 10.0806 5.34601 10.0806 5.16988C10.0806 4.99376 10.0109 4.82479 9.8867 4.69988C9.82473 4.6374 9.751 4.5878 9.66976 4.55396C9.58852 4.52011 9.50138 4.50269 9.41337 4.50269C9.32536 4.50269 9.23823 4.52011 9.15699 4.55396C9.07575 4.5878 9.00201 4.6374 8.94004 4.69988L6.11337 7.52655C6.05088 7.58853 6.00129 7.66226 5.96744 7.7435C5.9336 7.82474 5.91617 7.91188 5.91617 7.99988C5.91617 8.08789 5.9336 8.17503 5.96744 8.25627C6.00129 8.33751 6.05088 8.41124 6.11337 8.47322L8.94004 11.3332C9.00201 11.3957 9.07575 11.4453 9.15699 11.4791C9.23823 11.513 9.32536 11.5304 9.41337 11.5304C9.50138 11.5304 9.58852 11.513 9.66976 11.4791C9.751 11.4453 9.82473 11.3957 9.8867 11.3332Z" fill="#668BEA"></path>
                </svg>
            </div>
            <div>Назад</div>
        </a>
        <h1 class="title">

            <? if($array_main['stageName'] != "Выбор запчасти"):?>

                <?= $array_main['stageName'] ?>

            <? else:?>

                <?

                echo $main_name = end(end($array_main['mainMenu']))['label'];

                ?>

            <? endif;?>


            <? if($_GET['function'] == 'getVin'): ?>
            	Поиск по Vin/Frame 
            <? endif;?>
                
            </h1> 
    </div>
</div>
</section>


<?
// debug($array_main);
// debug($_SESSION);

if($_GET['function'] == 'getVin' || $_GET['VinAction']){
	// debug($array_main);	
	echo "<section>";
	echo "<div class='container'>";
	
	echo "{$HtmlTags['Header']['Start']}<div class='Top'>{$Page['MainMenu']}{$Page['Languages']}</div>", VinForm($array_main,$array_main['vinSearchParameters']), $HtmlTags['Header']['End'];
	// echo json_encode($array_main);
	echo "</div>";
	echo "</section>";

	// exit();
}

$templates = new ClassTemplates;

switch ($array_main['stageName']) {


	case 'Каталоги оригинальных автозапчастей':


			main_data_change($array_main);

		break;


	case 'Выбор производителя':
				
			main_data_change($array_main);

		break;

	case 'Выбор рынка':

		main_data_select_rinok($array_main);
		# code...
		break;


	case 'Выбор класса автомобиля':

		main_data_select_model($array_main, $array_main['data'][0]['format']);
		
		break;

	case 'Выбор типа автомобиля':

		main_data_select_model($array_main, $array_main['data'][0]['format']);
		# code...
		break;

	case 'Выбор модели':

	 	// debug($array_main);

	 	main_data_select_model($array_main, $array_main['data'][0]['format']);

		# code...
		break;

	case 'Выбор модели автомобиля':

		main_data_select_model($array_main);
		# code...
		break;

	case 'Выбор модификации':

//	    http://lider.netkama.ru/avtocatalog/?function=getModifications&model=2.5TL&startDate=1995&endDate=1998&brand=honda&

		main_data_select_modification($array_main,$array_main['data'][0]['format']);

		# code...
		break;


	case 'Выбор комплектации автомобиля':
		# code...
		// FOR TABLE
	
		// OR main_data_select_modification
		// main_data_select_сomplectation($array_main,$array_main['data'][0]['format'],"Выбор комплектации автомобиля");

		$templates->selectItemsFromTables($array_main,$array_main['data'][0]['format'],"Выбор комплектации автомобиля");

		break;


	case 'Выбор группы запчастей':

		// debug($array_main);

		switch ($array_main['data'][0]['format']) {
			case 'ifMultilist':
					
				main_data_select_group_zap($array_main, 1);

				break;
				
			case 'ifTable':	

				// debug(123);


				// FOR AVTOVAZ

				$templates->selectItemsFromTables($array_main,$array_main['data'][0]['format'],"Выбор группы запчастей");
				
				// main_data_select_сomplectation($array_main,$array_main['data'][0]['format']);

				break;

			case 'ifImage':
				
				$SiteLabels = $data['siteLabels'];


		        foreach ($array_main["data"] as $key => $Data){
		            echo "<div>";
		            echo "<div class='container'>";
		            echo '<div id="Body" class="ifImageBody">';
		            echo "<div class='ifImage'>";
		            // echo "<div>";

		            echo $Data['format']($Data, $SiteLabels);
		            
		            
		            echo "</div>";
		            echo "</div>";
		            echo "</div>";
		            echo "</div>";

		    	}



				main_data_select_zapchasti($array_main,1);

				break;
			
			default:

				main_data_select_group_zap($array_main,0);

				break;
		}


		// if($array_main['data'][0]['format'] == 'ifMultilist'){
		// 	main_data_select_group_zap($array_main, 1);

		// }elseif($array_main['data'][0]['format'] == 'ifTable'){

		// 	// or // main_data_select_modification     

		// 	main_data_select_сomplectation($array_main,$array_main['data'][0]['format']);

		// }else if($array_main['data'][0]['format'] == "ifImage") {

		// 	$SiteLabels = $data['siteLabels'];


	 //        foreach ($array_main["data"] as $key => $Data){
	 //            echo "<div>";
	 //            echo "<div class='container'>";
	 //            echo '<div id="Body" class="ifImageBody">';
	 //            echo "<div class='ifImage'>";
	 //            // echo "<div>";

	 //            echo $Data['format']($Data, $SiteLabels);
	            
	            
	 //            echo "</div>";
	 //            echo "</div>";
	 //            echo "</div>";
	 //            echo "</div>";

	 //    	}



		// 	main_data_select_zapchasti($array_main,1);

		// }else {
		// 	main_data_select_group_zap($array_main,0);
		// }




		# code...
		break;

	case 'Выбор подгруппы запчастей':


		if($array_main['data'][0]['format'] == "ifImage") {

			 $SiteLabels = $data['siteLabels'];


	        foreach ($array_main["data"] as $key => $Data){
	            echo "<div>";
	            echo "<div class='container'>";
	            echo '<div id="Body" class="ifImageBody">';
	            echo "<div class='ifImage'>";
	            // echo "<div>";

	            echo $Data['format']($Data, $SiteLabels);
	            
	            
	            echo "</div>";
	            echo "</div>";
	            echo "</div>";
	            echo "</div>";

	        }



			main_data_select_zapchasti($array_main,1);


		}elseif($array_main['data'][0]['values'][0]['values']) {

			main_data_select_pod_group_zap($array_main, 1);

		}else {

			main_data_select_pod_group_zap($array_main);
		}


		# code...
		break;

	case 'Выбор запчасти':

        // debug($array_main["data"]);


            $SiteLabels = $data['siteLabels'];


        foreach ($array_main["data"] as $key => $Data){
            echo "<div>";
            echo "<div class='container'>";
            echo '<div id="Body" class="ifImageBody">';
            echo "<div class='ifImage'>";
            // echo "<div>";

            echo $Data['format']($Data, $SiteLabels);
            
            
            echo "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div>";

        }



		main_data_select_zapchasti($array_main);
		# code...
		break;
	
	default:

		

			// debug($array_main);

		break;

} 

?>























<? if ($array_main['data'][0]['format'] == 'ifMultilist') { ?>


    <? foreach($array_main['data'][0]['values'] as $item):?>

     


        <? foreach($item['values'] as $sub_item):?>

         

        <? endforeach;?>


    <? endforeach;?>


<? } ?>





<?



?>




<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>

