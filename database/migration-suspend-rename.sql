-- Unified suspend system.
--
-- Before this, suspension was called "banned" in the schema and split by role
-- in the UI: admin/buyer.php said "Ban", admin/vendor.php said "Suspend", both
-- writing the same three columns. One word now — suspend — everywhere.
--
-- It also adds a SECOND, independent axis. Until now the only thing an admin
-- could switch off was the vendor's *account*, which blocks login. There was no
-- way to pull a bad storefront off the marketplace while leaving the vendor
-- able to sign in and fix it. `businesses.suspended` is that switch:
--
--   vendors.suspended    -> the person cannot sign in at all
--   businesses.suspended -> the shop and its products are hidden from buyers,
--                           but the vendor still signs in normally and can edit
--                           the business in Settings
--
-- The two are independent; either, both, or neither can be set.
--
-- NOTE ON `vendors`: the ban columns on this table were added by hand on the
-- server and never had a migration file (buyers got one —
-- migration-buyer-ban.sql — vendors did not). The CHANGE COLUMN statements
-- below therefore restate the full definition rather than reusing whatever is
-- there, which also normalises both tables to the same shape. If a statement
-- errors with "Unknown column", the rename already ran; skip that block.

-- 1. buyers: banned -> suspended
ALTER TABLE buyers
    CHANGE COLUMN banned     suspended         TINYINT(1)   NOT NULL DEFAULT 0,
    CHANGE COLUMN ban_reason suspension_reason VARCHAR(255) NULL,
    CHANGE COLUMN banned_at  suspended_at      DATETIME     NULL;

-- 2. vendors: banned -> suspended (same shape as buyers)
ALTER TABLE vendors
    CHANGE COLUMN banned     suspended         TINYINT(1)   NOT NULL DEFAULT 0,
    CHANGE COLUMN ban_reason suspension_reason VARCHAR(255) NULL,
    CHANGE COLUMN banned_at  suspended_at      DATETIME     NULL;

-- 3. businesses: the new storefront-level switch.
--    Defaults to 0 so every existing row stays live.
ALTER TABLE businesses
    ADD COLUMN suspended         TINYINT(1)   NOT NULL DEFAULT 0 AFTER approved,
    ADD COLUMN suspension_reason VARCHAR(255) NULL              AFTER suspended,
    ADD COLUMN suspended_at      DATETIME     NULL              AFTER suspension_reason;

-- Public listing queries filter on (approved = 1 AND suspended = 0), so give
-- that pair an index — every storefront and product page hits it.
CREATE INDEX idx_businesses_live ON businesses (approved, suspended);

-- ---------------------------------------------------------------------------
-- Email templates
-- ---------------------------------------------------------------------------
-- seed-email-templates.php only ever refreshes `label` and `tokens` on rows
-- that already exist, so wording changes have to be applied here by hand.

-- The two new storefront emails. INSERT IGNORE so re-running is safe and a
-- staff-edited row is never clobbered.
INSERT IGNORE INTO email_templates
    (template_key, label, tokens, subject_km, subject_en, heading_km, heading_en, body_km, body_en, cta_km, cta_en, sort_order)
VALUES
('business_suspended', 'Shop suspended (vendor)', '{name}, {business}, {reason}',
 'ហាងរបស់អ្នកត្រូវបានផ្អាក', 'Your shop has been suspended',
 'ហាងត្រូវបានផ្អាក', 'Shop suspended',
 'ជម្រាបសួរ {name}, ហាង <strong>{business}</strong> ត្រូវបានផ្អាក ហើយលែងបង្ហាញដល់អ្នកទិញទៀតហើយ។<br><br><strong>មូលហេតុ៖</strong> {reason}<br><br>គណនីរបស់អ្នកនៅដំណើរការធម្មតា — អ្នកនៅតែអាចចូលប្រើបាន។ សូមកែព័ត៌មាននៅក្នុងការកំណត់អាជីវកម្ម រួចទាក់ទងផ្នែកជំនួយ ដើម្បីឲ្យហាងដំណើរការឡើងវិញ។ ការបញ្ជាទិញដែលមានស្រាប់មិនរងផលប៉ះពាល់ទេ។',
 'Hi {name}, <strong>{business}</strong> has been suspended and is no longer visible to buyers.<br><br><strong>Reason:</strong> {reason}<br><br>Your account is fine — you can still sign in. Fix the details in Business settings, then contact support to have the shop put back up. Orders you already have are not affected.',
 'បើកការកំណត់អាជីវកម្ម', 'Open Business settings', 0),
('business_reinstated', 'Shop restored (vendor)', '{name}, {business}',
 'ហាងរបស់អ្នកដំណើរការឡើងវិញ', 'Your shop is back online',
 'ហាងដំណើរការឡើងវិញ', 'Shop restored',
 'ជម្រាបសួរ {name}, ហាង <strong>{business}</strong> បង្ហាញដល់អ្នកទិញវិញហើយ។ សូមអរគុណសម្រាប់ការដោះស្រាយ។',
 'Hi {name}, <strong>{business}</strong> is visible to buyers again. Thanks for sorting it out.',
 'ទៅផ្ទាំងគ្រប់គ្រង', 'Go to my dashboard', 0);

-- Lifting an account suspension no longer puts the shop back on its own, so the
-- reinstatement email must stop promising that it does.
UPDATE email_templates
   SET body_km = 'ជម្រាបសួរ {name}, ការផ្អាកលើគណនីអ្នកលក់របស់អ្នកត្រូវបានដកចេញ។ ឥឡូវអ្នកអាចចូលប្រើឡើងវិញ។ ប្រសិនបើហាងរបស់អ្នកត្រូវបានផ្អាកផងដែរ យើងនឹងផ្ញើអ៊ីមែលដាច់ដោយឡែកនៅពេលវាដំណើរការឡើងវិញ។',
       body_en = 'Hi {name}, the suspension on your vendor account has been lifted and you can sign in again. If your shop was taken down as well, we will email you separately once it is back online. Thanks for your patience.'
 WHERE template_key = 'vendor_reinstated';
