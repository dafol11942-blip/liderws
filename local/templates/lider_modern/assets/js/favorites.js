// Избранное — кнопка-сердечко на карточке каталога, странице товара и в офферах поиска.
// Бэкенд: /local/ajax/favorites.php (action=toggle_catalog|toggle_supplier).
// Активное состояние переключается классами is-active (цвет кнопки) + icon--fill (заливка
// сердечка — .icon--fill уже есть в style.css, тот же приём, что и остальные иконки на сайте).

function favIcon(btn) {
    return btn.querySelector('.icon');
}

function setFavActive(btn, active) {
    btn.classList.toggle('is-active', active);
    var icon = favIcon(btn);
    if (icon) icon.classList.toggle('icon--fill', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
}

function bumpFavBadge() {
    var icon = document.getElementById('favIcon');
    if (!icon) return;
    icon.classList.remove('header__icon--bump');
    void icon.offsetWidth; // перезапуск CSS-анимации при повторном клике подряд
    icon.classList.add('header__icon--bump');
}

window.updateFavBadge = function (qty) {
    var badge = document.getElementById('favBadge');
    if (!badge) return;
    qty = Math.max(0, parseInt(qty, 10) || 0);
    badge.textContent = qty;
    badge.style.display = qty > 0 ? '' : 'none';
    bumpFavBadge();
};

function goToAuth() {
    window.location.href = '/auth/?backurl=' + encodeURIComponent(window.location.pathname + window.location.search);
}

window.toggleFavorite = function (btn) {
    if (btn.disabled) return;
    var type = btn.getAttribute('data-fav-type');
    var payload = { action: type === 'supplier' ? 'toggle_supplier' : 'toggle_catalog' };

    if (type === 'supplier') {
        payload.article     = btn.getAttribute('data-article') || '';
        payload.brand       = btn.getAttribute('data-brand') || '';
        payload.supplier    = btn.getAttribute('data-supplier') || '';
        payload.task        = btn.getAttribute('data-task') || (window.TASK_ID || '');
        payload.offer_token = btn.getAttribute('data-token') || '';
    } else {
        payload.product_id = parseInt(btn.getAttribute('data-fav-id'), 10) || 0;
    }

    btn.disabled = true;
    fetch('/local/ajax/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (data) {
        btn.disabled = false;
        if (data.status === 'error' && data.code === 'auth_required') {
            goToAuth();
            return;
        }
        if (data.status !== 'ok') {
            if (window.showToast) window.showToast(data.message || 'Не удалось обновить избранное', 'warn');
            return;
        }
        setFavActive(btn, data.active);
        if (data.count !== undefined) window.updateFavBadge(data.count);
        // Хук для страницы /personal/favorites/ — там нужно убрать карточку/строку
        // целиком при снятии с избранного, а не просто погасить сердечко (как везде
        // ещё, где сама карточка не является записью избранного).
        if (window.onFavoriteToggled) window.onFavoriteToggled(btn, data.active);
    }).catch(function () {
        btn.disabled = false;
    });
};

// Карточка каталога и страница товара вызывают toggleFavorite(this) через инлайновый onclick
// (как и их кнопки "В корзину" — addToCart()/addToCartDetail()). /search/ рисует офферы через
// JS-строки и использует делегирование кликов (см. .actl-btn там же) — там toggleFavorite()
// вызывается из ЕГО собственного обработчика клика, отдельный делегированный слушатель здесь
// не нужен и задвоил бы срабатывание на страницах с инлайновым onclick.
