CREATE TABLE IF NOT EXISTS job_applications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      INT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(40)  NULL,
    message     TEXT         NULL,
    resume_file VARCHAR(255) NULL,
    status      ENUM('new','reviewed','shortlisted','rejected') NOT NULL DEFAULT 'new',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE
);
