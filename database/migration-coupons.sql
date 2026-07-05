-- Discount codes / coupons. Discount is a platform-funded marketing cost:
-- vendor_payout and royalty_amount on `orders` are computed on the
-- pre-discount subtotal (unaffected by coupons) — the discount only
-- reduces what the buyer pays and, in turn, the platform's own margin.

CREATE TABLE IF NOT EXISTS coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    type ENUM('percent','fixed') NOT NULL,
    value DECIMAL(10, 2) NOT NULL,
    min_order DECIMAL(10, 2) NOT NULL DEFAULT 0,
    max_uses INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coupon_uses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES buyers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

ALTER TABLE orders
    ADD COLUMN coupon_id INT UNSIGNED NULL AFTER buyer_notes,
    ADD COLUMN coupon_code VARCHAR(32) NULL AFTER coupon_id,
    ADD COLUMN discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0 AFTER coupon_code,
    ADD FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL;
