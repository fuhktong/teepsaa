CREATE TABLE reviews (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT UNSIGNED NOT NULL UNIQUE,
    buyer_id      INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED NULL,
    business_id   INT UNSIGNED NOT NULL,
    rating        TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment       TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id)      REFERENCES buyers(id)      ON DELETE CASCADE,
    FOREIGN KEY (product_id)    REFERENCES products(id)    ON DELETE SET NULL,
    FOREIGN KEY (business_id)   REFERENCES businesses(id)  ON DELETE CASCADE
);
