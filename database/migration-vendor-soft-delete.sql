-- Soft delete for vendors (mirrors migration-buyer-soft-delete.sql).
-- Deleting a vendor account used to hard-DELETE the vendors row, which cascaded
-- two hops away everything you need for accounting:
--     DELETE vendor
--       -> fk_businesses_vendor  ON DELETE CASCADE  -> deletes their businesses
--            -> orders.business_id ON DELETE CASCADE -> deletes ALL their orders
-- Those orders belong to buyers too, so one vendor deletion wiped buyers' order
-- history. Now "deleting" a vendor sets deleted_at (login blocked, re-register
-- revives the same row) and their businesses are soft-deleted, so orders survive.
ALTER TABLE vendors ADD COLUMN deleted_at DATETIME NULL;

-- Safety net: even a stray hard DELETE of a vendor or business must never be able
-- to cascade away orders. Flip the orders -> businesses FK from ON DELETE CASCADE
-- to RESTRICT so the database itself refuses to delete a business that still has
-- orders (which in turn aborts any cascading vendor delete).
--
-- The orders.business_id constraint was created unnamed in migration.sql, so MySQL
-- auto-generated its name (typically orders_ibfk_3). Find the real name with:
--    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--    WHERE TABLE_NAME='orders' AND COLUMN_NAME='business_id'
--          AND REFERENCED_TABLE_NAME='businesses';
-- and substitute it below.
ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_3;
ALTER TABLE orders
    ADD CONSTRAINT fk_orders_business FOREIGN KEY (business_id)
    REFERENCES businesses(id) ON DELETE RESTRICT;
