CREATE TABLE promo_codes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    uses_limit  INT UNSIGNED NULL,
    uses_count  INT UNSIGNED NOT NULL DEFAULT 0,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE businesses
    ADD COLUMN promo_code_id          INT UNSIGNED NULL,
    ADD COLUMN trial_starts_at        DATETIME NULL,
    ADD COLUMN trial_ends_at          DATETIME NULL,
    ADD COLUMN royalty_free_threshold DECIMAL(10,2) UNSIGNED NULL DEFAULT 100.00;

ALTER TABLE vendors
    ADD COLUMN promo_code VARCHAR(50) NULL DEFAULT NULL;
