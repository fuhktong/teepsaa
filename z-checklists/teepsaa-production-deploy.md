# Teepsaa — Production Deployment Checklist

Work through these in order when deploying to your web host.

---

## Before you upload

- [ ] Open `config/db.php` — swap in live database credentials (host, dbname, user, password)
- [ ] Open `config/db.php` — change `PAYOUT_WINDOW_SECONDS` from `60` to `86400` (sets the 24-hour refund/payout window)
- [ ] Open `config/app.php` — update `FROM_EMAIL` to your verified sending domain and `SITE_URL` to `https://teepsaa.com`
- [ ] Open `config/mapbox.php` — confirm the token is the production token (not a dev/restricted one)

---

## Upload files

- [ ] Upload everything to `public_html/` via FTP/SFTP or cPanel File Manager
- [ ] Do **not** upload: `teepsaa-*.md` files, `database/` folder, `dev/` folder

---

## Database

- [ ] cPanel → MySQL Databases → create a new database
- [ ] Create a new database user with a strong password
- [ ] Add the user to the database with **All Privileges**
- [ ] Update `config/db.php` with those credentials
- [ ] cPanel → phpMyAdmin → select your DB → Import → upload `database/migration.sql`

---

## Admin account

There is no admin registration page — insert directly into the database.

- [ ] Generate a password hash — run this in a temporary PHP file on the server, then delete it:
  ```php
  <?php echo password_hash('your_password_here', PASSWORD_DEFAULT); ?>
  ```
- [ ] phpMyAdmin → SQL tab → run:
  ```sql
  INSERT INTO admins (email, password)
  VALUES ('dustint505@gmail.com', '$2y$12$PASTE_HASH_HERE');
  ```

---

## Uploads folder

- [ ] cPanel File Manager → right-click `uploads/` → Permissions → set to **755**
- [ ] Upload Teepsaa's ABA QR code to `uploads/aba-qr.png`

---

## Sensitive folder protection

Already handled — `config/.htaccess` and `cron/.htaccess` are committed and will deploy with the project. No action needed. ✅

---

## Cron job

`cron/auto-confirm.php` is already built. ✅ Just register it in cPanel.

- [ ] cPanel → Cron Jobs → add:
  - Minute: `0` / Hour: `*` / Day: `*` / Month: `*` / Weekday: `*`
  - Command: `php /home/YOUR_CPANEL_USERNAME/public_html/cron/auto-confirm.php`
- [ ] SSH in and run the command once manually to confirm it works and sends the admin email

---

## PHP version

- [ ] cPanel → MultiPHP Manager (or PHP Selector) → set domain to **PHP 8.0 or higher**

---

## Email

- [ ] Send a test email from the server to confirm `mail()` is working
- [ ] Check spam folder — if cron emails land there, set up Resend (see `teepsaa-checklist-notifications.md`)

---

## Domain & DNS

- [ ] Point domain A record to host IP (find it in cPanel → Shared IP Address)
- [ ] If using Resend: add SPF and DKIM DNS records (Resend dashboard provides these)

---

## Final test — run the full order flow

- [ ] Register a vendor → submit a business → upload ABA QR
- [ ] Log in as admin → approve the business
- [ ] Add a product as vendor
- [ ] Register a buyer → add to cart → checkout → "I've paid"
- [ ] Log in as admin → confirm payment
- [ ] Log in as vendor → mark dispatched
- [ ] Log in as buyer → confirm delivery
- [ ] Log in as admin → process payout → mark completed
- [ ] Trigger `cron/auto-confirm.php` manually and confirm admin email arrives
