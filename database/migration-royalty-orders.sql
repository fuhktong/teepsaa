ALTER TABLE orders
    ADD COLUMN royalty_rate   DECIMAL(5,4)  NULL AFTER vendor_delivery_bonus,
    ADD COLUMN royalty_amount DECIMAL(10,2) NULL AFTER royalty_rate,
    ADD COLUMN vendor_payout  DECIMAL(10,2) NULL AFTER royalty_amount;
