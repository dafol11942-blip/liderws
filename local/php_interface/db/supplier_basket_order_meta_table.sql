-- order_meta для позиции корзины (см. SupplierOrderable::placeOrder()) хранился
-- в стандартном свойстве корзины b_sale_basket_props.VALUE — это VARCHAR(255)
-- Bitrix-ядра, менять нельзя. У АвтоЕвро offer_key сам по себе ~250 символов —
-- JSON {"offer_key":"..."} туда не помещается и молча обрезается, ломая JSON
-- при обратном чтении (заказ уходит без offer_key, хотя в корзине всё верно).
-- Выполнить ОДИН РАЗ вручную через Adminer/phpMyAdmin — код рантайма DDL не
-- выполняет (тот же принцип, что и для b_supplier_order/order_payment_hold).

CREATE TABLE IF NOT EXISTS b_supplier_basket_order_meta (
    BASKET_ITEM_ID INT UNSIGNED NOT NULL PRIMARY KEY,   -- b_sale_basket.ID
    ORDER_META_JSON MEDIUMTEXT NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
