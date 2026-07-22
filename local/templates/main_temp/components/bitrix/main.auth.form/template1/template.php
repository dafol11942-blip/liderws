<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)
{
	die();
}

use \Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

\Bitrix\Main\Page\Asset::getInstance()->addCss(
	'/bitrix/css/main/system.auth/flat/style.css'
);

if ($arResult['AUTHORIZED'])
{	
	LocalRedirect("/personal/");
	echo Loc::getMessage('MAIN_AUTH_FORM_SUCCESS');
	return;
}
?>



<div class="container-wrapper">
  <div class="container">
        <div class="forms">
            <div class="forms__tit">
                <!-- Авторизация  -->
                <a href="/"data-val="forms__login" class="forms__login active ft">Вход</a>
                <a href="registration.php" data-val="forms__registr" class="forms__registr ft">Регистрация</a>
                <!-- <button id="btn">modal</button> -->
            </div>
            <!-- <form id="forms__login" class="active" action=""> -->

            <form class="active" id="forms__login" name="<?= $arResult['FORM_ID'];?>" method="post" target="_top" action="<?= POST_FORM_ACTION_URI;?>">

				<input type="hidden" name="AUTH_FORM" value="Y" />
				<input type="hidden" name="TYPE" value="AUTH" />
				<?if ($arResult["BACKURL"] <> ''):?>
				<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
				<?endif?>
				<?foreach ($arResult["POST"] as $key => $value):?>
				<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
				<?endforeach?>

                <div class="form__inner">

                	<?if ($arResult['ERRORS']):?>
					<div class="alert alert-danger">
						<? foreach ($arResult['ERRORS'] as $error)
						{
							echo $error;
						}
						?>
					</div>
					<?endif;?>

                    <div class="form__label mt30">
                        <div>Логин</div>
                        <!-- <input name="USER_LOGIN" type="text" placeholder="master-trud"> -->
                        <input placeholder="master-trud" type="text" name="<?= $arResult['FIELDS']['login'];?>" maxlength="255" value="<?= \htmlspecialcharsbx($arResult['LAST_LOGIN']);?>" />
                    </div>
                    <div class="form__label">
                        <div class="form__pass">
                            <div>пароль</div>
                            <a href="/auth/?forgot_password=yes" class="dashed">Забыли пароль ?</a>
                        </div>
                        	<!-- <input name="USER_PASSWORD" type="text" placeholder="*****"> -->
                        <?if ($arResult['SECURE_AUTH']):?>
							<div class="bx-authform-psw-protected" id="bx_auth_secure" style="display:none">
								<div class="bx-authform-psw-protected-desc"><span></span>
									<?= Loc::getMessage('MAIN_AUTH_FORM_SECURE_NOTE');?>
								</div>
							</div>
							<script type="text/javascript">
								document.getElementById('bx_auth_secure').style.display = '';
							</script>
						<?endif?>
							<input placeholder="*****" type="password" name="<?= $arResult['FIELDS']['password'];?>" maxlength="255" autocomplete="off" />
                    </div>

                    <!-- <input type="checkbox" id="USER_REMEMBER" name="USER_REMEMBER" value=""> -->
                    <input type="hidden" name="AUTH_ACTION" value="Войти">
                    <?

                    // debug($arResult);

                    ?>
                    <div class="soglasie-na-obrabotky df">
                        <input class="checkbox_iphone" id="login__sogl" name="UF_SOGLASIE_FORM" type="checkbox">
                        <label for="login__sogl"></label>
                        <div class="sogl__text">Согласие на обработку <a class="blue" href="personal.html">персональных данных</a></div>
                    </div>
                    <button class="btn bg-red" type="submit">Войти</button>
                </div>
            </form>
           
        </div>
    </div>
</div>


<style>
.container-wrapper {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        /* margin: 0 auto; */
        background: #465B90;
        z-index: 1000;
    }
}
</style>















<script type="text/javascript">
	<?if ($arResult['LAST_LOGIN'] != ''):?>
	try{document.<?= $arResult['FORM_ID'];?>.USER_PASSWORD.focus();}catch(e){}
	<?else:?>
	try{document.<?= $arResult['FORM_ID'];?>.USER_LOGIN.focus();}catch(e){}
	<?endif?>
</script>