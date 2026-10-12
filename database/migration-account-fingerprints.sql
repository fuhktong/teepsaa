-- Fingerprints of suspended accounts, and sign-ups that match them.
-- See config/fingerprint.php. `hash` is an HMAC of the normalised email or
-- phone — never the email or phone itself.
CREATE TABLE account_fingerprints (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role        ENUM('buyer','vendor') NOT NULL,
    account_id  INT UNSIGNED NOT NULL,
    kind        ENUM('email','phone') NOT NULL,
    hash        CHAR(64) NOT NULL,
    reason      VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fingerprint (role, account_id, kind, hash),
    KEY idx_lookup (kind, hash)
);

CREATE TABLE account_matches (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('buyer','vendor') NOT NULL,
    account_id      INT UNSIGNED NOT NULL,
    fingerprint_id  INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     DATETIME NULL,
    reviewed_by     INT UNSIGNED NULL,
    UNIQUE KEY uniq_match (role, account_id, fingerprint_id),
    KEY idx_unreviewed (reviewed_at),
    CONSTRAINT fk_match_fingerprint FOREIGN KEY (fingerprint_id)
        REFERENCES account_fingerprints(id) ON DELETE CASCADE
);
