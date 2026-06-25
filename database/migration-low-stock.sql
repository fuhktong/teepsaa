ALTER TABLE products
    ADD COLUMN low_stock_threshold    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    ADD COLUMN low_stock_notified_at  DATETIME NULL;
