<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="basket-modern">
    <h2>Корзина</h2>
    
    <?php if (empty($arResult['ITEMS'])): ?>
        <div class="basket-empty">
            <p><svg class="icon"><use href="#icon-cart"></use></svg> Ваша корзина пуста</p>
            <a href="/catalog/" class="btn btn--primary">Перейти в каталог</a>
        </div>
    <?php else: ?>
        <div class="basket-table-wrapper">
            <table class="basket-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Цена</th>
                        <th>Количество</th>
                        <th>Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arResult['ITEMS'] as $item): ?>
                    <tr class="basket-item" data-id="<?= $item['ID'] ?>">
                        <td>
                            <div class="basket-product">
                                <img src="<?= $item['PREVIEW_PICTURE_SRC'] ?: '/no-photo.png' ?>" alt="">
                                <div>
                                    <a href="<?= $item['DETAIL_PAGE_URL'] ?>"><?= $item['NAME'] ?></a>
                                </div>
                            </div>
                        </td>
                        <td class="basket-price"><?= number_format($item['PRICE'], 0, ',', ' ') ?> ₽</td>
                        <td>
                            <div class="quantity-control">
                                <button class="qty-minus">−</button>
                                <input type="number" value="<?= $item['QUANTITY'] ?>" min="1" class="qty-input">
                                <button class="qty-plus">+</button>
                            </div>
                        </td>
                        <td class="basket-sum"><?= number_format($item['SUM'], 0, ',', ' ') ?> ₽</td>
                        <td>
                            <button class="basket-delete" title="Удалить"><svg class="icon"><use href="#icon-x"></use></svg></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="basket-footer">
            <div class="basket-coupon">
                <input type="text" placeholder="Промокод">
                <button class="btn btn--outline">Применить</button>
            </div>
            <div class="basket-total">
                <span>Итого: <strong><?= number_format($arResult['allSum'], 0, ',', ' ') ?> ₽</strong></span>
                <a href="/personal/order/" class="btn btn--primary btn--lg">Оформить заказ</a>
            </div>
        </div>
    <?php endif; ?>
</div>