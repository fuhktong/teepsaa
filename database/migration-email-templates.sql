CREATE TABLE IF NOT EXISTS email_templates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50)  NOT NULL UNIQUE,
    label        VARCHAR(120) NOT NULL,
    tokens       VARCHAR(255) NULL,
    subject_km   VARCHAR(255) NOT NULL,
    subject_en   VARCHAR(255) NOT NULL,
    heading_km   VARCHAR(255) NOT NULL,
    heading_en   VARCHAR(255) NOT NULL,
    body_km      TEXT NOT NULL,
    body_en      TEXT NOT NULL,
    cta_km       VARCHAR(120) NULL,
    cta_en       VARCHAR(120) NULL,
    sort_order   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
