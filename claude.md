# teepsaa — Claude Instructions

## File structure rule

Every page must have its own folder with its own PHP and CSS files inside. Example: the register page lives in `/register/` with `index.php` as the entry point and `register.css` alongside it.

The header and footer are each their own folder (`/header/`, `/footer/`) with their own files (`header.php`, `header.css`, etc.). Pages include them via `require`.

Two files live in the project root only:

- `index.php` — the homepage
- `style.css` — global reset/base styles only

Global JS files live in `/js/` — e.g. `map.js`, `boundary.js`.

DB config goes in `/config/db.php`. No `/assets/` folder.

## Role access rules

| Role   | Buyer portal | Vendor portal | Cart/Checkout | Sell (products/submit) |
| ------ | ------------ | ------------- | ------------- | ---------------------- |
| Buyer  | ✅           | ❌ rejected   | ✅            | ❌                     |
| Vendor | ❌ rejected  | ✅            | ❌            | ✅                     |
| Admin  | ❌           | ❌            | —             | —                      |

- Buyers can only buy — `role = 'buyer'`, login via `/login-buyer/`
- Vendors can only sell — `role = 'vendor'`, login via `/login-vendor/`. Vendors must create a separate buyer account to make purchases.
- Admins live in the separate `admins` table, login via `/login-admin/` only
- Cart and checkout are for buyers only — vendor sessions are rejected
- Each login portal hard-rejects any role that doesn't match exactly

## Session rules

All three subdomains share one cookie (`Domain=.teepsaa.com`), so one browser
holds one PHP session for all of them.

- **The admin identity is namespaced.** Admin login writes `admin_id`,
  `admin_role`, `admin_permissions` — never `user_id`, `role` or `is_admin`.
  Buyer/vendor logins write `user_id` + `role`. An admin and a buyer can be
  signed in at once without knocking each other out, and no role-independent
  "is admin" flag exists to go stale. Admin pages guard on `admin_id` only.
- **Logout is per-identity.** `/logout/logout.php` drops the buyer/vendor keys,
  `/admin/logout.php` drops the admin keys; each only calls `session_destroy()`
  when nothing is left. Never `session_destroy()` unconditionally.
- **Admin chrome follows the URL, not the session** — `admin_area_request()` in
  `config/admin-auth.php`, so a buyer page never sprouts the admin nav.
- **Shared API endpoints take an explicit `as=admin`**, honoured only when
  `admin_id` is set (`api/order-status.php`, `api/messages/poll.php`,
  `api/messages/reply.php`). Without it they act as the buyer/vendor.
- **Every `session_start()` needs the full options block**, including
  `'gc_maxlifetime' => 28800` and `cookie_domain` — `.user.ini` is disabled on
  Hostinger, so nothing is inherited from php.ini. Copy an existing block.

## CSS

`style.css` holds the global `select` rule (chevron, padding, `appearance:
none`). Page CSS should only set `width`. Two traps: the `background`
shorthand wipes the chevron — use `background-color`; and re-declaring
`padding` resets `padding-right`, putting text under the arrow.
