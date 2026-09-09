# teepsaa — Email reference

Every email the site can send, and the file that sends it. Reference only —
nothing here is a task. Extracted from `z-checklists/teepsaa-todos-launch-readiness.md`
on 2026-09-09, where it had been living as an appendix.

Verified against the code on 2026-09-09: all 31 templates are defined in
`config/email-templates.php`, all 31 are reachable from a real send, and no
send references a template that does not exist. 18 are called with a literal
key; the other 13 go through a variable key (`admin/action.php`,
`admin/vendor-action.php`, `admin/buyer-action.php`, `verify-email/verify.php`,
`admin/refund-action.php`).

Defaults live in `config/email-templates.php`; the live editable copies are in
the `email_templates` table, at Admin → Messages → Emails.

## Buyer

| Event                            | Template            | Sent from                                             |
| -------------------------------- | ------------------- | ----------------------------------------------------- |
| Registration → verification code | `verify_code`       | `register-buyer/register-buyer.php`                   |
| Resend verification code         | `verify_code`       | `resend-verification/resend.php`                      |
| Password reset link              | `reset_password`    | `forgot-password-buyer/request.php`                   |
| Order placed                     | `order_received`    | `checkout/confirm.php`                                |
| Payment confirmed by admin       | `payment_confirmed` | `admin/payments-action.php`                           |
| Order dispatched                 | `order_dispatched`  | `analytics/dispatch.php`                              |
| Abandoned cart reminder          | `abandoned_cart`    | `cron/abandoned-cart.php` (daily)                     |
| Review reminder after delivery   | `review_reminder`   | `cron/review-reminder.php` (daily)                    |
| Welcome after verification       | `welcome_buyer`     | `verify-email/verify.php`                             |
| Order cancelled                  | `order_cancelled`   | `admin/order-action.php`, `admin/payments-action.php` |
| Return approved                  | `refund_approved`   | `admin/refund-action.php`                             |
| Refund declined                  | `refund_rejected`   | `admin/refund-action.php`                             |
| Refund sent via ABA              | `refund_sent`       | `admin/refund-action.php`                             |
| Password changed                 | `password_changed`  | `settings-buyer/password-action.php`                  |
| Account deleted                  | `account_deleted`   | `settings-buyer/delete-action.php`                    |
| Account suspended by admin       | `buyer_suspended`   | `admin/buyer-action.php`                              |
| Account reinstated by admin      | `buyer_reinstated`  | `admin/buyer-action.php`                              |

## Vendor

| Event                            | Template              | Sent from                                                               |
| -------------------------------- | --------------------- | ----------------------------------------------------------------------- |
| Registration → verification code | `verify_code`         | `register-vendor/register-vendor.php`                                   |
| Resend verification code         | `verify_code`         | `resend-verification/resend.php`                                        |
| Password reset link              | `reset_password`      | `forgot-password-vendor/request.php`                                    |
| Low stock after a sale           | `low_stock`           | `checkout/confirm.php`                                                  |
| Buyer confirmed delivery         | `delivery_confirmed`  | `orders-buyer/confirm-delivery.php`                                     |
| Payout sent                      | `payout_sent`         | `admin/payouts-action.php`                                              |
| Welcome after verification       | `welcome_vendor`      | `verify-email/verify.php`                                               |
| Business submitted               | `business_submitted`  | `submit/submit.php`                                                     |
| Business approved                | `business_approved`   | `admin/action.php`                                                      |
| Business rejected                | `business_rejected`   | `admin/action.php`                                                      |
| Business deleted                 | `business_deleted`    | `settings-vendor/business-delete-action.php`, `admin/vendor-action.php` |
| Business suspended by admin      | `business_suspended`  | `admin/vendor-action.php`                                               |
| Business reinstated by admin     | `business_reinstated` | `admin/vendor-action.php`                                               |
| New paid order                   | `vendor_new_order`    | `admin/payments-action.php`                                             |
| Refund requested                 | `refund_requested`    | `orders-buyer/refund-request.php`                                       |
| Password changed                 | `password_changed`    | `settings-vendor/password-action.php`                                   |
| Account deleted                  | `account_deleted`     | `settings-vendor/delete-action.php`                                     |
| Account suspended by admin       | `vendor_suspended`    | `admin/vendor-action.php`                                               |
| Account reinstated by admin      | `vendor_reinstated`   | `admin/vendor-action.php`                                               |
| ABA payout details changed       | `vendor_bank_changed` | `business-vendor/aba-qr-action.php`                                     |

## Admin

| Event               | Template                    | Sent from                                                                                                                                                |
| ------------------- | --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| New job application | inline HTML, not a template | `careers/apply.php` → `ADMIN_EMAIL`                                                                                                                      |
| Daily digest        | `cron/admin-digest.php`     | pending payments, refund requests, business approvals, unread support threads, payouts due, canvassing follow-ups — sends only when a queue is non-empty |

All 31 templates are seeded into the live `email_templates` table and editable
at Admin → Messages → Emails as of 2026-09-09, and both digest crons are
registered. A second daily cron, `cron/admin-activity-digest.php`, mails
yesterday's completed admin actions from `admin_audit` — the counterpart to the
digest above, so that money leaving the business generates mail rather than
silence.

Before the digest existed, the job application was the admin's _only_ email —
everything else was dashboard-badge only and required logging in to notice.
