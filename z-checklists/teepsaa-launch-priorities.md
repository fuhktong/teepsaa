# Teepsaa — Launch Priorities

Last reviewed: 2026-06-14

---

## Still needs to be built (development)

### 1. Vendor promo trial system — ✅ DONE
Verified built end-to-end (2026-06-25): `database/migration-vendorpromo.sql`, `admin/promo-codes.php` + action handler (linked from admin nav), `register-vendor` validates/stores the code, `submit/submit.php` stores `promo_code_id` on the business, `admin/action.php` sets trial dates on approval, `checkout/confirm.php` zeroes royalty while trial is active, `dashboard-vendor/index.php` renders the trial progress banner.

Full spec: `z-checklists/teepsaa-checklist-vendorpromo.md`

### 2. Session cookie hardening — ✅ DONE
Fixed in code: every `session_start()` call passes `cookie_httponly` / `cookie_samesite=Strict` / `cookie_secure` / `cookie_domain` directly in its options array (see `teepsaa-completed.md`, Security + Three-Subdomain sections). Do NOT use `.user.ini` — Hostinger disables it entirely (`user_ini.filename` is empty).

---

## Post-launch builds (not needed for go-live)

### Discount / coupon codes
For buyer acquisition campaigns. Full spec in `z-checklists/discount-codes.md`. Not needed on day one.

### Homepage improvements
- "Buy again" row — products the buyer has ordered before (new query only, no new tables)
- "Browse by category" visual grid — categories table already exists, front-end only

### ABA PayWay API
Blocked externally — requires business registration + merchant account. Can't build until API docs are in hand. See `z-checklists/teepsaa-afterlaunch-payway-api.md`.

### Khmer language toggle
Logo swap is done. Translating every UI string is a large effort. Deferred. See `z-checklists/teepsaa-checklist-language.md`.

### Subdomains — ✅ DONE (live 2026-07-06)
teepsaa.com / vendor.teepsaa.com / admin.teepsaa.com routing live and verified. See `teepsaa-completed.md`, Three-Subdomain Layout section.

### Security — hosting-level decisions (moved from the retired security checklist, 2026-07-09)
- [ ] **SSH key authentication** — in hPanel, if SSH access is enabled, switch it to key-based auth (or keep SSH disabled); a weak SSH password is full-account access
- [ ] **Shared hosting risk** — on shared hosting a breach of a neighboring site can expose your database; revisit moving to a VPS once the site has real revenue (accepted risk for launch)
- [ ] **Extra Basic Auth on `admin.teepsaa.com`** — second lock on the admin door: same `.htpasswd` technique as the pre-launch gate but scoped by host (`SetEnvIf Host ^admin\.teepsaa\.com ADMIN_HOST`). Best added AT launch, when the site-wide pre-launch gate comes off — doing it earlier means two password prompts on admin

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
