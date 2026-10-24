-- The vendor's reply to a disputed refund request: their own words and,
-- optionally, photos (refund_photos, party 'vendor', stage 'reply'). Open for
-- 48 hours from refund_requested_at, once; the admin decides after the reply
-- or once the 48 hours are up (config/refund-evidence.php).

ALTER TABLE orders
  ADD COLUMN vendor_reply TEXT NULL DEFAULT NULL,
  ADD COLUMN vendor_reply_at DATETIME NULL DEFAULT NULL;
