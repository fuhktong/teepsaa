-- Run AFTER migration-refund-rejected-at.sql.
-- Un-strands orders left at status='refund_rejected' by the old reject path.
-- They were all 'delivered' before the refund was requested, so that is where
-- they go back to; the rejection moves into refund_rejected_at.

-- 1. Look first — these are the stranded orders.
SELECT id, public_id, status, delivered_at, refund_requested_at
FROM orders
WHERE status = 'refund_rejected';

-- 2. Fix them.
UPDATE orders
SET status = 'delivered',
    refund_rejected_at = COALESCE(refund_requested_at, NOW())
WHERE status = 'refund_rejected';
