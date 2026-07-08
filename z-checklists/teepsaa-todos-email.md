# Email Sending — Pick One Before Launch

The site cannot launch without working email: buyers must verify their email
to check out, and password resets / order confirmations all go through
`send_email()` in `config/mail.php`.

With no password configured, emails are silently written to `mail.log`
instead of being sent (fine for local dev, useless in production).

## Decide

- [x] Pick ONE option. DECIDED: Option A — Hostinger SMTP (in-house, no
      external services). Resend option removed; `config/resend.php` deleted.

## Option A — Hostinger SMTP (no new signups)

- [ ] hPanel → Emails → create mailbox `contact@teepsaa.com`, note the password
- [x] `send_email()` in `config/mail.php` rewritten to send via
      `smtp.hostinger.com` (port 465, SSL) — pure PHP, no libraries, no
      external API
      - same function signature — no other file changes
      - keeps the mail.log fallback when no password is configured, so local
        dev behavior is unchanged
      - failed sends are logged to mail.log with the SMTP error
- [ ] On the SERVER, create `config/smtp.php` (replaces the server's old
      `config/resend.php`, which can be deleted) with:

      ```php
      <?php
      define('SMTP_HOST', 'smtp.hostinger.com');
      define('SMTP_PORT', 465);
      define('SMTP_USER', 'contact@teepsaa.com');
      define('SMTP_PASS', 'the-mailbox-password-from-hPanel');
      define('MAIL_FROM',      'contact@teepsaa.com');
      define('MAIL_FROM_NAME', "teepsaa");
      ```

- [ ] Deploy the updated `config/mail.php` via deploycode.txt (the deploy
      script now excludes `config/smtp.php` instead of `config/resend.php`,
      so the server's password is never overwritten)

## Test

- [ ] Register a new buyer on the live site using a real personal email
- [ ] Verification code arrives in the inbox (check spam folder too)
- [ ] Complete verification, then do a password reset — that email arrives too
- [ ] Place a test order — order confirmation email arrives
- [ ] If anything landed in spam: hPanel → Emails → confirm the mailbox
      exists and that the domain's SPF/DKIM records are set (Hostinger adds
      these automatically when DNS is hosted with them, but verify in
      hPanel → Emails → DNS settings)
- [ ] If sends fail outright: check `mail.log` on the server — SMTP errors
      are logged there with the server's reply

## Related launch items (from teepsaa-todos-audit.md)

- [ ] `PAYOUT_WINDOW_SECONDS = 86400` in the server's `config/db.php`
- [ ] Remove the pre-launch Basic Auth block from `.htaccess` + delete `.htpasswd`
