-- Удержание заказа до оплаты (см. план "Оплата в течение 15 минут" — заказ
-- с позицией от поставщика не отправляется поставщику сразу для не-менеджера,
-- ждёт оплаты ORDER_PAYMENT_HOLD_MINUTES минут, иначе отменяется автоматически).
-- Выполнить ОДИН РАЗ вручную через Adminer/phpMyAdmin — код рантайма DDL не
-- выполняет (тот же принцип, что и для b_supplier_order/b_supplier_order_item).
-- После создания таблицы ничего больше делать не нужно — order_create_handler.php,
-- local/php_interface/init.php (OnSaleOrderPaid) и
-- cron/payment_hold_sweep.php начнут её использовать сами.

CREATE TABLE IF NOT EXISTS b_supplier_order_payment_hold (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ORDER_ID INT UNSIGNED NOT NULL,             -- наш заказ, b_sale_order.ID
    DEADLINE DATETIME NOT NULL,                 -- CREATED_AT + 15 минут; после — auto-cancel
    DISPATCHED TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = оплата пришла, заказ ушёл поставщику
    CANCELED TINYINT(1) NOT NULL DEFAULT 0,      -- 1 = дедлайн истёк без оплаты, заказ отменён
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY UNQ_ORDER_ID (ORDER_ID),
    KEY IDX_PENDING (DISPATCHED, CANCELED, DEADLINE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
