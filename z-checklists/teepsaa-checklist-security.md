# Teepsaa — Security Checklist

## Lower Priority

- [ ] **Admin account email obscurity** — avoid using an obvious email like `admin@teepsaa.com`. A hard-to-guess email is a first layer of protection against targeted brute force

## Database Attack Vectors

- [ ] **Weak database password** — ensure `config/db.php` uses a strong, unique password not reused anywhere else
- [ ] **phpMyAdmin exposure** — if your host exposes phpMyAdmin at a guessable URL with a weak password, it's a direct door into the database. Use a strong password and restrict access by IP if possible
- [ ] **SSH brute force** — if your server allows SSH with a weak password, attackers can get full server access. Use SSH key authentication instead of passwords
- [ ] **Shared hosting risk** — on shared hosting, a breach of another site on the same server can expose your database. Consider a VPS with isolated resources for production

### Flagged, not code-fixable (ops/deploy decisions — need your input before launch)

- [ ] `config/app.php` — `DEV_MODE` is currently `true`; must be `false` in production (this is what the OTP-leak gate depends on)
- [ ] `config/db.php` — still `root`/`root` and `PAYOUT_WINDOW_SECONDS = 60` (dev values, already flagged by an inline comment) — must be swapped for production before go-live
- [ ] Admin email obscurity, phpMyAdmin/SSH/shared-hosting hardening — all still open, these are hosting/data decisions rather than application code changes
- [ ] **Run `database/migration-public-ids.sql` against the live database** — adds `public_id` columns and backfills existing rows in one pass (paste it into phpMyAdmin's SQL tab). Required before any of the new UUID-based URLs will work.
