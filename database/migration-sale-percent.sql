-- Sale is now a percentage off, not a fixed dollar price. Storing the percent
-- lets the discount apply to the base price AND every variant's own price at
-- once, instead of a flat dollar figure that only made sense for the base.
-- The old sale_price column is left in place (unused) — safe to drop later.
ALTER TABLE products ADD COLUMN sale_percent TINYINT UNSIGNED NULL AFTER sale_price;
