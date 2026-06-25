ALTER TABLE products
    ADD COLUMN weight_g SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER stock;

ALTER TABLE orders
    ADD COLUMN delivery_fee DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN vendor_delivery_bonus DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 AFTER delivery_fee,
    ADD COLUMN delivery_distance_km DECIMAL(5,2) NULL AFTER vendor_delivery_bonus,
    ADD COLUMN delivery_weight_g INT UNSIGNED NULL AFTER delivery_distance_km;
