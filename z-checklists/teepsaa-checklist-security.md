# Teepsaa — Security Checklist

## High Priority

- [x] **Brute force protection** — `login_attempts` table + `config/rate-limit.php`; 5 failures in 15 min locks out IP for 15 min. Applied to all three login portals.
- [x] **XSS in map popups** — `escHtml()` added to `map.js`; all vendor-supplied strings escaped before DOM injection
- [x] **File upload hardening** — `config/upload.php` validates magic bytes (JPEG `FF D8 FF`, PNG `89 50 4E 47`) in all 8 upload handlers; `uploads/.htaccess` blocks PHP execution in `/uploads/`
- [x] **Remove or protect `/dev/` folder** — deleted

## Medium Priority

- [ ] **HTTPS enforcement** — without SSL, login credentials travel in plaintext. Enforce HTTPS in production and set `session.cookie_secure = true`
- [ ] **IDOR on orders** — once order detail pages are built, every query must verify the requesting user owns that order. Never trust an order ID from the URL without an ownership check
- [x] **Stock race condition** — `checkout/confirm.php` checks `rowCount()` after `UPDATE products SET stock = stock - ?`; rolls back transaction if stock ran out mid-checkout

## Lower Priority

- [ ] **Session cookie hardening** — `php_flag`/`php_value` in `.htaccess` only works with mod_php, not PHP-FPM (MAMP). For production, set `session.cookie_httponly = 1` and `session.cookie_samesite = Strict` in the server's `php.ini` or via `ini_set()` before every `session_start()` call
- [ ] **Admin account email obscurity** — avoid using an obvious email like `admin@teepsaa.com`. A hard-to-guess email is a first layer of protection against targeted brute force
- [ ] **Sequential IDs** — business, order, and product IDs are sequential integers making enumeration easy. Consider switching to UUIDs for public-facing IDs

## Database Attack Vectors

- [ ] **Weak database password** — ensure `config/db.php` uses a strong, unique password not reused anywhere else
- [ ] **`config/db.php` exposure** — if the web server ever serves PHP files as plain text (misconfiguration), credentials are visible publicly. Verify PHP is always executed, never served raw. Ideally move config files above the web root
- [ ] **phpMyAdmin exposure** — if your host exposes phpMyAdmin at a guessable URL with a weak password, it's a direct door into the database. Use a strong password and restrict access by IP if possible
- [ ] **SSH brute force** — if your server allows SSH with a weak password, attackers can get full server access. Use SSH key authentication instead of passwords
- [ ] **Shared hosting risk** — on shared hosting, a breach of another site on the same server can expose your database. Consider a VPS with isolated resources for production
- [ ] **PHP shell via file upload** — a disguised PHP file uploaded to `/uploads/` could be used to run raw database commands. Covered under file upload hardening above, but critical enough to note separately
- [ ] **Automated bots** — bots constantly scan the internet for known vulnerabilities regardless of site size or traffic. SQL injection and exposed config files are the most common automated attacks. No action needed beyond keeping the items above addressed

## Already Protected

- [x] SQL injection — PDO prepared statements on all queries
- [x] CSRF — `csrf_verify()` on all POST forms
- [x] Password storage — bcrypt via `password_hash()`
- [x] Session fixation — `session_regenerate_id(true)` on every login
- [x] Role enforcement — hard walls between buyer, vendor, and admin logins
- [x] Ownership checks — vendors can only edit their own products and businesses
