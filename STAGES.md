# STAGES — журнал проекта liderws.ru
## Инфраструктура
Сервер: server17.reg.ru | SSH: ssh u3564357@31.31.198.55 -p 22 | Корень: /var/www/u3564357/data/www/liderws.ru/ | PHP: /usr/bin/php
БД: u3564357_liderws_db / u3564357_liderws / S)'uAp]3.$@wWd- | mysql -u u3564357_liderws -p"S)'uAp]3.\$@wWd-" u3564357_liderws_db
Репозиторий: https://github.com/dafol11942-blip/liderws | Локально: C:\Users\user\Documents\GitHub\liderws | Ветка: fix/cache-pipeline-bugs
Платформа: 1С-Битрикс «Малый бизнес» | Поставщики (10): moskvorechie, rossko, berg, autoeuro, partkom, ixora, shatem, tatparts, autoruss, autopiter
## Правила
Заказчик не пишет код — только копирует команды. Каждый диалог = 1 этап с измеримым результатом.
Правки: VS Code → git push → сервер git pull --rebase. После патча: php -l проверка.
## Ключевые файлы
local/ajax/analog_search.php — точка входа всех поисков
local/ajax/analog_poll.php — polling Phase 2
local/php_interface/cron/p2_worker.php — крон-воркер Phase 2
local/php_interface/init.php — getSupplierFactory()
local/php_interface/lib/Search/InstantSearcher.php — search()+saveResults()→MySQL кэш
local/php_interface/lib/Search/SearchCacheManager.php — файловый кэш 300с
local/php_interface/lib/Search/BrandNormalizer.php — normalizeBrand()/normalizeArticle()
local/php_interface/lib/Search/Stage2/FullSearchLauncher.php — launchPhase1()+executePhase2()
local/php_interface/lib/Search/Stage2/OfferAggregator.php — группировка офферов
local/php_interface/lib/Search/Stage2/ResultBuilder.php — финальная сборка
local/php_interface/lib/Search/Common/MultiCurlExecutor.php — параллельные curl
local/php_interface/lib/Supplier/*Connector.php — коннекторы поставщиков
local/php_interface/lib/Search/UmapiClient.php — UMAPI кроссы
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
Прайс-листы ОТЛОЖЕН: 5/10 дают CSV/XLSX, но без кросс-номеров.

=== ЭТАП 12 — Нормализованный ключ P2 (❌ 29.07.2026) ===
Попытка: нормализация артикула+бренда в ключе P2, CLI с параметрами вместо хеша.
Проблема: P2-файл не создаётся. Гипотезы: автозагрузка, хеш, права, фатальная ошибка.

=== ЭТАП 13 — Дубль искомого + символ «+» (✅ 29.07.2026) ===
Баг 29: W7008 дублировался в «Аналоги» — W+7008≠W7008 ключи групп
Фикс: normalizeArticle() + «+» в regex. analog_search.php защитный unset.
НЕ решено: в «Аналоги» только Росско. Phase2→1 поставщик.

=== ЭТАП 14 — Диагностика Phase 2 (❌ 29.07.2026) ===
Проблема: аналоги W7008 — только Росско, 81 поз. вместо 2000+. ⚠️ Не все поставщики загружены.
Диагностика:
- b_umapi_crosses: 491 аналог для w7008 ✅
- P2-файл создаётся с umapiAnalogs:491 ✅
- poll выполняет P2 синхронно → пишет done=true, p2_count=2000+ ✅
- analog_p2_exec.php запускается через exec() → НО exec в веб-контексте не работает (prolog_before.php вызывает exit в CLI)
- Гонка: poll пишет done=true+2000 строк → exec перезаписывает файл → 23 строки
- Фикс бага гонки: добавлен if(!empty($data['done'])) exit в p2_exec ✅
- Фикс isStale 90с в poll ✅
- Новый баг: после фикса p2_exec poll устанавливает running=true, nginx режет соединение ~30с
- Попытка перенести P2 в exec — не работает (Битрикс CLI несовместим)
- Возврат к синхронному poll + deadline 55с — UI таймаут JS 40с срабатывает раньше
Итог: архитектура poll→синхронный P2→nginx timeout нестабильна для 491 аналога

=== ЭТАП 15 — Инкрементальная P2 + фильтрация кроссов (❌ 29.07.2026) ===
Проблема: синхронный P2 рвал nginx; крон-воркер ждал 15с; аналоги содержали посторонние запчасти.
Что сделано:
- p2_chunk endpoint: JS запрашивает по 10 кроссов, сервер выполняет executePhase2 → HTML
- Серверный рендер из кэша b_supplier_stock + создание P2-файла в stage2_search_v2.php
- analog_poll.php заменён на прямую проверку JSON (без крона)
- p2_worker.php: bind_param fix, цикл без возврата в pending
- Убран cron для p2_worker, используется exec() из веба (работает ✅)
- UmapiClient: добавлена колонка is_search TINYINT в b_umapi_crosses
- phase=final читает p2_results из JSON вместо b_supplier_stock
- Аналоги: все 491 кросс W7008 имеют isSearch: false (UMAPI) — фильтрация невозможна
Баги:
- Бесконечный цикл p2_chunk: сервер не вырезает обработанные кроссы → 144+ запросов JS
- Посторонние запчасти: executePhase2 ищет без контекста типа детали → воздушные фильтры, крышки
- Дедупликация BrandNormalizer: ALCO≠ALCOFILTER после normalize → дубли
- 491 кросс × 10 поставщиков = ~5000 API-запросов → 8+ минут
Рекомендации:
- Фильтровать кроссы по title (напр. «маслян» для масляного фильтра)
- Брать первые N кроссов (50) или фильтровать по crosses[0][1] (вес связи)
- Передавать brand в buildSearchRequest (не пустую строку)
НЕ решено: инкрементальный P2 зависает, аналоги содержат нерелевантные запчасти.
Нужна полная переработка P2
=== ЭТАП 16 — Откат к чистому ядру + план перестройки P2 (⏳ 01.08.2026) ===
Причина: Этапы 11-15 (UMAPI/P2/p2_chunk/polling) — 50+ коммитов мусора, 5000 API-запросов (8+мин), гонки, timeout nginx, лимит файлов 170k/150k.
Решение: откат к Этапу 10 + cherry-pick полезных фиксов.
Ветка: fix/clean-base от 04df973 (тап 10 закрыт).
Коммиты: 630a4ce (MultiCurl POST), 890a582 (normalizeArticle «+»), e6b2d32 (self из analogGroups).
Удалено: UmapiClient.php, p2_worker.php, executePhase2, p2_chunk, b_umapi_crosses, p2_results/*.json.
Сохранено: 10 поставщиков, кэш MySQL 4ч, P1 MultiCurl, OfferAggregator/ResultBuilder, normalizeArticle/Brand, фикс левых брендов.
Инцидент: лимит файлов превышен (170525/150000), кэш Битрикса/P2-файлы очищены поддержкой. Сайт недоступен — требуется доп.очистка.
НЕ решено: восстановление доступа к сайту, дроп b_umapi_crosses, build_cross_index.

План Этапа 17 — новая архитектура:
1. Аналог = ПАРА (brand+article), не артикул.
2. UMAPI только в ночном кроне (CLI).
3. Цены/остатки живые от поставщиков для отфильтрованных кроссов.
4. Нормализация — единый язык.

Архитектура:
НОЧЬ: b_supplier_stock→UMAPI→b_cross_index (связи пар + weight, без цен).
ПОИСК: нормализация→b_cross_index(30 пар, weight>=3)→b_supplier_stock(кэш мгновенно)→MultiCurlExecutor(недостающие, 2-4с)→ответ <5с.
ХОЛОДНЫЙ: P1 10 поставщиков (1-2с)+UMAPI фоном→3-5с.

Таблицы:
CREATE TABLE b_cross_index (article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm, weight, title_keywords, PK(4 поля), INDEX(orig));
ALTER TABLE b_supplier_stock ADD article_normalized, brand_normalized, INDEX(brand,article,is_active).

Было→Стало: UMAPI в реалтайме→в кроне; 5000 запросов→0-300; article без brand→пара; 8+мин→<5с; p2_chunk+гонки→один поток.
=== ЭТАП 16 — Откат к чистому ядру (✅ 01.08.2026) ===
Причина: Этапы 11-15 (UMAPI/P2/p2_chunk/polling) — 50+ коммитов, 5000 API-запросов (8+мин), гонки состояний, timeout nginx, лимит файлов 170k/150k.
Результат: сайт восстановлен, 117 622 файла, чистая ветка fix/clean-base от Этапа 10.
Коммиты: 630a4ce (MultiCurl POST), 890a582 (normalizeArticle «+»), e6b2d32 (self из analogGroups).
Удалено: UmapiClient.php, p2_worker.php, executePhase2, p2_chunk, b_umapi_crosses, p2_results/*.
Чистка: bitrix/cache, managed_cache, stack_cache, p2_results, git gc (сжато 1124 дельты).
Синтаксис: analog_search.php, init.php, FullSearchLauncher.php — OK.

Тест гипотез (W7008/MANN):
- UMAPI Analogs/pro: 491 аналог, 189 уникальных brandSearchRoot, ~1с ответ
- b_supplier_stock: 78 уникальных брендов, 108 уникальных пар (brand+article)
- Пересечение брендов UMAPI↔БД: 41 из 189 (22%)
- После бренд-фильтра: 490 аналогов (бренды поставщиков), но в кэше только 108 пар
- MultiCurl на 382 пары × 10 поставщиков = 10-15с — неприемлемо

Вывод: стратегия «живой MultiCurl» нестабильна и не даёт гарантий по скорости.
Новая стратегия: b_supplier_stock как единственный источник, UMAPI только в ночном кроне, прайс-листы где доступны.
=== ЭТАП 17 — Ревизия, b_cross_index, новый поиск, прайс-листы (⏳ план) ===

ШАГ 17.1 — Ревизия коннекторов (отсечь товар под заказ)
  Проверить 10 *Connector.php — убрать чужие склады и товар под заказ.
  Результат: в b_supplier_stock только реальные склады поставщика.

ШАГ 17.2 — b_cross_index + ночной крон
  SQL: CREATE TABLE b_cross_index (пары article+brand → article+brand + title + weight).
  cron/build_cross_index.php: читает уникальные пары из b_supplier_stock,
  для каждой → UMAPI Analogs/pro, фильтрация по title, INSERT в b_cross_index.
  Результат: ~6000×400=2.4M связей, заполняется раз в сутки.

ШАГ 17.3 — Новый analog_search.php
  Убрать FullSearchLauncher/MultiCurl/executePhase2 для аналогов.
  Поиск: b_cross_index (пары, мгновенно) → b_supplier_stock (цены/остатки, мгновенно).
  ALTER b_supplier_stock: source_type ENUM('pricelist','api'), source_updated DATETIME.
  Вывод: единый массив, все поставщики, пометка свежести. <200мс.
  Холодный артикул: UMAPI → b_cross_index на лету (1с) → SQL → ответ.

ШАГ 17.4 — Тестирование потока
  Запустить build_cross_index.php на сервере.
  Проверить b_cross_index заполнился, поиск W7008 — скорость и состав.
  Проверить saveResults() с source_type='api' + source_updated.
  Проверить новый артикул (нет в индексе) — UMAPI на лету.

ШАГ 17.5 — Прайс-листы
  cron/load_pricelist_moskvorechie.php — CSV.
  cron/load_pricelist_partkom.php — (формат уточнить).
  cron/load_pricelist_autoeuro.php — (формат уточнить).
  cron/load_all_pricelists.php — запуск всех.
  Крон раз в сутки. source_type='pricelist'.
  🎯 Модель данных после Этапа 17
-- b_cross_index: связи пар (заполняется ночью, читается при поиске)
SELECT article_cross_norm, brand_cross_norm, title_cross
FROM b_cross_index
WHERE article_orig_norm = 'w7008' AND brand_orig_norm = 'mann';

-- b_supplier_stock: цены и остатки (единый источник для всех поставщиков)
SELECT supplier_code, article, brand, price, quantity, warehouse,
       source_type, source_updated
FROM b_supplier_stock
WHERE article_normalized IN (все кроссы) AND is_active = 1
ORDER BY price ASC;
Никаких API-запросов к поставщикам при поиске. Только SQL. Только b_supplier_stock.
Актуальность: прайс-листы обновляются ночью, API-кэш обновляется при каждом поиске любого артикула. Для свежих цен — кнопка «обновить» или фоновый запрос через 5 секунд.
=== ЭТАП 17 — Ревизия, b_cross_index, новый поиск, прайс-листы (⏳ 01.08.2026) ===

ШАГ 17.1 — Ревизия коннекторов (✅ 01.08.2026, коммиты ac75694, ..., 2f15834)
  Проблема: коннекторы возвращали чужие склады и товар под заказ без ограничений.
  Решение: 2 группы поставщиков.
  Группа А (только свои склады, без лимита):
    - Москворечье: hide_extstor=1 (API).
    - ПартКом: store=1 (API).
  Группа Б (свои все + топ-10 чужих по срокам+цене):
    - Berg: свои warehouse_types 1,2 все; чужие type=3 — 10.
    - Rossko: свои не «Партнерский склад» все; партнёрские — 10.
    - Autopiter: свои StoreType 0,2 все; чужие 1,3,4,9 — 10.
    - Autoruss: своих нет — 10.
    - Ixora: свои «IXORA СКЛАД*» все; чужие — 10.
    - Autoeuro: свои stock=1 все; чужие stock=0 — 10.
    - Tatparts: своих нет — 10.
  Сортировка везде: deliveryDays→price (быстрее+дешевле в топе).
  Убран товар под заказ (isSched/!isStock/avail≤0).
  Файлы: все 9 *Connector.php.
  Результат: только реальные склады, быстрая доставка, лучшие цены.

ШАГ 17.2 — b_cross_index + ночной крон (⏳ план)
  1. SQL: CREATE TABLE b_cross_index.
     Поля: article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm,
            weight, title_keywords, created_at.
     PK: (article_orig_norm, brand_orig_norm, article_cross_norm, brand_cross_norm).
     INDEX: (article_orig_norm, brand_orig_norm).
  2. SQL: ALTER TABLE b_supplier_stock.
     ADD article_normalized VARCHAR(255), brand_normalized VARCHAR(255),
         source_type ENUM('api','pricelist') DEFAULT 'api',
         source_updated DATETIME.
     ADD INDEX idx_brand_article_active (brand_normalized, article_normalized, is_active).
  3. cron/build_cross_index.php (запуск раз в сутки, CLI):
     - SELECT DISTINCT article, brand FROM b_supplier_stock WHERE is_active=1.
     - Для каждой пары → UMAPI Analogs/pro (пакетно по 10).
     - Фильтрация аналогов по title_keywords (тип детали).
     - INSERT IGNORE в b_cross_index с weight из UMAPI.
     - Лог: upload/logs/build_cross_index_*.log.
  4. Миграция: заполнить article_normalized/brand_normalized в b_supplier_stock
     через BrandNormalizer по существующим article/brand.
  Результат: b_cross_index с ~6000×400=2.4M связей, заполняется раз в сутки.

ШАГ 17.3 — Новый analog_search.php (⏳ план)
  Убрать: FullSearchLauncher, MultiCurlExecutor для аналогов, executePhase2.
  Новый поток:
    1. Нормализация входа: BrandNormalizer::normalize() + normalizeArticle().
    2. Поиск кроссов: SELECT из b_cross_index WHERE article_orig_norm+ brand_orig_norm.
       Если пусто → UMAPI на лету (1с) → INSERT в b_cross_index → повтор SELECT.
    3. Цены/остатки: SELECT из b_supplier_stock WHERE
       article_normalized IN (все кроссы) AND is_active=1.
    4. OfferAggregator + ResultBuilder → JSON.
  Холодный артикул: UMAPI → b_cross_index (1с) → SQL (<200мс).
  Горячий артикул: SQL → SQL (<200мс).
  Никаких API-запросов к поставщикам при поиске. Только b_supplier_stock.
  source_type='pricelist' — пометка «Прайс»; 'api' — «Кэш API».

ШАГ 17.4 — Тестирование потока (⏳ план)
  1. Запустить build_cross_index.php на сервере: php cron/build_cross_index.php.
  2. Проверить: SELECT COUNT(*) FROM b_cross_index — не пусто.
  3. Тест W7008/MANN: открыть parts-search/ → скорость, состав аналогов.
  4. Тест холодного артикула (нет в b_cross_index) → UMAPI на лету → ответ <2с.
  5. Проверить saveResults() с source_type='api' + source_updated = NOW().
  6. Проверить фильтры 17.1 работают через b_supplier_stock.

ШАГ 17.5 — Прайс-листы (⏳ план)
  Поставщики с прайс-листами (из Этапа 11): Москворечье (CSV), ПартКом, Autoeuro и др.
  cron/load_pricelist_moskvorechie.php: скачать CSV → парсить → INSERT b_supplier_stock
    с source_type='pricelist', source_updated=NOW().
  cron/load_pricelist_partkom.php: формат уточнить.
  cron/load_pricelist_autoeuro.php: формат уточнить.
  cron/load_all_pricelists.php: запуск всех, раз в сутки.
  Прайс-листы содержат цены и остатки, но без кросс-номеров → кроссы из b_cross_index.

🎯 Модель данных после Этапа 17
-- b_cross_index: связи пар (заполняется ночью, читается при поиске)
SELECT article_cross_norm, brand_cross_norm, title_cross
FROM b_cross_index
WHERE article_orig_norm = 'w7008' AND brand_orig_norm = 'mann';

-- b_supplier_stock: цены и остатки (единый источник для всех поставщиков)
SELECT supplier_code, article, brand, price, quantity, warehouse,
       source_type, source_updated
FROM b_supplier_stock
WHERE article_normalized IN (все кроссы) AND is_active = 1
ORDER BY price ASC;

Никаких API-запросов к поставщикам при поиске. Только SQL. Только b_supplier_stock.
Актуальность: прайс-листы обновляются ночью, API-кэш обновляется при поиске.