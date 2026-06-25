-- Run once on existing databases
ALTER TABLE orders ADD COLUMN dispatched_at DATETIME NULL AFTER status;
