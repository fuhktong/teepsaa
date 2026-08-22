-- Teepsaa — Announcements (bulk email to buyers / vendors)
-- Run once.
--
-- An announcement is composed once in the admin panel, then "queued": every
-- eligible recipient is frozen into announcement_recipients at that moment so
-- the audience can't drift mid-send, and a cron worker walks the queue one
-- message at a time (config/mail.php opens a fresh SMTP connection per email,
-- so a web request can never deliver a whole list).

CREATE TABLE IF NOT EXISTS announcements (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audience         ENUM('buyers','vendors','both') NOT NULL DEFAULT 'buyers',
    -- 'promotional' honours the unsubscribe flag; 'service' is an operational
    -- notice (policy change, downtime) and goes to everyone eligible.
    kind             ENUM('promotional','service') NOT NULL DEFAULT 'promotional',
    subject_km       VARCHAR(255) NOT NULL,
    subject_en       VARCHAR(255) NOT NULL,
    heading_km       VARCHAR(255) NOT NULL,
    heading_en       VARCHAR(255) NOT NULL,
    body_km          TEXT NOT NULL,
    body_en          TEXT NOT NULL,
    cta_km           VARCHAR(120)  NULL,
    cta_en           VARCHAR(120)  NULL,
    cta_url          VARCHAR(255)  NULL,
    status           ENUM('draft','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
    total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count       INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    queued_at        DATETIME NULL,
    finished_at      DATETIME NULL,
    KEY idx_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_recipients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    role            ENUM('buyer','vendor') NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    email           VARCHAR(255) NOT NULL,
    status          ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    error           VARCHAR(255) NULL,
    sent_at         DATETIME NULL,
    UNIQUE KEY uniq_recipient (announcement_id, role, user_id),
    KEY idx_queue (announcement_id, status, id),
    CONSTRAINT fk_arcp_announcement FOREIGN KEY (announcement_id)
        REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marketing opt-out. NULL token means "not generated yet"; it is minted the
-- first time the account is sent a promotional email. Transactional email
-- (orders, refunds, password resets) ignores unsubscribed_at entirely.
ALTER TABLE buyers
    ADD COLUMN unsubscribed_at   DATETIME NULL,
    ADD COLUMN unsubscribe_token CHAR(32) NULL,
    ADD UNIQUE KEY uniq_buyer_unsub_token (unsubscribe_token);

ALTER TABLE vendors
    ADD COLUMN unsubscribed_at   DATETIME NULL,
    ADD COLUMN unsubscribe_token CHAR(32) NULL,
    ADD UNIQUE KEY uniq_vendor_unsub_token (unsubscribe_token);
