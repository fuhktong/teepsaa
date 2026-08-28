-- Admin audit log + the accountability columns the payout rules depend on.
-- Run once. Safe to re-run only after dropping admin_audit.
--
-- Why this exists: before this migration nothing in the schema recorded WHICH
-- admin approved a business, confirmed a payment or released a payout. The
-- only `created_by` anywhere was vendor_penalties. Real money leaves the
-- business at admin/payouts-action.php and left no trace of who sent it.

-- ── The log ───────────────────────────────────────────────────────────────
-- Deliberately has NO foreign key to admins: deleting an admin must never
-- delete the record of what they did. admin_email is a denormalised snapshot
-- taken at write time so the row still names a person after the account is
-- gone.
--
-- Append-only by convention — no application code issues UPDATE or DELETE
-- against this table, and none ever should. If you need to prune it, archive
-- to a file first.
CREATE TABLE IF NOT EXISTS admin_audit (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id     INT UNSIGNED NULL,          -- NULL = system/cron, not a person
    admin_email  VARCHAR(255) NULL,          -- snapshot; survives admin deletion
    action       VARCHAR(64)  NOT NULL,      -- e.g. 'payout.complete'
    entity_type  VARCHAR(32)  NULL,          -- 'order' | 'payment' | 'business' | ...
    entity_id    INT UNSIGNED NULL,
    detail       TEXT NULL,                  -- JSON: amounts, before/after, reasons
    ip           VARCHAR(45)  NULL,          -- 45 = max INET6_NTOA length
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created  (created_at),
    INDEX idx_audit_admin    (admin_id, created_at),
    INDEX idx_audit_action   (action, created_at),
    INDEX idx_audit_entity   (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Accountability columns ────────────────────────────────────────────────
-- The audit log is the narrative record; these columns are the enforcement
-- record. The two-person payout rule reads payments.confirmed_by directly
-- rather than doing archaeology on the log.
ALTER TABLE payments
    ADD COLUMN confirmed_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN confirmed_at DATETIME     NULL AFTER confirmed_by;

ALTER TABLE orders
    ADD COLUMN paid_out_by INT UNSIGNED NULL,
    ADD COLUMN paid_out_at DATETIME     NULL;

ALTER TABLE businesses
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER approved_at;

-- ── Vendor bank-change hold ───────────────────────────────────────────────
-- Set whenever a vendor changes their ABA QR or account name. Payouts to that
-- vendor are held for BANK_CHANGE_HOLD_SECONDS afterwards so a hijacked vendor
-- account cannot redirect a payout that is already queued.
ALTER TABLE vendors
    ADD COLUMN aba_changed_at DATETIME NULL AFTER aba_account_name;

-- ── Self-dealing flags ────────────────────────────────────────────────────
-- Comma-separated reason codes computed at checkout (see checkout/confirm.php).
-- Advisory only: nothing is blocked, but the payout screen shows a warning so
-- nobody wires money to what is plainly the buyer's own shop by accident.
ALTER TABLE orders
    ADD COLUMN self_deal_flags VARCHAR(255) NULL,
    ADD COLUMN buyer_ip        VARCHAR(45)  NULL;
