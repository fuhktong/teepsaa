CREATE TABLE IF NOT EXISTS job_postings (
    id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150)     NOT NULL,
    location        VARCHAR(120)     NULL,
    employment_type VARCHAR(40)      NULL,
    description     TEXT             NULL,
    is_open         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
