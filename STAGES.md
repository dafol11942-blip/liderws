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

Код



Этап 3 — СЛЕДУЮЩИЙ (не начат)

Задача

Аналоги теряются: LC331 LYNXauto — 1 поставщик вместо 6+

С чего начать

# 1. Что в кэше по LC331
mysql -u u3564357_liderws -p"S)'uAp]3.\$@wWd-" u3564357_liderws_db -e "
SELECT supplier_code, article, brand, price, quantity
FROM b_supplier_stock
WHERE article LIKE '%LC331%'
ORDER BY supplier_code, price LIMIT 30;"

# 2. Смотрим SearchCacheManager
cat /var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/SearchCacheManager.php

# 3. Смотрим OfferAggregator
cat /var/www/u3564357/data/www/liderws.ru/local/php_interface/lib/Search/Stage2/OfferAggregator.php

---

## Этап 3: LC331 LYNXauto — 1 поставщик вместо 6+ (ЗАВЕРШЁН ЧАСТИЧНО)

### Коммиты этапа:
- c082790 — fix: normalize article in cache (LC-331→lc331), reload on verify done, fastcgi early response
- 6d1251e — debug: log cache search params

### Найденные баги:

#### Баг #1 — ИСПРАВЛЕН ✅
**Файл:** `local/php_interface/lib/Search/InstantSearcher.php`
**Проблема:** `WHERE article = ?` — точное совпадение. Поставщики хранят `LC-331`, запрос ищет `lc331` → 5 из 6 не находило.
**Фикс:** `WHERE REPLACE(REPLACE(REPLACE(LOWER(article),'-',''),' ',''),'.','') = ?`
**Плюс:** `saveResults()` теперь сохраняет нормализованный артикул (`$articleNorm`) вместо сырого.
**Плюс:** `deactivateStmt` тоже использует нормализованный артикул.

#### Баг #2 — ИСПРАВЛЕН ✅
**Файл:** `local/php_interface/ajax/verify_start.php`
**Проблема:** Браузер ждал 15 сек пока идёт live-поиск (блокирующий).
**Фикс:** Добавлен `fastcgi_finish_request()` — ответ браузеру отправляется немедленно, поиск продолжается в фоне.

#### Баг #3 — НЕ ИСПРАВЛЕН ⏳
**Файл:** `parts-search/index.php` строка 134
**Проблема:** `require "stage2_search.php"` — подключается СТАРЫЙ файл без гибридной логики. Все правки в `stage2_search_v2.php` не работают.
**Нужно:** заменить на `require __DIR__ . "/stage2_search_v2.php";`
**Внимание:** Правильный `index.php` лежит в `/parts-search/index.php` (не в корне).

#### Баг #4 — НЕ ИСПРАВЛЕН ⏳
**Файл:** `parts-search/stage2_search_v2.php` (JS блок)
**Проблема:** После `verify done` JS только меняет текст, не делает `window.location.reload()`.
**Нужно:** добавить `setTimeout(function(){ window.location.reload(); }, 500);`

### SQL исправления применены ✅:
- 3480 строк нормализованы: `LC-331` → `lc331`
- Запрос: `UPDATE b_supplier_stock SET article = LOWER(REPLACE(REPLACE(REPLACE(article,'-',''),' ',''),'.','')) WHERE article REGEXP '[^a-z0-9]';`

### Структура таблицы b_supplier_stock (ключевые поля):
- UNIQUE KEY: `(supplier_code, stock_id)`
- INDEX: `article`, `brand_normalized`, `supplier_code`, `last_updated`
- Колонка нормализованного артикула **отсутствует** (поиск через REPLACE в WHERE)

### Архитектура гибридного поиска:

markdown




index.php → stage2_search_v2.php (надо переключить!)
→ InstantSearcher::search() — кэш из b_supplier_stock
→ если пусто: FullSearchLauncher (live API)
→ JS: POST verify_start.php → live-поиск → saveResults()
→ JS: GET verify_poll.php каждые 500мс → при done: reload()



### Первоочередные задачи следующего этапа:
1. Исправить `parts-search/index.php` строка 134: `stage2_search.php` → `stage2_search_v2.php`
2. Добавить `window.location.reload()` в JS блок `stage2_search_v2.php`
3. Убедиться что гибридный баннер `⚡ из кэша` появляется при повторном поиске
4. Проверить что 6+ поставщиков показываются из кэша

### Credentials (для нового диалога):
- Репо: https://github.com/dafol11942-blip/liderws
- Ветка: fix/cache-pipeline-bugs
- Сервер: u3564357@server17, путь: /var/www/u3564357/data/www/liderws.ru
- БД: u3564357_liderws_db, таблица кэша: b_supplier_stock (4266+ строк)