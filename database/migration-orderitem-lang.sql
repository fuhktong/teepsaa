ALTER TABLE order_items ADD COLUMN product_name_km  VARCHAR(255) NULL AFTER product_name;
ALTER TABLE order_items ADD COLUMN variant_label_km VARCHAR(100) NULL AFTER variant_label;
