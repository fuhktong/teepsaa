# Teepsaa — Launch Priorities

Last reviewed: 2026-06-14

---

## Still needs to be built (development)

### 1. Vendor promo trial system — ✅ DONE
Verified built end-to-end (2026-06-25): `database/migration-vendorpromo.sql`, `admin/promo-codes.php` + action handler (linked from admin nav), `register-vendor` validates/stores the code, `submit/submit.php` stores `promo_code_id` on the business, `admin/action.php` sets trial dates on approval, `checkout/confirm.php` zeroes royalty while trial is active, `dashboard-vendor/index.php` renders the trial progress banner.

Full spec: `z-checklists/teepsaa-checklist-vendorpromo.md`

### 2. Session cookie hardening — still outstanding, fix location below is wrong
`.htaccess` cookie flags only work with mod_php. Production likely runs PHP-FPM so the flags are silently ignored.

The doc previously said to fix this in `config/db.php` before `session_start()` — that doesn't work: all ~161 files call `session_start()` as their first line, before `config/db.php` is even required (confirmed in `login-buyer/login-buyer.php`), so an `ini_set()` there would run too late.

Correct fix for PHP-FPM: add a `.user.ini` file (the FPM equivalent of `.htaccess`) at the project root, or call `session_set_cookie_params()` ahead of every `session_start()`.

- [ ] Add `.user.ini` with `session.cookie_httponly = 1` and `session.cookie_samesite = Strict`

---

## Post-launch builds (not needed for go-live)

### Discount / coupon codes
For buyer acquisition campaigns. Full spec in `z-checklists/discount-codes.md`. Not needed on day one.

### Homepage improvements
- "Buy again" row — products the buyer has ordered before (new query only, no new tables)
- "Browse by category" visual grid — categories table already exists, front-end only

### ABA PayWay API
Blocked externally — requires business registration + merchant account. Can't build until API docs are in hand. See `z-checklists/teepsaa-checklist-payway-api.md`.

### Khmer language toggle
Logo swap is done. Translating every UI string is a large effort. Deferred. See `z-checklists/teepsaa-checklist-language.md`.

### Subdomains
Not a functional requirement. Role separation works without it. See `z-checklists/teepsaa-checklist-subdomains.md`.

---

## Deployment tasks (not code — done when you switch to production)

These are one-time server/config steps, not development work:

- [ ] `config/db.php` — live DB credentials, set `PAYOUT_WINDOW_SECONDS` to `86400`
- [ ] `config/app.php` — set `SITE_URL = 'https://teepsaa.com'`, confirm `FROM_EMAIL`
- [ ] `config/mapbox.php` — confirm production Mapbox token
- [ ] `display_errors = Off`
- [ ] Run all `database/migration-*.sql` files on production DB
- [ ] Insert admin account into `admins` table via phpMyAdmin
- [ ] Upload ABA QR code to `uploads/aba-qr.png`
- [ ] Set `/uploads/` to 755
- [ ] Enable SSL + HTTPS redirect in `.htaccess`
- [ ] Register cron jobs in cPanel: `auto-confirm.php` (hourly), `abandoned-cart.php` (daily), `review-reminder.php` (daily)

---

## Open questions (policy, not code)

- [ ] Does acting as a payment intermediary require a financial license in Cambodia? (item #4 in `teepsaa-notes-open-questions.md`)
- [ ] If an order is refunded, does Teepsaa claw back its royalty cut? (no code needed until decision is made)
