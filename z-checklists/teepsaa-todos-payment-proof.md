# Teepsaa — Payment Proof on Confirmation (Deferred)

Deferred on purpose. This is the one anti-fraud fix from the August 2026 review
that was **not** built, because it adds typing to every single payment while one
person is handling the whole queue. Build it before a second admin gets the
`payments` permission — from that point on it costs nothing extra and it is the
only control that makes a fake confirmation leave a trace.

---

## The gap it closes

Today, confirming a payment is one click in `admin/order.php` → `admin/payments-action.php`.
The click sets `payments.status = 'confirmed'`, which flips the order to `paid`,
which starts the clock toward a real ABA transfer leaving the business account.

Nothing anywhere records **why** the admin believed the money arrived. There is
no bank reference, no screenshot, no amount cross-check — so a confirmation for
money that never landed is byte-for-byte identical to a real one. The audit log
(`admin_audit`, built in the same review) records *that* an admin confirmed and
*when*, which is a large improvement, but it cannot record *evidence that was
never collected*. That is what this item adds.

This is the exact shape of the Amazon vendor-fraud case: the fraudulent step was
never a hack, it was a legitimate-looking approval by someone with the authority
to make it, with nothing to check it against afterwards.

---

## Build Steps

- [ ] Migration — add to `payments`:
  - `bank_ref VARCHAR(64) NULL` — the ABA transaction ID / reference from the bank statement
  - `bank_ref_amount DECIMAL(10,2) NULL` — the amount as it appears on the statement
  - `proof_path VARCHAR(255) NULL` — optional screenshot upload
  - `UNIQUE KEY uniq_payments_bank_ref (bank_ref)` — one bank reference can only pay for one order
- [ ] `admin/order.php` — add a required text input for the bank reference to the
      confirm form, plus an optional file input for the statement screenshot.
      Show `payments.total` next to it so the amounts are compared on screen.
- [ ] `admin/payments-action.php` — reject the confirm when `bank_ref` is empty
      or shorter than the real ABA reference length (check a live statement for
      the format before picking a minimum).
- [ ] Same file — catch the duplicate-key error on `bank_ref` and show
      "That bank reference has already been used on another order." A reused
      reference is the highest-signal fraud indicator available here.
- [ ] Warn (do not block) when `bank_ref_amount` differs from `payments.total` —
      partial payments and bank fees are real, but they should be seen.
- [ ] Screenshot upload — reuse the pattern in `api/upload/index.php`
      (jpg/png only, size cap, UUID filename). Store outside `/uploads/` or behind
      an admin-only fetch: a bank statement is not a public product photo.
- [ ] Include `bank_ref` in the `payment.confirm` audit detail so the log line
      itself carries the reference — `config/audit.php`, `audit_log()` call in
      `admin/payments-action.php`.
- [ ] Show `bank_ref` on `admin/payouts.php` and `admin/order.php` so the person
      releasing the payout can see what the payment was matched against.
- [ ] Backfill decision — existing confirmed payments have no reference. Leave
      them NULL and let the column be required only going forward; do not invent
      references for historical rows.

---

## Notes

- Do this **at the same time** as, or before, granting a second person the
  `payments` permission. The two-person payout rule already stops one admin from
  confirming and paying out the same order, but it does not stop two colluding
  admins — evidence does.
- If ABA PayWay API integration happens first (`teepsaa-todos-payway-api.md`),
  most of this becomes unnecessary: the callback supplies a real transaction ID
  automatically and no human types anything. Check that checklist before building
  this one — there is no point building a manual reference field a month before
  the API removes the manual step entirely.
- Related work already built: `admin_audit` + `/admin/audit.php` (activity log),
  two-person payout rule in `admin/payouts-action.php`, 24-hour bank-change hold,
  self-dealing flags in `config/self-deal.php`, and the daily activity email
  `cron/admin-activity-digest.php`.
