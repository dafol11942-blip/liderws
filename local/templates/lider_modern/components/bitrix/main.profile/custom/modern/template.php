<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="checkout-modern">
    <!-- Прогресс-бар -->
    <div class="checkout-steps">
        <div class="checkout-step active" data-step="1">
            <span class="step-number">1</span> Контакты
        </div>
        <div class="checkout-step" data-step="2">
            <span class="step-number">2</span> Доставка
        </div>
        <div class="checkout-step" data-step="3">
            <span class="step-number">3</span> Оплата
        </div>
    </div>
    
    <form id="checkoutForm" class="checkout-form">
        <!-- Шаг 1: Контакты -->
        <div class="checkout-tab active" data-tab="1">
            <div class="form-group">
                <label>Имя <span class="required">*</span></label>
                <input type="text" name="NAME" required>
            </div>
            <div class="form-group">
                <label>Телефон <span class="required">*</span></label>
                <input type="tel" name="PHONE" required placeholder="+7 (___) ___-__-__">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="EMAIL">
            </div>
            <button type="button" class="btn btn--primary next-step">Далее →</button>
        </div>
        
        <!-- Шаг 2: Доставка -->
        <div class="checkout-tab" data-tab="2">
            <div class="delivery-options">
                <label class="radio-card">
                    <input type="radio" name="DELIVERY" value="pickup" checked>
                    <span class="radio-card__title"><svg class="icon"><use href="#icon-store"></use></svg> Самовывоз</span>
                    <span class="radio-card__desc">пр-т Нефтяников, 4 — бесплатно</span>
                </label>
                <label class="radio-card">
                    <input type="radio" name="DELIVERY" value="courier">
                    <span class="radio-card__title"><svg class="icon"><use href="#icon-car"></use></svg> Курьер по Елабуге</span>
                    <span class="radio-card__desc">от 300 ₽, 1-2 дня</span>
                </label>
                <label class="radio-card">
                    <input type="radio" name="DELIVERY" value="sdek">
                    <span class="radio-card__title"><svg class="icon"><use href="#icon-box"></use></svg> СДЭК / Почта</span>
                    <span class="radio-card__desc">от 500 ₽, рассчитывается индивидуально</span>
                </label>
            </div>
            <div class="step-buttons">
                <button type="button" class="btn btn--outline prev-step">← Назад</button>
                <button type="button" class="btn btn--primary next-step">Далее →</button>
            </div>
        </div>
        
        <!-- Шаг 3: Оплата + Комментарий -->
        <div class="checkout-tab" data-tab="3">
            <div class="payment-options">
                <label class="radio-card">
                    <input type="radio" name="PAYMENT" value="cash" checked>
                    <span><svg class="icon"><use href="#icon-banknote"></use></svg> При получении</span>
                </label>
                <label class="radio-card">
                    <input type="radio" name="PAYMENT" value="online">
                    <span><svg class="icon"><use href="#icon-card"></use></svg> Онлайн (картой)</span>
                </label>
            </div>
            <div class="form-group">
                <label>Комментарий к заказу</label>
                <textarea name="COMMENT" rows="3" placeholder="Любые пожелания..."></textarea>
            </div>
            <div class="step-buttons">
                <button type="button" class="btn btn--outline prev-step">← Назад</button>
                <button type="submit" class="btn btn--success btn--lg"><svg class="icon"><use href="#icon-check-circle"></use></svg> Подтвердить заказ</button>
            </div>
        </div>
    </form>
</div>

<script>
// Переключение шагов
document.querySelectorAll('.next-step').forEach(btn => {
    btn.addEventListener('click', function() {
        const currentTab = this.closest('.checkout-tab');
        const nextTabNum = parseInt(currentTab.dataset.tab) + 1;
        currentTab.classList.remove('active');
        document.querySelector(`.checkout-tab[data-tab="${nextTabNum}"]`).classList.add('active');
        document.querySelector(`.checkout-step[data-step="${nextTabNum}"]`).classList.add('active');
    });
});
document.querySelectorAll('.prev-step').forEach(btn => {
    btn.addEventListener('click', function() {
        const currentTab = this.closest('.checkout-tab');
        const prevTabNum = parseInt(currentTab.dataset.tab) - 1;
        currentTab.classList.remove('active');
        document.querySelector(`.checkout-tab[data-tab="${prevTabNum}"]`).classList.add('active');
        document.querySelector(`.checkout-step[data-step="${prevTabNum + 1}"]`).classList.remove('active');
    });
});
</script>