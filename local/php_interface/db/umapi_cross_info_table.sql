-- Кэш карточки товара для /search/ (UMAPI getCrossInfo — фото/описание/характеристики/
-- OEM/замены для конкретной пары артикул+бренд, см. local/ajax/umapi_cross_info.php).
-- Отдельная таблица от b_cross_index — та хранит связи аналогов (Analogs/pro), эта —
-- справочные данные по одной конкретной позиции (getCrossInfo), разные эндпоинты UMAPI.
-- RESPONSE_JSON = NULL/пусто означает подтверждённый пустой ответ UMAPI (см. TTL-логику
-- в local/ajax/umapi_cross_info.php — сетевые сбои в эту таблицу не пишутся вовсе).
-- Выполнить ОДИН РАЗ вручную через Adminer/phpMyAdmin — код рантайма DDL не выполняет
-- (тот же принцип, что и для b_user_favorites/b_supplier_basket_order_meta).

CREATE TABLE IF NOT EXISTS b_umapi_cross_info (
    ARTICLE_NORM VARCHAR(100) NOT NULL,
    BRAND_NORM VARCHAR(100) NOT NULL,
    RESPONSE_JSON MEDIUMTEXT NULL,
    FETCHED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ARTICLE_NORM, BRAND_NORM)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
