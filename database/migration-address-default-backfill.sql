-- Repairs buyers who saved an address but never pressed "set as default".
-- Their buyer_addresses rows were all is_default = 0, so the buyers table
-- (which cart and checkout read) stayed empty and they were bounced back
-- to settings with "please set your delivery address".
-- New addresses are now defaulted automatically in address-book-action.php.

-- 1. Promote the oldest saved address for any buyer with no default at all
UPDATE buyer_addresses ba
JOIN (
    SELECT MIN(id) AS id
    FROM buyer_addresses
    WHERE buyer_user_id IN (
        SELECT buyer_user_id FROM (
            SELECT buyer_user_id
            FROM buyer_addresses
            GROUP BY buyer_user_id
            HAVING MAX(is_default) = 0
        ) AS no_default
    )
    GROUP BY buyer_user_id
) AS pick ON pick.id = ba.id
SET ba.is_default = 1;

-- 2. Copy the default address onto the buyers table, but only where that
--    row is still blank — never overwrite an address already in use
UPDATE buyers b
JOIN buyer_addresses ba ON ba.buyer_user_id = b.id AND ba.is_default = 1
SET b.house_number  = ba.house_number,
    b.address       = ba.address,
    b.address_notes = ba.address_notes,
    b.khan          = ba.khan,
    b.sangkat       = ba.sangkat,
    b.city          = ba.city,
    b.lat           = ba.lat,
    b.lng           = ba.lng
WHERE b.address IS NULL AND b.khan IS NULL AND b.house_number IS NULL;
