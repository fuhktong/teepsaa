-- Alerts for buyers: a saved (wishlist) item dropping in price or coming back
-- into stock, and new products from a shop the buyer follows. Sent by
-- cron/buyer-alerts.php.

-- What the buyer was last told about each saved item. NULL until the cron
-- first sees the row, so saving an item never sends anything by itself.
ALTER TABLE wishlists
    ADD COLUMN alert_price    DECIMAL(10, 2) NULL DEFAULT NULL,
    ADD COLUMN alert_in_stock TINYINT(1)     NULL DEFAULT NULL,
    ADD COLUMN alert_sent_at  DATETIME       NULL DEFAULT NULL;

-- Shops a buyer follows. seen_product_id is the newest product the buyer has
-- been told about; anything live above it is new.
CREATE TABLE IF NOT EXISTS business_follows (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_user_id    INT UNSIGNED NOT NULL,
    business_id      INT UNSIGNED NOT NULL,
    seen_product_id  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_buyer_business (buyer_user_id, business_id),
    INDEX idx_business (business_id),
    FOREIGN KEY (buyer_user_id) REFERENCES buyers(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
