-- Избранное: товары своего склада (TYPE=catalog, PRODUCT_ID — элемент IBLOCK_ID 42) и заказные
-- позиции у поставщика (TYPE=supplier) — у последних нет стабильного ID элемента (все офферы
-- одного поставщика шарят один синтетический элемент SUPPLIER_ORDER_<supplier>, см.
-- local/ajax/order_from_supplier.php), поэтому для них хранится снимок цены/остатка/срока
-- доставки на момент добавления/последнего обновления (CONFIRMED_AT — якорь TTL 2ч, см.
-- CART_TTL_SECONDS в local/php_interface/init.php и local/ajax/favorites.php).
-- Выполнить ОДИН РАЗ вручную через Adminer/phpMyAdmin — код рантайма DDL не выполняет (тот же
-- принцип, что и для b_supplier_basket_order_meta/b_supplier_order/order_payment_hold).

CREATE TABLE IF NOT EXISTS b_user_favorites (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    USER_ID INT UNSIGNED NOT NULL,
    TYPE ENUM('catalog','supplier') NOT NULL,

    -- TYPE = catalog:
    PRODUCT_ID INT UNSIGNED NULL,               -- b_iblock_element.ID (IBLOCK_ID 42)

    -- TYPE = supplier (снимок с момента добавления/последнего обновления через recheck):
    ARTICLE VARCHAR(100) NULL,
    BRAND VARCHAR(100) NULL,
    SUPPLIER VARCHAR(50) NULL,
    SNAP_NAME VARCHAR(255) NULL,
    SNAP_PRICE_BASE DECIMAL(15,4) NULL,
    SNAP_PRICE_DISPLAY DECIMAL(15,4) NULL,
    SNAP_DELIVERY_DAYS INT NULL,
    SNAP_DELIVERY_LABEL VARCHAR(100) NULL,
    SNAP_DELIVERY_TIME VARCHAR(100) NULL,
    SNAP_QTY_AVAIL INT NULL,
    SNAP_RETURNABLE CHAR(1) NULL,
    CONFIRMED_AT INT UNSIGNED NULL,

    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- MySQL не считает NULL в UNIQUE конфликтующими друг с другом, поэтому эти два индекса
    -- корректно дедуплицируют каждый TYPE независимо, несмотря на общую таблицу.
    UNIQUE KEY uq_catalog (USER_ID, PRODUCT_ID),
    UNIQUE KEY uq_supplier (USER_ID, ARTICLE, BRAND, SUPPLIER),
    KEY idx_user (USER_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
