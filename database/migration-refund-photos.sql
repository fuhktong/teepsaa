-- Photos attached to a refund (nationwide shipping, Part 4 — refund evidence).
-- One row per photo. The files live with the dispatch photos in
-- teepsaa-private/order-photos/ and are served by api/order-photo.php.
--
--   party  who took it: 'buyer' or 'vendor'
--   stage  'request'    the buyer's photos of the problem, sent with the request
--          (later)      the vendor's reply to a dispute, the vendor's photos of
--                       a return that came back used, empty or different
--
-- Run BEFORE deploying the code that reads it — every refund page selects it.
CREATE TABLE IF NOT EXISTS refund_photos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    party      ENUM('buyer', 'vendor') NOT NULL,
    stage      VARCHAR(20)  NOT NULL,
    filename   VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_photos_order (order_id),
    CONSTRAINT fk_refund_photos_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
