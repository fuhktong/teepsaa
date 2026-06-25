-- Product variants (sizes, options) per product
CREATE TABLE IF NOT EXISTS product_variants (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id     INT UNSIGNED NOT NULL,
    label          VARCHAR(100) NOT NULL,
    price_override DECIMAL(10,2) UNSIGNED NULL,
    stock          INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Add variant reference to cart_items; drop old unique key so same product can be
-- added multiple times with different variants
ALTER TABLE cart_items
    DROP KEY unique_cart_item,
    ADD COLUMN variant_id INT UNSIGNED NULL AFTER product_id,
    ADD CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- Add variant snapshot columns to order_items
ALTER TABLE order_items
    ADD COLUMN variant_id    INT UNSIGNED NULL AFTER product_id,
    ADD COLUMN variant_label VARCHAR(100)  NULL AFTER variant_id,
    ADD CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL;
