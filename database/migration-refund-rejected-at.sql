-- Rejecting a refund used to overwrite orders.status with 'refund_rejected',
-- destroying the 'delivered' it came from — the order could never reach
-- 'completed' and dropped out of the payout queue, so the vendor was never
-- paid for a delivered order whose refund was denied.
--
-- The rejection now lives in its own column: status goes back to 'delivered'
-- and refund_rejected_at records that it happened.
ALTER TABLE orders ADD COLUMN refund_rejected_at DATETIME NULL DEFAULT NULL AFTER refunded_at;
