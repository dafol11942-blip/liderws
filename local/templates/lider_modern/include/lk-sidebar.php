<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$lkNavActive = $lkNavActive ?? '';
?>
<aside class="lk-sidebar">
    <div class="lk-user-block">
        <div class="lk-user-avatar">
            <?= mb_substr($USER->GetFullName() ?: $USER->GetLogin(), 0, 1) ?>
        </div>
        <div class="lk-user-name"><?= $USER->GetFullName() ?: $USER->GetLogin() ?></div>
    </div>
    <nav class="lk-nav">
        <a href="/personal/" class="<?= $lkNavActive === 'profile' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-user"></use></svg> Профиль</a>
        <a href="/personal/orders/" class="<?= $lkNavActive === 'orders' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-box"></use></svg> История заказов</a>
        <a href="/personal/favorites/" class="<?= $lkNavActive === 'favorites' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-star"></use></svg> Избранное</a>
        <a href="/personal/bonus/" class="<?= $lkNavActive === 'bonus' ? 'active' : '' ?>"><svg class="icon"><use href="#icon-gift"></use></svg> Бонусная программа</a>
        <a href="/?logout=yes" class="lk-nav--logout"><svg class="icon"><use href="#icon-logout"></use></svg> Выйти</a>
    </nav>
</aside>
