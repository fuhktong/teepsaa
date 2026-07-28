-- Featured product for the storefront (Phase 1 of the storefront redesign).
-- A vendor may mark ONE product per shop as "featured"; it renders as the big
-- hero tile at the top of the business storefront. One-per-shop is enforced in
-- app code (products/feature.php clears the shop's other featured flags before
-- setting a new one), so this is just a plain boolean.
ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0;

-- Speeds up the storefront's "find this shop's featured product" lookup.
CREATE INDEX idx_products_featured ON products (business_id, is_featured);
