<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?if (!empty($arResult)):?>



<li class="footer-nav__title">
    <a href="<?= $arResult[0]['LINK'] ?>"><?= $arResult[0]['TEXT'] ?></a>
</li>

	



<div class="footer-catalog__inner">
	<ul>
<?
$count = 0;

foreach($arResult as $arItem):
	if($arParams["MAX_LEVEL"] == 1 && $arItem["DEPTH_LEVEL"] > 1) 
		continue;

	?>

	<? if($count != 0):?>


		<?
			if($count == 5){
				echo " </ul><ul>";
			}
		?>
		<?if($arItem["SELECTED"]):?>
			
			<li><a href="<?=$arItem["LINK"]?>" class="selected"><?=$arItem["TEXT"]?></a></li>

			
		<?else:?>
			
			<li><a href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a></li>

		<?endif?>

	<? endif;?>
	

	<? $count++ ?>

	
<?endforeach?>
  </ul>
</div>

<?endif?>


                            