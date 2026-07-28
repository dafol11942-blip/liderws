# STAGES — журнал проекта liderws.ru

---

## КОНТЕКСТ ПРОЕКТА (читать в начале каждого диалога)

### Участники
- **Исполнитель**: Senior Fullstack (ИИ) — пишет весь код и команды
- **Заказчик**: без опыта в коде — только копирует и запускает команды

### Инфраструктура
| Параметр | Значение |
|----------|----------|
| Сервер | `server17.reg.ru` |
| SSH | `ssh u3564357@31.31.198.55 -p 22` |
| Пользователь VPS | `u3564357` |
| Сайт | `liderws.ru` |
| Корень сайта | `/var/www/u3564357/data/www/liderws.ru/` |
| PHP | `/usr/bin/php8.2` |
| БД MySQL | `u3564357_liderws_db` |
| Пользователь БД | `u3564357_liderws` |
| Пароль БД | `S)'uAp]3.$@wWd-` |
| MySQL команда | `mysql -u u3564357_liderws -p"S)'uAp]3.\$@wWd-" u3564357_liderws_db` |
| Репозиторий | https://github.com/dafol11942-blip/liderws |
| Локальный репо | `C:\Users\user\Documents\GitHub\liderws` |
| Текущая ветка | `fix/cache-pipeline-bugs` |
| Платформа | 1С-Битрикс «Малый бизнес» |
| Redis | ❌ не установлен |
| Cron Bitrix | `* * * * * /usr/bin/php8.2 .../cron_events.php` |

### Поставщики (8 штук)
`moskvorechie`, `rossko`, `berg`, `autoeuro`, `partkom`, `ixora`, `shatem`, `tatparts`

### Ключевые файлы
local/ajax/analog_search.php          ← точка входа (все поиски идут сюда)
local/php_interface/init.php          ← getSupplierFactory() — регистрация поставщиков
local/php_interface/lib/Search/
InstantSearcher.php                 ← search() + saveResults() → MySQL кэш
SearchCacheManager.php              ← файловый кэш (300 сек)
BrandNormalizer.php                 ← нормализация бренда/артикула
SearchResultItem.php                ← модель результата
Stage2/
FullSearchLauncher.php            ← параллельные запросы к поставщикам
OfferAggregator.php               ← группировка офферов
ResultBuilder.php                 ← финальная сборка ответа
local/php_interface/cron/
cache_cleanup.php                   ← крон очистки кэша (каждые 30 мин)
local/php_interface/lib/Supplier/
*Connector.php                      ← коннекторы поставщиков
### Таблица кэша
```sql
b_supplier_stock
  id, supplier_code, article, brand, brand_normalized,
  name, price, quantity, warehouse_name, warehouse_code,
  delivery_days, is_sched, multiplicity, stock_id,
  last_updated, is_active
UNIQUE KEY: (supplier_code, stock_id)
Реальный поток запроса

Браузер → analog_search.php
  → InstantSearcher::search()  [MySQL кэш, TTL 4ч]
  → если пусто: FullSearchLauncher::launch() [live API ~25 сек]
    → InstantSearcher::saveResults() [сохранить в MySQL]
  → OfferAggregator → ResultBuilder → JSON → браузер

Код


Правила работы

    Заказчик НЕ пишет код сам — только копирует команды
    Каждый диалог = один этап с измеримым результатом
    Все правки через Python-скрипты (точные якоря, бэкап перед патчем)
    После каждого патча: php -l для проверки синтаксиса
    VPS и Windows — разные репо, синхронизировать через git pull --rebase



Этап 1 — Диагностика и устранение потерь кэша

    Дата: 2026-07-23
    Коммиты: 798fae9, 5788238, d330963


Баги (все исправлены ✅)

#	Файл	Баг	Эффект
ROOT	analog_search.php	saveResults() никогда не вызывался	кэш = 0 строк навсегда
1	InstantSearcher.php	bind_param('sssssdissiis') — 12 типов вместо 13	silent fail на каждом INSERT
2	InstantSearcher.php	пустой stock_id → коллизия UNIQUE KEY	1 строка на поставщика
3	SearchService.php	array_slice(brands, 0, 3)	выпадал бренд на позиции 4+
4	SearchService.php	array_slice(items, 0, 3)	обрезка до 3 офферов

Результат

До:    b_supplier_stock = 0 строк (никогда не работало)
После: 1140 строк от 7 поставщиков за первый поиск

Код



Этап 2 — TTL инвалидация + крон-очистка кэша

    Дата: 2026-07-23
    Коммит: d7bf7d5 (VPS) / 9b93fe4 (после rebase+push)


Что сделано ✅

    1.InstantSearcher::search() — TTL фильтр INTERVAL 4 HOUR (2 запроса: с брендом и без)
    2.InstantSearcher::saveResults() — деактивация is_active=0 перед INSERT для связки supplier+article+brand
    3.cron/cache_cleanup.php — новый файл, крон */30 * * * *
        Деактивирует строки старше 4 часов
        Удаляет строки старше 48 часов
        Лог: upload/logs/cache_cleanup_YYYY-MM-DD.log


Результат

Активных строк: 4266 (7 поставщиков)
autoeuro=2322, ixora=855, tatparts=643, berg=196,
rossko=171, moskvorechie=72, partkom=7
Крон: работает, 0.109 сек

## Этап 3 — Аналоги LC331 LYNXauto (ЗАВЕРШЁН ЧАСТИЧНО)

### Дата: 2026-07-23
### Коммиты: c082790, 6d1251e, (fix-stage2-commit)
### Ветка: fix/cache-pipeline-bugs

---

### Рабочий процесс (важно для следующих диалогов)
- Локально: **VS Code** → `C:\Users\user\Documents\GitHub\liderws`
- Все правки делаются в **VS Code**, затем:

markdown




git add ... → git commit -m "..." → git push origin fix/cache-pipeline-bugs



- На сервере:



git pull --rebase origin fix/cache-pipeline-bugs



- Python-патчи на сервере НЕ используем — только git
- PHP на сервере: `which php` (не `/usr/bin/php8.2` — путь неверный)

---

### Найденные и исправленные баги ✅

| # | Файл | Баг | Фикс |
|---|------|-----|------|
| #1 | InstantSearcher.php | `WHERE article = ?` — точное совпадение, не находило `LC-331` → `lc331` | `WHERE REPLACE(REPLACE(REPLACE(LOWER(article),'-',''),' ',''),'.','') = ?` |
| #2 | verify_start.php | Браузер ждал 15 сек пока идёт live-поиск | `fastcgi_finish_request()` — ответ немедленно, поиск в фоне |
| #3 | parts-search/index.php | `require "stage2_search.php"` (старый файл) | Уже был исправлен ранее — `stage2_search_v2.php` стр. 134 ✅ |
| #4 | stage2_search_v2.php | Нет `window.location.reload()` после verify done | Уже был исправлен ранее ✅ |
| #5 | stage2_search_v2.php | Дублирующий `echo` блок (div#instant-notice + JS) — параллельно с `_hybrid_notice.php` | Удалён блок 3707 символов |
| #6 | stage2_search_v2.php | `$instantCacheMs` не определена в `_hybrid_notice.php` | Добавлен алиас `$instantCacheMs = $instantMs` |

### SQL исправления применены ✅
```sql
-- 3480 строк нормализованы: LC-331 → lc331
UPDATE b_supplier_stock 
SET article = LOWER(REPLACE(REPLACE(REPLACE(article,'-',''),' ',''),'.',''))
WHERE article REGEXP '[^a-z0-9]';


Баги НЕ исправлены ⏳ — начать с них в Этапе 4


Баг #9 — КРИТИЧНЫЙ: JS lazy-loader перезаписывает аналоги



Файл: parts-search/index.php, JS-блок внизу

Проблема: через 800мс после загрузки срабатывает старый AJAX:



fetch('/local/ajax/analog_search.php?...')
    .then(data => { analogContainer.innerHTML = data.html; }) // перезаписывает гибридные результаты

javascript




Фикс: в начало IIFE добавить guard:



if (<?= !empty($verifyTaskHash) ? 'true' : 'false' ?>) return;


Баг #10 — $skipLive не инициализирована



Файл: parts-search/stage2_search_v2.php

Проблема: $skipLive появляется только внутри if ($useHybrid) → PHP Notice если $useHybrid = false

Фикс: добавить рядом с $verifyTaskHash = '':



$skipLive = false;


Баг #11 — pending задачи при каждом ?verified=1



Файл: parts-search/stage2_search_v2.php

Проблема: каждый reload (включая ?verified=1) создаёт новый task_hash в БД → зависшие pending засоряют таблицу

Фикс: обернуть блок создания задачи:



if (!isset($_GET['verified'])) {
    $verifyTaskHash = md5(...);
    // INSERT INTO b_search_verify_tasks ...
}

php





Текущее состояние кэша


## b_supplier_stock для LC331 LYNXauto:
  autoeuro  = 150 строк (brand_normalized = lynxauto)
  tatparts  =  78 строк
  ixora     =  20 строк
  berg      =   2 строки
  ИТОГО: 245 активных строк, age = 0 мин ✅

## b_search_verify_tasks:
  Последние задачи: status=done, saved=1751~2707 ✅

Код



Результат в UI


Найдено: 1 позиция (32 предложения от всех поставщиков) ✅
Аналоги: НЕ показываются (Баг #9 — lazy-loader перезаписывает пустым)
⚡ баннер "из кэша": НЕ показывается (echo-блок удалён, но _hybrid_notice
                      не отображается — нужна диагностика)

STAGES — Дополнение: Этап 4 (завершён)




✅ Что сделано в Этапе 4



Баг #9 — JS lazy-loader (ЗАКРЫТ)



Добавлен guard в IIFE:


if (<?= (!empty($verifyTaskHash) && !empty($analogGroups)) ? 'true' : 'false' ?>) return;



Баг #10 — $skipLive не инициализирована (ЗАКРЫТ)



$skipLive = false; // рядом с $verifyTaskHash = '';



Баг #11 — pending задачи при ?verified=1 (ЗАКРЫТ)
INSERT обёрнут в if (!isset($_GET['verified'])) { ... }




Баг #12 — ResultBuilder дропал LYNX/lc331 (ЗАКРЫТ)



} elseif ($gan === $normTargetArt && $gbn !== $normTargetBrand) {
    $exactGroups[$key] = $g; // тот же артикул, другой бренд → exact
} else {
    $analogGroups[$key] = $g;
}

php




Баг #13 — analog_search.php брал кэш вместо live (ЗАКРЫТ)
Убран cache-first блок, всегда live через FullSearchLauncher




Баг #14 — Контейнер аналогов не рендерился при пустом $analogGroups (ЗАКРЫТ)
Добавлен fallback-div с шапкой




Баг #15 — Мало складов (лимиты OfferAggregator) (ЗАКРЫТ)



$aggregator = new OfferAggregator(50, 500);
$builder = new ResultBuilder(300, 50, 500);

STAGES — Этап 5 (завершён)




## Этап 5 — Не все поставщики в блоках точного совпадения и аналогов

### Дата: 2026-07-23
### Ветка: fix/cache-pipeline-bugs

---

### Задачи
1. В блоке с искомым бренд+артикул не все поставщики
2. В блоке аналогов не все поставщики

---

### Диагностика — что выяснили

#### Кэш LC331 LYNXauto на момент этапа:
| Поставщик | Строк в кэше | Причина выпадения |
|-----------|-------------|-------------------|
| autoeuro | 150 | лимит 50 обрезал до 50 |
| tatparts | 69 | лимит 50 обрезал до 50 |
| ixora | 19 | проходил в лимит |
| berg | 2 | проходил в лимит |
| moskvorechie | 0 по lc331 | нет LC331 в каталоге (есть Bosch 0451103316 → аналог) |
| rossko | 0 по lc331 | нет LC331 в каталоге (есть Bosch 0451103316 → аналог) |
| partkom | 0 по lc331 | нет LC331 в каталоге |
| shatem | 0 всего | не зарегистрирован в init.php — отложено |

#### Москворечье и Росско в аналогах — норма ✅
Bosch 0451103316 = аналог LC331. Оба поставщика правильно
попадают в блок "Аналоги (149 поз.)", а не в точное совпадение.

---

### Найденные и исправленные баги ✅

#### Баг #16 — Live-ветка: OfferAggregator и ResultBuilder без параметров

Файл: parts-search/stage2_search_v2.php, строки ~151-153

Было два пути в одном файле с разными лимитами:
```php
## // Гибридная ветка (строки ~92-93) — было правильно:
$aggregator = new OfferAggregator(50, 500);
$builder    = new ResultBuilder(300, 50, 500);

## // Live-ветка (строки ~151-153) — БАГ: дефолтные параметры:
$aggregator  = new OfferAggregator();   // 10/поставщик, 40 всего
$builder     = new ResultBuilder();     // 15/поставщик, 60 всего

Код




Эффект: live-поиск показывал только 121 склад из 240 доступных.
autoeuro обрезался с 150 до 50 (-100), tatparts с 69 до 50 (-19).




Фикс: унифицировали оба места — увеличили лимиты:



## // Обе ветки теперь одинаково:
$aggregator  = new OfferAggregator(200, 1000);
$offerGroups = $aggregator->aggregate($allResults);
$builder     = new ResultBuilder(300, 200, 1000);

php




Результат: 240 складов вместо 121 (+119) ✅





НЕ решено — перенесено в Этап 6


#	Проблема	Причина
shatem	Не зарегистрирован в init.php	Отложено, требует ShatempConnector
berg	2 строки в кэше, но не виден в списке	Возможно is_sched=1 (под заказ → в конец)




Состояние кэша на конец этапа


autoeuro:     17722 active (150 по lc331)
tatparts:      3460 active  (69 по lc331)
ixora:         5556 active  (19 по lc331)
berg:           305 active   (2 по lc331)
moskvorechie:   101 active   (0 по lc331 — норма)
rossko:         262 active   (0 по lc331 — норма)
partkom:          7 active   (0 по lc331 — норма)
shatem:           0          (не зарегистрирован)

sql



UI на конец этапа



✅ LC331 LYNXauto: 1 позиция, 240 складов
✅ Аналоги: 149 позиций
✅ Москворечье и Росско корректно в блоке аналогов (Bosch 0451103316)
✅ Мгновенный кэш ~5-9 мс

## Этап 6 — Коллизия UNIQUE KEY stock_id + пропавшие поставщики

### Дата: 2026-07-24
### Ветка: fix/cache-pipeline-bugs

---

### Проблема
Берг, Москворечье и Росско не показывались в выдаче LC331 LYNXauto.
ПартКом — только 3 склада из 115 для W7008 MANN-FILTER.

---

### Диагностика

#### 1. Берг: 2 строки в кэше вместо 79
- `UNIQUE KEY (supplier_code, stock_id)` — Берг переиспользует `warehouse.id`
- `BERG KZN` (id=46) используется и для `w811/80` (MANN) и для `lc331` (LYNX)
- `ON DUPLICATE KEY UPDATE` обновлял только price/quantity/name, не трогая article/brand
- 76 из 79 строк Берга перезаписывали чужие строки → терялись

#### 2. ПартКом: 3 строки в кэше вместо 115
- `placementId=136` одинаков для 81 позиции на складе «МСК склад: Наб. Челны»
- Даже с `|article` в stock_id получалось `136|w7008` для всех → коллизия
- Дополнительно: неверный пароль в `init.php` (`8dTpDU8}Myr)*&` вместо `LidGates16`)

#### 3. Москворечье и Росско — та же проблема коллизий

---

### Исправления

#### Баг #17 — stock_id не уникален (коллизия UNIQUE KEY)
Файл: `InstantSearcher.php`

Было:
```php
$stockId = !empty($item->stockId)
    ? (string)$item->stockId . '|' . $articleNorm
    : md5(...);

markdown




Стало:



$stockId = md5($item->source . '|' . $articleNorm . '|' . $brandNorm . '|' .
    ($item->warehouse ?? '') . '|' . round($item->price, 2) . '|' . ($item->stockId ?? ''));

php




MD5-хеш гарантирует уникальность для каждой комбинации поставщик+артикул+бренд+склад+цена+stockId.



Баг #18 — ON DUPLICATE KEY UPDATE не обновлял article/brand



Файл: InstantSearcher.php




Было:



# ON DUPLICATE KEY UPDATE
price = VALUES(price), quantity = VALUES(quantity), 
name = VALUES(name), last_updated = NOW(), is_active = 1

sql




Стало:



# ON DUPLICATE KEY UPDATE
article = VALUES(article), brand = VALUES(brand), brand_normalized = VALUES(brand_normalized),
price = VALUES(price), quantity = VALUES(quantity), 
name = VALUES(name), last_updated = NOW(), is_active = 1

sql



Баг #19 — Неверный пароль PartKom в init.php



Файл: local/php_interface/init.php




Было: 'PASSWORD' => '8dTpDU8}Myr)*&'
Стало: 'PASSWORD' => 'LidGates16'





Результат


Поставщик	Было в кэше (w7008)	Стало
autoeuro	150	150 ✅
berg	2	118 ✅
partkom	3	115 ✅
moskvorechie	0	26 ✅
rossko	0	5 ✅
ixora	21	21 ✅



Общий кэш: 21363 + 2213 + 1182 + 1033 + 414 + 330 + 251 = ~26786 активных строк

## Этап 7 — Поставщики в блоке аналогов (НЕ ЗАВЕРШЁН)

### Дата: 2026-07-24
### Ветка: fix/cache-pipeline-bugs

### Задача
В блоке аналогов мало поставщиков — не подтягиваются все, в отличие от блока точного совпадения.
Пример: SUFIX SP1041 (кросс-номер для MANN W81180) показывает только ТатПартс (7 складов)
при том что в MySQL данные есть у всех 7 поставщиков (autoeuro=80, berg=30, ixora=13, moskvorechie=8 и т.д.)

### Диагностика

#### Все поставщики находят SP1041 через прямые API-запросы ✅
Berg: 150₽ BERG MSK | Москворечье: 184₽ 699шт | Росско: 150₽ 65+288шт

#### FullSearchLauncher — раздвоение поведения
- launch("SUFIX", "SP1041") → 2500+ результатов, все 7 поставщиков ✅
- launch("MANN FILTER", "W81180") → SP1041 НЕ возвращается (sufix|sp1041: 0 записей) ❌

#### Причина
Поставщики не возвращают SP1041 как кросс-номер для W81180 в API-ответах.
BrandMap знает sufix|w81180 (тот же артикул, другой бренд), но не sufix|sp1041 (истинный кросс).

#### Роль lazy-loader
Гибридный поиск (InstantSearcher) не отдаёт кросс-номера — только точные совпадения артикула.
Lazy-loader → analog_search.php — единственный путь загрузки кросс-номеров в аналогах.
Без него аналоги пустые.

### Исправленные баги ✅

#### Баг #20а — Заниженные лимиты OfferAggregator/ResultBuilder
Файл: local/ajax/analog_search.php
Было: OfferAggregator(50,1000) + ResultBuilder(800,50,1000)
Стало: OfferAggregator(200,1000) + ResultBuilder(800,200,1000)
Эффект: выровнены с stage2_search_v2.php

#### Баг #20б — Чанкинг в MultiCurlExecutor (20 → без чанкинга)
Файл: local/php_interface/lib/Search/Common/MultiCurlExecutor.php
~800 запросов Фазы 2 → 40 чанков → последние не успевали (deadline_exceeded)
Убран чанкинг: все запросы параллельно через executeChunk($requests, ...)

#### Баг #20в — brandMap → analogMap в Фазе 2
Файл: local/php_interface/lib/Search/Stage2/FullSearchLauncher.php
Записи BrandMap (кроме точного совпадения) добавлены в $analogMap с пустым sources
чтобы Фаза 2 дозапросила их у всех поставщиков

#### Баг #20г — Дозагрузка кросс-номеров из MySQL
Файл: local/ajax/analog_search.php
После launch() — сбор брендов из результатов + BrandMap → SQL-запрос в b_supplier_stock
→ слияние с $allResults с дедупликацией. При дублях показывало 354 поз./6763 складов.
После исправления ключа дедупликации — 113 поз./802 склада (ключ стал слишком агрессивным).

### НЕ решено ⏳

1. SUFIX SP1041 не показывает всех поставщиков
2. Ключ дедупликации требует тонкой настройки (было много → стало мало)
3. Двойная надпись «Догружаем» (hybrid_notice + lazy-loader) — но убирать нельзя

### Состояние кода
| Файл | Изменения |
|------|-----------|
| analog_search.php | лимиты 200 + MySQL-дозагрузка + дедупликация |
| MultiCurlExecutor.php | чанкинг убран |
| FullSearchLauncher.php | brandMap→analogMap |
| index.php | без изменений |
| stage2_search_v2.php | без изменений |

### Направление для Этапа 8
1. Дедупликация: вернуть более мягкий ключ (source|stockId|price|warehouse) — 
   дубли допустимы (launch и MySQL отдают одни и те же склады с разным supplierName),
   но duplicates из-за разного stockId — это ложные дубли
2. Лог в analog_search.php: какие бренды собраны, сколько строк из MySQL, сколько добавлено
3. Рассмотреть: для каждого кросс-номера из BrandMap делать launch(бренд, артикул) вместо MySQL

## Этап 8 — Дедупликация, Phase 2, прогрессивная загрузка (ЗАВЕРШЁН)

### Дата: 2026-07-24
### Коммиты: (последние)
### Ветка: fix/cache-pipeline-bugs

---

### Задачи
1. Починить дедупликацию в analog_search.php — ключ стал слишком агрессивным
2. SUFIX SP1041 должен показывать всех поставщиков
3. Двойная надпись «Догружаем» — косметика

---

### Найденные и исправленные баги ✅

#### Баг #21 — `$r` (leftover) вместо `$row` в ключе дедупликации
Файл: `local/ajax/analog_search.php`, строка 232
Переменная `$r` — leftover от предыдущего `foreach ($allResults as $r)`.
Все MySQL-строки получали одинаковый `$dk` → проходила только первая.
Фикс: `$r->source` → `$row['supplier_code']`, все поля берутся из `$row`.

#### Баг #22 — `$seenKeys` не инициализирован
Файл: `local/ajax/analog_search.php`
Массив `$seenKeys` не создавался перед MySQL-дозагрузкой.
Фикс: инициализация + заполнение из launch-результатов до MySQL-цикла.

#### Баг #23 — Посторонние запчасти в аналогах
Файл: `local/ajax/analog_search.php`
MySQL-запрос брал ВСЕ артикулы брендов из BrandMap (TATSUMI TEA115 попадал в аналоги к MANN W81180).
Фикс: фильтр `$crossArticles` — добавлена проверка `if (!isset($crossArticles[$na])) continue;`
в MySQL-цикле. Пропускаются только артикулы, реально фигурирующие в BrandMap как кросс-номера.

#### Баг #24 — curl_multi захлёбывался на 1200+ запросах (P2: +0)
Файл: `local/php_interface/lib/Search/Common/MultiCurlExecutor.php`
1264 запроса в одном curl_multi_exec → большинство не успевали → P2 всегда +0.
Фикс: чанкинг по 100 запросов, каждый чанк со своим `$chunkTime`.

#### Баг #25 — FullSearchLauncher: разделение на launchPhase1 + executePhase2
Файл: `local/php_interface/lib/Search/Stage2/FullSearchLauncher.php`
Добавлены методы `launchPhase1()` и `executePhase2()` для прогрессивной загрузки.
`launch()` теперь вызывает оба метода. Phase 2 state сериализуется в файл.

#### Баг #26 — example: объект → массив для JSON-сериализации
Файл: `FullSearchLauncher.php`
`$analogMap[$na]['example'] = $item` — SearchResultItem не сериализуется.
Фикс: `['brand'=>$item->brand, 'article'=>$item->article]`

#### Баг #27 — execute в вебе нестабилен
Файлы: `analog_search.php`, `analog_poll.php`
`register_shutdown_function` + `exec` не срабатывали из веба.
Фикс: `analog_poll.php` при первом вызове сам запускает Phase 2 через `flock`.

---

### Прогрессивная загрузка аналогов (новая архитектура)

markdown




Пользователь → поиск
│
├─ 0мс: ⚡ Мгновенный кэш (InstantSearcher)
│
├─ 800мс: JS lazy-loader → analog_search.php?phase=fast
│   ├─ Phase 1 (launchPhase1) + MySQL-дозагрузка → 3-5 сек
│   ├─ Рендерит HTML → аналоги видны СРАЗУ
│   ├─ Сохраняет P1 + P2-state в JSON-файл (upload/cache/search/p2/)
│   └─ JSON: {p2_hash, p2_pending: true}
│
├─ JS polling каждые 2 сек → analog_poll.php?hash=xxx
│   └─ Первый вызов: сам запускает Phase 2 → 10-15 сек → {ready: true}
│
└─ JS: fetch analog_search.php?phase=final&p2_hash=xxx
├─ Читает P1 + P2 из state-файла
├─ Рендерит ПОЛНЫЙ HTML (все хелперы, цены, доставка, маскировка)
└─ Заменяет таблицу + обновляет счётчики



### Новые файлы
| Файл | Назначение |
|------|-----------|
| `local/ajax/analog_p2_exec.php` | CLI-запуск Phase 2 (для отладки) |
| `local/ajax/analog_poll.php` | Polling + автозапуск Phase 2 через flock |

### Изменённые файлы
| Файл | Что изменено |
|------|-------------|
| `analog_search.php` | Режимы phase=fast / phase=final, P1 state после MySQL, дедупликация |
| `FullSearchLauncher.php` | launchPhase1(), executePhase2(), example→массив |
| `MultiCurlExecutor.php` | Чанкинг по 100 |
| `parts-search/index.php` | JS: phase=fast + polling + final fetch |

### Результат
- P2: было +0 → стало +1758, +3692, +6821 складов
- Аналоги: видны через 3-5 сек, полные через 10-15 сек
- Баннеры: «Догружаем остальных поставщиков...» → «✅ Загружены все поставщики»
- Посторонние запчасти (TATSUMI TEA115) больше не попадают

### НЕ решено ⏳ — на Этап 9
1. Холодный первый поиск сбоит — не всегда включается live-поиск
   (проверено: Москворечье для GOODWILL AG290 есть в live API, но не в кэше → не показывается)
2. Ускорение первого холодного поиска

Итог: корень проблемы и почему этап провален

Настоящая причина

REG.RU shared-хостинг блокирует прямое выполнение PHP-файлов через nginx. Проверено:

Запрос	Результат
curl https://liderws.ru/local/ajax/_test.php (просто <?php echo "test";)	0 байт
curl https://liderws.ru/parts-search/_test.php	0 байт
curl https://liderws.ru/bitrix/tools/_test.php	0 байт
php -r (CLI)	✅ работает
php -S built-in server	✅ работает

analog_search.php через CLI выдаёт 368KB HTML. Через nginx — 0 байт. Без sudo нельзя посмотреть логи php-fpm или изменить конфиг nginx.

Что делали — по этапам

#	Действие	Результат
1	Убрали JS-guard в index.php	✅ guard убран
2	set_time_limit(120) перед FullSearchLauncher	✅ синтаксис ок, но проблема не в таймауте
3	Убрали синхронный FullSearchLauncher при холодном	❌ сломало страницу, откатили
4	brandMap таймаут 6→15с	✅ но brandMap всё равно не пишется
5	Спиннер вместо «Нет предложений»	❌ сломало PHP-синтаксис, несколько правок
6	AJAX-шлюз через index.php	❌ конфликт функций + двойной ? в URL
7	function_exists обёртки	✅ синтаксис исправлен
8	Исправили ? на & в JS URL	✅
9	Но итог: спиннер висит	❌ nginx всё равно не исполняет PHP

Этап 9 — Поиск всех поставщиков при холодном поиске
====================================================
Статус: НЕ РЕШЁН
Блокер: REG.RU shared-хостинг — nginx не исполняет .php файлы при прямом доступе.
        Без root-доступа нельзя посмотреть логи php-fpm или изменить конфиг nginx.

Симптомы:
  - Тёплый поиск: все поставщики ✅ (lazy-loader работает)
  - Холодный поиск: только 1 поставщик (autoeuro из кэша)
  - JS lazy-loader вызывает analog_search.php → nginx возвращает 0 байт

Что нужно для фикса:
  1. Доступ к конфигу nginx (sudo) — чтобы разрешить PHP в /local/ajax/
  2. ИЛИ перенос AJAX-эндпоинтов в место, где nginx разрешает PHP
  3. ИЛИ использование Bitrix-роутинга с правильной изоляцией функций

Рекомендация: обратиться в поддержку REG.RU с вопросом,
почему nginx не исполняет PHP в /local/ajax/*.php

Задачи Этапа 9




1.Исправить «левые» бренды в блоке точного совпадения при холодном поиске


2.Ускорить холодный поиск (отложено — слишком рискованно)






Диагностика


Проблема «левых» брендов



При холодном поиске (например MANN W81180) в блок точного совпадения попадают
посторонние бренды с тем же артикулом: Big Filter, Clean Filters, Alco Filter.




Поток данных при холодном поиске:



Пользователь → выбор бренда → stage2_search_v2.php
  → InstantSearcher::search() → кэш пуст
  → $skipLive = false
  → FullSearchLauncher::launch("MANN FILTER", "W81180")
  → API поставщиков возвращают ВСЕ результаты по артикулу w81180
     (Big Filter W81180, Clean Filters W81180, ...)
  → OfferAggregator::aggregate() → группировка по brand|article
  → ResultBuilder::build() → БАГ в строке 44

Код




При тёплом поиске:



→ InstantSearcher::search() → фильтр WHERE brand_normalized = 'mannfilter'
  → ТОЛЬКО MANN-FILTER → проблема не видна

Код





Найденный и исправленный баг ✅


Баг #28 — ResultBuilder: elseif слишком широкий — все бренды с тем же артикулом попадают в exact



Файл: local/php_interface/lib/Search/Stage2/ResultBuilder.php, строки 42-48




Проблема:



if (($gbn === $normTargetBrand && $gan === $normTargetArt) || $key === $exactKey) {
    $exactGroups[$key] = $g;
} elseif ($gan === $normTargetArt && $gbn !== $normTargetBrand) {
    // Тот же артикул, другой бренд → в exactGroups
    $exactGroups[$key] = $g;  // ← ЛЮБОЙ бренд с тем же артикулом!
}

php




Это условие (добавлено в Баге #12 для случая LYNX ↔ LYNXauto) захватывало ВСЕ бренды
с одинаковым артикулом. Clean Filters с артикулом W81180 попадал в точное совпадение MANN.




Исправление:



if (($gbn === $normTargetBrand && $gan === $normTargetArt) || $key === $exactKey) {
    $exactGroups[$key] = $g;
} elseif ($gan === $normTargetArt && $gbn !== $normTargetBrand) {
    // Только варианты ТОГО ЖЕ бренда (LYNX ⊂ LYNXauto), не посторонние
    if (stripos($gbn, $normTargetBrand) !== false || stripos($normTargetBrand, $gbn) !== false) {
        $exactGroups[$key] = $g;
    } else {
        $analogGroups[$key] = $g;
    }
} else {
    $analogGroups[$key] = $g;
}

php




Логика: stripos проверяет, является ли один бренд подстрокой другого:





lynxauto содержит lynx → exact ✅


cleanfilters не содержит mannfilter → analog ✅






Попутные исправления


Опечатка strtomorrow → strtotime



Файл: parts-search/index.php, строка 64
В функции calcDelivery() была опечатка в вызове strtomorrow('tomorrow 11:00').
Исправлено на strtotime('tomorrow 11:00').



Восстановление index.php после неудачной правки __pending__



Файл: parts-search/index.php
В процессе Этапа 9 была попытка добавить спиннер для холодного поиска через флаг __pending__.
Правка сломала PHP-синтаксис (смешение <?php if(): ?> с if(){}). Файл восстановлен
до рабочего состояния через git checkout.





Что НЕ сделано (отложено на Этап 10)




1.


Ускорение холодного поиска через fastcgi_finish_request() — слишком рискованно
для одного коммита. Текущий холодный поиск: BrandMap (3-6 сек) + FullSearchLauncher (15-25 сек)
= 20-30 сек. После первого поиска данные в кэше → тёплый поиск < 100 мс.





2.


Спиннер вместо пустых блоков — попытка реализации через __pending__ флаг провалилась
из-за конфликта синтаксисов PHP. Нужен другой подход.









Изменённые файлы в Этапе 9


Файл	Изменение
local/php_interface/lib/Search/Stage2/ResultBuilder.php	Баг #28: сужен elseif — проверка stripos для вариантов бренда; добавлен else для настоящих аналогов
parts-search/index.php	Исправлена опечатка strtomorrow → strtotime; восстановлен после неудачной правки




Результат




✅ «Левые» бренды (Clean Filters, Big Filter) больше не попадают в блок точного совпадения


✅ Варианты одного бренда (LYNX/LYNXauto) корректно остаются в exact


✅ Настоящие кросс-номера уходят в блок аналогов


✅ Сайт работает без ошибок (проверено: MANN-FILTER W81180, 773KB HTML)


⏳ Скорость холодного поиска — отложено на Этап 10






Направление для Этапа 10




1.Подключение нового поставщика с учётом накопленного опыта

## Этап 10 — Подключение Авторусь + Автопитер (ЗАВЕРШЁН)

### Дата: 2026-07-27
### Коммиты: (последние)
### Ветка: fix/cache-pipeline-bugs

---

### Новые поставщики

| Поставщик | Тип API | Авторизация | getCode() | Префикс склада |
|-----------|---------|-------------|-----------|----------------|
| **Авторусь** | REST/JSON (ABCP) | `userlogin` + `userpsw` (MD5) | `autoruss` | `ar` |
| **Автопитер** | SOAP/XML | Cookie: `Authorization(UserID, Password)` | `autopiter` | `ap` |

### Особенности реализации

#### Авторусь
- Хост: `autorus.public.api.abcp.ru`
- Поиск брендов: `/search/brands/?number=`
- Поиск предложений: `/search/articles/?number=&brand=`
- Сроки доставки: `deliveryPeriod` (часы) → +48ч запас; 0 → min 48ч
- Возвратность: из поля `noReturn`

#### Автопитер
- Хост: `service.autopiter.ru/v2/price`
- Поиск брендов: `FindCatalog(Number)` → `SearchCatalogModel[]`
- Поиск предложений: `GetPriceId(ArticleId, SearchCross)` → `PriceSearchModel[]`
- Сроки доставки: `NumberOfDaysSupply` → +2 дня запас
- **Весь товар невозвратный** (`returnable = false` всегда)
- Авторизация через Cookie: `Authorization(UserID, Password, Save=true)` → `Set-Cookie: AuthCoocies=...`

### Новые файлы
| Файл | Назначение |
|------|-----------|
| `local/php_interface/lib/Supplier/AutorussConnector.php` | Коннектор Авторусь (REST) |
| `local/php_interface/lib/Supplier/AutopiterConnector.php` | Коннектор Автопитер (SOAP) |

### Изменённые файлы
| Файл | Изменение |
|------|-----------|
| `local/php_interface/init.php` | Регистрация `AutorussConnector` + `AutopiterConnector` |
| `local/ajax/analog_search.php` | Добавлены в `getAjaxFactory()` для маскировки складов в аналогах |
| `parts-search/index.php` | CSS-стикеры `.source-tag--autoruss` (оранжевый) + `.source-tag--autopiter` (синий) |

### Исправленные баги
| # | Баг | Файл | Фикс |
|---|-----|------|------|
| — | Автопитер: `ensureAuth()` не вызывался → HTTP 500 | `AutopiterConnector.php` | Добавлен вызов в `searchBrands/searchByBrandArticle/search` |
| — | Автопитер: `article_id` не передавался в `parseBrandsResponse` | `AutopiterConnector.php` | Добавлено поле `article_id` |
| — | Автопитер: `resolveArticleId` не находил бренд (MANN-FILTER ≠ Mann+Hummel) | `AutopiterConnector.php` | Добавлен fuzzy match + fallback |
| — | Автопитер: Cookie не передавался в `buildSearchRequest/buildBrandsRequest` → MultiCurlExecutor слал без Cookie | `AutopiterConnector.php` | Cookie добавляется в заголовки запроса |
| — | Авторусь: склады не маскировались в аналогах | `analog_search.php` | Добавлен в `getAjaxFactory()` |
| — | Авторусь: `deliveryPeriod=0` → 96ч вместо 48ч (двойной буфер) | `AutorussConnector.php` | Исправлена логика: 0→48ч, >0→+48ч |
| — | Авторусь: `warehouse` содержал префикс дважды | `AutorussConnector.php` | Убран дублирующий префикс |

### Результат
- **10 поставщиков** работают: autoeuro, autopiter, autoruss, berg, ixora, moskvorechie, partkom, rossko, shatem, tatparts
- Авторусь: цены от 432 ₽, наличие до 40 шт, доставка 2-4 дня
- Автопитер: цены от 193 ₽, наличие до 1225 шт, доставка 5-6 дней
- Поиск W81180 MANN-FILTER: все поставщики возвращают результаты
- Кэш: ~3000 активных строк

---

## Этап 11 — СЛЕДУЮЩИЙ (не начат)

### Задача
Решить проблему холодного поиска: когда запись уже есть в кэше, она не обновляется при новых запросах к поставщикам, а отдаётся как была записана.

### Проблема
- Тёплый поиск: `InstantSearcher::search()` → MySQL кэш → данные выдаются мгновенно, но никогда не обновляются
- Холодный поиск: 20-30 сек ожидания
- Нет механизма принудительного обновления «протухших» данных

### Возможные решения
1. **TTL с принудительным обновлением**: если данные старше N минут, запускать фоновый live-поиск и обновлять кэш асинхронно, а пользователю отдавать кэш мгновенно
2. **Отказ от кэша для критичных полей**: цены и наличие всегда запрашивать live (риск: медленно)
3. **Гибрид с инвалидацией**: отдавать кэш + показывать «обновляем цены» и через 2-3 сек заменять live-данными

### С чего начать
```bash
# 1. Текущий TTL и логика InstantSearcher
grep -n "INTERVAL\|is_active\|last_updated\|TTL\|4 HOUR" /var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/InstantSearcher.php

# 2. Как сейчас работает холодный/тёплый поиск в stage2_search_v2.php
grep -n "skipLive\|InstantSearcher\|cachedItems\|FullSearchLauncher" /var/www/u3564357/data/www/liderws.ru/parts-search/stage2_search_v2.php | head -30