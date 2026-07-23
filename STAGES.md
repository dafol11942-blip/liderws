# STAGES — журнал этапов проекта liderws.ru

---

## Этап 1 — Диагностика и устранение потерь результатов поиска

- **Дата**: 2026-07-23
- **Ветка**: `fix/cache-pipeline-bugs`
- **Коммиты**: `798fae9` → `5788238` → финальный (VPS)

### Симптом
Таблица `b_supplier_stock` = 0 строк навсегда.
Каждый поиск делал 30-секундные запросы к 7 поставщикам вместо мгновенного чтения кэша.

### Найденные баги (от корня к симптому)

| # | Файл | Баг | Эффект |
|---|------|-----|--------|
| ROOT | `analog_search.php` | `saveResults()` никогда не вызывался | кэш = 0 строк навсегда |
| 1 | `InstantSearcher.php` | `bind_param('sssssdissiis')` — 12 типов при 13 переменных | silent fail на каждом INSERT |
| 2 | `InstantSearcher.php` | пустой `stock_id` → коллизия UNIQUE KEY | сохранялась 1 строка на поставщика |
| 3 | `SearchService.php` | `array_slice(brands, 0, 3)` | поставщик выпадал если бренд на позиции 4+ |
| 4 | `SearchService.php` | `array_slice(items, 0, 3)` | обрезка до 3 офферов вместо всех |

### Что сделано

1. `analog_search.php` — добавлена cache-first логика:
   - `InstantSearcher::search()` → MySQL (< 100ms) если кэш есть
   - иначе → `FullSearchLauncher::launch()` + `saveResults()` в MySQL
2. `InstantSearcher.php` — исправлен `bind_param` формат + генерация `stock_id` hash
3. `SearchService.php` — убраны `array_slice` лимиты, добавлен `error_log`

### Результат (подтверждён)
До:    TOTAL_ROWS = 0  (никогда не работало)
После: TOTAL_ROWS = 1140 от 7 поставщиков за один поиск
autoeuro=469, ixora=273, berg=139, rossko=97,
tatparts=93, moskvorechie=62, partkom=7
### Следующие задачи
- [ ] Проверить скорость второго поиска (должен отвечать < 200ms из кэша)
- [ ] Настроить TTL инвалидации кэша (сейчас `is_active` не обновляется)
- [ ] Проверить `InstantSearcher::search()` — правильно ли фильтрует по бренду
- [ ] Добавить крон для очистки устаревших записей (`last_updated < NOW() - INTERVAL 24 HOUR`)

### Ключ для следующего диалога
СЛЕДУЮЩИЙ ДИАЛОГ:

репо: https://github.com/dafol11942-blip/liderws
ветка: fix/cache-pipeline-bugs
этап завершён: кэш b_supplier_stock работает (1140 строк)
следующая задача: TTL инвалидация + проверка скорости второго поиска