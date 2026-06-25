ALTER TABLE buyers
    ADD COLUMN email_verified_at DATETIME NULL,
    ADD COLUMN verify_token VARCHAR(64) NULL;

ALTER TABLE vendors
    ADD COLUMN email_verified_at DATETIME NULL,
    ADD COLUMN verify_token VARCHAR(64) NULL;
