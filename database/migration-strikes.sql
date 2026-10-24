-- Strikes (nationwide shipping, Part 4 — H, part 3).
--
--   vendor_strikes            one row per strike an admin gives a shop. `level`
--                             is the step it triggered (1-4, config/strikes.php).
--                             A strike counts for 12 months (expires_at), unless
--                             an admin removes it (revoked_at).
--   businesses.suspended_until  set by a 3rd strike: the hourly cron lifts the
--                             shop suspension at this time. NULL means a
--                             suspension only an admin lifts, as before.
--   email template vendor_strike  the email the shop gets with each strike.
--
-- Run BEFORE deploying the code that reads it — the admin vendor page selects it.

CREATE TABLE vendor_strikes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id  INT UNSIGNED NOT NULL,
    level        TINYINT UNSIGNED NOT NULL,
    reason       VARCHAR(255) NOT NULL,
    created_by   INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL DEFAULT NULL,
    revoked_by   INT UNSIGNED NULL DEFAULT NULL,
    KEY idx_strikes_business (business_id, expires_at),
    CONSTRAINT fk_strikes_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE businesses
    ADD COLUMN suspended_until DATETIME NULL DEFAULT NULL AFTER suspended_at;

INSERT IGNORE INTO email_templates
    (template_key, label, tokens, subject_km, subject_en, heading_km, heading_en, body_km, body_en, cta_km, cta_en, sort_order)
VALUES
('vendor_strike', 'Strike given (vendor)', '{name}, {business}, {strike}, {reason}, {penalty_km}, {penalty_en}',
 'ហាងរបស់អ្នកទទួលបានការព្រមាន {strike} ក្នុងចំណោម 4', 'Your shop has received strike {strike} of 4',
 'ការព្រមាន {strike} ក្នុងចំណោម 4', 'Strike {strike} of 4',
 'ជម្រាបសួរ {name}, ហាង <strong>{business}</strong> ទទួលបានការព្រមានលើកទី {strike} ក្នុងចំណោម 4។<br><br><strong>មូលហេតុ៖</strong> {reason}<br><br><strong>អ្វីដែលកើតឡើងឥឡូវនេះ៖</strong> {penalty_km}<br><br>ការព្រមាននីមួយៗមានសុពលភាព 12 ខែ។ ការព្រមានលើកទី 3 ផ្អាកហាងរយៈពេល 30 ថ្ងៃ ហើយលើកទី 4 ផ្អាកគណនីជាអចិន្ត្រៃយ៍។ ប្រសិនបើអ្នកគិតថានេះជាកំហុស សូមទាក់ទងផ្នែកជំនួយ។',
 'Hi {name}, <strong>{business}</strong> has received strike {strike} of 4.<br><br><strong>Reason:</strong> {reason}<br><br><strong>What happens now:</strong> {penalty_en}<br><br>Each strike counts for 12 months. A 3rd strike suspends the shop for 30 days, and a 4th suspends the account for good. If you believe this is a mistake, contact support.',
 'ទាក់ទងផ្នែកជំនួយ', 'Contact support', 0);
