# Teepsaa — Vendor Promo Trial

Early vendors receive a 0% royalty trial period activated by a promo code given out on a business card at vendor pitches. The trial runs until **both** conditions are met: 3 months have passed AND the vendor has exceeded $100 in completed sales. Until both are met, royalty = 0 on all orders.

---

## How it works

1. Admin creates a promo code in the admin panel before a pitch event
2. Vendor receives a business card with the code printed on it
3. Vendor enters the code during registration
4. Code is validated and marked used; trial start date is recorded
5. When the vendor's business is approved, trial terms are applied automatically
6. At checkout, the royalty rate is set to 0 if the trial is still active
7. Trial ends automatically once both conditions pass — no admin action needed
8. Vendor dashboard shows trial progress so vendors understand their status

---

## Schema

### New table: `promo_codes`

```sql
CREATE TABLE promo_codes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(50) NOT NULL UNIQUE,
    description  VARCHAR(255) NULL,         -- e.g. "May 2026 pitch event"
    uses_limit   INT UNSIGNED NULL,          -- NULL = unlimited
    uses_count   INT UNSIGNED NOT NULL DEFAULT 0,
    active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### New columns on `businesses`

```sql
ALTER TABLE businesses
    ADD COLUMN promo_code_id INT UNSIGNED NULL,
    ADD COLUMN trial_starts_at DATETIME NULL,
    ADD COLUMN trial_ends_at DATETIME NULL,        -- trial_starts_at + 3 months
    ADD COLUMN royalty_free_threshold DECIMAL(10,2) UNSIGNED NULL DEFAULT 100.00;
```

Write as `database/migration-vendorpromo.sql`.

---

## Config

Add to `config/db.php` or a new `config/promo.php`:

```php
define('VENDOR_TRIAL_MONTHS', 3);
define('VENDOR_TRIAL_THRESHOLD', 100.00);
```

---

## Build steps

### Promo codes admin page

- [ ] New page `admin/promo-codes.php` — list all codes, show uses_count / uses_limit, active toggle
- [ ] Create code form: code string, description, uses limit (blank = unlimited)
- [ ] `admin/promo-codes-action.php` — create, toggle active
- [ ] Link from admin nav

### Vendor registration

- [ ] Add optional "Promo code" field to `register-vendor/index.php`
- [ ] In `register-vendor/register-vendor.php`: validate code on submit
  - Code must exist, be active, and have remaining uses (or unlimited)
  - If invalid: show error, do not block registration — code is optional
  - If valid: store `promo_code_id` in session, increment `uses_count`
- [ ] When business is created/approved, apply trial:
  - `trial_starts_at = NOW()`
  - `trial_ends_at = NOW() + VENDOR_TRIAL_MONTHS months`
  - `royalty_free_threshold = VENDOR_TRIAL_THRESHOLD`
  - Link `promo_code_id` to the business record

### Checkout — royalty override

- [ ] In `checkout/confirm.php`, before applying `$effectiveRate`, check trial status per business:
  ```php
  $trialActive = false;
  if ($group['trial_ends_at'] && $group['trial_starts_at']) {
      $withinTime = strtotime($group['trial_ends_at']) > time();
      $belowThreshold = $group['completed_sales'] < VENDOR_TRIAL_THRESHOLD;
      $trialActive = $withinTime || $belowThreshold; // 0% until BOTH conditions pass
  }
  if ($trialActive) $effectiveRate = 0;
  ```
- [ ] Query must include `trial_starts_at`, `trial_ends_at`, `royalty_free_threshold`, and a subquery summing completed order subtotals per business for `completed_sales`

### Vendor dashboard — trial status

- [ ] In `dashboard-vendor/index.php` (or settings), show a trial status banner if trial is active:
  - "$X of $100 in sales used — trial ends [date]"
  - Once both conditions pass, banner disappears
- [ ] Pull `completed_sales`, `trial_ends_at`, `royalty_free_threshold` in the vendor dashboard query

---

## Edge cases

- [ ] Vendor enters code but business isn't approved yet — trial clock should start at approval, not registration. Store code on the pending business; apply `trial_starts_at` when admin approves
- [ ] Code used but vendor never completes registration — `uses_count` was incremented; admin can reset manually or set a generous limit
- [ ] Vendor with no promo code — unaffected, normal royalty rate applies from day one
- [ ] Trial expired but vendor disputes it — admin can manually extend `trial_ends_at` in the DB

---

## Future

- [ ] Per-code trial terms — different codes could offer different durations or thresholds (e.g. a partner referral code with 6 months / $200)
- [ ] Vendor referral codes — existing vendors refer new vendors and earn a bonus
- [ ] Analytics — track which pitch events converted to active vendors using `promo_codes.description`
