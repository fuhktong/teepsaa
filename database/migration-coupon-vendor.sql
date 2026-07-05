-- Vendor-owned discount codes. business_id NULL = admin/sitewide coupon
-- (platform absorbs the discount, unchanged behavior). business_id set =
-- vendor-created coupon, scoped to only that vendor's items in the cart —
-- the discount comes out of that vendor's own payout, not the platform's.

ALTER TABLE coupons
    ADD COLUMN business_id INT UNSIGNED NULL AFTER code,
    ADD FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;
