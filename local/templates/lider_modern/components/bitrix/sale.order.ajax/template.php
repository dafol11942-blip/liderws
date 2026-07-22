<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

CJSCore::Init(['fx', 'popup']);
?>

<div class="checkout-page" id="bx-soa-order">
    <h1 class="checkout-page__title">Оформление заказа</h1>

    <div class="checkout-layout">
        <!-- Левая колонка: форма -->
        <div class="checkout-form-col">
            <?php
            // Выводим блоки в нужном порядке
            $blockOrder = ['LOCATION', 'BUYER', 'DELIVERY', 'PAYMENT', 'COMMENT'];
            foreach ($blockOrder as $blockCode) {
                if (!empty($arResult[$blockCode])) {
                    include __DIR__ . '/blocks/' . strtolower($blockCode) . '.php';
                }
            }
            ?>
        </div>

        <!-- Правая колонка: состав заказа -->
        <div class="checkout-sidebar">
            <div class="checkout-summary" id="bx-soa-basket">
                <h3 class="checkout-summary__title">Ваш заказ</h3>

                <?php if (!empty($arResult['GRID']['ROWS'])): ?>
                    <div class="checkout-items">
                        <?php foreach ($arResult['GRID']['ROWS'] as $row): ?>
                        <div class="checkout-item">
                            <div class="checkout-item__img">
                                <?php if (!empty($row['PREVIEW_PICTURE_SRC'])): ?>
                                    <img src="<?= $row['PREVIEW_PICTURE_SRC'] ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="checkout-item__info">
                                <div class="checkout-item__name"><?= $row['NAME'] ?></div>
                                <div class="checkout-item__qty">×<?= $row['QUANTITY'] ?></div>
                            </div>
                            <div class="checkout-item__price"><?= $row['SUM_FORMATTED'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="checkout-summary__rows">
                    <div class="checkout-summary__row">
                        <span>Товары</span>
                        <span id="soa-basket-total"><?= $arResult['ORDER_PRICE_FORMATTED'] ?? '0 ₽' ?></span>
                    </div>
                    <div class="checkout-summary__row" id="delivery-price-block" style="display:none;">
                        <span>Доставка</span>
                        <span id="soa-delivery-price">0 ₽</span>
                    </div>
                </div>
                <div class="checkout-summary__total">
                    <span>Итого к оплате</span>
                    <span id="soa-grand-total"><?= $arResult['ORDER_PRICE_FORMATTED'] ?? '0 ₽' ?></span>
                </div>

                <button class="btn btn--primary btn--lg btn--block" onclick="submitOrder()">
                    Оформить заказ
                </button>
                <p class="checkout-agree">
                    Нажимая «Оформить заказ», вы соглашаетесь с <a href="/agreement/" target="_blank">условиями</a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.checkout-page { max-width: 1240px; margin: 0 auto; padding: 30px 20px; font-family: 'Inter', -apple-system, sans-serif; }
.checkout-page__title { font-size: 28px; font-weight: 700; margin-bottom: 30px; color: #1a1a1a; }
.checkout-layout { display: grid; grid-template-columns: 1fr 420px; gap: 30px; align-items: start; }
@media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }

/* Блоки формы */
.checkout-block {
    background: #fff; border: 1px solid #eee; border-radius: 12px;
    padding: 24px; margin-bottom: 16px;
}
.checkout-block__title { font-size: 18px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.checkout-block__num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; background: #E30613; color: #fff;
    border-radius: 50%; font-size: 13px; font-weight: 700;
}
.form-row { margin-bottom: 14px; }
.form-row label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 4px; }
.form-row input[type="text"],
.form-row input[type="tel"],
.form-row input[type="email"],
.form-row textarea,
.form-row select {
    width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
    font-size: 15px; font-family: inherit; transition: border-color 0.2s; box-sizing: border-box;
}
.form-row input:focus, .form-row textarea:focus, .form-row select:focus { outline: none; border-color: #E30613; }
.form-row textarea { resize: vertical; min-height: 80px; }

/* Доставка */
.delivery-options { display: flex; flex-direction: column; gap: 8px; }
.delivery-option {
    padding: 14px 16px; border: 2px solid #eee; border-radius: 10px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px;
}
.delivery-option:hover { border-color: #ccc; }
.delivery-option.active { border-color: #E30613; background: #fff5f5; }
.delivery-option__radio { width: 20px; height: 20px; border-radius: 50%; border: 2px solid #ddd; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.delivery-option.active .delivery-option__radio { border-color: #E30613; }
.delivery-option.active .delivery-option__radio::after { content: ''; width: 10px; height: 10px; border-radius: 50%; background: #E30613; }
.delivery-option__info { flex: 1; }
.delivery-option__name { font-weight: 600; font-size: 15px; }
.delivery-option__desc { font-size: 12px; color: #888; margin-top: 2px; }
.delivery-option__price { font-weight: 700; font-size: 15px; }

/* Оплата */
.payment-options { display: flex; flex-direction: column; gap: 8px; }
.payment-option {
    padding: 14px 16px; border: 2px solid #eee; border-radius: 10px;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px;
}
.payment-option:hover { border-color: #ccc; }
.payment-option.active { border-color: #E30613; background: #fff5f5; }
.payment-option__radio { width: 20px; height: 20px; border-radius: 50%; border: 2px solid #ddd; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.payment-option.active .payment-option__radio { border-color: #E30613; }
.payment-option.active .payment-option__radio::after { content: ''; width: 10px; height: 10px; border-radius: 50%; background: #E30613; }
.payment-option__name { font-weight: 600; font-size: 15px; }

/* Сайдбар */
.checkout-sidebar { position: sticky; top: 100px; }
.checkout-summary { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 24px; }
.checkout-summary__title { font-size: 18px; font-weight: 700; margin-bottom: 16px; }

.checkout-items { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; max-height: 300px; overflow-y: auto; }
.checkout-item { display: flex; gap: 12px; align-items: center; }
.checkout-item__img { width: 52px; height: 52px; border-radius: 6px; overflow: hidden; background: #f5f5f5; flex-shrink: 0; }
.checkout-item__img img { width: 100%; height: 100%; object-fit: contain; }
.checkout-item__info { flex: 1; min-width: 0; }
.checkout-item__name { font-size: 13px; font-weight: 500; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.checkout-item__qty { font-size: 12px; color: #999; margin-top: 2px; }
.checkout-item__price { font-weight: 700; font-size: 14px; flex-shrink: 0; }

.checkout-summary__rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.checkout-summary__row { display: flex; justify-content: space-between; font-size: 14px; color: #666; }
.checkout-summary__total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; padding-top: 14px; border-top: 1px solid #eee; margin-bottom: 18px; }

.checkout-agree { font-size: 12px; color: #999; text-align: center; margin-top: 12px; }
.checkout-agree a { color: #E30613; }

/* Buttons (same as cart) */
.btn { display: inline-flex; align-items: center; justify-content: center; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; font-family: inherit; }
.btn--primary { background: #E30613; color: #fff; }
.btn--primary:hover { background: #c20510; }
.btn--outline { background: #fff; color: #333; border: 1px solid #ddd; }
.btn--outline:hover { background: #f5f5f5; }
.btn--lg { padding: 14px 24px; font-size: 16px; }
.btn--block { display: flex; width: 100%; }

/* Скрываем стандартный тотал в левой колонке */
#bx-soa-total { display: none; }
</style>

<script>
BX.ready(function() {
    // Обработка кликов по доставке
    document.querySelectorAll('.delivery-option').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.delivery-option').forEach(function(o) { o.classList.remove('active'); });
            this.classList.add('active');
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            var priceEl = this.querySelector('.delivery-option__price');
            if (priceEl) {
                document.getElementById('soa-delivery-price').textContent = priceEl.textContent;
                document.getElementById('delivery-price-block').style.display = 'flex';
            }
        });
    });

    // Обработка кликов по оплате
    document.querySelectorAll('.payment-option').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.payment-option').forEach(function(o) { o.classList.remove('active'); });
            this.classList.add('active');
            var radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
});

function submitOrder() {
    var btn = document.querySelector('.checkout-summary .btn--primary');
    btn.textContent = 'Оформляем...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
    BX.Sale.OrderAjaxComponent.sendRequest();
}
</script>