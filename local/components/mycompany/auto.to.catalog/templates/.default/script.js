(function() {
    'use strict';

    var COMP = 'mycompany:auto.to.catalog';
    var $brand  = document.getElementById('brandSelect');
    var $model  = document.getElementById('modelSelect');
    var $mod    = document.getElementById('modSelect');
    var $btn    = document.getElementById('showPartsBtn');
    var $result = document.getElementById('partsResult');

    if (!$brand || !$model || !$mod) return; // нет селектов — выходим

    // ======== ЗАГРУЗКА МАРОК ========
    BX.ajax.runComponentAction(COMP, 'getBrands', { data: {} }).then(function(r) {
        r.data.brands.forEach(function(b) {
            var o = document.createElement('option');
            o.value = b.UF_BRAND_ID;
            o.textContent = b.UF_NAME;
            $brand.appendChild(o);
        });
    });

    // ======== ВЫБОР МАРКИ → МОДЕЛИ ========
    $brand.addEventListener('change', function() {
        var brandId = this.value;
        $model.innerHTML = '<option value="">— Модель —</option>';
        $mod.innerHTML   = '<option value="">— Модификация —</option>';
        $model.disabled  = true;
        $mod.disabled    = true;
        $btn.disabled    = true;
        $result.innerHTML = '';

        if (!brandId) return;

        BX.ajax.runComponentAction(COMP, 'getModels', { data: { brandId: parseInt(brandId) } }).then(function(r) {
            r.data.models.forEach(function(m) {
                var o = document.createElement('option');
                o.value = m.UF_MODEL_ID;
                o.textContent = m.UF_NAME + (m.UF_YEAR_FROM ? ' (' + m.UF_YEAR_FROM + '–' + (m.UF_YEAR_TO || 'н.в.') + ')' : '');
                $model.appendChild(o);
            });
            $model.disabled = false;
        });
    });

    // ======== ВЫБОР МОДЕЛИ → МОДИФИКАЦИИ ========
    $model.addEventListener('change', function() {
        var modelId = this.value;
        $mod.innerHTML  = '<option value="">— Модификация —</option>';
        $mod.disabled   = true;
        $btn.disabled   = true;
        $result.innerHTML = '';

        if (!modelId) return;

        BX.ajax.runComponentAction(COMP, 'getModifications', { data: { modelId: parseInt(modelId) } }).then(function(r) {
            r.data.modifications.forEach(function(m) {
                var o = document.createElement('option');
                o.value = m.UF_MODIFICATION_ID;
                o.textContent = m.UF_FULL_NAME
                    + ' | ' + (m.UF_ENGINE_CAPACITY || '?') + ' л'
                    + ' | ' + (m.UF_HORSE_POWER || '?') + ' л.с.'
                    + ' | ' + (m.UF_FUEL || '');
                $mod.appendChild(o);
            });
            $mod.disabled = false;
        });
    });

    // ======== ВЫБОР МОДИФИКАЦИИ ========
    $mod.addEventListener('change', function() {
        $btn.disabled = !this.value;
        $result.innerHTML = '';
    });

    // ======== КНОПКА: ПОКАЗАТЬ ЗАПЧАСТИ ========
    $btn.addEventListener('click', function() {
        var modId = $mod.value;
        if (!modId) return;

        $result.innerHTML = '<p style="text-align:center;padding:20px;color:var(--gray);">⏳ Загрузка...</p>';

        BX.ajax.runComponentAction(COMP, 'getParts', { data: { modificationId: parseInt(modId) } }).then(function(r) {
            renderResult(r.data);
        });
    });

    // ======== РЕНДЕР РЕЗУЛЬТАТА ========
    function renderResult(d) {
        var h = '';

        // Запчасти
        if (d.parts && Object.keys(d.parts).length) {
            h += '<div class="to-section" style="margin-bottom:24px;">';
            h += '<h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">🔧 Запчасти для ТО</h3>';
            for (var cat in d.parts) {
                h += '<h4 style="font-size:14px;font-weight:700;color:var(--black);margin:12px 0 6px;">' + esc(cat) + '</h4>';
                h += '<table class="to-table"><thead><tr><th>Наименование</th><th>Артикул</th><th>Кол-во</th><th>Примечание</th></tr></thead><tbody>';
                d.parts[cat].forEach(function(p) {
                    h += '<tr><td>' + esc(p.UF_ITEM_NAME) + '</td><td><code>' + esc(p.UF_PART_NUMBER) + '</code></td><td>' + p.UF_QUANTITY + '</td><td>' + esc(p.UF_COMMENT || '') + '</td></tr>';
                });
                h += '</tbody></table>';
            }
            h += '</div>';
        }

        // Масла
        if (d.oils && Object.keys(d.oils).length) {
            h += '<div class="to-section" style="margin-bottom:24px;">';
            h += '<h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">🛢️ Масла и жидкости</h3>';
            for (var type in d.oils) {
                h += '<h4 style="font-size:14px;font-weight:700;color:var(--black);margin:12px 0 6px;">' + esc(type) + '</h4>';
                h += '<table class="to-table"><thead><tr><th>Продукт</th><th>Артикул</th><th>Объём, л</th></tr></thead><tbody>';
                d.oils[type].forEach(function(o) {
                    h += '<tr><td>' + esc(o.UF_GROUP_NAME) + '</td><td><code>' + esc(o.UF_ART_NUMBER) + '</code></td><td>' + (o.UF_VOLUME || '') + '</td></tr>';
                });
                h += '</tbody></table>';
            }
            h += '</div>';
        }

        // Спецификации
        if (d.specs && d.specs.length) {
            h += '<div class="to-section" style="margin-bottom:24px;">';
            h += '<h3 style="font-size:18px;font-weight:700;color:var(--blue-dark);border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:16px;">⚙️ Спецификации и объёмы заправки</h3>';
            h += '<table class="to-table"><thead><tr><th>Жидкость</th><th>Объём</th><th>Допуски / примечание</th></tr></thead><tbody>';
            d.specs.forEach(function(s) {
                h += '<tr><td>' + esc(s.UF_NAME) + '</td><td>' + (s.UF_VOLUME || '') + '</td><td>' + esc(s.UF_PROPERTIES || s.UF_COMMENT || '') + '</td></tr>';
            });
            h += '</tbody></table></div>';
        }

        if (!h) h = '<p style="text-align:center;padding:20px;color:var(--gray);">Нет данных для выбранной модификации.</p>';
        $result.innerHTML = h;
    }

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
