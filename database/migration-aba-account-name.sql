-- Vendor's bank account holder name, exactly as ABA displays it on scan.
-- Shown next to the payout QR in admin so employees can verify the recipient.
ALTER TABLE vendors
    ADD COLUMN aba_account_name VARCHAR(100) NULL DEFAULT NULL AFTER aba_qr;
