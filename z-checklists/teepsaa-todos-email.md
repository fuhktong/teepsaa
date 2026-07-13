# Email — Remaining Tests & Notification Roadmap

SMTP is live (Hostinger, `contact@teepsaa.com` — setup archived in
teepsaa-completed.md). Emails render from bilingual templates in
`config/email-templates.php`, staff-editable via Admin → Messages → Emails.

## Remaining live tests

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

---

# Current email notifications (live today)

## Buyer — current

| Event | Template | Sent from |
| --- | --- | --- |
| Registration → verification code | `verify_code` | `register-buyer/register-buyer.php` |
| Resend verification code | `verify_code` | `resend-verification/resend.php` |
| Password reset link | `reset_password` | `forgot-password-buyer/request.php` |
| Order placed (confirmation) | `order_received` | `checkout/confirm.php` |
| Payment confirmed by admin | `payment_confirmed` | `admin/payments-action.php` |
| Order dispatched by vendor | `order_dispatched` | `dashboard-vendor/dispatch.php` |
| Abandoned cart reminder | `abandoned_cart` | `cron/abandoned-cart.php` (daily) |
| Review reminder after delivery | `review_reminder` | `cron/review-reminder.php` (daily) |

## Vendor — current

| Event | Template | Sent from |
| --- | --- | --- |
| Registration → verification code | `verify_code` | `register-vendor/register-vendor.php` |
| Resend verification code | `verify_code` | `resend-verification/resend.php` |
| Password reset link | `reset_password` | `forgot-password-vendor/request.php` |
| Low stock after a sale | `low_stock` | `checkout/confirm.php` |
| Buyer confirmed delivery | `delivery_confirmed` | `dashboard-buyer/confirm-delivery.php` |
| Payout sent | `payout_sent` | `admin/payouts-action.php` |

## Admin — current

| Event | Template | Sent from |
| --- | --- | --- |
| New job application | (inline HTML, not a template) | `careers/apply.php` → `ADMIN_EMAIL` |

That is the admin's ONLY email. Everything else (pending payments, refund
requests, pending business approvals, support messages) is dashboard-badge
only — the admin must log in to notice.

---

# New notifications (BUILT — awaiting deploy + live test)

All 14 templates are bilingual and staff-editable (Admin → Messages → Emails
after seeding). Fallback defaults live in `config/email-templates.php`, so
sends work even before seeding.

## Buyer — built

| Event | Template | Sent from |
| --- | --- | --- |
| Welcome after verification | `welcome_buyer` | `verify-email/verify.php` |
| Order cancelled (admin cancel or payment reject) | `order_cancelled` | `admin/order-action.php`, `admin/payments-action.php` |
| Return approved (send item back) | `refund_approved` | `admin/refund-action.php` |
| Refund request declined | `refund_rejected` | `admin/refund-action.php` |
| Refund sent via ABA | `refund_sent` | `admin/refund-action.php` |
| Password changed (security notice) | `password_changed` | `dashboard-buyer/settings/password-action.php` |
| Account deleted confirmation | `account_deleted` | `dashboard-buyer/settings/delete-action.php` |

## Vendor — built

| Event | Template | Sent from |
| --- | --- | --- |
| Welcome after verification | `welcome_vendor` | `verify-email/verify.php` |
| Business submitted (under review) | `business_submitted` | `submit/submit.php` |
| Business approved — "your store is live!" | `business_approved` | `admin/action.php` |
| Business rejected | `business_rejected` | `admin/action.php` |
| Business deleted (vendor or admin path) | `business_deleted` | `dashboard-vendor/settings/business-delete-action.php`, `admin/vendor-action.php` |
| New paid order | `vendor_new_order` | `admin/payments-action.php` (confirm) |
| Buyer requested a refund | `refund_requested` | `dashboard-buyer/refund-request.php` |
| Password changed (security notice) | `password_changed` | `dashboard-vendor/settings/password-action.php` |
| Account deleted confirmation | `account_deleted` | `dashboard-vendor/settings/delete-action.php` |

## Admin — built

One daily digest instead of per-event pings: `cron/admin-digest.php` emails
`ADMIN_EMAIL` the pending counts (payments awaiting confirmation, refund
requests, business approvals, unread support threads, payouts due) with links
to each admin page. Sends **only when at least one queue is non-empty**.

## Deploy steps

- [ ] Deploy code (lftp mirror per deploycode.txt)
- [ ] Run `database/seed-email-templates.php` against the live DB (safe —
      only inserts missing keys, never overwrites staff edits) so the 14 new
      templates appear in Admin → Messages → Emails
- [ ] Register the digest cron in hPanel: `/usr/bin/php /path/to/cron/admin-digest.php`
      once daily (e.g. 07:00 Phnom Penh)
- [ ] Live-test: verify a new account (welcome), approve a business, confirm
      a payment (vendor new-order email now actually matches the
      "Vendors have been notified" message)
