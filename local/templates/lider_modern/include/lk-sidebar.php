<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$lkNavActive = $lkNavActive ?? '';
?>
<aside class="lk-sidebar">
    <div class="lk-user-block">
        <?php
        // Для аккаунтов, созданных автоматически по телефону (LOGIN вида
        // "tel_9161234567"), пока не заполнено ФИО, показываем отформатированный
        // номер вместо технического логина — require_phone_auth.php уже
        // гарантирует, что до /personal/* эта ситуация не доходит, но сюда
        // может попасть шаблон, вызванный из другого места без гварда.
        $lkLogin = $USER->GetLogin();
        $lkDisplayName = $USER->GetFullName() ?: (strpos($lkLogin, 'tel_') === 0 ? '+7 ' . substr($lkLogin, 4) : $lkLogin);
        ?>
        <div class="lk-user-avatar">
            <?= mb_substr($lkDisplayName, 0, 1) ?>
        </div>
        <div class="lk-user-name"><?= htmlspecialchars($lkDisplayName) ?></div>
    </div>
    <nav class="lk-nav">
        <a href="/personal/" class="<?= $lkNavActive === 'profile' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-user"></use></svg> Профиль</a>
        <a href="/personal/orders/" class="<?= $lkNavActive === 'orders' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-box"></use></svg> История заказов</a>
        <a href="/personal/favorites/" class="<?= $lkNavActive === 'favorites' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-star"></use></svg> Избранное</a>
        <a href="/personal/bonus/" class="<?= $lkNavActive === 'bonus' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-gift"></use></svg> Бонусная программа</a>
        <a href="/?logout=yes" class="lk-nav--logout"><svg class="icon"><use href="#icon-logout"></use></svg> Выйти</a>
    </nav>
</aside>
