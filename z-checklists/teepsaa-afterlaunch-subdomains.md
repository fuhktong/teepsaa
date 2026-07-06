# Teepsaa — Subdomains

Split the app across two subdomains: `teepsaa.com` for buyers and `vendor.teepsaa.com` for vendors. Same codebase, same server, same database — subdomain detection handles routing and role enforcement.

**Defer until core product is complete.** Primary benefit is vendor perception during pitches. Role separation already works without this.

---

## Infrastructure

- [ ] Add A record or CNAME for `vendor.teepsaa.com` pointing to the same server IP
- [ ] Obtain wildcard SSL cert for `*.teepsaa.com` via Let's Encrypt (free)
- [ ] Add virtual host config in Apache/Nginx for `vendor.teepsaa.com` pointing to the same document root as `teepsaa.com`

---

## Sessions

- [ ] Set `session.cookie_domain = '.teepsaa.com'` in `php.ini` or at the top of `config/db.php` via `ini_set()` so sessions are shared across both subdomains

---

## Application middleware

- [ ] Create a subdomain helper (e.g. in `config/db.php` or a new `config/subdomain.php`):
  - Detect current host via `$_SERVER['HTTP_HOST']`
  - Define `IS_VENDOR_SUBDOMAIN` = true when host is `vendor.teepsaa.com`
- [ ] Add subdomain enforcement to all entry points:
  - Buyer pages (`/browse/`, `/cart/`, `/checkout/`, `/dashboard-buyer/`) — if `IS_VENDOR_SUBDOMAIN`, redirect to `teepsaa.com` equivalent
  - Vendor pages (`/products/`, `/dashboard-vendor/`, `/submit/`) — if not `IS_VENDOR_SUBDOMAIN`, redirect to `vendor.teepsaa.com` equivalent
  - Admin — no subdomain required, accessible from either

---

## Login portals

- [ ] `teepsaa.com/login-buyer/` — buyer login, redirect vendor sessions to `vendor.teepsaa.com/login-vendor/`
- [ ] `vendor.teepsaa.com/login-vendor/` — vendor login
- [ ] `vendor.teepsaa.com/login-admin/` — admin login (or keep on main domain)

---

## Link audit

- [ ] Audit all cross-context links — any link that takes a buyer to a vendor page or vice versa must use an absolute URL with the correct subdomain
- [ ] Business/product pages (`/business/`, `/product/`) are buyer-facing — ensure they always resolve on `teepsaa.com`
- [ ] Vendor settings and dashboard links in the header must point to `vendor.teepsaa.com`

---

## Edge cases

- [ ] User visits `teepsaa.com` while logged in as vendor — redirect to `vendor.teepsaa.com/dashboard-vendor/`
- [ ] User visits `vendor.teepsaa.com` while logged in as buyer — redirect to `teepsaa.com/dashboard-buyer/`
- [ ] Shared assets (`/style.css`, `/js/`, `/uploads/`) — ensure they load correctly from both subdomains (relative paths already handle this)
