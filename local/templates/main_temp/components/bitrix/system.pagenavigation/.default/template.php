<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);




/***CustomPageNav start***/
// $nPageWindow = 15; //количество отображаемых страниц 
// if ($arResult["NavPageNomer"] > floor($nPageWindow/2) + 1 && $arResult["NavPageCount"] > $nPageWindow)
//     $nStartPage = $arResult["NavPageNomer"] - floor($nPageWindow/2);
// else
//     $nStartPage = 1;
//  
// if ($arResult["NavPageNomer"] <= $arResult["NavPageCount"] - floor($nPageWindow/2) && $nStartPage + $nPageWindow-1 <= $arResult["NavPageCount"])
//     $nEndPage = $nStartPage + $nPageWindow - 1;
// else
// {
//     $nEndPage = $arResult["NavPageCount"];
//     if($nEndPage - $nPageWindow + 1 >= 1)
//         $nStartPage = $nEndPage - $nPageWindow + 1;
// }
// $arResult["nStartPage"] = $arResult["nStartPage"] = $nStartPage;
// $arResult["nEndPage"] = $arResult["nEndPage"] = $nEndPage;
/***CustomPageNav end***/




if(!$arResult["NavShowAlways"])
{
	if ($arResult["NavRecordCount"] == 0 || ($arResult["NavPageCount"] == 1 && $arResult["NavShowAll"] == false))
		return;
}

$strNavQueryString = ($arResult["NavQueryString"] != "" ? $arResult["NavQueryString"]."&amp;" : "");
$strNavQueryStringFull = ($arResult["NavQueryString"] != "" ? "?".$arResult["NavQueryString"] : "");
?>










<?if($arResult["bDescPageNumbering"] === true):?>

	<?=$arResult["NavFirstRecordShow"]?> <?=GetMessage("nav_to")?> <?=$arResult["NavLastRecordShow"]?> <?=GetMessage("nav_of")?> <?=$arResult["NavRecordCount"]?><br /></font>

	<font class="text">

	<?if ($arResult["NavPageNomer"] < $arResult["NavPageCount"]):?>
		<?if($arResult["bSavePage"]):?>
			<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["NavPageCount"]?>"><?=GetMessage("nav_begin")?></a>
			|
			<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=($arResult["NavPageNomer"]+1)?>"><?=GetMessage("nav_prev")?></a>
			|
		<?else:?>
			<a href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=GetMessage("nav_begin")?></a>
			|
			<?if ($arResult["NavPageCount"] == ($arResult["NavPageNomer"]+1) ):?>
				<a href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=GetMessage("nav_prev")?></a>
				|
			<?else:?>
				<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=($arResult["NavPageNomer"]+1)?>"><?=GetMessage("nav_prev")?></a>
				|
			<?endif?>
		<?endif?>
	<?else:?>
		<?=GetMessage("nav_begin")?>&nbsp;|&nbsp;<?=GetMessage("nav_prev")?>&nbsp;|
	<?endif?>

	<?while($arResult["nStartPage"] >= $arResult["nEndPage"]):?>
		<?$NavRecordGroupPrint = $arResult["NavPageCount"] - $arResult["nStartPage"] + 1;?>

		<?if ($arResult["nStartPage"] == $arResult["NavPageNomer"]):?>
			<b><?=$NavRecordGroupPrint?></b>
		<?elseif($arResult["nStartPage"] == $arResult["NavPageCount"] && $arResult["bSavePage"] == false):?>
			<a  href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=$NavRecordGroupPrint?></a>
		<?else:?>
			<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["nStartPage"]?>"><?=$NavRecordGroupPrint?></a>
		<?endif?>

		<?$arResult["nStartPage"]--?>
	<?endwhile?>

	|

	<?if ($arResult["NavPageNomer"] > 1):?>
		<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=($arResult["NavPageNomer"]-1)?>"><?=GetMessage("nav_next")?></a>
		|
		<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=1"><?=GetMessage("nav_end")?></a>
	<?else:?>
		<?=GetMessage("nav_next")?>&nbsp;|&nbsp;<?=GetMessage("nav_end")?>
	<?endif?>

<?else:?>

	

	<!-- <font class="text"> -->

	<?/*if ($arResult["NavPageNomer"] > 1):?>

		<?if($arResult["bSavePage"]):?>
			<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=1"><?=GetMessage("nav_begin")?></a>
			|
			<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=($arResult["NavPageNomer"]-1)?>"><?=GetMessage("nav_prev")?></a>
			|
		<?else:?>
			<a href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=GetMessage("nav_begin")?></a>
			|
			<?if ($arResult["NavPageNomer"] > 2):?>
				<a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=($arResult["NavPageNomer"]-1)?>"><?=GetMessage("nav_prev")?></a>
			<?else:?>
				<a href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=GetMessage("nav_prev")?></a>
			<?endif?>
			|
		<?endif?>

	<?else:?>
		<!-- <?//=GetMessage("nav_begin")?>&nbsp;|&nbsp;<?//=GetMessage("nav_prev")?>&nbsp;| -->
	<?endif?>

	*/ ?>


	<div class="product__pagination news__pagination">
    	<ul class="df">

	<?while($arResult["nStartPage"] <= $arResult["nEndPage"]):?>

		<?if ($arResult["nStartPage"] == $arResult["NavPageNomer"]):?>
			
			<li class="active">
	            <a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["nStartPage"]?>"><?=$arResult["nStartPage"]?></a>
	        </li>


		<?elseif($arResult["nStartPage"] == 1 && $arResult["bSavePage"] == false):?>


			

			<li>
	            <a href="<?=$arResult["sUrlPath"]?><?=$strNavQueryStringFull?>"><?=$arResult["nStartPage"]?></a>
	        </li>


		<?else:?>


			

			<li>
	            <a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["nStartPage"]?>"><?=$arResult["nStartPage"]?></a>
	        </li>


		<?endif?>
		<?$arResult["nStartPage"]++?>
	<?endwhile?>

		









	
<?

$last_pagin_elem = $arResult["nStartPage"] - 1;




?>



	<?if($arResult["NavPageNomer"] < $arResult["NavPageCount"] && $arResult["NavPageCount"] != $last_pagin_elem ):?>
		

		<li>
            <a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["nStartPage"]?>">...</a>
        </li>
		
		<li>
            <a href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>PAGEN_<?=$arResult["NavNum"]?>=<?=$arResult["NavPageCount"]?>"><?=$arResult["NavPageCount"]?></a>
        </li>

	<?else:?>
		<!-- <?//=GetMessage("nav_next")?>&nbsp;|&nbsp;<?//=GetMessage("nav_end")?> -->
	<?endif?>

<?endif?>


<?if ($arResult["bShowAll"]):?>

<?endif?>




	</ul>
</div>


