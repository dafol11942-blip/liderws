<script>
(function(){
var API='/search/ajax.php';
var Q=<?=json_encode($q)?>,B=<?=json_encode($brand)?>,N=<?=json_encode($number)?>;
function qs(s,el){return(el||document).querySelector(s)}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function fmt(n){return new Intl.NumberFormat('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}
function dRange(d){return d>=0?d+' дн.':'—'}

function showProgress(pct, msg) {
    qs('#resultContent').innerHTML =
        '<div class="loader"><div class="spinner"></div>' +
        '<div class="progress-bar"><div class="progress-fill" style="width:' + pct + '%"></div></div>' +
        '<div class="progress-text">' + pct + '% — ' + esc(msg) + '</div></div>';
}

async function loadResults(){
    showProgress(0, 'Запуск поиска...');
    
    // Запускаем search (долгий запрос)
    var searchUrl = API + '?action=search&article=' + encodeURIComponent(Q) +
        '&brand=' + encodeURIComponent(B) + '&number=' + encodeURIComponent(N);
    
    var searchPromise = fetch(searchUrl).then(function(r){return r.json()});
    
    // Параллельно поллим прогресс (сначала нужно узнать taskId)
    // Ждём первый ответ чтобы узнать task_id
    var result = await searchPromise;
    
    if (result.error) {
        showError(result.error);
        return;
    }
    
    // Если есть task_id — прогресс уже был записан, но мы уже получили результат
    renderResults(result);
}

// Если search вернулся быстро (<2с) — прогресс не нужен
// Если >2с — показываем прогресс через отдельный запрос

async function loadResultsWithProgress(){
    showProgress(5, 'Запрашиваем точное совпадение...');
    
    var taskId = '';
    
    // Первый запрос — запускаем search с генерацией taskId
    var searchUrl = API + '?action=search&article=' + encodeURIComponent(Q) +
        '&brand=' + encodeURIComponent(B) + '&number=' + encodeURIComponent(N) +
        '&task=' + Date.now() + Math.random().toString(36).substr(2);
    
    // Поллинг прогресса
    var progressInterval = null;
    var progressDone = false;
    var finalResult = null;
    
    // Функция поллинга
    function startPolling(taskId) {
        progressInterval = setInterval(async function() {
            if (progressDone) return;
            try {
                var r = await fetch(API + '?action=progress&task=' + taskId);
                var p = await r.json();
                if (p.done) {
                    progressDone = true;
                    clearInterval(progressInterval);
                    showProgress(100, 'Готово');
                    if (p.result) {
                        setTimeout(function(){ renderResults(p.result); }, 300);
                    }
                } else {
                    showProgress(p.percent || 0, p.message || 'Поиск...');
                }
            } catch(e) {}
        }, 500);
    }
    
    // Запускаем поиск
    try {
        var resp = await fetch(searchUrl);
        var result = await resp.json();
        
        if (result.error) {
            if (progressInterval) clearInterval(progressInterval);
            showError(result.error);
            return;
        }
        
        // Если есть task_id — запускаем поллинг
        if (result.task_id) {
            taskId = result.task_id;
            // Проверяем прогресс (возможно уже завершён)
            var pr = await fetch(API + '?action=progress&task=' + taskId);
            var pd = await pr.json();
            
            if (pd.done && pd.result) {
                // Уже готово
                showProgress(100, 'Готово');
                setTimeout(function(){ renderResults(pd.result); }, 200);
            } else {
                // Показываем прогресс из результата поиска
                showProgress(pd.percent || 50, pd.message || 'Поиск...');
                startPolling(taskId);
                
                // Если результат уже в ответе — рендерим
                if (result.exact || result.analogs) {
                    progressDone = true;
                    if (progressInterval) clearInterval(progressInterval);
                    renderResults(result);
                }
            }
        } else {
            // Нет task_id — результат пришёл сразу
            renderResults(result);
        }
    } catch(e) {
        if (progressInterval) clearInterval(progressInterval);
        showError('Ошибка: ' + e.message);
    }
}

function renderResults(d){
    var exact=d.exact||null,analogs=d.analogs||[];
    var allOffers=[];
    if(exact&&exact.suppliers){exact.suppliers.forEach(function(s){s._type='exact';s._brand=exact.brand;s._article=exact.article;allOffers.push(s)});}
    analogs.forEach(function(a){a.suppliers.forEach(function(s){s._type='analog';s._brand=a.brand;s._article=a.article;s._description=a.description||'';allOffers.push(s)});});

    var bestPriceExact=null,bestPriceAnalog=null,bestDelivery=null;
    allOffers.forEach(function(o){
        if(o.price>0){
            if(o._type==='exact'&&(!bestPriceExact||o.price<bestPriceExact.price))bestPriceExact=o;
            if(o._type==='analog'&&(!bestPriceAnalog||o.price<bestPriceAnalog.price))bestPriceAnalog=o;
        }
        if(o.delivery_days>=0&&(!bestDelivery||o.delivery_days<bestDelivery.delivery_days))bestDelivery=o;
    });

    var h='';
    h+='<div class="phead"><h1 class="phead-title">'+esc(N)+' '+esc(B)+'</h1>';
    if(exact&&exact.suppliers)h+='<p class="phead-sub">Найдено '+exact.suppliers.length+' предл. искомого + '+analogs.length+' аналогов</p>';
    h+='</div>';

    if(bestPriceExact||bestPriceAnalog||bestDelivery){
        h+='<div class="hl-cards">';
        if(bestPriceExact)h+=hlCard(bestPriceExact,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Искомый номер');
        if(bestPriceAnalog)h+=hlCard(bestPriceAnalog,'САМАЯ НИЗКАЯ ЦЕНА','hl-card--best','hl-badge--price','Аналог');
        if(bestDelivery)h+=hlCard(bestDelivery,'НАИМЕНЬШИЙ СРОК','hl-card--fast','hl-badge--delivery',bestDelivery._type==='exact'?'Искомый номер':'Аналог');
        h+='</div>';
    }

    h+='<div class="full-tbl">';

    if(exact&&exact.suppliers&&exact.suppliers.length){
        h+='<div class="ft-sec ft-sec--exact"><div class="ft-sec-head"><span class="ft-sec-title">✅ Искомый номер</span><span class="ft-sec-sub">'+esc(B)+' / '+esc(N)+' — '+exact.suppliers.length+' складов</span></div>';
        h+=supplierTable(exact.suppliers,'exact');
        h+='</div>';
    }

    if(analogs.length){
        h+='<div class="ft-sec ft-sec--analog"><div class="ft-sec-head"><span class="ft-sec-title">🔄 Аналоги ('+analogs.length+')</span><span class="ft-sec-sub">Топ-5 поставщиков по каждому аналогу</span></div>';
        analogs.forEach(function(a){
            h+='<div class="ft-group"><div class="ft-ghead"><div class="ft-ginfo"><strong class="ft-gbrand">'+esc(a.brand)+'</strong><code class="ft-gart">'+esc(a.article)+'</code><span class="ft-gdesc">'+esc(a.description||'')+'</span></div><div class="ft-gmeta"><span class="ft-gbest">Лучшая: <b>'+fmt(a.best_price)+' р.</b> / '+(a.best_delivery!==null?a.best_delivery+' дн.':'—')+'</span><span class="badge '+(a.has_instock?'badge--green':'badge--yellow')+'">'+a.total_qty+' шт.</span></div></div>';
            h+=supplierTable(a.suppliers,'analog');
            h+='</div>';
        });
        h+='</div>';
    }

    if(!exact&&!analogs.length)h='<div class="hero" style="margin-top:16px"><div class="hero-icon">⚠️</div><p>По запросу «'+esc(B)+' '+esc(N)+'» ничего не найдено</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';

    h+='</div>';
    qs('#resultContent').innerHTML=h;

    document.querySelectorAll('.ft-showmore').forEach(function(btn){
        btn.addEventListener('click',function(){
            var group=btn.closest('.ft-sec, .ft-group');
            group.querySelectorAll('.ft-more').forEach(function(r){r.style.display=''});
            btn.style.display='none';
        });
    });
}

function hlCard(o,title,cardCls,badgeCls,type){
    return '<div class="hl-card '+cardCls+'"><div class="hl-badge '+badgeCls+'">'+title+'</div><div class="hl-type">'+type+'</div><div class="hl-name">'+esc(o._brand)+' / '+esc(o._article)+'</div><div class="hl-price">'+fmt(o.price)+' р.</div><div class="hl-meta">'+o.quantity+' шт. &middot; '+dRange(o.delivery_days)+'</div><div class="hl-src"><span class="src-tag src-tag--'+o.supplier+'">'+o.supplier+'</span></div></div>';
}

function supplierTable(suppliers,type){
    var limit=type==='exact'?15:5;
    var h='<table class="ft-tbl"><thead><tr><th class="ft-th--det">Деталь</th><th class="ft-th--skl">Склад</th><th class="ft-th--num">Кол.</th><th class="ft-th--num">Доставка</th><th class="ft-th--num">Цена</th></tr></thead><tbody>';
    suppliers.forEach(function(s,i){
        var cls=i>=limit?' class="ft-more" style="display:none"':'';
        h+='<tr'+cls+'><td class="ft-td--det"><div class="ft-det-name">'+esc(s._description||'')+'</div><div class="ft-det-brand">'+esc(s._brand||'')+' '+esc(s._article||'')+'</div></td><td class="ft-td--skl"><span class="ft-skl-name">'+esc(s.warehouse||'')+'</span><span class="src-tag src-tag--'+s.supplier+'">'+s.supplier+'</span></td><td class="ft-td--num">'+s.quantity+' шт.</td><td class="ft-td--num">'+dRange(s.delivery_days)+'</td><td class="ft-td--prc"><strong>'+fmt(s.price)+' р.</strong></td></tr>';
    });
    h+='</tbody></table>';
    if(suppliers.length>limit)h+='<button class="ft-showmore">Показать еще '+(suppliers.length-limit)+' товаров</button>';
    return h;
}

function showError(msg){
    qs('#resultContent').innerHTML='<div class="hero" style="margin-top:16px"><div class="hero-icon">⚠️</div><p>'+esc(msg)+'</p><a href="/search/?q='+encodeURIComponent(Q)+'" class="hero-back">← К выбору бренда</a></div>';
}

document.addEventListener('DOMContentLoaded',function(){loadResultsWithProgress()});
})();
</script>