ALTER TABLE buyers
    ADD COLUMN khan VARCHAR(100) NULL AFTER address_notes,
    ADD COLUMN sangkat VARCHAR(100) NULL AFTER khan;

ALTER TABLE businesses
    ADD COLUMN address_notes VARCHAR(255) NULL AFTER address,
    ADD COLUMN khan VARCHAR(100) NULL AFTER address_notes,
    ADD COLUMN sangkat VARCHAR(100) NULL AFTER khan;
