# Teepsaa — Test Accounts

## Admin

- **Email: dustint505@gmail.com **
- **Password:**
- **Login:** `/login-admin/`
- **Table:** `admins`
- **Note:** Cannot log in through buyer or vendor portals — admin login only

---

## Test Vendor

- **Email: dustintaylor87102@gmail.com **
- **Name:**
- **Password:**
- **Login:** `/login-vendor/`
- **Table:** `vendors`
- **Note:** Register at `/register-vendor/` — must submit a business and wait for admin approval before products are visible

---

## Test Buyer

- **Email: dustintaylor@gmail.com ** (actually whynot browser)
- **Name:**
- **Password:**
- **Login:** `/login-buyer/`
- **Table:** `buyers`
- **Note:** Register at `/register-buyer/`

---

## Test Flow

1. Log in as **vendor** → upload ABA QR code → submit a business
2. Log in as **admin** → approve the business
3. Log in as **vendor** → add a product to the business
4. Log in as **buyer** → browse, add to cart, checkout, click "I've paid"
5. Log in as **admin** → confirm payment (`/admin/payments.php`)
6. Log in as **vendor** → mark order as dispatched
7. Log in as **buyer** → confirm delivery
8. Log in as **admin** → pay out vendor, mark completed (`/admin/payouts.php`)
