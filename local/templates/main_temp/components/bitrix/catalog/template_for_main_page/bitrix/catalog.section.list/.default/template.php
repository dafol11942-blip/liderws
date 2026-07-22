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

//echo "string TEST";


$arViewModeList = $arResult['VIEW_MODE_LIST'];

$arViewStyles = array(
	'LIST' => array(
		'CONT' => 'bx_sitemap',
		'TITLE' => 'bx_sitemap_title',
		'LIST' => 'bx_sitemap_ul',
	),
	'LINE' => array(
		'CONT' => 'bx_catalog_line',
		'TITLE' => 'bx_catalog_line_category_title',
		'LIST' => 'bx_catalog_line_ul',
		'EMPTY_IMG' => $this->GetFolder().'/images/line-empty.png'
	),
	'TEXT' => array(
		'CONT' => 'bx_catalog_text',
		'TITLE' => 'bx_catalog_text_category_title',
		'LIST' => 'bx_catalog_text_ul'
	),
	'TILE' => array(
		'CONT' => 'bx_catalog_tile',
		'TITLE' => 'bx_catalog_tile_category_title',
		'LIST' => 'bx_catalog_tile_ul',
		'EMPTY_IMG' => $this->GetFolder().'/images/tile-empty.png'
	)
);
$arCurView = $arViewStyles[$arParams['VIEW_MODE']];

$strSectionEdit = CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "SECTION_EDIT");
$strSectionDelete = CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "SECTION_DELETE");
$arSectionDeleteParams = array("CONFIRM" => GetMessage('CT_BCSL_ELEMENT_DELETE_CONFIRM'));


// foreach ($arResult['SECTIONS'] as $key => $value) {


// 	if(!empty($value['UF_LINK'])){
// 		$value['SECTION_PAGE_URL'] = $value['UF_LINK'];
// 		$value['~SECTION_PAGE_URL'] = $value['UF_LINK'];
// 	} 
// 	// debug($value);
// }

?>



<?

// debug($arResult);

// UF_LINK 


// foreach ($arResult['SECTIONS'] as $key => $value) {


// 	if(!empty($value['UF_LINK'])){
// 		$value['SECTION_PAGE_URL'] = $value['UF_LINK'];
// 		$value['~SECTION_PAGE_URL'] = $value['UF_LINK'];
// 	} 
// 	// debug($value);
// }

?>


<? if (0 < $arResult["SECTIONS_COUNT"]): ?>

<section class="main-catalog">
        <div class="container">

        	
		


		<?	if (0 < $arResult["SECTIONS_COUNT"]) { // ЕСЛИ ЕСТЬ РАЗДЕЛЫ
			?>



            <div class="catalog__inner <? echo $arCurView['LIST']; ?>" >

            	<?



            		if($arResult['SECTIONS'][0]){

            			$arSect = $arResult['SECTIONS'][0];

				   		$first_section_id = $arSect['ID'];

				   		$arSect['PICTURE']['SRC'] = isset($arSect['PICTURE']['SRC']) ? $arSect['PICTURE']['SRC'] : $arCurView['EMPTY_IMG'];

				   		// debug($arSect);


						?>


			                <a href="<? echo $arSect['SECTION_PAGE_URL']; ?>" class="catalog__item catalog__item-first">
			                    <div class="catalog__text"><?echo $arSect['NAME']?></div>
			                    <div class="catalog__img">
			                        <img src="<? echo $arSect['PICTURE']['SRC'] ?>" alt="<? echo $arSect['PICTURE']['ALT'] ?>">
			                    </div>
			                </a>


						<? 
				       	}
				   	// }

						 

            	?>

                <div class="catalog-wrapper catalog-wrapper_3">

                	<?

                	$count_sections = 0;


	                	foreach ($arResult['SECTIONS'] as &$arSection){

	                		$count_sections++;

                			if($count_sections < 8){


								$this->AddEditAction($arSection['ID'], $arSection['EDIT_LINK'], $strSectionEdit);
								$this->AddDeleteAction($arSection['ID'], $arSection['DELETE_LINK'], $strSectionDelete, $arSectionDeleteParams);

								if (false === $arSection['PICTURE'])
									$arSection['PICTURE'] = array(
										'SRC' => $arCurView['EMPTY_IMG'],
										'ALT' => (
											'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
											? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
											: $arSection["NAME"]
										),
										'TITLE' => (
											'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
											? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
											: $arSection["NAME"]
										)
									);


								?>

								<? if($first_section_id != $arSection['ID']): ?>

									<?

									// debug($arSection);

									?>


									<? if($arSection['ID'] === 1927):?>

										<a href="<? echo $arSection['SECTION_PAGE_URL']; ?>" id="<? echo $this->GetEditAreaId($arSection['ID']); ?>" class="catalog__item custom-catalog__item">
					                        <div class="catalog__text"><? echo $arSection['NAME']; ?></div>
					                        <div class="catalog__img">
					                            <img src="<? echo $arSection['PICTURE']['SRC']; ?>" alt="<? echo $arSection['PICTURE']['TITLE']; ?>">
					                        </div>
					                    </a>


					                <? else:?>



					                	<a href='<? echo $arSection['SECTION_PAGE_URL']; ?>' id="<? echo $this->GetEditAreaId($arSection['ID']); ?>" class="catalog__item">
					                        <div class="catalog__text"><? echo $arSection['NAME']; ?></div>
					                        <div class="catalog__img">
					                            <img src="<? echo $arSection['PICTURE']['SRC']; ?>" alt="<? echo $arSection['PICTURE']['TITLE']; ?>">
					                        </div>
					                    </a>

					                <? endif;?>

									<?
										if ($arParams["COUNT_ELEMENTS"] && $arSection['ELEMENT_CNT'] !== null)
										{
											?> <span>(<? echo $arSection['ELEMENT_CNT']; ?>)</span><?
										}

									?>

								<? endif;?>









							<?
							}else {
								break;
							}

						}
							unset($arSection);


					
	                

	                ?>
                </div>

                <?

                //debug($count_sections);

                ?>


                </div>

                  <div class="catalog-wrapper catalog-wrapper_mr4">

                	<?

                		if($count_sections >= 8){
                	

                			$new_count = 0;


		                	foreach ($arResult['SECTIONS'] as &$arSection){

		                		$count_sections++;
		                		$new_count++;

	                			if($count_sections > 8 && $new_count > 7){


									$this->AddEditAction($arSection['ID'], $arSection['EDIT_LINK'], $strSectionEdit);
									$this->AddDeleteAction($arSection['ID'], $arSection['DELETE_LINK'], $strSectionDelete, $arSectionDeleteParams);

									if (false === $arSection['PICTURE'])
										$arSection['PICTURE'] = array(
											'SRC' => $arCurView['EMPTY_IMG'],
											'ALT' => (
												'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
												? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_ALT"]
												: $arSection["NAME"]
											),
											'TITLE' => (
												'' != $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
												? $arSection["IPROPERTY_VALUES"]["SECTION_PICTURE_FILE_TITLE"]
												: $arSection["NAME"]
											)
										);


									?>

									<? if($first_section_id != $arSection['ID']): ?>

										<? //debug($arSection);?>
										
									<? if($arSection['ID'] == 1927):?>

										<a href="<? echo $arSection['SECTION_PAGE_URL']; ?>" id="<? echo $this->GetEditAreaId($arSection['ID']); ?>" class="catalog__item custom-catalog__item">
					                        <div class="catalog__text"><? echo $arSection['NAME']; ?></div>
					                        <div class="catalog__img">
					                            <img src="<? echo $arSection['PICTURE']['SRC']; ?>" alt="<? echo $arSection['PICTURE']['TITLE']; ?>">
					                        </div>
					                    </a>


					                <? else:?>



					                	<a href='<? echo $arSection['SECTION_PAGE_URL']; ?>' id="<? echo $this->GetEditAreaId($arSection['ID']); ?>" class="catalog__item">
					                        <div class="catalog__text"><? echo $arSection['NAME']; ?></div>
					                        <div class="catalog__img">
					                            <img src="<? echo $arSection['PICTURE']['SRC']; ?>" alt="<? echo $arSection['PICTURE']['TITLE']; ?>">
					                        </div>
					                    </a>

					                <? endif;?>

										<?
											if ($arParams["COUNT_ELEMENTS"] && $arSection['ELEMENT_CNT'] !== null)
											{
												?> <span>(<? echo $arSection['ELEMENT_CNT']; ?>)</span><?
											}

										?>

									<? endif;?>









								<?
								}

							}
							unset($arSection);



						}


					
	                

	                ?>
	            </div> 





            </div>
        <? } // ЕСЛИ ЕСТЬ РАЗДЕЛЫ КОНЕЦ ?>




        


        </div>
</section>


<?
	echo ('LINE' != $arParams['VIEW_MODE'] ? '<div style="clear: both;"></div>' : '');
	$SECTIONS_COUNT = $arResult["SECTIONS_COUNT"];



?>

</div>

<? endif;?>





