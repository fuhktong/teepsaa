ALTER TABLE products
    ADD COLUMN delivery_method ENUM('bike','tuktuk') NOT NULL DEFAULT 'bike' AFTER weight_g;
