<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?>
<?


// debug($arParams);
?>



<div class="container-wrapper-form avtorization">
  <div class="container">
        <div class="forms">
            <div class="forms__tit">
                <!-- Авторизация  -->
                <a href="/"data-val="forms__login" class="forms__login active ft">Вход</a>
                <a href="registration.php" data-val="forms__registr" class="forms__registr ft">Регистрация</a>
                <!-- <button id="btn">modal</button> -->
            </div>
            <!-- <form id="forms__login" class="active" action=""> -->

            <form data-url_in_temp='system.auth.authorize/.default/' id="forms__login" name="form_auth" method="post" class="active" target="_top" action="<?=$arResult["AUTH_URL"]?>">

				<input type="hidden" name="AUTH_FORM" value="Y" />
				<input type="hidden" name="TYPE" value="AUTH" />
				<?if ($arResult["BACKURL"] <> ''):?>
				<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
				<?endif?>
				<?foreach ($arResult["POST"] as $key => $value):?>
				<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
				<?endforeach?>

                <div class="form__inner">

                	<?
                	echo  ShowMessage($arParams["~AUTH_RESULT"]);
					echo ShowMessage($arResult['ERROR_MESSAGE']);

                	?>

                    <div class="form__label mt30">
                        <div>Логин</div>
                        <input name="USER_LOGIN" type="text" placeholder="master-trud">
                    </div>
                    <div class="form__label">
                        <div class="form__pass">
                            <div>Пароль</div>
                            <a href="/auth/forget.php" class="dashed">Забыли пароль ?</a>
                        </div>
                        <input name="USER_PASSWORD" type="password" placeholder="*****">
                    </div>

                    <!-- <input type="checkbox" id="USER_REMEMBER" name="USER_REMEMBER" value=""> -->
                    <input type="hidden" name="Login" value="Войти">

                  <!--   <div class="soglasie-na-obrabotky df">
                    	<input class="checkbox_iphone"  id="login__sogl" <? if($arResult['VALUES']['UF_SOGLASIE_FORM'] == 1): ?> checked <? endif;?> name="UF_SOGLASIE_FORM" type="checkbox">
                        <input class="checkbox_iphone" id="login__sogl" type="checkbox">
                        <label for="login__sogl"></label>
                        <div class="sogl__text">Согласие на обработку <a class="blue" href="personal.html">персональных данных</a></div>
                    </div> -->
                    <button class="btn bg-red" type="submit">Войти</button>
                </div>
            </form>
           
        </div>
    </div>
</div>


<style>


</style>

<!-- 
<div class="bx-auth">
<?if($arResult["AUTH_SERVICES"]):?>
	<div class="bx-auth-title"><?echo GetMessage("AUTH_TITLE")?></div>
<?endif?>
	<div class="bx-auth-note"><?=GetMessage("AUTH_PLEASE_AUTH")?></div>

	<form name="form_auth" method="post" target="_top" action="<?=$arResult["AUTH_URL"]?>">

		<input type="hidden" name="AUTH_FORM" value="Y" />
		<input type="hidden" name="TYPE" value="AUTH" />
		<?if ($arResult["BACKURL"] <> ''):?>
		<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
		<?endif?>
		<?foreach ($arResult["POST"] as $key => $value):?>
		<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
		<?endforeach?>

		<table class="bx-auth-table">
			<tr>
				<td class="bx-auth-label"><?=GetMessage("AUTH_LOGIN")?></td>
				<td><input class="bx-auth-input form-control" type="text" name="USER_LOGIN" maxlength="255" value="<?=$arResult["LAST_LOGIN"]?>" /></td>
			</tr>
			<tr>
				<td class="bx-auth-label"><?=GetMessage("AUTH_PASSWORD")?></td>
				<td><input class="bx-auth-input form-control" type="password" name="USER_PASSWORD" maxlength="255" autocomplete="off" />
<?if($arResult["SECURE_AUTH"]):?>
				<span class="bx-auth-secure" id="bx_auth_secure" title="<?echo GetMessage("AUTH_SECURE_NOTE")?>" style="display:none">
					<div class="bx-auth-secure-icon"></div>
				</span>
				<noscript>
				<span class="bx-auth-secure" title="<?echo GetMessage("AUTH_NONSECURE_NOTE")?>">
					<div class="bx-auth-secure-icon bx-auth-secure-unlock"></div>
				</span>
				</noscript>
<script type="text/javascript">
document.getElementById('bx_auth_secure').style.display = 'inline-block';
</script>
<?endif?>
				</td>
			</tr>
			<?if($arResult["CAPTCHA_CODE"]):?>
				<tr>
					<td></td>
					<td><input type="hidden" name="captcha_sid" value="<?echo $arResult["CAPTCHA_CODE"]?>" />
					<img src="/bitrix/tools/captcha.php?captcha_sid=<?echo $arResult["CAPTCHA_CODE"]?>" width="180" height="40" alt="CAPTCHA" /></td>
				</tr>
				<tr>
					<td class="bx-auth-label"><?echo GetMessage("AUTH_CAPTCHA_PROMT")?>:</td>
					<td><input class="bx-auth-input form-control" type="text" name="captcha_word" maxlength="50" value="" size="15" autocomplete="off" /></td>
				</tr>
			<?endif;?>
<?if ($arResult["STORE_PASSWORD"] == "Y"):?>
			<tr>
				<td></td>
				<td><input type="checkbox" id="USER_REMEMBER" name="USER_REMEMBER" value="Y" /><label for="USER_REMEMBER">&nbsp;<?=GetMessage("AUTH_REMEMBER_ME")?></label></td>
			</tr>
<?endif?>
			<tr>
				<td></td>
				<td class="authorize-submit-cell"><input type="submit" class="btn btn-primary" name="Login" value="<?=GetMessage("AUTH_AUTHORIZE")?>" /></td>
			</tr>
		</table>

<?if ($arParams["NOT_SHOW_LINKS"] != "Y"):?>
		<noindex>
			<p>
				<a href="<?=$arResult["AUTH_FORGOT_PASSWORD_URL"]?>" rel="nofollow"><?=GetMessage("AUTH_FORGOT_PASSWORD_2")?></a>
			</p>
		</noindex>
<?endif?>

<?if($arParams["NOT_SHOW_LINKS"] != "Y" && $arResult["NEW_USER_REGISTRATION"] == "Y" && $arParams["AUTHORIZE_REGISTRATION"] != "Y"):?>
		<noindex>
			<p>
				<a href="<?=$arResult["AUTH_REGISTER_URL"]?>" rel="nofollow"><?=GetMessage("AUTH_REGISTER")?></a><br />
				<?=GetMessage("AUTH_FIRST_ONE")?>
			</p>
		</noindex>
<?endif?>

	</form>
</div>

<script type="text/javascript">
<?if ($arResult["LAST_LOGIN"] <> ''):?>
try{document.form_auth.USER_PASSWORD.focus();}catch(e){}
<?else:?>
try{document.form_auth.USER_LOGIN.focus();}catch(e){}
<?endif?>
</script>

<?if($arResult["AUTH_SERVICES"]):?>
<?
$APPLICATION->IncludeComponent("bitrix:socserv.auth.form", "",
	array(
		"AUTH_SERVICES" => $arResult["AUTH_SERVICES"],
		"CURRENT_SERVICE" => $arResult["CURRENT_SERVICE"],
		"AUTH_URL" => $arResult["AUTH_URL"],
		"POST" => $arResult["POST"],
		"SHOW_TITLES" => $arResult["FOR_INTRANET"]?'N':'Y',
		"FOR_SPLIT" => $arResult["FOR_INTRANET"]?'Y':'N',
		"AUTH_LINE" => $arResult["FOR_INTRANET"]?'N':'Y',
	),
	$component,
	array("HIDE_ICONS"=>"Y")
);
?>
<?endif?> -->
