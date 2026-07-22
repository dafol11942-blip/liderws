<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
IncludeTemplateLangFile(__FILE__);
use Bitrix\Main\Page\Asset;
use Bitrix\Sale;
// use Bitrix\modules\catalog\include.php;

GLOBAL $USER;
?>

<html>
<head>

<?$APPLICATION->ShowHead();?>

<title><?$APPLICATION->ShowTitle()?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css"> -->

    
<?


    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/custom-select/custom-select.css");

    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/style.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/normalize.min.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/new_css.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/arcticmodal/jquery.arcticmodal-0.3.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/arcticmodal/simple.css");
    // Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/сss/modaal.css");


?>








<?php


    // CJSCore::Init(array('jquery'));
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/jquery-3.6.0.js");



    // Asset::getInstance()->addJs("https://code.jquery.com/ui/1.11.4/jquery-ui.js");

    Asset::getInstance()->addJs("https://unpkg.com/swiper/swiper-bundle.min.js");
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/arcticmodal/arcticmodal.js");

    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/inputmask.min.js");

    // Asset::getInstance()->addJs("https://rawgit.com/RobinHerbots/jquery.inputmask/3.x/dist/jquery.inputmask.bundle.js");


    // in this add, remove from catalog filterst
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/catalog.js");


    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/main.js");
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/avtocatalog.js");
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/js/search.js");
    // search.js
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . "/assets/custom-select/custom-select.js");
    // <script src="https://rawgit.com/RobinHerbots/jquery.inputmask/3.x/dist/jquery.inputmask.bundle.js"></script>


    ?>


</head>





<body>
<?$APPLICATION->ShowPanel()?>


<header>
        <div class="header-top df">
            <a href="/" class="logo">
                <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/logo.png" alt="Логотип">
            </a>
           <div class="header-top__content w100">
                <div class="header-top__content-inner df jsb">
                    <!-- <div class="header-top__menu df ac">
                        <nav class="df">
                            <li>
                                <a href="#">Спецпредложения</a>
                            </li>
                            <li>
                                <a href="#">Услуги</a>
                            </li>
                            <li>
                                <a href="#">О нас</a>
                            </li>
                            <li>
                                <a href="#">Клиентам</a>
                            </li>
                            <li>
                                <a href="#">Поставщикам</a>
                            </li>
                            <li>
                                <a href="#">Оптовым клиентам</a>
                            </li>
                            <li>
                                <a href="#">Контакты</a>
                            </li>

                        </nav>
                    </div> -->
                    <?$APPLICATION->IncludeComponent(
                        "bitrix:menu",
                        "top_menu-top",
                        Array(
                            "ALLOW_MULTI_SELECT" => "N",
                            "CHILD_MENU_TYPE" => "top",
                            "DELAY" => "N",
                            "MAX_LEVEL" => "1",
                            "MENU_CACHE_GET_VARS" => array(0=>"",),
                            "MENU_CACHE_TIME" => "3600",
                            "MENU_CACHE_TYPE" => "N",
                            "MENU_CACHE_USE_GROUPS" => "Y",
                            "ROOT_MENU_TYPE" => "top",
                            "USE_EXT" => "N"
                        )
                    );?>
                    <div class="df ac header-top__city__phone">
                        <?/*?><a href="#" class="city white"><? if(empty($_SESSION['city'])): ?>Набережные Челны<? else:?><? echo $_SESSION['city']; ?><? endif;?></a><?*/?>
                        <a href="#" class="city white"><? if(empty($_SESSION['city'])): ?>Елабуга<? else:?><? echo $_SESSION['city']; ?><? endif;?></a>
                        <div class="phone-select">
                           
                                <?$APPLICATION->IncludeComponent(
                                    "bitrix:main.include",
                                    ".default",
                                    array(
                                        "AREA_FILE_SHOW" => "file",
                                        "AREA_FILE_SUFFIX" => "inc",
                                        "EDIT_TEMPLATE" => "",
                                        "PATH" => "/include/header/inc_header-phone-select.php",
                                        "COMPONENT_TEMPLATE" => ".default"
                                    ),
                                    false
                                );?>
                            
                        </div>
                    </div>
                </div>   
                <div class="header-top__main-content df">
                    <div class="header-item__btn df bg-blue">
                        <a href="/catalog/" class="header-item__btn-menu df">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 13H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M1 8H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M1 3H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>Каталог</div>
                        </a>
                    </div>
                    <a href="/avtocatalog/" class="header-item__btn marka">Марка</a>
                    <div class="header-item__search">
                        <!-- ?vin=JT2AT00N6R0014269&VinAction=Search&language=ru&function=getVin -->
                        <!-- search__select-otion -->
                        <!-- http://lider.netkama.ru/test.php -->
                        <form id='search' action="/search/" style="height: 100%;">
                            <input type="text" class="main-input"  name="q" value="<?=$arResult["REQUEST"]["QUERY"]?>">
                            <input type="text" class="hidden" id='getVin' name="function" value="">
                            <input type="text" class="hidden" id='vin' name="VinAction" value="1">
                            <button style="border-radius: 10px;" class="search-btn df fw bg-blue">
<!--                                Поиск-->

                                <svg width="20px" fill="white" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26.1 26.1" xmlns:xlink="http://www.w3.org/1999/xlink" enable-background="new 0 0 26.1 26.1">
                                    <path d="m25.806,22.9l-5.4-5.4c-0.2-0.2-0.2-0.4-0.1-0.6 1.1-1.7 1.7-3.7 1.7-5.9 0-6.1-4.9-11-11-11s-11,4.9-11,11 4.9,11 11,11c2.2,0 4.2-0.6 5.9-1.7 0.2-0.1 0.5-0.1 0.6,0.1l5.4,5.4c0.4,0.4 1,0.4 1.4,0l1.4-1.4c0.5-0.5 0.5-1.1 0.1-1.5zm-14.7-4.9c-3.9,0-7-3.1-7-7s3.1-7 7-7 7,3.1 7,7-3.1,7-7,7z"/>
                                </svg>

                            </button>
                        </form>
                            <div class="header__search-inner">
                                <div class="header-item__search-text">Поиск</div>
                                <div class="header-item__search-line"></div>
                                <div class="header-item__search-select">

<!-- 
                                <select class="search__select" name="" id="search__select-otion">
                                    <option selected value="detail">по номеру детали</option>
                                    <option value="getVin">по vin-номеру</option>
                                </select> -->

                                <!-- div class="select search__select" id="select-Vin">
									<button type="button" class="select__toggle" name="car" value="" data-select="toggle" data-index="-1">по номеру детали</button>
									<div class="select__dropdown">
										<ul class="select__options">
											<li class="select__option select__option_selected" data-select="option" data-value="detail" data-index="0">по номеру детали</li>
											<li class="select__option" data-select="option" data-value="getVin" data-index="1">по vin-номеру</li>
											
										</ul>
									</div>
								</div> -->
                                <div class="search__mob-contorls">
                                    <div class="mob-select select mob-select-in-desctop">
                                        <li class="active" data-search_item="detail">по номеру детали</li>
                                        <li data-search_item="getVin" class=""> по vin-номеру</li>
                                    </div>
                                </div>



                            </div>
                            <div class="header-item__search-svg"></div>
                            </div>
<!--                         </input>
 -->
                         <? /*$APPLICATION->IncludeComponent(
                            "bitrix:catalog.search",
                            "template1",
                            Array(
                                "ACTION_VARIABLE" => "action",
                                "AJAX_MODE" => "N",
                                "AJAX_OPTION_ADDITIONAL" => "",
                                "AJAX_OPTION_HISTORY" => "N",
                                "AJAX_OPTION_JUMP" => "N",
                                "AJAX_OPTION_STYLE" => "Y",
                                "BASKET_URL" => "/personal/basket.php",
                                "CACHE_TIME" => "36000000",
                                "CACHE_TYPE" => "A",
                                "CHECK_DATES" => "N",
                                "CONVERT_CURRENCY" => "N",
                                "DETAIL_URL" => "catalog/#SECTION_CODE_PATH#/#ELEMENT_CODE#/",
                                "DISPLAY_BOTTOM_PAGER" => "Y",
                                "DISPLAY_COMPARE" => "N",
                                "DISPLAY_TOP_PAGER" => "N",
                                "ELEMENT_SORT_FIELD" => "sort",
                                "ELEMENT_SORT_FIELD2" => "id",
                                "ELEMENT_SORT_ORDER" => "asc",
                                "ELEMENT_SORT_ORDER2" => "desc",
                                "HIDE_NOT_AVAILABLE" => "N",
                                "HIDE_NOT_AVAILABLE_OFFERS" => "N",
                                "IBLOCK_ID" => "36",
                                "IBLOCK_TYPE" => "1c_catalog",
                                "LINE_ELEMENT_COUNT" => "3",
                                "NO_WORD_LOGIC" => "N",
                                "OFFERS_CART_PROPERTIES" => array(),
                                "OFFERS_FIELD_CODE" => array("", ""),
                                "OFFERS_LIMIT" => "5",
                                "OFFERS_PROPERTY_CODE" => array("", ""),
                                "OFFERS_SORT_FIELD" => "sort",
                                "OFFERS_SORT_FIELD2" => "id",
                                "OFFERS_SORT_ORDER" => "asc",
                                "OFFERS_SORT_ORDER2" => "desc",
                                "PAGER_DESC_NUMBERING" => "N",
                                "PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
                                "PAGER_SHOW_ALL" => "N",
                                "PAGER_SHOW_ALWAYS" => "N",
                                "PAGER_TEMPLATE" => ".default",
                                "PAGER_TITLE" => "Товары",
                                "PAGE_ELEMENT_COUNT" => "30",
                                "PRICE_CODE" => array(),
                                "PRICE_VAT_INCLUDE" => "Y",
                                "PRODUCT_ID_VARIABLE" => "id",
                                "PRODUCT_PROPERTIES" => "",
                                "PRODUCT_PROPS_VARIABLE" => "prop",
                                "PRODUCT_QUANTITY_VARIABLE" => "quantity",
                                "PROPERTY_CODE" => array("", ""),
                                "RESTART" => "N",
                                "SECTION_ID_VARIABLE" => "SECTION_ID",
                                "SECTION_URL" => "catalog/#SECTION_CODE_PATH#/",
                                "SHOW_PRICE_COUNT" => "1",
                                "USE_LANGUAGE_GUESS" => "Y",
                                "USE_PRICE_COUNT" => "N",
                                "USE_PRODUCT_QUANTITY" => "N",
                                "USE_SEARCH_RESULT_ORDER" => "N",
                                "USE_TITLE_RANK" => "N"
                            )
                        );
                            **/
                        ?>


                    </div>

                    <?

                        $main_url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                        // echo $main_url;


                    ?>

                   

                    <a href=" <?if($USER->IsAuthorized()):?> <?= $main_url . "/personal/"?> <? else: ?> /auth/ <? endif;?>" class="header-item__btn">
                        <div class="header-item__btn-inner df">
                            <div class="header-item__svg">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13.3334 14V12.6667C13.3334 11.9594 13.0525 11.2811 12.5524 10.781C12.0523 10.281 11.374 10 10.6667 10H5.33341C4.62617 10 3.94789 10.281 3.4478 10.781C2.9477 11.2811 2.66675 11.9594 2.66675 12.6667V14" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M7.99992 7.33333C9.47268 7.33333 10.6666 6.13943 10.6666 4.66667C10.6666 3.19391 9.47268 2 7.99992 2C6.52716 2 5.33325 3.19391 5.33325 4.66667C5.33325 6.13943 6.52716 7.33333 7.99992 7.33333Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>                                    
                            </div>
                            <div class="header-item__text">Кабинет</div>
                        </div>


                       
                        <?if($USER->IsAuthorized()):?>

                            <div class="header-item__text-bottom"><?echo $USER->GetFirstName();?></div>

                        <? else: ?>

                            <div class="header-item__text-bottom">Войти</div>
                        
                        <? endif;?>



                    </a>
                    <a href="/cart/" class="header-item__btn bg-blue">
                        <div class="header-item__btn-inner df">
                            <div class="header-item__svg">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.74835 6H13.2517C13.9304 6 14.4119 6.66174 14.2032 7.30756L12.587 12.3076C12.4536 12.7203 12.0693 13 11.6355 13H4.36451C3.93072 13 3.5464 12.7203 3.41298 12.3076L1.79682 7.30757C1.58807 6.66174 2.06962 6 2.74835 6Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M5 5.33342L8 1.66675L11 5.33342" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="header-item__text">Корзина</div>
                        </div>
                        <div class="header-item__text-bottom"><? 

                        if (CModule::IncludeModule("sale")){

                        	$basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());

                            // debug($basket);
                            // $basketItems = $basket->getBasketItems(); // массив объектов Sale\BasketItem
                           
                            // foreach ($basket as $basketItem) {
                            // echo $basketItem->getField('NAME') . ' - ' . $basketItem->getQuantity() . '<br />';
                            // }

    
                            // CSaleBasket::DeleteAll(CSaleBasket::GetBasketUserID());
    

                        	echo FormatCurrency($basket->getPrice(), "RUB");

                        

	                        // if($_SESSION['SALE_USER_BASKET_PRICE']['s1'][1]){

	                               
	                        //         echo FormatCurrency( $_SESSION['SALE_USER_BASKET_PRICE']['s1'][1], "RUB"); 

	                        // }else {

	                                
	                        //         echo FormatCurrency(0, "RUB");

	                           
	                        // }

	                   }

                        ?>


                        </div>
                        
                    </a>
                </div>            
           </div>
        </div>


        <?

        $page_url =  explode('/', $APPLICATION->GetCurPage());

        // debug($page_url);

        ?>
        <? if($page_url['1'] == 'catalog'):?>




            <?$APPLICATION->IncludeComponent(
	"bitrix:catalog.section.list", 
	"header_sections", 
	array(
		"ADD_SECTIONS_CHAIN" => "N",
		"CACHE_FILTER" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"COUNT_ELEMENTS" => "Y",
		"COUNT_ELEMENTS_FILTER" => "CNT_ACTIVE",
		"FILTER_NAME" => "sectionsFilter",
		"IBLOCK_ID" => "36",
		"IBLOCK_TYPE" => "1c_catalog",
		"SECTION_CODE" => "",
		"SECTION_FIELDS" => array(
			0 => "",
			1 => "",
		),
		"SECTION_ID" => "0",
		"SECTION_URL" => "#SITE_DIR#/catalog/#SECTION_CODE_PATH#/",
		"SECTION_USER_FIELDS" => array(
			0 => "",
			1 => "",
		),
		"SHOW_PARENT_NAME" => "Y",
		"TOP_DEPTH" => "2",
		"VIEW_MODE" => "TEXT",
		"COMPONENT_TEMPLATE" => "header_sections"
	),
	false
);?>







            <!-- <div class="header__center">
                <div class="container">
                    <div class="header-bottom">
                        <div class="header-bottom__menu">
                            <nav>
                                <ul>
                                    <li>
                                        <a href="">ТЕХОСМОТР</a>
                                    </li>
                                    <li class="active">
                                        <a href="">ЗАПЧАСТИ ДЛЯ ТО</a>
                                    </li>
                                    <li>
                                        <a href="">ЗАПЧАСТИ</a>
                                    </li>
                                    <li>
                                        <a href="masla_i_avtoximie.html">МАСЛА И АВТОХИМИЯ</a>
                                    </li>
                                    <li>
                                        <a href="">EVA КОВРИКИ</a>
                                    </li>
                                    <li>
                                        <a href="">АККУМУЛЯТОРЫ </a>
                                    </li>
                                    <li>
                                        <a href="">ШИНЫ И ДИСКИ</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                       <div class="header-bottom__select">
                            <select name="" id="">
                                <option value="">еще</option>
                                <option value="">еще</option>
                            </select>
                       </div>
                    </div>
                </div>
            </div> -->

        <? endif;?>


        <div class="mob__header bg-blue-black">
           <div class="container menu__old">
               <div class="mob__header-top df jsb ac">
                    <div class="mob__menu">
                        <div class="mob__menu-btn product-item-btn">
                            <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 13.7261H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M1 8.76025H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M1 3.79456H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                    <a href="<? echo $main_url ?>" class="mob__logo">
                        <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/mob__logo.png" alt="мобильный лого">
                    </a>
                    <a class="product-item-btn" href="<? echo $main_url ?>/cart/">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.74835 6H13.2517C13.9304 6 14.4119 6.66174 14.2032 7.30756L12.587 12.3076C12.4536 12.7203 12.0693 13 11.6355 13H4.36451C3.93072 13 3.5464 12.7203 3.41298 12.3076L1.79682 7.30757C1.58807 6.66174 2.06962 6 2.74835 6Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M5 5.33342L8 1.66675L11 5.33342" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
               </div>
                <div class="mob__search bg-blue">
                    <div class="mob__search-btn df jsb">
                        <div>Поиск</div>
                        <div class="bg-blue-black mob__svg">
                            <svg width="8" height="6" viewBox="0 0 8 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.33334 1.1133C7.20843 0.989128 7.03946 0.919434 6.86334 0.919434C6.68722 0.919434 6.51825 0.989128 6.39334 1.1133L4.00001 3.4733L1.64001 1.1133C1.5151 0.989128 1.34613 0.919434 1.17001 0.919434C0.993883 0.919434 0.824915 0.989128 0.700006 1.1133C0.637521 1.17527 0.587925 1.249 0.554079 1.33024C0.520233 1.41148 0.502808 1.49862 0.502808 1.58663C0.502808 1.67464 0.520233 1.76177 0.554079 1.84301C0.587925 1.92425 0.637521 1.99799 0.700006 2.05996L3.52667 4.88663C3.58865 4.94912 3.66238 4.99871 3.74362 5.03256C3.82486 5.0664 3.912 5.08383 4.00001 5.08383C4.08801 5.08383 4.17515 5.0664 4.25639 5.03256C4.33763 4.99871 4.41136 4.94912 4.47334 4.88663L7.33334 2.05996C7.39582 1.99799 7.44542 1.92425 7.47927 1.84301C7.51311 1.76177 7.53054 1.67464 7.53054 1.58663C7.53054 1.49862 7.51311 1.41148 7.47927 1.33024C7.44542 1.249 7.39582 1.17527 7.33334 1.1133Z" fill="white"/>
                            </svg>                                
                        </div>
                    </div>
                </div>

                <form id="search-mob" class="mob__search-main hidden df jsb" action="/search/">


                    <input class="mob__search-input" name="q" type="text" value="<?=$arResult["REQUEST"]["QUERY"]?>" placeholder="Введите запрос">
                    <input type="text" class="hidden" id='getVin-mob' name="function" value="">
                    <input type="text" class="hidden" id='vin-mob' name="VinAction" value="1">

                    <button style="border:none;" class="bg-blue-black mob__svg mob-svg-search">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.33333 12.6667C10.2789 12.6667 12.6667 10.2789 12.6667 7.33333C12.6667 4.38781 10.2789 2 7.33333 2C4.38781 2 2 4.38781 2 7.33333C2 10.2789 4.38781 12.6667 7.33333 12.6667Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 14L11.1 11.1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>                              
                    </button>
                	
                </form>


               <!--  <form id='search' action="/search/" style="width: 100%;height: 100%;">
                    <input type="text" class="main-input"  name="q" value="<?//=$arResult["REQUEST"]["QUERY"]?>">
                    <input type="text" class="hidden" id='getVin' name="function" value="">
                    <input type="text" class="hidden" id='vin' name="VinAction" value="1">
                    <button class="search-btn  bg-blue">Поиск</button>
                </form>


                <div class="mob__search-main hidden df jsb">


                    <input class="mob__search-input" type="text" placeholder="Введите запрос">
                    <div class="bg-blue-black mob__svg mob-svg-search">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.33333 12.6667C10.2789 12.6667 12.6667 10.2789 12.6667 7.33333C12.6667 4.38781 10.2789 2 7.33333 2C4.38781 2 2 4.38781 2 7.33333C2 10.2789 4.38781 12.6667 7.33333 12.6667Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 14L11.1 11.1" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>                              
                    </div>


                </div> -->




                <div class="search__mob-contorls hidden search__mob-contorls-mob">
                    <div class="mob-select select">
                        <li class="active" data-search_item='detail'>по номеру детали</li>
                        <li data-search_item='getVin'> по vin-номеру</li>
                    </div>
                    <div class="search__close">
                        <svg width="20" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.2132 1.07107L1.07102 15.2132M1.07102 1.07107L15.2132 15.2132" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>



           </div>

           <div class="mob__menu-content hidden">
                <div class="container">
                    <!-- <div class="mob__header-top df jsb ac">
                        <a class="product-item-btn" href="#">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.74835 6H13.2517C13.9304 6 14.4119 6.66174 14.2032 7.30756L12.587 12.3076C12.4536 12.7203 12.0693 13 11.6355 13H4.36451C3.93072 13 3.5464 12.7203 3.41298 12.3076L1.79682 7.30757C1.58807 6.66174 2.06962 6 2.74835 6Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M5 5.33342L8 1.66675L11 5.33342" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <a href="/" class="mob__logo">
                            <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/mob__logo.png" alt="мобильный лого">
                        </a>
                        <div class="mob__menu">
                            <div class="mob__menu-btn product-item-btn">
                                <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 13.7261H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M1 8.76025H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M1 3.79456H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                   </div> -->
                    <div class="header-top__main-content">
                        <div class="header-item__btn df bg-blue">
                            <a href="<? echo $main_url; ?>/catalog/" class="header-item__btn-menu df">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 13H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M1 8H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M1 3H15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div>Каталог</div>
                            </a>
                        </div>
                        <a href="" class="header-item__btn bg-blue marka">Марка</a>
                        <div class="header-item__btn df">
                        <div class="header-item__svg">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13.3334 14V12.6667C13.3334 11.9594 13.0525 11.2811 12.5524 10.781C12.0523 10.281 11.374 10 10.6667 10H5.33341C4.62617 10 3.94789 10.281 3.4478 10.781C2.9477 11.2811 2.66675 11.9594 2.66675 12.6667V14" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M7.99992 7.33333C9.47268 7.33333 10.6666 6.13943 10.6666 4.66667C10.6666 3.19391 9.47268 2 7.99992 2C6.52716 2 5.33325 3.19391 5.33325 4.66667C5.33325 6.13943 6.52716 7.33333 7.99992 7.33333Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>                                    
                        </div>
                        <a href="<?if($USER->IsAuthorized()):?> <?= $main_url . "/personal/"?> <? else: ?> /auth/ <? endif;?>" class="header-item__text">Кабинет</a>
                        </div>
                    </div>

                     <?$APPLICATION->IncludeComponent(
                        "bitrix:menu",
                        "top_menu-top",
                        Array(
                            "ALLOW_MULTI_SELECT" => "N",
                            "CHILD_MENU_TYPE" => "top",
                            "DELAY" => "N",
                            "MAX_LEVEL" => "1",
                            "MENU_CACHE_GET_VARS" => array(0=>"",),
                            "MENU_CACHE_TIME" => "3600",
                            "MENU_CACHE_TYPE" => "N",
                            "MENU_CACHE_USE_GROUPS" => "Y",
                            "ROOT_MENU_TYPE" => "top",
                            "USE_EXT" => "N"
                        )
                    );?>


                  <!--   <div class="header-top__menu df ac">
                        <nav class="df">
                            <li>
                                <a href="#">Спецпредложения</a>
                            </li>
                            <li>
                                <a href="#">Услуги</a>
                            </li>
                            <li>
                                <a href="#">О нас</a>
                            </li>
                            <li>
                                <a href="#">Клиентам</a>
                            </li>
                            <li>
                                <a href="#">Поставщикам</a>
                            </li>
                            <li>
                                <a href="#">Оптовым клиентам</a>
                            </li>
                            <li>
                                <a href="#">Контакты</a>
                            </li>
                        </nav>
                    </div> -->
                    <div class="df">

                          <?$APPLICATION->IncludeComponent(
                                "bitrix:main.include",
                                ".default",
                                Array(
                                    "AREA_FILE_SHOW" => "file",
                                    "AREA_FILE_SUFFIX" => "inc",
                                    "COMPONENT_TEMPLATE" => ".default",
                                    "EDIT_TEMPLATE" => "",
                                    "PATH" => SITE_TEMPLATE_PATH ."/include/inc_footer__have__question.php"
                                )
                            );?>


                       <!--  <div class="services__item">
                            <div class="services__title">остались вопросы ?</div>
                            <div class="services__text">Получите профессианальную консультацию 
                                у наших специалистов. </div>
                            <div class="services__bottom">
                                <a href="#" id="have__question" class="services__btn btn bg-red">
                                    <div>Заказать звонок</div>
                                </a> -->
                                <!-- <a href='#' class=" main__all">Подробнее</a> -->
                            <!-- /div>
                        </div>
 -->
                    </div>
                    <div class="footer__contacts">
                        <div class="footer-contant">Контакты</div>
                        <div class="footer__items">
                            <div class="footer__item">
                                <div class="footer__address">РТ, Елабуга, пр-т Нефтяников 4</div>
                                <a href='#' class="email">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.66659 2.66666H13.3333C14.0666 2.66666 14.6666 3.26666 14.6666 3.99999V12C14.6666 12.7333 14.0666 13.3333 13.3333 13.3333H2.66659C1.93325 13.3333 1.33325 12.7333 1.33325 12V3.99999C1.33325 3.26666 1.93325 2.66666 2.66659 2.66666Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14.6666 4L7.99992 8.66667L1.33325 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>                                        
                                </a>
                                <div class="footer__select">
                                    <select name="" id="">
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                    </select>
                                </div>
                                
                            </div>
                            <div class="footer__item">
                                <div class="footer__address">РТ, Елабуга, пр-т Нефтяников 4</div>
                                <a href='#' class="email">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.66659 2.66666H13.3333C14.0666 2.66666 14.6666 3.26666 14.6666 3.99999V12C14.6666 12.7333 14.0666 13.3333 13.3333 13.3333H2.66659C1.93325 13.3333 1.33325 12.7333 1.33325 12V3.99999C1.33325 3.26666 1.93325 2.66666 2.66659 2.66666Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14.6666 4L7.99992 8.66667L1.33325 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>                                        
                                </a>
                                <div class="footer__select">
                                    <select name="" id="">
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                        <option value="">
                                            <div class="footer-select__items">
                                                <div>Иномарки</div>
                                                <div>8 987 262 45 85</div>
                                            </div>
                                        </option>
                                    </select>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <div class="footer__bottom">
                        <div class="footer__personal">
                            <div>© 2021 Лидер</div>
                            <a href="#">Правила обработки персональных данных</a>
                        </div>


                        <!-- <div class="footer__icons"> -->


                         <?$APPLICATION->IncludeComponent(
                            "bitrix:menu", 
                            "footer_menu-icons", 
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
                                "ROOT_MENU_TYPE" => "footer_menu__icons",
                                "USE_EXT" => "N",
                                "COMPONENT_TEMPLATE" => "footer_menu-icons"
                            ),
                            false
                        );?>
                        
                        <!-- </div> -->
                        <div class="footer__netkam">
                            <div>Создание сайтов —</div>
                            <a href="https://netkam.ru">Неткам</a>
                        </div>
                    </div>
                </div>

            </div>  
            
        </div>
</header>
<?$APPLICATION->IncludeComponent(
	"bitrix:breadcrumb", 
	"main_bread", 
	array(
		"PATH" => "",
		"SITE_ID" => "s1",
		"START_FROM" => "0",
		"COMPONENT_TEMPLATE" => "main_bread"
	),
	false
);?>
