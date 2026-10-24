-- Refused and overdue returns (nationwide shipping, Part 4 — H, part 1).
--
--   return_dispatched_at  when the buyer sent the return. The overdue clock
--                         runs from here (cron/auto-confirm.php).
--   return_bounced_at     the buyer says the shop refused the parcel or it came
--                         back to them, with photos (refund_photos, party
--                         'buyer', stage 'bounced'). Held for an admin.
--   return_overdue_at     the shop never pressed Received or "used, empty or
--                         different" in time. Held for an admin.
--
-- Returns already on their way get a fresh clock from now, so none of them is
-- flagged the moment this runs.
--
-- Run BEFORE deploying the code that reads it — every refund page selects it.

ALTER TABLE orders
  ADD COLUMN return_dispatched_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN return_bounced_at    DATETIME NULL DEFAULT NULL,
  ADD COLUMN return_overdue_at    DATETIME NULL DEFAULT NULL;

UPDATE orders SET return_dispatched_at = NOW()
 WHERE status = 'return_dispatched' AND return_dispatched_at IS NULL;
