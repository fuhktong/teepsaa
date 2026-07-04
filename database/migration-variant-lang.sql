ALTER TABLE product_option_types  ADD COLUMN name_km  VARCHAR(64)  NULL AFTER name;
ALTER TABLE product_option_values ADD COLUMN label_km VARCHAR(64)  NULL AFTER label;
ALTER TABLE product_variants      ADD COLUMN label_km VARCHAR(100) NULL AFTER label;
