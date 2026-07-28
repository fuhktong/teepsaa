-- City column for vendors + buyers. The marketplace is Phnom Penh-only for now;
-- the column exists so we can add Siem Reap and other cities later without a
-- schema change (just extend config/cities.php). Backfills every existing
-- address to Phnom Penh since that is where all current addresses are.

ALTER TABLE businesses       ADD COLUMN city VARCHAR(100) NULL AFTER sangkat;
ALTER TABLE buyers           ADD COLUMN city VARCHAR(100) NULL AFTER sangkat;
ALTER TABLE buyer_addresses  ADD COLUMN city VARCHAR(100) NULL AFTER sangkat;

UPDATE businesses      SET city = 'Phnom Penh' WHERE city IS NULL;
UPDATE buyers          SET city = 'Phnom Penh' WHERE city IS NULL AND (khan IS NOT NULL OR address IS NOT NULL OR house_number IS NOT NULL);
UPDATE buyer_addresses SET city = 'Phnom Penh' WHERE city IS NULL;
