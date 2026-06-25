ALTER TABLE orders
    ADD COLUMN refund_reason       TEXT NULL,
    ADD COLUMN refund_requested_at DATETIME NULL,
    ADD COLUMN refunded_at         DATETIME NULL;
