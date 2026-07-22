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
?>

 <section class="services mt20">
        <div class="container">
            <div class="services__inner">

<?foreach($arResult["ITEMS"] as $arItem):?>
	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>


	<div class="services__item" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
        <div data-modal-title='<?echo $arItem["NAME"]?>' class="services__title"><?echo $arItem["NAME"]?></div>
        <div class="services__text"><?echo $arItem["PREVIEW_TEXT"];?></div>
        <div class="services__bottom">
            <a data-modal-name='<?echo $arItem["NAME"]?>' href="#" class="modal-tech-osmotr-btn services__btn btn bg-red">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 7.33329L8 9.33329L14.6667 2.66663" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 8V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V3.33333C2 2.97971 2.14048 2.64057 2.39052 2.39052C2.64057 2.14048 2.97971 2 3.33333 2H10.6667" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>                                
                <div>Записаться</div>
            </a>
        </div>
    </div>

<?endforeach;?>

</div>
</div>
</section>


