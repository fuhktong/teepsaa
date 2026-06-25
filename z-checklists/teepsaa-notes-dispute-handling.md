# Teepsaa — Dispute Handling

The core scenario: buyer clicks Confirm Delivery and the order completes normally. The dispute arises when a buyer claims they did not receive their order but the vendor says it was delivered — or the buyer simply never clicks Confirm Delivery.

---

## Scenario 1 — Buyer never confirms delivery

The vendor dispatched the order but the buyer goes silent and never clicks Confirm Delivery. The order stays in `dispatched` indefinitely and the vendor never gets paid.

**Options:**

### A — Auto-confirm after X days (recommended)
If the buyer does not confirm or dispute within a set number of days after the order is marked `dispatched`, the system automatically moves the order to `delivered`. The admin is then prompted to pay out the vendor.
- Simple to build — a cron job checks for stale `dispatched` orders
- Requires deciding on the number of days (3–7 is typical for local delivery)
- Risk: legitimate non-deliveries get auto-confirmed

### B — Admin manually confirms after X days
After X days with no buyer response, admin receives a notification and manually reviews before confirming.
- More oversight but adds admin workload at scale

---

## Scenario 2 — Buyer claims non-delivery

The buyer says the order never arrived. The vendor says it was delivered. Teepsaa is holding the funds.

**Options:**

### A — Admin decides (simplest)
Admin reviews the claim and makes a judgment call:
- If ruling for buyer: order → `refunded`, admin refunds buyer via ABA, vendor is not paid
- If ruling for vendor: order → `delivered`, admin pays out vendor as normal
- Requires adding a `disputed` status and an admin dispute review interface

### B — Require proof of delivery
Vendor must upload a photo of the delivery (handoff photo, Grab receipt screenshot) when marking as dispatched. This gives admin evidence to review if a dispute is raised.
- Adds friction for vendors but reduces bad-faith buyer claims
- Requires a photo upload field on the dispatch action

### C — No refunds policy (simplest but highest risk)
Once the vendor marks dispatched, the payout proceeds automatically after X days regardless of buyer claims. All sales final.
- Zero admin overhead
- High risk of losing buyer trust, especially early in the platform's life

---

## Recommendation

Start with **Scenario 1A** (auto-confirm after 5 days) combined with **Scenario 2A** (admin decides on disputes). This is the minimum viable approach:

1. Buyer has 5 days after `dispatched` to confirm delivery or raise a dispute
2. If no action after 5 days → auto-confirm → admin pays out vendor
3. If buyer clicks "I have a problem" → order → `disputed` → admin reviews and resolves

---

## What needs to be built

- [ ] Add `disputed` and `refunded` statuses to orders ENUM
- [x] Add auto-confirm cron job — `cron/auto-confirm.php` marks `dispatched` orders older than 24 hours as `delivered`, emails admin
- [ ] Add "I have a problem" button to buyer dashboard (visible on `dispatched` orders)
- [ ] Admin disputes queue — new tab in admin panel showing `disputed` orders with buyer/vendor info
- [ ] Admin resolve actions — rule for buyer (refund) or rule for vendor (complete)
- [x] Decide: how many days before auto-confirm? ✅ 24 hours
- [ ] Decide: require vendor proof-of-delivery photo? ⚠️ *Unanswered*
