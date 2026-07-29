# STAGES — журнал проекта liderws.ru
## Инфраструктура
Сервер: server17.reg.ru | SSH: ssh u3564357@31.31.198.55 -p 22 | Корень: /var/www/u3564357/data/www/liderws.ru/ | PHP: /usr/bin/php8.2
БД: u3564357_liderws_db / u3564357_liderws / S)'uAp]3.$@wWd- | mysql -u u3564357_liderws -p"S)'uAp]3.\$@wWd-" u3564357_liderws_db
Репозиторий: https://github.com/dafol11942-blip/liderws | Локально: C:\Users\user\Documents\GitHub\liderws | Ветка: fix/cache-pipeline-bugs
Платформа: 1С-Битрикс «Малый бизнес» | Поставщики (10): moskvorechie, rossko, berg, autoeuro, partkom, ixora, shatem, tatparts, autoruss, autopiter
## Правила
Заказчик не пишет код — только копирует команды. Каждый диалог = 1 этап с измеримым результатом.
Правки: VS Code → git push → сервер git pull --rebase. После патча: php -l проверка.
## Ключевые файлы
local/ajax/analog_search.php — точка входа всех поисков
local/ajax/analog_poll.php — polling Phase 2
local/ajax/analog_p2_exec.php — CLI Phase 2
local/php_interface/init.php — getSupplierFactory()
local/php_interface/lib/Search/InstantSearcher.php — search()+saveResults()→MySQL кэш
local/php_interface/lib/Search/SearchCacheManager.php — файловый кэш 300с
local/php_interface/lib/Search/BrandNormalizer.php — normalizeBrand()/normalizeArticle()
local/php_interface/lib/Search/Stage2/FullSearchLauncher.php — launchPhase1()+executePhase2()
local/php_interface/lib/Search/Stage2/OfferAggregator.php — группировка офферов
local/php_interface/lib/Search/Stage2/ResultBuilder.php — финальная сборка
local/php_interface/lib/Search/Common/MultiCurlExecutor.php — параллельные curl
local/php_interface/lib/Supplier/*Connector.php — коннекторы поставщиков
local/php_interface/cron/cache_cleanup.php — крон очистки */30
parts-search/stage2_search_v2.php + index.php — фронтенд
## Таблица b_supplier_stock
UNIQUE KEY(supplier_code, stock_id), stock_id=md5(source|article|brand|warehouse|price|stockId)
Поток: Браузер→analog_search.php→InstantSearcher::search()(MySQL TTL 4ч)→если пусто: FullSearchLauncher(API ~25с)→saveResults()→OfferAggregator→ResultBuilder→JSON
=== ЭТАП 1 — Диагностика потерь кэша (✅ 23.07.2026, коммиты 798fae9,5788238,d330963) ===
Баг ROOT: saveResults() никогда не вызывался → кэш 0 строк
Баг 1: bind_param 12 типов вместо 13 → silent fail INSERT
Баг 2: пустой stock_id → коллизия UNIQUE → 1 строка на поставщика
Баг 3-4: array_slice(brands/items,0,3) → выпадали бренды/офферы 4+
Результат: 0→1140 строк от 7 поставщиков
=== ЭТАП 2 — TTL + крон-очистка (✅ 23.07.2026, d7bf7d5/9b93fe4) ===
search(): TTL INTERVAL 4 HOUR. saveResults(): is_active=0 перед INSERT.
cron/cache_cleanup.php: деактивация >4ч, удаление >48ч, лог upload/logs/cache_cleanup_*.log
Результат: 4266 активных строк, крон 0.109с
=== ЭТАП 3 — Аналоги LC331 LYNXauto (✅ частично, 23.07.2026, c082790,6d1251e) ===
Баг 1: WHERE article=? — точное → REPLACE(REPLACE(REPLACE(LOWER(article)... — нормализация
Баг 2: браузер ждал 15с → fastcgi_finish_request()
Баг 5: дублирующий echo-блок → удалён 3707 символов
Баг 6: $instantCacheMs не определена → алиас $instantCacheMs=$instantMs
Результат: 245 строк LC331, 1 позиция/32 предложения в UI
НЕ решено: #9 JS lazy-loader перезаписывает аналоги, #10 $skipLive не инициализирована, #11 pending при ?verified=1
=== ЭТАП 4 — JS lazy-loader + skipLive + verified (✅ 23.07.2026) ===
Баг 9: guard if(!empty($verifyTaskHash)&&!empty($analogGroups))return;
Баг 10: $skipLive=false;
Баг 11: if(!isset($_GET['verified'])){INSERT...}
Баг 12: ResultBuilder дропал LYNX/lc331 → elseif с проверкой вариантов бренда
Баг 13: analog_search.php брал кэш вместо live → убран cache-first
Баг 14: контейнер аналогов не рендерился → fallback-div
Баг 15: мало складов → OfferAggregator(50,500)+ResultBuilder(300,50,500)
=== ЭТАП 5 — Не все поставщики в блоках (✅ 23.07.2026) ===
Проблема: autoeuro 150→50, tatparts 69→50 — обрезались лимитами
Баг 16: live-ветка с дефолтными лимитами (10/пост, 40 всего)
Фикс: унифицированы OfferAggregator(200,1000)+ResultBuilder(300,200,1000)
Результат: 240 складов вместо 121. Шатем отложен (не зарегистрирован)
=== ЭТАП 6 — Коллизия UNIQUE KEY stock_id (✅ 24.07.2026) ===
Проблема: Берг 2→79, ПартКом 3→115, Москворечье/Росско — коллизии stock_id
Баг 17: stock_id не уникален (Берг id=46 для обеих позиций) → md5(source|article|brand|warehouse|price|stockId)
Баг 18: ON DUPLICATE KEY UPDATE не обновлял article/brand → добавлены
Баг 19: неверный пароль PartKom → LidGates16
Результат: берг 2→118, partkom 3→115, moskvorechie 0→26, rossko 0→5. ~26786 строк
=== ЭТАП 7 — Поставщики в блоке аналогов (⏳ 24.07.2026) ===
Проблема: SUFIX SP1041 (кросс W81180) — только ТатПартс, у всех 7 есть в MySQL
Диагностика: поставщики не возвращают SP1041 как кросс для W81180. BrandMap знает sufix|w81180, но не sufix|sp1041
Баг 20а: занижены лимиты analog_search.php → (200,1000)+(800,200,1000)
Баг 20б: чанкинг MultiCurlExecutor 20 запросов → убран, все параллельно
Баг 20в: BrandMap не добавлял кроссы в Фазу 2 → brandMap→analogMap
Баг 20г: дозагрузка кросс-номеров из MySQL → SQL→слияние+дедупликация
НЕ решено: дедупликация нестабильна, двойная надпись «Догружаем»
=== ЭТАП 8 — Дедупликация + Phase 2 + прогрессивная загрузка (✅ 24.07.2026) ===
Баг 21: $r вместо $row в ключе дедупликации → все поля из $row
Баг 22: $seenKeys не инициализирован → иниц.+заполнение из launch
Баг 23: посторонние запчасти (TATSUMI TEA115) → фильтр $crossArticles
Баг 24: curl_multi захлёбывался 1200+ запросов → чанкинг по 100
Баг 25: FullSearchLauncher без P1/P2 → launchPhase1()+executePhase2()
Баг 26: SearchResultItem не сериализуется → example→массив
Баг 27: exec не работал из веба → analog_poll.php+flock
Архитектура: 0мс:⚡кэш→800мс:lazy-loader→P1(3-5с)→polling 2с→P2(10-15с)→полный HTML
Результат: P2 +0→+1758~6821 складов. Аналоги видны через 3-5с, полные 10-15с.
Важно: домен liderws.ru→другой сервер. Сайт по IP 31.31.198.55. curl с Host:liderws.ru.
=== ЭТАП 9 — «Левые» бренды в точном совпадении (✅ 27.07.2026) ===
Проблема: Clean/Big Filter с W81180 попадали в exact MANN
Баг 28: elseif($gan===$normTargetArt) захватывал все бренды → stripos($gbn,$normTargetBrand)!==false — только варианты бренда
Попутно: strtomorrow→strtotime в index.php. Восстановлен после __pending__.
НЕ сделано: ускорение холодного поиска, спиннер
=== ЭТАП 10 — Авторусь + Автопитер (✅ 27.07.2026) ===
Авторусь: REST/JSON ABCP, autoruss, ar. Автопитер: SOAP/XML, autopiter, ap.
Новые: AutorussConnector.php, AutopiterConnector.php.
Баги: ensureAuth(), article_id, Cookie в MultiCurlExecutor, fuzzy брендов, двойной префикс, deliveryPeriod=0→96ч.
Результат: 10 поставщиков, ~3000 строк
=== ЭТАП 11 — Гибрид: кэш + асинхронная Phase 2 (✅ 28.07.2026) ===
Баг 1: P2 кэш пересоздавался → time() убран из md5()
Баг 2: MultiCurlExecutor GET вместо POST → автоопределение по body
Баг 3: Phase2 0 результатов → бренд вместо пустой строки
Баг 4: skipLive=false при кэше → skipLive=true
Баг 5: analog_poll.php падал → раздельный require+ob_start
Баг 6: ?verified=1 не обходил кэш → !isset($_GET['verified'])
Прайс-листы ОТЛОЖЕН: 5/10 дают CSV/XLSX, но без кросс-номеров. UMAPI /crosses/by_code ~100-300мс vs BrandMap 3-6с. Сначала UMAPI→потом прайс-листы.
=== ЭТАП 12 — Нормализованный ключ P2 (❌ 29.07.2026) ===
Попытка: нормализация артикула+бренда в ключе P2, CLI с параметрами вместо хеша.
Проблема: P2-файл не создаётся. Гипотезы: автозагрузка, хеш, права, фатальная ошибка.
Диагностика: php -d display_errors=1 analog_p2_exec.php w7008 "MANN-FILTER"
=== ЭТАП 13 — Дубль искомого + символ «+» (✅ 29.07.2026) ===
Баг 29: W7008 дублировался в «Аналоги» — W+7008≠W7008 ключи групп
Фикс: normalizeArticle() + «+» в regex. analog_search.php защитный unset.
НЕ решено: в «Аналоги» только Росско. Phase2→1 поставщик. →Этап 14.
=== ЭТАП 14 — Диагностика Phase 2 (текущий) ===
# Поставщики в P2: LATEST=$(ls -t .../upload/cache/search/p2/*.json|head -1); php -r "$d=json_decode(file_get_contents('$LATEST'),true); foreach($d['p2_results']??[] as \$r) \$sup[\$r['source']]=(\$sup[\$r['source']]??0)+1; print_r(\$sup);"
# Лог: tail -30 .../upload/logs/analog_p2_$(date +%Y-%m-%d).log
=== ИТОГОВОЕ СОСТОЯНИЕ ===
10 поставщиков работают. Тёплый поиск: <100мс из кэша. Холодный: P1 3-5с + P2 10-15с.
Кэш: ~27000 активных строк, TTL 4ч. UI: баннеры «Догружаем»→«✅ Загружены все поставщики»
Открыто: Phase2 только 1 поставщик в аналогах (Этап 14), ускорение холодного поиска, прайс-листы (после UMAPI)
=== ЭТАП 14 — Диагностика Phase 2 / аналоги (❌ 29.07.2026) ===
Проблема: аналоги W7008 — только Росско, 81 поз. вместо 2000+. ⚠️ Не все поставщики загружены.
Диагностика:
- b_umapi_crosses: 491 аналог для w7008 ✅
- P2-файл создаётся с umapiAnalogs:491 ✅
- poll выполняет P2 синхронно → пишет done=true, p2_count=2000+ ✅
- analog_p2_exec.php запускается через exec() → НО exec в веб-контексте не работает (prolog_before.php вызывает exit в CLI)
- Гонка: poll пишет done=true+2000 строк → exec перезаписывает файл → 23 строки
- Фикс бага гонки: добавлен if(!empty($data['done'])) exit в p2_exec ✅
- Фикс isStale 90с в poll ✅
- Новый баг: после фикса p2_exec poll устанавливает running=true, nginx режет соединение ~30с → файл навсегда running=true, done=false
- Попытка перенести P2 в exec — не работает (Битрикс CLI несовместим)
- Возврат к синхронному poll + deadline 55с — UI таймаут JS 40с (20 опросов×2с) срабатывает раньше
- Увеличен JS таймаут до 40 опросов (80с) — всё равно ⚠️
Итог: архитектура poll→синхронный P2→nginx timeout нестабильна для 491 аналога
НЕ решено: аналоги только Росско, 81 поз.
Коммиты: fix/cache-pipeline-bugs ветка
=== ЭТАП 15 — план (следующий) ===
Варианты решения (обсудить):
1. Отказ от кэша P2-файлов: analog_poll.php выполняет P2 с увеличенным nginx timeout (fastcgi_read_timeout 120)
2. MySQL-очередь вместо JSON-файлов: poll пишет задание в таблицу, крон каждые 5с выполняет P2
3. Разбить 491 аналог на чанки по 50 → несколько poll-запросов по 10с каждый
4. Кэш результатов P2 в b_supplier_stock: после первого холодного поиска все 2000+ строк в MySQL → тёплый поиск мгновенный
Диагностика для следующего этапа:
- nginx fastcgi_read_timeout текущее значение: grep -r fastcgi_read_timeout /etc/nginx/
- Сколько времени реально занимает poll с P2: time curl http://31.31.198.55/local/ajax/analog_poll.php?hash=XXX -H "Host:liderws.ru"
- Логи nginx: tail -20 /var/log/nginx/error.log
=== ЭТАП 15 — MySQL-очередь для Phase 2 (⏳ 29.07.2026) ===
Проблема: analog_poll.php выполнял P2 синхронно → nginx рвал соединение на ~53с → ready:false навсегда
Диагностика: fastcgi_read_timeout недоступен (shared reg.ru), time curl poll = 53с, nginx error.log недоступен
Решение: MySQL-очередь b_p2_queue + крон p2_worker.php без Битрикс
Таблица b_p2_queue: hash(UNI), article, brand, status(pending/running/done/error), result_count, created_at, started_at, done_at
Новый analog_poll.php: только читает b_p2_queue → pending/done, при отсутствии записи INSERT pending
Новый p2_worker.php: SELECT pending → UPDATE running → executePhase2(90с) → UPDATE done + пишет p2_results в JSON
Тест: 491 аналог W7008 → 3932 результата за 3 минуты (без timeout) ✅
Баг 30: p2_worker сохраняет p2_results только в JSON, не в b_supplier_stock → JS после ready:true читает MySQL → видит старые 81 аналог
НЕ решено: p2_worker должен вызывать saveResults() или аналог для записи в b_supplier_stock
НЕ решено: 3 минуты → нужен array_slice(umapiAnalogs, 0, 50) + крон каждые 15с + JS maxPolls=90
Следующий шаг: 1) p2_worker добавить saveResults в b_supplier_stock 2) array_slice 50 3) crontab 4) JS таймаут
Коммиты: feat: MySQL queue for P2, cron worker without Bitrix (ветка fix/cache-pipeline-bugs)