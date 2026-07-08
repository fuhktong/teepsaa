# Email Sending — Pick One Before Launch

The site cannot launch without working email: buyers must verify their email
to check out, and password resets / order confirmations all go through
`send_email()` in `config/mail.php`.

With no key/password configured, emails are silently written to `mail.log`
instead of being sent (fine for local dev, useless in production).

## Decide

- [ ] Pick ONE option below. Both end with the same test.

| | Option A: Hostinger SMTP | Option B: Resend |
| --- | --- | --- |
| New accounts needed | None — included in hosting plan | Sign up at resend.com |
| Code changes | Yes — rewrite `send_email()` (Claude does this) | None — already built |
| Sending limit | A few hundred/day (shared hosting) | Free tier: 100/day, 3,000/month |
| Deliverability | Good (DNS already at Hostinger) | Better, with delivery dashboard/logs |

## Option A — Hostinger SMTP (no new signups)

- [ ] hPanel → Emails → create mailbox `contact@teepsaa.com`, note the password
- [ ] Have Claude rewrite `send_email()` in `config/mail.php` to send via
      `smtp.hostinger.com` (port 465, SSL) instead of the Resend API
      - keeps the same function signature — no other file changes
      - keeps the mail.log fallback when no password is configured, so local
        dev behavior is unchanged
- [ ] Config on the SERVER becomes the mailbox password instead of a Resend
      key (exact file contents provided with the code change)
- [ ] Deploy the updated `config/mail.php` via deploycode.txt

## Option B — Resend (better deliverability, one more account)

- [ ] Sign up at resend.com (free tier)
- [ ] Resend → Domains → add `teepsaa.com` — it shows 3–4 DNS records
- [ ] hPanel → Domains → DNS → add those records, then click Verify in Resend
- [ ] Resend → API Keys → Create API Key (starts with `re_`)
- [ ] On the SERVER, edit `config/resend.php`:
      `define('RESEND_API_KEY', 're_xxxxxxxx');`
- [ ] No code changes and no deploy needed — the Resend path is already built

## Test (either option)

- [ ] Register a new buyer on the live site using a real personal email
- [ ] Verification code arrives in the inbox (check spam folder too)
- [ ] Complete verification, then do a password reset — that email arrives too
- [ ] Place a test order — order confirmation email arrives
- [ ] If anything landed in spam: confirm the DNS records (Option B) or that
      the mailbox exists and the password is right (Option A)

## Related launch items (from teepsaa-todos-audit.md)

- [ ] `PAYOUT_WINDOW_SECONDS = 86400` in the server's `config/db.php`
- [ ] Remove the pre-launch Basic Auth block from `.htaccess` + delete `.htpasswd`
