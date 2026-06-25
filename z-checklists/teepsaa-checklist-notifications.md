# Teepsaa — Notifications

Transactional email triggered at status change points. Use **Resend** (resend.com) — free tier covers 3,000 emails/month, simple API, good PHP support.

## Setup

1. Sign up at resend.com, verify sending domain (add DNS records for `teepsaa.com`)
2. `composer require resend/resend-php`
3. Store API key in `config/resend.php` (not committed to git)
4. Drop send calls into the four action files below

---

## Trigger points

| Event | File | Recipient |
|---|---|---|
| Payment confirmed | `admin/payments-action.php` | Buyer |
| Order dispatched | `dashboard-vendor/dispatch.php` | Buyer |
| Delivery confirmed | `dashboard-buyer/confirm-delivery.php` | Vendor |
| Payout sent / completed | `admin/payouts-action.php` | Vendor |

---

## Email content (draft)

**Payment confirmed → buyer**
> Subject: Your payment has been confirmed
> Your order #YYMMDD-0000 has been confirmed and is being prepared by the vendor.

**Order dispatched → buyer**
> Subject: Your order is on its way
> Your order #YYMMDD-0000 has been dispatched. Click here to confirm delivery once it arrives.

**Delivery confirmed → vendor**
> Subject: Delivery confirmed — payout incoming
> The buyer has confirmed receipt of order #YYMMDD-0000. Teepsaa will process your payout shortly.

**Payout sent → vendor**
> Subject: Your payout has been sent
> Your payout for order #YYMMDD-0000 has been sent to your ABA account.

---

## Open questions

- [ ] What email address should notifications send from? (e.g. `orders@teepsaa.com`)
- [ ] Should buyers get an email immediately after placing an order (payment submitted state)?
- [ ] Should admins get an email when a new payment is submitted for confirmation?
