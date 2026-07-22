
<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main;
?> 
<div class="product__item">
    <a class="product__img"  href="<?=$item['DETAIL_PAGE_URL']?>" title="<?=$imgTitle?>">
        <? $bgImage = empty($item['PREVIEW_PICTURE']['SRC']) ? '/local/templates/main_temp/components/bitrix/catalog.section/.default/images/no_photo.png' : $item['PREVIEW_PICTURE']['SRC']; ?>
        <img src="<?= $bgImage?>" alt="">

    </a>

       
        <a href="<?=$item['DETAIL_PAGE_URL']?>" class="product__title" title="<?= $item['NAME']?>">
        <?= $item['NAME']?>
        </a>
       

       <?
    if (!empty($arParams['PRODUCT_BLOCKS_ORDER']))
    {
        foreach ($arParams['PRODUCT_BLOCKS_ORDER'] as $blockName)
        {
            switch ($blockName)
            {
                case 'price': ?>
                    <div class="product-item-info-container product-item-price-container" data-entity="price-block">
                        <?
                        if ($arParams['SHOW_OLD_PRICE'] === 'Y')
                        {
                            ?>
                            <span class="product-item-price-old" id="<?=$itemIds['PRICE_OLD']?>"
                                <?=($itemIds['RATIO_PRICE'] >= $itemIds['RATIO_BASE_PRICE'] ? 'style="display: none;"' : '')?>>
                                <?=$itemIds['PRINT_RATIO_BASE_PRICE']?>
                            </span>&nbsp;
                            <?
                        }
                        ?>
                        <span class="product-item-price-current" id="<?=$itemIds['PRICE']?>">
                            <?
                            if (!empty($itemIds))
                            {
                                if ($arParams['PRODUCT_DISPLAY_MODE'] === 'N' && $haveOffers)
                                {
                                    echo Loc::getMessage(
                                        'CT_BCI_TPL_MESS_PRICE_SIMPLE_MODE',
                                        array(
                                            '#PRICE#' => $itemIds['PRINT_RATIO_PRICE'],
                                            '#VALUE#' => $measureRatio,
                                            '#UNIT#' => $minOffer['ITEM_MEASURE']['TITLE']
                                        )
                                    );
                                }
                                else
                                {
                                    echo $itemIds['PRINT_RATIO_PRICE'];
                                }
                            }
                            ?>
                        </span>
                    </div>
                    <?
                    break;

            }
        }
    }

    ?>


  




    <div class="product__sum">
        <div class="product__price">2 290.0</div>
        <div class="hidden product__count">
            <button class="item__minus">
                <svg width="12" height="2" viewBox="0 0 12 2" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <line x1="1" y1="1" x2="11" y2="1" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>                                   
            </button>
            <input class="item__number" value="1" type="number">
            <button class="item__plus">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 1L6 11M1 6H11" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg> 
            </button>
        </div>
        <a href="#" class="product-item-btn">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.79682 8.55757C1.58807 7.91174 2.06962 7.25 2.74835 7.25H17.2517C17.9304 7.25 18.4119 7.91174 18.2032 8.55756L15.8597 15.8076C15.7263 16.2203 15.342 16.5 14.9082 16.5H5.09178C4.65799 16.5 4.27367 16.2203 4.14026 15.8076L1.79682 8.55757Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                <path d="M6.25 6.66659L10 2.08325L13.75 6.66659" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
    </div>
    <div class="product__status">
        <div class="product__status-name">Статус</div>
        <div class="product__status-have">В наличии</div>
    </div>
    <div class="product__hover-container">
        <div class="product__hover">
            <div class="product__hover-item">
                <div>Артикул</div>
                <div>155D3A</div>
            </div>
            <div class="product__hover-item">
                <div>Производитель</div>
                <div>Ford</div>
            </div>
            <div class="product__hover-item">
                <div>Вязкость</div>
                <div>5W-30</div>
            </div>
            <div class="product__hover-item">
                <div>Объем, л</div>
                <div>5</div>
            </div>
            <div class="product__hover-item">
                <div>Состав</div>
                <div>Синтетическое</div>
            </div>
        </div>
    </div>
</div>