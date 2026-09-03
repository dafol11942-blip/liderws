-- Таблицы для реальных заказов у поставщиков (см. план: "Реальный заказ у
-- поставщика при оформлении"). Выполнить ОДИН РАЗ вручную через Adminer/phpMyAdmin
-- — код рантайма DDL не выполняет (тот же принцип, что и для уже существующей
-- b_search_offer_cache). После создания таблиц ничего больше делать не нужно —
-- order_create_handler.php и cron/supplier_order_status_poll.php начнут их
-- использовать сами.

CREATE TABLE IF NOT EXISTS b_supplier_order (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ORDER_ID INT UNSIGNED NOT NULL,                    -- наш заказ, b_sale_order.ID
    SUPPLIER_CODE VARCHAR(32) NOT NULL,                 -- 'partkom', позже другие
    TEST_MODE TINYINT(1) NOT NULL DEFAULT 0,
    SUBMIT_STATUS VARCHAR(16) NOT NULL DEFAULT 'sent',  -- sent | error — результат placeOrder()
    REQUEST_JSON MEDIUMTEXT NULL,
    RESPONSE_JSON MEDIUMTEXT NULL,
    ERROR_MESSAGE TEXT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY IDX_ORDER_ID (ORDER_ID),
    KEY IDX_SUPPLIER_CODE (SUPPLIER_CODE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS b_supplier_order_item (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    SUPPLIER_ORDER_ID INT UNSIGNED NOT NULL,            -- FK -> b_supplier_order.ID
    BASKET_ITEM_ID INT UNSIGNED NULL,
    ARTICLE VARCHAR(64) NOT NULL,
    BRAND VARCHAR(128) NULL,
    QUANTITY INT NOT NULL,
    PRICE DECIMAL(18,2) NOT NULL,
    REFERENCE VARCHAR(64) NOT NULL,                     -- {orderId}_{basketItemId}, ключ для опроса motion
    SUPPLIER_ORDER_NUMBER VARCHAR(64) NULL,             -- orderNumber от поставщика (приходит при опросе)
    STATE_ID VARCHAR(32) NULL,                          -- state
    STATE_TEXT VARCHAR(255) NULL,                       -- stateTxt
    EXPECTED_DATE DATETIME NULL,
    GUARANTEED_DATE DATETIME NULL,
    STORE_COUNT INT NULL,
    RELEASE_COUNT INT NULL,
    REFUSAL_COUNT INT NULL,
    LAST_STATUS_JSON MEDIUMTEXT NULL,                   -- сырой ответ motion по этому reference
    LAST_CHECKED_AT DATETIME NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY IDX_SUPPLIER_ORDER_ID (SUPPLIER_ORDER_ID),
    KEY IDX_REFERENCE (REFERENCE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
