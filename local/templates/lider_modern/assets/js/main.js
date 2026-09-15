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

        // Переключение активной категории во флайауте: наведение (десктоп) и
        // первый тап (сенсорные экраны — тап переключает панель, повторный
        // тап по уже активному пункту идёт по ссылке как обычно).
        var activatePanel = function(item) {
            dropdown.querySelectorAll('.catalog-dropdown__nav-item').forEach(function(el) {
                el.classList.remove('active');
            });
            dropdown.querySelectorAll('.catalog-dropdown__panel').forEach(function(el) {
                el.classList.remove('active');
            });
            item.classList.add('active');
            var panel = document.getElementById(item.dataset.panel);
            if (panel) panel.classList.add('active');
        };
        dropdown.querySelectorAll('.catalog-dropdown__nav-item').forEach(function(item) {
            item.addEventListener('mouseenter', function() { activatePanel(item); });
            item.addEventListener('click', function(e) {
                if (!item.classList.contains('active')) {
                    e.preventDefault();
                    activatePanel(item);
                }
            });
        });
    }
})();

// Подменю разделов шапки (Масла/Фильтры/Тормозные колодки/Автосервис) —
// на десктопе раскрываются по :hover (CSS), но на тач-экранах первый тап
// по ссылке должен раскрыть список подпунктов, а не сразу уводить со
// страницы (второй тап по уже открытому пункту идёт по ссылке как обычно).
(function() {
    var wrappers = document.querySelectorAll('.nav-dropdown-wrapper');
    if (!wrappers.length) return;

    wrappers.forEach(function(wrapper) {
        var link = wrapper.querySelector(':scope > a');
        if (!link) return;
        link.addEventListener('click', function(e) {
            if (window.innerWidth > 1024) return;
            if (!wrapper.classList.contains('open')) {
                e.preventDefault();
                wrappers.forEach(function(w) {
                    if (w !== wrapper) w.classList.remove('open');
                });
                wrapper.classList.add('open');
            }
        });
    });

    document.addEventListener('click', function(e) {
        wrappers.forEach(function(w) {
            if (!w.contains(e.target)) w.classList.remove('open');
        });
    });
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

// Мгновенный AJAX-фильтр каталога: чекбоксы, слайдер цены, сортировка,
// пагинация — без перезагрузки страницы. #catalogFilterPanel/#catalogMain
// не пересоздаются между запросами, меняется только их innerHTML, поэтому
// обработчики вешаются один раз на сами эти контейнеры.
(function() {
    var filterPanel = document.getElementById('catalogFilterPanel');
    var mainPanel = document.getElementById('catalogMain');
    if (!filterPanel || !mainPanel) return;

    // Мобильный/планшетный доступ к фильтру (≤1024px, см. style.css):
    // сайдбар прячется за экран и выезжает панелью по кнопке.
    var layout = document.querySelector('.catalog-layout');
    var filterToggleBtn = document.getElementById('catalogFilterToggle');
    var filterBackdrop = document.getElementById('catalogFilterBackdrop');
    function openMobileFilter() {
        if (layout) layout.classList.add('filter-open');
        document.body.classList.add('catalog-filter-locked');
    }
    function closeMobileFilter() {
        if (layout) layout.classList.remove('filter-open');
        document.body.classList.remove('catalog-filter-locked');
    }
    if (filterToggleBtn) filterToggleBtn.addEventListener('click', openMobileFilter);
    if (filterBackdrop) filterBackdrop.addEventListener('click', closeMobileFilter);

    var currentAbort = null;
    var debounceTimer = null;

    function updateSliderFill(wrap) {
        var slider = wrap.querySelector('.filter__slider');
        if (!slider) return;
        var minInput = slider.querySelector('[data-role="min"]');
        var maxInput = slider.querySelector('[data-role="max"]');
        var fill = slider.querySelector('.filter__slider-fill');
        var bMin = parseFloat(slider.dataset.boundMin);
        var bMax = parseFloat(slider.dataset.boundMax);
        var vMin = parseFloat(minInput.value);
        var vMax = parseFloat(maxInput.value);
        var span = (bMax - bMin) || 1;
        var left = Math.max(0, Math.min(100, ((vMin - bMin) / span) * 100));
        var right = Math.max(0, Math.min(100, ((bMax - vMax) / span) * 100));
        fill.style.left = left + '%';
        fill.style.right = right + '%';
    }

    function initSliders() {
        filterPanel.querySelectorAll('.filter__price-range').forEach(updateSliderFill);
    }

    function currentParams() {
        var params = new URLSearchParams(window.location.search);
        var form = document.getElementById('smartFilterForm');
        if (form) {
            Array.prototype.forEach.call(form.elements, function(el) {
                if (el.name) params.delete(el.name);
            });
            new FormData(form).forEach(function(value, key) { params.append(key, value); });
        }
        return params;
    }

    function loadUrl(url) {
        if (currentAbort) currentAbort.abort();
        currentAbort = new AbortController();
        filterPanel.classList.add('is-loading');
        mainPanel.classList.add('is-loading');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: currentAbort.signal, cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                filterPanel.innerHTML = data.filter;
                mainPanel.innerHTML = data.results;
                history.pushState(null, '', url);
                initSliders();
                closeMobileFilter();
            })
            .catch(function(e) {
                if (e.name !== 'AbortError') window.location.href = url;
            })
            .finally(function() {
                filterPanel.classList.remove('is-loading');
                mainPanel.classList.remove('is-loading');
            });
    }

    function submitFilter() {
        var params = currentParams();
        params.delete('PAGEN_1');
        loadUrl(window.location.pathname + '?' + params.toString());
    }

    filterPanel.addEventListener('change', function(e) {
        var target = e.target;
        if (target.matches('.filter__cat-link')) return;
        if (target.matches('input[type="checkbox"]')) submitFilter();
    });

    filterPanel.addEventListener('input', function(e) {
        var target = e.target;

        if (target.matches('.filter__slider-input')) {
            var slider = target.closest('.filter__slider');
            var wrap = target.closest('.filter__price-range');
            var minInput = slider.querySelector('[data-role="min"]');
            var maxInput = slider.querySelector('[data-role="max"]');
            if (parseFloat(minInput.value) > parseFloat(maxInput.value)) {
                if (target.dataset.role === 'min') minInput.value = maxInput.value;
                else maxInput.value = minInput.value;
            }
            updateSliderFill(wrap);
            var textMin = wrap.querySelector('.filter__range [data-role="min"]');
            var textMax = wrap.querySelector('.filter__range [data-role="max"]');
            if (textMin) textMin.value = minInput.value;
            if (textMax) textMax.value = maxInput.value;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitFilter, 400);
            return;
        }

        if (target.matches('.filter__range .filter__input--half')) {
            var wrap2 = target.closest('.filter__price-range');
            if (wrap2 && target.value !== '') {
                var slider2 = wrap2.querySelector('.filter__slider');
                var sInput = slider2.querySelector('[data-role="' + target.dataset.role + '"]');
                if (sInput) sInput.value = target.value;
                updateSliderFill(wrap2);
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitFilter, 400);
            return;
        }

        if (target.matches('.filter__search')) {
            var q = target.value.trim().toLowerCase();
            var body = target.closest('.filter__body');
            body.querySelectorAll('.filter__checkbox').forEach(function(label) {
                var textEl = label.querySelector('.filter__label');
                var match = textEl && textEl.textContent.toLowerCase().indexOf(q) !== -1;
                label.style.display = match ? '' : 'none';
            });
        }
    });

    filterPanel.addEventListener('click', function(e) {
        if (e.target.closest('#catalogFilterClose')) {
            closeMobileFilter();
            return;
        }

        var treeToggle = e.target.closest('.filter__tree-toggle');
        if (treeToggle) {
            e.preventDefault();
            treeToggle.closest('.filter__tree-node').classList.toggle('filter__tree-node--open');
            return;
        }

        var showMoreBtn = e.target.closest('.filter__show-more');
        if (showMoreBtn) {
            var box = showMoreBtn.closest('.filter__box');
            var more = box.querySelector('.filter__more-items');
            if (more) {
                more.hidden = !more.hidden;
                showMoreBtn.textContent = more.hidden ? showMoreBtn.dataset.moreLabel : showMoreBtn.dataset.lessLabel;
            }
            return;
        }

        var resetBtn = e.target.closest('.filter__buttons .btn--outline');
        if (resetBtn) {
            e.preventDefault();
            loadUrl(window.location.pathname);
        }
    });

    filterPanel.addEventListener('submit', function(e) {
        if (e.target.id === 'smartFilterForm') {
            e.preventDefault();
            submitFilter();
        }
    });

    mainPanel.addEventListener('click', function(e) {
        var pageLink = e.target.closest('.pagination a');
        if (pageLink) {
            e.preventDefault();
            loadUrl(pageLink.getAttribute('href'));
        }
    });

    mainPanel.addEventListener('change', function(e) {
        var select = e.target.closest('.catalog-toolbar__sort select');
        if (!select) return;
        var sortParams = new URLSearchParams(select.value.replace(/^\?/, ''));
        var params = currentParams();
        params.set('sort', sortParams.get('sort'));
        params.delete('PAGEN_1');
        loadUrl(window.location.pathname + '?' + params.toString());
    });

    window.addEventListener('popstate', function() {
        window.location.reload();
    });

    initSliders();
})();

// Лайтбокс для фотогалерей: клик по миниатюре в любом .photo-gallery
// открывает полноразмерное фото с навигацией стрелками/клавиатурой.
(function() {
    var gallery = null;
    var items = [];
    var index = 0;
    var overlay = null;

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'lightbox';
        overlay.innerHTML =
            '<button type="button" class="lightbox__close" aria-label="Закрыть"><svg class="icon"><use href="#icon-x"></use></svg></button>' +
            '<button type="button" class="lightbox__nav lightbox__nav--prev" aria-label="Предыдущее фото"><svg class="icon"><use href="#icon-chevron-down"></use></svg></button>' +
            '<img class="lightbox__img" src="" alt="">' +
            '<button type="button" class="lightbox__nav lightbox__nav--next" aria-label="Следующее фото"><svg class="icon"><use href="#icon-chevron-down"></use></svg></button>';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) close();
        });
        overlay.querySelector('.lightbox__close').addEventListener('click', close);
        overlay.querySelector('.lightbox__nav--prev').addEventListener('click', function() { show(index - 1); });
        overlay.querySelector('.lightbox__nav--next').addEventListener('click', function() { show(index + 1); });
    }

    function show(i) {
        index = (i + items.length) % items.length;
        var link = items[index];
        overlay.querySelector('.lightbox__img').src = link.getAttribute('href');
        overlay.querySelector('.lightbox__img').alt = link.getAttribute('data-caption') || '';
    }

    function open(clickedItems, startIndex) {
        if (!overlay) build();
        items = clickedItems;
        show(startIndex);
        overlay.classList.add('lightbox--open');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('lightbox--open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e) {
        var link = e.target.closest('.photo-gallery__item');
        if (!link) return;
        e.preventDefault();
        gallery = link.closest('.photo-gallery');
        open(Array.prototype.slice.call(gallery.querySelectorAll('.photo-gallery__item')), Array.prototype.indexOf.call(gallery.querySelectorAll('.photo-gallery__item'), link));
    });

    document.addEventListener('keydown', function(e) {
        if (!overlay || !overlay.classList.contains('lightbox--open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
})();