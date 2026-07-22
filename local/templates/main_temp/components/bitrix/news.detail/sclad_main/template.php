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

		 
		    <div class="detail__center df">

		        <div class="image__container-full w580">
                     <img class="detail_picture"
						border="0"
						src="<?=$arResult["DETAIL_PICTURE"]["SRC"]?>"
						width="<?=$arResult["DETAIL_PICTURE"]["WIDTH"]?>"
						height="<?=$arResult["DETAIL_PICTURE"]["HEIGHT"]?>"
						alt="<?=$arResult["DETAIL_PICTURE"]["ALT"]?>"
						title="<?=$arResult["DETAIL_PICTURE"]["TITLE"]?>"
					/>
                    <div class="lypa__svg">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M17.5 17.5L13.875 13.875" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M9.14062 11.2828L9.14062 7" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M7.2973 9.00073L10.9863 9.00073" stroke="#C4C4C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>                                
                    </div>
                </div>



		       
		  </div>
		  <div class="klientam__text">
		    <?echo $arResult["DETAIL_TEXT"];?>
		  </div>
		</div>

	</div>
</section>














