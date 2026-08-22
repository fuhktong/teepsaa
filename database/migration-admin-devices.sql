-- Teepsaa — "Remember this device" for admin login
-- Run once.
--
-- Split-token scheme: the cookie holds "selector:validator". Only the selector
-- is stored in the clear, so the lookup is an indexed equality match; the
-- validator is compared as a SHA-256 hash with hash_equals(). A stolen cookie
-- therefore cannot be reconstructed from the database.
--
-- The validator is rotated on every use. prev_validator_hash keeps the
-- previous one alive for a couple of minutes so two requests firing at the
-- same moment (a page and its background poll) do not look like a theft.

CREATE TABLE IF NOT EXISTS admin_devices (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id            INT UNSIGNED NOT NULL,
    selector            CHAR(24) NOT NULL,
    validator_hash      CHAR(64) NOT NULL,
    prev_validator_hash CHAR(64) NULL,
    rotated_at          DATETIME NULL,
    label               VARCHAR(80)  NULL,
    user_agent          VARCHAR(255) NULL,
    ip                  VARCHAR(45)  NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at        DATETIME NULL,
    expires_at          DATETIME NOT NULL,
    UNIQUE KEY uniq_selector (selector),
    KEY idx_admin (admin_id, expires_at),
    CONSTRAINT fk_adevice_admin FOREIGN KEY (admin_id)
        REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
