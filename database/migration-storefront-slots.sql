-- Storefront layout: the vendor arranges which products show on their shop page
-- and in what order. storefront_order = the slot number (1, 2, 3, …) the vendor
-- placed the product in; NULL = not slotted, so it auto-lists after the slotted
-- ones (ordered by name). The featured product (is_featured) is the hero and is
-- excluded from this grid. Requires migration-product-featured.sql first.

ALTER TABLE products ADD COLUMN storefront_order INT UNSIGNED NULL AFTER is_featured;
CREATE INDEX idx_products_storefront ON products (business_id, storefront_order);
