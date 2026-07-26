-- Soft delete for buyers (mirrors migration-business-soft-delete.sql).
-- A "deleted" buyer keeps their row — and therefore their orders, payments,
-- and reviews — for accounting. Deleting sets deleted_at; login is blocked for
-- such rows; re-registering with the same email revives the same row so the
-- retained orders stay linked to the buyer.
ALTER TABLE buyers ADD COLUMN deleted_at DATETIME NULL;

-- Safety net: a buyer must never be hard-deletable in a way that cascades away
-- their orders. Flip the orders → buyers FK from ON DELETE CASCADE to RESTRICT
-- so the database itself refuses any DELETE of a buyer that still has orders.
-- (Constraint name from migration-split-users.sql. If your live DB reports a
--  different name, find it with:
--    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--    WHERE TABLE_NAME='orders' AND COLUMN_NAME='buyer_user_id'
--          AND REFERENCED_TABLE_NAME='buyers';
--  and substitute it below.)
ALTER TABLE orders DROP FOREIGN KEY fk_orders_buyer;
ALTER TABLE orders
    ADD CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_user_id)
    REFERENCES buyers(id) ON DELETE RESTRICT;
