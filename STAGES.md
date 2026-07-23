# STAGES — журнал этапов проекта liderws.ru

---

## тап 1 — иагностика и устранение потерь результатов поиска

- **ата**: 2026-07-23
- **етка**: `fix/cache-pipeline-bugs`
- **оммит**: TBD (см. ниже)

### то сделано
1. рочитан весь код цепочки поиска (14 файлов): SearchService, MultiCurlExecutor, HybridStage2Orchestrator, CachingFullSearchLauncher, FullSearchLauncher, InstantSearcher, BrandNormalizer, SupplierFactory, RosskoConnector + ajax-файлы
2. бнаружен ROOT CAUSE: `b_supplier_stock` = 0 строк — кэш никогда не работал ни одного дня
3.  #1 (ROOT): `bind_param('sssssdissiis')` — 12 типов при 13 переменных → silent fail на каждом вызове saveResults()
4.  #2: UNIQUE KEY (supplier_code, stock_id='') — коллизия при пустом stock_id → сохранялась только 1 строка на поставщика
5.  #3: `array_slice(brands, 0, 3)` — поставщик выпадал если нужный бренд стоял на позиции 4+
6.  #4: `array_slice(items, 0, 3)` — обрезка до 3 офферов вместо всех складов; пустые catch скрывали ошибки

### айлы изменены
- `local/php_interface/lib/Search/InstantSearcher.php`
  - fix bind_param: 'sssssdissiis' → 'sssssdissiiis' (добавлен тип 'i' для multiplicity)
  - добавлена генерация stock_id через md5-хеш когда поле пустое
  - добавлен error_log при провале execute()
- `local/php_interface/lib/Search/SearchService.php`
  - array_slice(brands, 0, 3) → array_slice(brands, 0, 10)
  - убраны оба array_slice(items, 0, 3)
  - добавлен error_log во все пустые catch-блоки

### езультат (ожидаемый после деплоя)
- b_supplier_stock начинает заполняться после первого же поиска
- се 8 поставщиков отдают полные результаты без обрезки
- шибки парсинга коннекторов видны в PHP error_log

### люч для следующего диалога
СЩ : репо https://github.com/dafol11942-blip/liderws ветка fix/cache-pipeline-bugs коммит TBD
