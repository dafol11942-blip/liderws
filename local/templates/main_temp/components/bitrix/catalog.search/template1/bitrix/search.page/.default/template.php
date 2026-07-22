<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>

<!-- <div class="search-page"> -->
	<div style="margin-top: 1rem;">Поиск: <?=$arResult["REQUEST"]["QUERY"]?></div> 
<form  class="custom-form" action="" method="get">
<?if($arParams["USE_SUGGEST"] === "Y"):
	if(mb_strlen($arResult["REQUEST"]["~QUERY"]) && is_object($arResult["NAV_RESULT"]))
	{
		$arResult["FILTER_MD5"] = $arResult["NAV_RESULT"]->GetFilterMD5();
		$obSearchSuggest = new CSearchSuggest($arResult["FILTER_MD5"], $arResult["REQUEST"]["~QUERY"]);
		$obSearchSuggest->SetResultCount($arResult["NAV_RESULT"]->NavRecordCount);
	}
	?>
	<?$APPLICATION->IncludeComponent(
		"bitrix:search.suggest.input",
		"",
		array(
			"NAME" => "q",
			"VALUE" => $arResult["REQUEST"]["~QUERY"],
			"INPUT_SIZE" => 40,
			"DROPDOWN_SIZE" => 10,
			"FILTER_MD5" => $arResult["FILTER_MD5"],
		),
		$component, array("HIDE_ICONS" => "Y")
	);?>
<?else:?>

<!-- 	<input type="text" class="main-input" name="q" value="<?=$arResult["REQUEST"]["QUERY"]?>">
        <div class="header__search-inner">
            <div class="header-item__search-text">Поиск</div>
            <div class="header-item__search-line"></div>
            <div class="header-item__search-select">
            <select class="search__select" name="" id="">
                <option value="">по номеру детали</option>
                <option value="">по номеру детали</option>
            </select>
        </div>
        <div class="header-item__search-svg"></div>
        </div>
    </input> -->


<!-- <div class="main-input-page">  -->


	<input class="serarch_1" placeholder="Искать здесь" type="text" name="q" value="<?=$arResult["REQUEST"]["QUERY"]?>" size="40" />
<?endif;?>
	&nbsp;
	<button>
		<!-- <svg fill="#000000" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="50px" height="50px"><path fill="none" stroke="#000000" stroke-linecap="round" stroke-miterlimit="10" stroke-width="2" d="M21 4A16 16 0 1 0 21 36A16 16 0 1 0 21 4Z"/><path fill="none" stroke="#000000" stroke-miterlimit="10" stroke-width="4" d="M32.229 32.229L45.5 45.5"/>
		</svg> -->
	</button>
	<!-- <input class="serarch_2" type="submit" value="<?//=GetMessage("SEARCH_GO")?>" /> -->
	<input type="hidden" name="how" value="<?echo $arResult["REQUEST"]["HOW"]=="d"? "d": "r"?>" />


<!-- </div> -->




<?if($arParams["SHOW_WHEN"]):?>
	<script>
	var switch_search_params = function()
	{
		var sp = document.getElementById('search_params');
		var flag;

		if(sp.style.display == 'none')
		{
			flag = false;
			sp.style.display = 'block'
		}
		else
		{
			flag = true;
			sp.style.display = 'none';
		}

		var from = document.getElementsByName('from');
		for(var i = 0; i < from.length; i++)
			if(from[i].type.toLowerCase() == 'text')
				from[i].disabled = flag

		var to = document.getElementsByName('to');
		for(var i = 0; i < to.length; i++)
			if(to[i].type.toLowerCase() == 'text')
				to[i].disabled = flag

		return false;
	}
	</script>
	<br /><a class="search-page-params" href="#" onclick="return switch_search_params()"><?echo GetMessage('CT_BSP_ADDITIONAL_PARAMS')?></a>
	<div id="search_params" class="search-page-params" style="display:<?echo $arResult["REQUEST"]["FROM"] || $arResult["REQUEST"]["TO"]? 'block': 'none'?>">
		<?$APPLICATION->IncludeComponent(
			'bitrix:main.calendar',
			'',
			array(
				'SHOW_INPUT' => 'Y',
				'INPUT_NAME' => 'from',
				'INPUT_VALUE' => $arResult["REQUEST"]["~FROM"],
				'INPUT_NAME_FINISH' => 'to',
				'INPUT_VALUE_FINISH' =>$arResult["REQUEST"]["~TO"],
				'INPUT_ADDITIONAL_ATTR' => 'size="10"',
			),
			null,
			array('HIDE_ICONS' => 'Y')
		);?>
	</div>
<?endif?>
</form><br />

<?if(isset($arResult["REQUEST"]["ORIGINAL_QUERY"])):
	?>
	<div class="search-language-guess">
		<?echo GetMessage("CT_BSP_KEYBOARD_WARNING", array("#query#"=>'<a href="'.$arResult["ORIGINAL_QUERY_URL"].'">'.$arResult["REQUEST"]["ORIGINAL_QUERY"].'</a>'))?>
	</div><br /><?
endif;?>
<!-- </div> -->
<!-- </div> -->



<style>



* {box-sizing: border-box;}
.custom-form {
  position: relative;
  /*width: 300px;
  margin: 0 auto;*/
  width: 420px;   
  margin-top: 2rem;
  display: none;
}
.custom-form  input {
  width: 100%;
  height: 42px;
  padding-left: 10px;
  border: 2px solid #7BA7AB;
  border-radius: 5px;
  outline: none;
  background: #F9F0DA;
  color: #9E9C9C;
}
.custom-form button {
  position: absolute; 
  top: 0;
  right: 0px;
  width: 42px;
  height: 42px;
  border: none;
  background: #7BA7AB;
  border-radius: 0 5px 5px 0;
  cursor: pointer;
}
.custom-form button:before {
  content: "\f002";
  font-family: FontAwesome;
  font-size: 16px;
  color: #F9F0DA;
}
</style>