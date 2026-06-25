ALTER TABLE orders
  MODIFY COLUMN status ENUM('pending','paid','dispatched','delivered','completed','cancelled','refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected') NOT NULL DEFAULT 'pending',
  ADD COLUMN return_tracking_url VARCHAR(500) NULL DEFAULT NULL;
