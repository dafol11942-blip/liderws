document.addEventListener('DOMContentLoaded', () => {
    // +/- в корзине
    document.querySelectorAll('.qty-minus, .qty-plus').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.qty-input');
            let val = parseInt(input.value) || 1;
            if (this.classList.contains('qty-minus')) val = Math.max(1, val - 1);
            else val = val + 1;
            input.value = val;
            // AJAX-обновление корзины
            updateBasketItem(input.closest('.basket-item').dataset.id, val);
        });
    });
    
    // Удаление из корзины
    document.querySelectorAll('.basket-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.closest('.basket-item').dataset.id;
            BX.ajax.runAction('sale.basketitem.delete', { data: { id } }).then(() => location.reload());
        });
    });
});

function updateBasketItem(id, quantity) {
    BX.ajax.runAction('sale.basketitem.update', {
        data: { id, fields: { quantity } }
    }).then(() => location.reload());
}
// Выпадающее меню каталога (мобильная версия по клику)
(function() {
    var wrapper = document.querySelector('.catalog-dropdown-wrapper');
    var btn = document.getElementById('catalogBtn');
    var dropdown = document.getElementById('catalogDropdown');

    if (wrapper && btn && dropdown) {
        btn.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                wrapper.classList.toggle('open');
            }
            // На десктопе просто переходим по ссылке /catalog/
        });

        // Закрытие по клику вне меню
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });
    }
})();

// Кнопка «Наверх»
(function() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;

    function toggle() {
        if (window.scrollY > 400) btn.classList.add('is-visible');
        else btn.classList.remove('is-visible');
    }

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();

    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();

// Согласие на обработку cookie — показывается заново в каждой новой сессии
// браузера (sessionStorage, а не localStorage/cookie), закрывается по
// «Принять» или крестику.
(function() {
    var KEY = 'lider_cookie_consent_seen';
    var el = document.getElementById('cookieConsent');
    if (!el) return;

    var alreadySeen = false;
    try { alreadySeen = sessionStorage.getItem(KEY) === '1'; } catch (e) {}

    if (!alreadySeen) el.hidden = false;

    function dismiss() {
        el.hidden = true;
        try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    }

    var acceptBtn = document.getElementById('cookieConsentAccept');
    var closeBtn = document.getElementById('cookieConsentClose');
    if (acceptBtn) acceptBtn.addEventListener('click', dismiss);
    if (closeBtn) closeBtn.addEventListener('click', dismiss);
})();