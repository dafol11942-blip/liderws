<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

/**
 * @global CMain $APPLICATION
 */


global $APPLICATION;



// 

// debug($arResult);


// echo "12312312";


//delayed function must return a string
if(empty($arResult))
	return "ERROR";

$strReturn = '';

//we can't use $APPLICATION->SetAdditionalCSS() here because we are inside the buffered function GetNavChain()
$css = $APPLICATION->GetCSSArray();
// if(!is_array($css) || !in_array("/bitrix/css/main/font-awesome.css", $css))
// {
// 	$strReturn .= '<link href="'.CUtil::GetAdditionalFileURL("/bitrix/css/main/font-awesome.css").'" type="text/css" rel="stylesheet" />'."\n";
// }

$strReturn .= '<section class="bread">
        <div class="container">
             <ul>';

$itemSize = count($arResult);
for($index = 0; $index < $itemSize; $index++)

	// $strReturn .= $arResult[$index]['LINK'];




{
	$title = htmlspecialcharsex($arResult[$index]["TITLE"]);
	// $arrow = ($index > 0? '<i class="fa fa-angle-right"></i>' : '');




	if($arResult[$index]["LINK"] <> "" && $index != $itemSize-1)
	{
		// $strReturn .= '
		// 	<div class="bx-breadcrumb-item" id="bx_breadcrumb_'.$index.'" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
		// 		'.$arrow.'
		// 		<a href="'.$arResult[$index]["LINK"].'" title="'.$title.'" itemprop="item">
		// 			<span itemprop="name">'.$title.'</span>
		// 		</a>
		// 		<meta itemprop="position" content="'.($index + 1).'" />
		// 	</div>';

		$strReturn .= '
			<li>
               	<a href="'.$arResult[$index]["LINK"].'">'.$title.'</a>
            </li>';			
	}




	else{
		
		$strReturn .= '
			<li class="bread__active">
                <a href="'.$arResult[$index]["LINK"].'">'.$title.'</a>
            </li>
		';
	}



}

$strReturn .= '
			 </ul>
        </div>
     </section>';

return $strReturn;


