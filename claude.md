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
- Admins are `role = 'admin'` + `is_admin = 1`, login via `/login-admin/` only
- Cart and checkout are for buyers only — vendor sessions are rejected
- Each login portal hard-rejects any role that doesn't match exactly
