-- "Item is used, empty or different": the vendor's second button when a
-- return arrives (products/return-disputed.php, api/v1/refund-disputed.php).
--
-- The order still moves to 'return_received' — the item is back — but
-- return_disputed_at holds the refund until an admin decides from the
-- vendor's photos (refund_photos, party 'vendor', stage 'return'). The admin
-- then refunds in full, in part, or not at all.
--
-- refund_paid is what the buyer was actually sent: the full product price,
-- part of it, or NULL when nothing was paid. Older refunds leave it NULL and
-- were always the full product price.

ALTER TABLE orders
  ADD COLUMN return_disputed_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN refund_paid DECIMAL(10,2) NULL DEFAULT NULL;
