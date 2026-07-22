<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>


<?if (!empty($arResult)):?>

<?
// Наименование раздела
$sSectionName = "";
$sPath = $_SERVER["DOCUMENT_ROOT"].$APPLICATION->GetCurDir().".section.php";
include($sPath);
?>


<section class="">
    <div class="container df ac ">
        <h1 class="title"><?= $sSectionName; ?></h1> 
    </div>
</section>



  <section class="contacts">
        <div class="container row">


            <div class="left__menu">
                <nav>
                    <ul class="left__menu-ul">
                       
			                <? foreach($arResult as $arItem): ?>

			                	<?if($arItem["SELECTED"]):?>

									<li><a href="<?=$arItem["LINK"]?>" class="active"><?=$arItem["TEXT"]?></a></li>

								<?else:?>

									<li><a href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a></li>

								<?endif?>

							<?endforeach?>


                    </ul>
                   
                </nav>
            </div>


            <div class="right__inner">
                <? if($arParams['SHOW_RIGHT_TIT'] != 'N'): ?>

                    <div class="right__tit">
                       <?$APPLICATION->ShowTitle(false)?>
                    </div>

                <? endif;?>
<? endif;   ?>