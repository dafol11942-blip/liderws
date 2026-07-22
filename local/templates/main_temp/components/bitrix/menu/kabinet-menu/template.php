<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>


<?if (!empty($arResult)):?>

<?
// Наименование раздела
$sSectionName = "";
$sPath = $_SERVER["DOCUMENT_ROOT"].$APPLICATION->GetCurDir().".section.php";
include($sPath);
?>


<!-- <section class="">
    <div class="container df ac ">
        <div class="title"><?= $sSectionName; ?></div> 
    </div>
</section>
 -->

 <section class="kabinet">
        <div class="container kabinet-container">
            <div class="kabinet__menu">
                <nav>
                    <ul class="kabinet__menu-ul">
                        
                        <? foreach($arResult as $arItem): ?>

                                <?if($arItem["SELECTED"]):?>

                                    <li><a href="<?=$arItem["LINK"]?>" class="active"><?=$arItem["TEXT"]?></a></li>

                                <?else:?>

                                    <li><a href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a></li>

                                <?endif?>

                            <?endforeach?>

                    </ul>
                    <div class="filter__box mr0">
                        <div class="fz14 fw700 consult__tit">Консультация</div>
                        <div class="fz14 mt10">Получите профессианальную консультацию у наших специалистов. </div>

						<?$APPLICATION->IncludeComponent(
	"bitrix:menu", 
	"personal_menu-icons1", 
	array(
		"ALLOW_MULTI_SELECT" => "N",
		"CHILD_MENU_TYPE" => "top",
		"DELAY" => "N",
		"MAX_LEVEL" => "1",
		"MENU_CACHE_GET_VARS" => array(
		),
		"MENU_CACHE_TIME" => "3600",
		"MENU_CACHE_TYPE" => "N",
		"MENU_CACHE_USE_GROUPS" => "Y",
		"ROOT_MENU_TYPE" => "",
		"USE_EXT" => "N",
		"COMPONENT_TEMPLATE" => "personal_menu-icons1"
	),
	false
);?>

                        



                    </div>
                    <a href="?logout=yes" class="filter__reset btn bg-red mr0" >Выход</a>
                </nav>
            </div>




            

          







<? endif;   ?>