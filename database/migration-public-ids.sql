-- Adds a random public_id (UUID v4) to entities whose sequential int id is
-- exposed in URLs, so those URLs can't be enumerated. The int `id` column
-- stays as the internal primary key for joins/FKs — public_id is only used
-- for user-facing lookups (WHERE public_id = ?) and outgoing links.
--
-- Run this whole file once (e.g. via phpMyAdmin's SQL tab). It adds the
-- columns and backfills any pre-existing rows in one pass. New rows going
-- forward get their public_id from PHP's uuid_v4() at insert time
-- (config/db.php) — this file only needs to run once.

ALTER TABLE businesses ADD COLUMN public_id CHAR(36) NULL UNIQUE AFTER id;
ALTER TABLE products   ADD COLUMN public_id CHAR(36) NULL UNIQUE AFTER id;
ALTER TABLE orders     ADD COLUMN public_id CHAR(36) NULL UNIQUE AFTER id;

-- One-time backfill for rows that existed before this migration.
-- RAND()-based UUID v4 generator: fine for a one-off historical backfill;
-- new rows use the cryptographically random uuid_v4() in PHP instead.
UPDATE businesses
SET public_id = LOWER(CONCAT(
    LPAD(HEX(FLOOR(RAND() * 0xffffffff)), 8, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
    '4', LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 4 + 8)), 1, '0'), LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffffffffffff)), 12, '0')
))
WHERE public_id IS NULL;

UPDATE products
SET public_id = LOWER(CONCAT(
    LPAD(HEX(FLOOR(RAND() * 0xffffffff)), 8, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
    '4', LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 4 + 8)), 1, '0'), LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffffffffffff)), 12, '0')
))
WHERE public_id IS NULL;

UPDATE orders
SET public_id = LOWER(CONCAT(
    LPAD(HEX(FLOOR(RAND() * 0xffffffff)), 8, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffff)), 4, '0'), '-',
    '4', LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 4 + 8)), 1, '0'), LPAD(HEX(FLOOR(RAND() * 0xfff)), 3, '0'), '-',
    LPAD(HEX(FLOOR(RAND() * 0xffffffffffff)), 12, '0')
))
WHERE public_id IS NULL;
