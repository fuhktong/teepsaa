-- Teepsaa — Account settings fields
-- Run once. Adds contact, address, and lang fields.

ALTER TABLE buyers
    ADD COLUMN phone         VARCHAR(20)  NULL               AFTER name,
    ADD COLUMN address       VARCHAR(255) NULL,
    ADD COLUMN address_notes VARCHAR(255) NULL,
    ADD COLUMN lat           DECIMAL(10,7) NULL,
    ADD COLUMN lng           DECIMAL(10,7) NULL,
    ADD COLUMN lang          ENUM('en','km') NOT NULL DEFAULT 'en';

ALTER TABLE vendors
    ADD COLUMN phone VARCHAR(20) NULL AFTER name,
    ADD COLUMN lang  ENUM('en','km') NOT NULL DEFAULT 'en';

ALTER TABLE admins
    ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT '' AFTER email;
