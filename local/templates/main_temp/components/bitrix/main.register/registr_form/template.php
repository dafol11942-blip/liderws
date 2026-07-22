<?
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage main
 * @copyright 2001-2014 Bitrix
 */

/**
 * Bitrix vars
 * @global CMain $APPLICATION
 * @global CUser $USER
 * @param array $arParams
 * @param array $arResult
 * @param CBitrixComponentTemplate $this
 */

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();

if($arResult["SHOW_SMS_FIELD"] == true)
{
	CJSCore::Init('phone_auth');
}
?>



<?if(!$USER->IsAuthorized()):?>


<?

    $_SESSION['select__form'] = $_REQUEST['select__form'];
    
?>







<div class="container-wrapper-form" <? if(count($arResult["ERRORS"]) > 0): ?> style='height: fit-content;' <? endif;?>  >

  <div class="container">
        <div class="forms">
            <div class="forms__tit">
                <!-- регистрация -->
                <a href="/auth/" data-val="forms__login" class="forms__login ft">Вход</a>
                <a href="/" data-val="forms__registr" class="forms__registr active ft">Регистрация</a>
            </div>

            <?
                // debug($arResult);
            ?>


            <form method="post" action="<?=POST_FORM_ACTION_URI?>" name="regform" id="forms__registr" class="active">


                <div class="form__inner">

                    <?
                        // debug($arResult);/
                    ?>



                     <?
                    if (count($arResult["ERRORS"]) > 0):
                        print_r($arResult['RRORS']);
                        foreach ($arResult["ERRORS"] as $key => $error)
                            if (intval($key) == 0 && $key !== 0) 
                                $arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "&quot;".GetMessage("REGISTER_FIELD_".$key)."&quot;", $error);

                        ShowError(implode("<br />", $arResult["ERRORS"]));

                    elseif($arResult["USE_EMAIL_CONFIRMATION"] === "Y"):
                    ?>
                    <p><?echo GetMessage("REGISTER_EMAIL_WILL_BE_SENT")?></p>


                    <?endif?>

                    <!-- <input type="text" placeholder="0000000000" name="UF_KPP" value="<?= $arResult['VALUES']['UF_KPP'] ?>"> -->


                    <div class="form__sub-tit">
                        <div>

                            <?

                            // debug($arResult['VALUES']);

                            ?>

                            <input type="radio" <? if($arResult['VALUES']['UF_SELECT_FORM'] == "0" || empty($arResult['VALUES']['UF_SELECT_FORM'])) :?> checked <? endif;?> data-val='chastnoe' value="0" name="UF_SELECT_FORM">
                            <div>Частное лицо</div>
                        </div>
                        <div>
                            <input type="radio" data-val='eridicheskoe' <? if($arResult['VALUES']['UF_SELECT_FORM'] == "1"):?> checked <? endif;?> value="1" name="UF_SELECT_FORM">
                            <div>Юридическое лицо</div>
                        </div>
                    </div>


                    <div id="chastnoe" class="form__main-innner <? if($arResult['VALUES']['UF_SELECT_FORM'] == "0"):?> active <? elseif(empty($arResult['VALUES']['UF_SELECT_FORM'])):?> active  <? endif;?>   ">

                        <div class="form__label mt30">
                            <div>Представьтесь</div>
                            <input type="text" name="REGISTER[NAME]" placeholder="Иван Караченский" value="<?= $arResult['VALUES']['NAME'] ?>">
                        </div>

                         <div class="form__label mt30">
                            <div>Логин(для авторизации)</div>
                            <input type="text" name="REGISTER[LOGIN]" placeholder="Иван Караченский" value="<?= $arResult['VALUES']['LOGIN'] ?>">
                        </div>

                    	

                        <div class="form__label">
                            <div>Укажите ваш контактный номер</div>
                            <input type="text" name="REGISTER[PERSONAL_MOBILE]" placeholder="+7 900 123 90 00" value="<?= $arResult['VALUES']['PERSONAL_MOBILE'] ?>">
                        </div>


                        <div class="form__label">
                            <div>Электронная почта</div>
                            <input type="text" name="REGISTER[EMAIL]" placeholder="youmail@mail.ru" value="<?= $arResult['VALUES']['EMAIL'] ?>">
                        </div>


                        <div class="form__label">
                            <div>Пароль</div>
                            <input type="password" name="REGISTER[PASSWORD]" placeholder="*******" value="<?= $arResult['VALUES']['PASSWORD'] ?>">
                        </div>


                        <div class="form__label">
                            <div>Повторите пароль</div>
                            <input type="password" name="REGISTER[CONFIRM_PASSWORD]" placeholder="*******" value="<?= $arResult['VALUES']['CONFIRM_PASSWORD'] ?>">
                        </div>

                        <input type="hidden" name="register_submit_button" value="Регистрация">

                         


                        <!-- <div class="form__label">
                            <div class="form__pass">
                                <div>пароль</div>
                                <a href="" class="dashed">Забыли пароль ?</a>
                            </div>
                            <input type="text" placeholder="*****">
                        </div> -->
                        <div class="soglasie-na-obrabotky df">
                            <!-- registr_form -->

                            <?

                            // debug($arResult['UF_SOGLASIE_FORM']);

                            ?>
                           
                            <!-- UF_SOGLASIE_FORM -->
                            <input class="checkbox_iphone" id="regist__sogl" required="required" value="1" <?// if($arResult['VALUES']['UF_SOGLASIE_FORM'] == 1): ?> <? //endif;?> name="UF_SOGLASIE_FORM" type="checkbox">
                            <label for="regist__sogl"></label>
                            <div class="sogl__text">Согласие на обработку <a class="blue" href="/soglasie/" target="_blank">персональных данных</a></div>
                        </div>
                        <div class="btn__check">
                            <button class="btn bg-red" type="submit">Регистрация</button>
                            <!-- <div class="g-recaptcha" data-sitekey="6LdCCyAcAAAAAIaRfyjq4WUnhmTlkjf0Ik-wkyPm"></div> -->
                        </div>
                    </div>

                </form>

                <form method="post" action="<?=POST_FORM_ACTION_URI?>" name="regform" id="eridicheskoe" class="form__main-innner  <? if($arResult['VALUES']['UF_SELECT_FORM'] == "1"):?> active<? endif;?>">

                      <input class="hidden" type="hidden" data-val='eridicheskoe' checked value="1" name="UF_SELECT_FORM">





                       <?
                    if (count($arResult["ERRORS"]) > 500):
                        print_r($arResult['RRORS']);
                        foreach ($arResult["ERRORS"] as $key => $error)
                            if (intval($key) == 0 && $key !== 0) 
                                $arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "&quot;".GetMessage("REGISTER_FIELD_".$key)."&quot;", $error);

                        ShowError(implode("<br />", $arResult["ERRORS"]));

                    elseif($arResult["USE_EMAIL_CONFIRMATION"] === "Y"):
                    ?>
                    <p><?echo GetMessage("REGISTER_EMAIL_WILL_BE_SENT")?></p>


                    <?endif?>





                	
                    <!-- <div id="eridicheskoe" class="form__main-innner"> -->
                        <div class="form__label">
                            <div>Наименование компаннии</div>
                            <div class="df">
                                <select class="custom__select select92"  name="UF_SELECT_IP" value="<?= $arResult['VALUES']['UF_SELECT_IP'] ?>" >
                                    <option value="IP">ИП</option>
                                    <option value="ooo">ООО</option>
                                </select>
                                <input type="text" placeholder="Наименование" name="REGISTER[WORK_COMPANY]" value="<?= $arResult['VALUES']['WORK_COMPANY'] ?>">
                            </div>
                        </div>

                        <div class="form__label mt30">
                            <div>Представьтесь</div>
                            <input type="text" name="REGISTER[NAME]" placeholder="Иван Караченский" value="<?= $arResult['VALUES']['NAME'] ?>">
                        </div>

                         <div class="form__label mt30">
                            <div>Логин(для авторизации)</div>
                            <input type="text" name="REGISTER[LOGIN]" placeholder="Иван Караченский" value="<?= $arResult['VALUES']['LOGIN'] ?>">
                        </div>


                        <div class="form__label">
                            <div>ИНН</div>
                            <input type="text" placeholder="0000000000" name="UF_INN" value="<?= $arResult['VALUES']['UF_INN'] ?>">
                        </div>
                        <div class="form__label">
                            <div>КПП</div>
                            <input type="text" placeholder="0000000000" name="UF_KPP" value="<?= $arResult['VALUES']['UF_KPP'] ?>">
                        </div>
                        <div class="form__label">
                            <div>Телефон компании</div>
                            <input type="text" placeholder="+7 900 123 90 00" name="REGISTER[PERSONAL_MOBILE]" value="<?= $arResult['VALUES']['PERSONAL_MOBILE'] ?>">
                        </div>

                        <div class="form__label">
                            <div>Электронная почта</div>
                            <input type="text" name="REGISTER[EMAIL]" placeholder="youmail@mail.ru" value="<?= $arResult['VALUES']['EMAIL'] ?>">
                        </div>


                        <div class="form__label">
                            <div>Пароль</div>
                            <input type="password" name="REGISTER[PASSWORD]" placeholder="*******" value="<?= $arResult['VALUES']['PASSWORD'] ?>">
                        </div>


                        <div class="form__label">
                            <div>Повторите пароль</div>
                            <input type="password" name="REGISTER[CONFIRM_PASSWORD]" placeholder="*******" value="<?= $arResult['VALUES']['CONFIRM_PASSWORD'] ?>">
                        </div>

                        <div class="form__label">
                            <div>Юридический адрес</div>
                            <div class="df">
                                <input type="text" placeholder="Индекс" name="REGISTER[PERSONAL_ZIP]" value="<?= $arResult['VALUES']['PERSONAL_ZIP'] ?>">
                                <select class="custom__select select260" name="REGISTER[PERSONAL_CITY]"  value="<?= $arResult['VALUES']['PERSONAL_CITY'] ?>">
                                    <option value="">Город</option>
                                    <option value="Набережные Челны">Набережные Челны</option>
                                    <option value="Елабуга">Елабуга</option>
                                    <? if($arResult['VALUES']['PERSONAL_CITY']):?>

                                        <option value="<?= $arResult['VALUES']['PERSONAL_CITY'] ?>" selected><?= $arResult['VALUES']['PERSONAL_CITY'] ?></option>

                                    <? endif;?>
                                </select>
                            </div>
                        </div>
                        <div class="form__label">
                            <input type="text" placeholder="Улица, корпус, офис" name="REGISTER[PERSONAL_STATE]" value="<?= $arResult['VALUES']['PERSONAL_STATE'] ?>">
                        </div>

                        <!-- <input type="hidden" name="register_submit_button" value="Регистрация"> -->


                        <!-- <div class="form__label">
                            <div class="form__pass">
                                <div>пароль</div>
                                <a href="" class="dashed">Забыли пароль ?</a>
                            </div>
                            <input type="text" placeholder="*****">
                        </div> -->
                        <div class="soglasie-na-obrabotky df">
                            <input class="checkbox_iphone" required="required" id="regist__sogl1"  value="1" <? if($arResult['VALUES']['UF_SOGLASIE_FORM'] == 1): ?> checked <? endif;?> name="UF_SOGLASIE_FORM" type="checkbox">
                            <label for="regist__sogl1"></label>
                            <div class="sogl__text">Согласие на обработку <a class="blue" href="/soglasie/">персональных данных</a></div>
                        </div>

  						<input type="hidden" name="register_submit_button" value="Регистрация">

                        <button class="btn bg-red" type="submit">Регистрация</button>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>





<? endif;?>



<?if($USER->IsAuthorized()):?>




    <div class="container-wrapper">

  <div class="container">
        <div class="forms">

            <div method="post" action="<?=POST_FORM_ACTION_URI?>" name="regform" id="forms__registr" class="active">


                <div class="form__inner">
                    <div> <?echo GetMessage("MAIN_REGISTER_AUTH")?></div>
                <div>


               <a style="    display: block;
    width: max-content;" href="/" class="btn bg-red " >Хорошо !</a>

            </div>
        </div>
    </div>
</div>






<style>
    .container-wrapper {
        height: 100vh !important;
    }
    .form__inner {
    width: inherit;
    }
</style>










<?endif;?>


<style>
.container-wrapper {

     position: absolute;
        width: 100%;
        height: initial;
        top: 0;
        /* margin: 0 auto; */
        background: #465B90;
        z-index: 1000;
          /*overflow: scroll;*/
}
/*forms .active {
    width: 100%;
}*/
/*body {
    background: #465B90;
    position: absolute;
    height: 100%;
}*/



</style>




<div style="display: none;" class=" bx-auth-reg">

<?if($USER->IsAuthorized()):?>

<p><?echo GetMessage("MAIN_REGISTER_AUTH")?></p>

<?else:?>
<?
if (count($arResult["ERRORS"]) > 0):
	foreach ($arResult["ERRORS"] as $key => $error)
		if (intval($key) == 0 && $key !== 0) 
			$arResult["ERRORS"][$key] = str_replace("#FIELD_NAME#", "&quot;".GetMessage("REGISTER_FIELD_".$key)."&quot;", $error);

	ShowError(implode("<br />", $arResult["ERRORS"]));

elseif($arResult["USE_EMAIL_CONFIRMATION"] === "Y"):
?>
<p><?echo GetMessage("REGISTER_EMAIL_WILL_BE_SENT")?></p>
<?endif?>

<?if($arResult["SHOW_SMS_FIELD"] == true):?>

<form method="post" action="<?=POST_FORM_ACTION_URI?>" name="regform">
<?
if($arResult["BACKURL"] <> ''):
?>
	<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
<?
endif;
?>
<input type="hidden" name="SIGNED_DATA" value="<?=htmlspecialcharsbx($arResult["SIGNED_DATA"])?>" />
<table>
	<tbody>
		<tr>
			<td><?echo GetMessage("main_register_sms")?><span class="starrequired">*</span></td>
			<td><input size="30" type="text" name="SMS_CODE" value="<?=htmlspecialcharsbx($arResult["SMS_CODE"])?>" autocomplete="off" /></td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<td></td>
			<td><input type="submit" name="code_submit_button" value="<?echo GetMessage("main_register_sms_send")?>" /></td>
		</tr>
	</tfoot>
</table>
</form>

<script>
new BX.PhoneAuth({
	containerId: 'bx_register_resend',
	errorContainerId: 'bx_register_error',
	interval: <?=$arResult["PHONE_CODE_RESEND_INTERVAL"]?>,
	data:
		<?=CUtil::PhpToJSObject([
			'signedData' => $arResult["SIGNED_DATA"],
		])?>,
	onError:
		function(response)
		{
			var errorDiv = BX('bx_register_error');
			var errorNode = BX.findChildByClassName(errorDiv, 'errortext');
			errorNode.innerHTML = '';
			for(var i = 0; i < response.errors.length; i++)
			{
				errorNode.innerHTML = errorNode.innerHTML + BX.util.htmlspecialchars(response.errors[i].message) + '<br>';
			}
			errorDiv.style.display = '';
		}
});
</script>

<div id="bx_register_error" style="display:none"><?ShowError("error")?></div>

<div id="bx_register_resend"></div>

<?else:?>

<form method="post" action="<?=POST_FORM_ACTION_URI?>" name="regform" enctype="multipart/form-data">
<?
if($arResult["BACKURL"] <> ''):
?>
	<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
<?
endif;
?>

<table>
	<thead>
		<tr>
			<td colspan="2"><b><?=GetMessage("AUTH_REGISTER")?></b></td>
		</tr>
	</thead>
	<tbody>
<?foreach ($arResult["SHOW_FIELDS"] as $FIELD):?>
	<?if($FIELD == "AUTO_TIME_ZONE" && $arResult["TIME_ZONE_ENABLED"] == true):?>
		<tr>
			<td><?echo GetMessage("main_profile_time_zones_auto")?><?if ($arResult["REQUIRED_FIELDS_FLAGS"][$FIELD] == "Y"):?><span class="starrequired">*</span><?endif?></td>
			<td>
				<select name="REGISTER[AUTO_TIME_ZONE]" onchange="this.form.elements['REGISTER[TIME_ZONE]'].disabled=(this.value != 'N')">
					<option value=""><?echo GetMessage("main_profile_time_zones_auto_def")?></option>
					<option value="Y"<?=$arResult["VALUES"][$FIELD] == "Y" ? " selected=\"selected\"" : ""?>><?echo GetMessage("main_profile_time_zones_auto_yes")?></option>
					<option value="N"<?=$arResult["VALUES"][$FIELD] == "N" ? " selected=\"selected\"" : ""?>><?echo GetMessage("main_profile_time_zones_auto_no")?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td><?echo GetMessage("main_profile_time_zones_zones")?></td>
			<td>
				<select name="REGISTER[TIME_ZONE]"<?if(!isset($_REQUEST["REGISTER"]["TIME_ZONE"])) echo 'disabled="disabled"'?>>
		<?foreach($arResult["TIME_ZONE_LIST"] as $tz=>$tz_name):?>
					<option value="<?=htmlspecialcharsbx($tz)?>"<?=$arResult["VALUES"]["TIME_ZONE"] == $tz ? " selected=\"selected\"" : ""?>><?=htmlspecialcharsbx($tz_name)?></option>
		<?endforeach?>
				</select>
			</td>
		</tr>
	<?else:?>
		<tr>
			<td><?=GetMessage("REGISTER_FIELD_".$FIELD)?>:<?if ($arResult["REQUIRED_FIELDS_FLAGS"][$FIELD] == "Y"):?><span class="starrequired">*</span><?endif?></td>
			<td><?
	switch ($FIELD)
	{
		case "PASSWORD":
			?><input size="30" type="password" name="REGISTER[<?=$FIELD?>]" value="<?=$arResult["VALUES"][$FIELD]?>" autocomplete="off" class="bx-auth-input" />
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
<?
			break;
		case "CONFIRM_PASSWORD":
			?><input size="30" type="password" name="REGISTER[<?=$FIELD?>]" value="<?=$arResult["VALUES"][$FIELD]?>" autocomplete="off" /><?
			break;

		case "PERSONAL_GENDER":
			?><select name="REGISTER[<?=$FIELD?>]">
				<option value=""><?=GetMessage("USER_DONT_KNOW")?></option>
				<option value="M"<?=$arResult["VALUES"][$FIELD] == "M" ? " selected=\"selected\"" : ""?>><?=GetMessage("USER_MALE")?></option>
				<option value="F"<?=$arResult["VALUES"][$FIELD] == "F" ? " selected=\"selected\"" : ""?>><?=GetMessage("USER_FEMALE")?></option>
			</select><?
			break;

		case "PERSONAL_COUNTRY":
		case "WORK_COUNTRY":
			?><select name="REGISTER[<?=$FIELD?>]"><?
			foreach ($arResult["COUNTRIES"]["reference_id"] as $key => $value)
			{
				?><option value="<?=$value?>"<?if ($value == $arResult["VALUES"][$FIELD]):?> selected="selected"<?endif?>><?=$arResult["COUNTRIES"]["reference"][$key]?></option>
			<?
			}
			?></select><?
			break;

		case "PERSONAL_PHOTO":
		case "WORK_LOGO":
			?><input size="30" type="file" name="REGISTER_FILES_<?=$FIELD?>" /><?
			break;

		case "PERSONAL_NOTES":
		case "WORK_NOTES":
			?><textarea cols="30" rows="5" name="REGISTER[<?=$FIELD?>]"><?=$arResult["VALUES"][$FIELD]?></textarea><?
			break;
		default:
			if ($FIELD == "PERSONAL_BIRTHDAY"):?><small><?=$arResult["DATE_FORMAT"]?></small><br /><?endif;
			?><input size="30" type="text" name="REGISTER[<?=$FIELD?>]" value="<?=$arResult["VALUES"][$FIELD]?>" /><?
				if ($FIELD == "PERSONAL_BIRTHDAY")
					$APPLICATION->IncludeComponent(
						'bitrix:main.calendar',
						'',
						array(
							'SHOW_INPUT' => 'N',
							'FORM_NAME' => 'regform',
							'INPUT_NAME' => 'REGISTER[PERSONAL_BIRTHDAY]',
							'SHOW_TIME' => 'N'
						),
						null,
						array("HIDE_ICONS"=>"Y")
					);
				?><?
	}?></td>
		</tr>
	<?endif?>
<?endforeach?>
<?// ********************* User properties ***************************************************?>
<?if($arResult["USER_PROPERTIES"]["SHOW"] == "Y"):?>
	<tr><td colspan="2"><?=trim($arParams["USER_PROPERTY_NAME"]) <> '' ? $arParams["USER_PROPERTY_NAME"] : GetMessage("USER_TYPE_EDIT_TAB")?></td></tr>
	<?foreach ($arResult["USER_PROPERTIES"]["DATA"] as $FIELD_NAME => $arUserField):?>
	<tr><td><?=$arUserField["EDIT_FORM_LABEL"]?>:<?if ($arUserField["MANDATORY"]=="Y"):?><span class="starrequired">*</span><?endif;?></td><td>
			<?$APPLICATION->IncludeComponent(
				"bitrix:system.field.edit",
				$arUserField["USER_TYPE"]["USER_TYPE_ID"],
				array("bVarsFromForm" => $arResult["bVarsFromForm"], "arUserField" => $arUserField, "form_name" => "regform"), null, array("HIDE_ICONS"=>"Y"));?></td></tr>
	<?endforeach;?>
<?endif;?>
<?// ******************** /User properties ***************************************************?>
<?
/* CAPTCHA */
if ($arResult["USE_CAPTCHA"] == "Y")
{
	?>
		<tr>
			<td colspan="2"><b><?=GetMessage("REGISTER_CAPTCHA_TITLE")?></b></td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="hidden" name="captcha_sid" value="<?=$arResult["CAPTCHA_CODE"]?>" />
				<img src="/bitrix/tools/captcha.php?captcha_sid=<?=$arResult["CAPTCHA_CODE"]?>" width="180" height="40" alt="CAPTCHA" />
			</td>
		</tr>
		<tr>
			<td><?=GetMessage("REGISTER_CAPTCHA_PROMT")?>:<span class="starrequired">*</span></td>
			<td><input type="text" name="captcha_word" maxlength="50" value="" autocomplete="off" /></td>
		</tr>
	<?
}
/* !CAPTCHA */
?>
	</tbody>
	<tfoot>
		<tr>
			<td></td>
			<td><input type="submit" name="register_submit_button" value="<?=GetMessage("AUTH_REGISTER")?>" /></td>
		</tr>
	</tfoot>
</table>
</form>

<p><?echo $arResult["GROUP_POLICY"]["PASSWORD_REQUIREMENTS"];?></p>

<?endif //$arResult["SHOW_SMS_FIELD"] == true ?>

<p><span class="starrequired">*</span><?=GetMessage("AUTH_REQ")?></p>

<?endif?>
</div> 