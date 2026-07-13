-- Soft delete for businesses: deleted businesses keep their row (and their orders,
-- reviews, coupons, penalties) for accounting. "Deleting" sets deleted_at + approved = -1.
ALTER TABLE businesses ADD COLUMN deleted_at DATETIME NULL;
