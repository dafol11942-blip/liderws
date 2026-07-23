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