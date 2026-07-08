# Teepsaa — Subdomains (three-domain layout)

Split the app across three subdomains — same codebase, same server, same
database. A host check decides which pages answer on which domain:

| Domain | Serves | Login |
| --- | --- | --- |
| `teepsaa.com` | buyers + all public pages (products, business, content) | `/login-buyer/` |
| `vendor.teepsaa.com` | vendor dashboard, products, orders, messages | `/login-vendor/` |
| `admin.teepsaa.com` | admin panel only | `/login-admin/` |

Admin subdomain notes: admin paths return not-found on the other two
domains, and the admin door can later carry an extra Basic Auth lock.
Subdomains are NOT secret (SSL certificate transparency logs list them) —
the win is separation + a place for extra protection, not hiding.

**Defer until core product is complete.** Buyer/vendor split is mostly
vendor perception during pitches; role separation already works without it.

---

## Code (buildable now, safe before the subdomains exist)

All gated behind a `SUBDOMAINS_ENABLED` flag in `config/subdomain.php` —
`false` means the site behaves exactly as today (including localhost/MAMP),
so this can be built and deployed ahead of the hPanel work.

- [x] `config/subdomain.php`: detects host, defines `IS_VENDOR_SUBDOMAIN` /
      `IS_ADMIN_SUBDOMAIN` and base-URL constants (`BASE_URL_MAIN` /
      `BASE_URL_VENDOR` / `BASE_URL_ADMIN` — empty while off, so relative
      links keep working); always off on localhost and CLI (cron)
- [x] Hooked into the shared bootstrap: required from the top of
      `config/i18n.php`, which db.php loads on every page (db.php itself is
      unmanaged on the server, so it can't hold the require)
- [x] Enforcement (path-prefix rules, one central map — tested, 18 cases):
      - vendor paths (`/dashboard-vendor/`, `/products/`, `/orders-vendor/`,
        `/submit/`, `/messages-vendor/`, vendor login/register/reset/contact)
        off the vendor subdomain → redirect to `vendor.teepsaa.com`
      - admin paths (`/admin/`, `/login-admin/`) anywhere but
        `admin.teepsaa.com` → 404
      - buyer/public paths on vendor/admin subdomain → redirect to
        `teepsaa.com`; `/` on the vendor subdomain goes to the vendor
        dashboard, `/` on admin goes to `/admin/`
      - neutral (work on every domain): `/api/`, `/lang/`, `/currency/`,
        `/logout/`, `/cron/`, `/verify-email/`, `/resend-verification/`
- [x] Wrong door, right person: logged-in vendor on the `teepsaa.com`
      homepage → forwarded to `vendor.teepsaa.com/dashboard-vendor/`
      (homepage only — vendors can still preview their public product and
      business pages on the main domain)
- [x] Session cookie sharing is a SERVER step, not code: every page calls
      session_start() before any config loads, so the cookie domain must
      come from PHP config — a `.user.ini` created on the server at flip-on
      (see Hostinger section). `.htaccess` already blocks serving it.
- [ ] Link audit (optional polish): cross-domain links currently work by
      bounce (relative link → enforcement redirect). For emails and any
      hot cross-domain links, use the base-URL constants to skip the bounce
- [ ] Shared assets (`/style.css`, `/js/`, `/uploads/`) load from all three
      domains — relative paths already handle this, verify once live

## Hostinger setup (when ready to flip on)

- [ ] hPanel → Domains → teepsaa.com → Subdomains → create `vendor`,
      and point its folder at the SAME `public_html` the main site uses
      (custom folder option — do not let it create a separate folder)
- [ ] Same again for `admin`
- [ ] DNS records: hPanel creates them automatically with the subdomain
- [ ] SSL: hPanel → Security → SSL — install the free certificate for both
      new subdomains if not added automatically
- [ ] Create `.user.ini` in `public_html` ON THE SERVER (File Manager →
      New File) containing exactly one line:
      `session.cookie_domain = ".teepsaa.com"`
      (leading dot = cookie valid on all three domains; do NOT add this
      file locally — it would break MAMP logins if MAMP ran PHP as CGI)
- [ ] Set `SUBDOMAINS_ENABLED` to `true` in `config/subdomain.php`, deploy
- [ ] If the pre-launch Basic Auth gate is still up, it covers the
      subdomains too (same folder, same .htaccess) — same password

## Test (after flipping on)

- [ ] All three domains load with valid SSL (no browser warning)
- [ ] Log in as vendor on `vendor.teepsaa.com` → visit `teepsaa.com` →
      forwarded back to vendor dashboard; session survives the hop
- [ ] Log in as buyer → try `vendor.teepsaa.com` → forwarded to
      `teepsaa.com`, still logged in
- [ ] `teepsaa.com/login-admin/` and `vendor.teepsaa.com/login-admin/`
      → not-found; `admin.teepsaa.com/login-admin/` works
- [ ] `teepsaa.com/dashboard-vendor/` → lands on
      `vendor.teepsaa.com/dashboard-vendor/`
- [ ] Product/business links inside the vendor dashboard open on
      `teepsaa.com`
- [ ] Header/footer links point to the right domain from all three
- [ ] Images, CSS, JS, uploads load on all three domains
- [ ] Localhost/MAMP still works exactly as before (flag has no effect
      locally)

## Later (optional hardening)

- [ ] Extra Basic Auth prompt on `admin.teepsaa.com` only (second lock on
      the admin door — same technique as the pre-launch gate, scoped by
      host)
