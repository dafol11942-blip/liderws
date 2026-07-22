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






<?foreach($arResult["ITEMS"] as $arItem):?>
	<? 
		// debug($arItem);
	?>

	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>

	<div class="vakansie__box" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
		<div class="filter__box zapchasti-open">
			<div class="box__table df jsb">
				<div class="vakansie__box-inner">
					<div class="vakansi-inner__left">
						<div class="vakansi__tit">	


							<?echo $arItem["NAME"]?>


						</div>
	 <a href="<?= $arItem['PROPERTIES']['LINK']['VALUE'] ?>" class="btn bg-red">Откликнуться</a>
					</div>
					<div class="vakansi-inner__right">


						<?echo $arItem["PREVIEW_TEXT"]?>


					</div>
				</div>
				<div class="filter__btns">
                                <div class="filter__minus df ac">
                                    <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 1H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="filter__plus" >
                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6 1L6 11M1 6H11" stroke="#668BEA" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                </div>
                            </div>
			</div>
			<div class="box__tab">
				<div class="box__content df jsb ac">
					<div class="product__zapchsti-inner">
						<div class="vakansie-box__content-left">
						</div>



						<div class="vakansie-box__content">

							<?echo $arItem["DETAIL_TEXT"]?>
							

					</div>
				</div>
			</div>
		</div>
	</div>
	</div>

	
<?endforeach;?>








