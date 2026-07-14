-- One-week vendor spot check after business approval.
-- approved_at: stamped when admin approves (reset on re-approval).
-- spot_checked_at: stamped when admin marks the spot check done.
-- Run BEFORE deploying the spot-check code (the digest cron queries these columns).

ALTER TABLE businesses
  ADD COLUMN approved_at DATETIME NULL AFTER approved,
  ADD COLUMN spot_checked_at DATETIME NULL AFTER approved_at;

-- Backfill: existing approved businesses enter the queue immediately
-- (approval date approximated by creation date).
UPDATE businesses SET approved_at = created_at WHERE approved = 1;
