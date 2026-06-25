CREATE TABLE IF NOT EXISTS wishlists (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_user_id   INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_buyer_product (buyer_user_id, product_id),
    INDEX idx_buyer (buyer_user_id)
);
