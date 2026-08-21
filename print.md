# Coupons + promo codes — test instructions

Checklist item:

> Coupons + promo codes: create sitewide coupon, limits (max uses,
> expiry, min subtotal) all enforced at checkout

These are **two different things** that happen to sit in the same admin
section. Do Part A, then Part B.

| | What it is | Who uses it | Where |
|---|---|---|---|
| **Coupon** | Money off an order | Buyer, at checkout | Admin → Marketing → **Coupons** |
| **Promo code** | 3-month royalty-free trial | Vendor, at signup | Admin → Marketing → **Promo Codes** |

**Before you start:** you need a buyer account you can log in as, and at
least one product in its cart. Read the two "gotchas" below first — they
will save you from thinking something is broken when it isn't.

### Gotcha 1 — the coupon box hides itself

On `/checkout/` the "enter a code" box **only appears when at least one
usable coupon exists** for that cart (active, started, not expired, uses
left). So when you test an expired or used-up code, the box vanishes
instead of showing an error.

**Fix:** always keep one plain valid coupon (e.g. `KEEPBOX`, 5% off, no
limits) active the whole time you test. It keeps the box on screen so you
can type the bad codes into it.

### Gotcha 2 — one use per buyer, forever

A buyer can never reuse a code they've already ordered with. Every test
below that places an order needs a **fresh code**, not the same one again.

---

## Part A — Sitewide coupon

### A1. Create the coupons

Log in as admin → **Marketing → Coupons** (`/admin/coupons.php`).

Create these six with the row of boxes at the top. Leave any box blank
that isn't listed. Dates are set with the little calendar pickers.

| Code | Type | Value | Min order | Max uses | Starts | Expires |
|---|---|---|---|---|---|---|
| `KEEPBOX` | % off | 5 | — | — | — | — |
| `SAVE10` | % off | 10 | — | — | — | — |
| `MIN50` | $ off | 5 | 50 | — | — | — |
| `ONEUSE` | $ off | 3 | — | 1 | — | — |
| `GONE` | % off | 20 | — | — | — | **yesterday** |
| `SOON` | % off | 20 | — | — | **tomorrow** | — |

**Check after creating:** every row's **Shop** column shows a dash `—`.
That dash is what makes it sitewide. A row with a shop name in it is a
vendor's own coupon, not yours.

`GONE` should immediately show a red **Expired** badge and only have a
Delete button.

### A2. The basic discount works

As the buyer, put something in the cart and go to `/checkout/`.

1. Type `SAVE10` → Apply.
2. Green message "Code applied — 10% off."
3. The order summary shows a **−$x.xx** discount line and the total drops
   by 10% of the subtotal.
4. Click **Remove** → discount disappears, total goes back up.

**Note:** the discount comes off the **product subtotal only**. Delivery
is paid in cash to the driver and is never discounted.

### A3. Minimum subtotal is enforced

1. Get the cart subtotal **under $50**. Apply `MIN50`.
   → Red: "Minimum order of $50.00 required." No discount.
2. Add items so the subtotal is **over $50**. Apply `MIN50` again.
   → Works, $5 off.

### A4. Expiry is enforced

Cart with anything in it, at `/checkout/`:

1. Apply `GONE` → red "This code has expired."
2. Apply `SOON` → red "This code is not active yet."

Neither should ever discount anything.

**Also check the day boundary:** a coupon expiring *today* must still
work today (it dies at 23:59:59, not at midnight this morning). Edit
`SOON`'s expiry to today's date, save, then apply it — it should be
accepted.

### A5. Max uses is enforced

`ONEUSE` has max uses = 1.

1. As buyer #1, apply `ONEUSE` and **place the order for real**.
2. Admin → Coupons: the `ONEUSE` row now reads **1 / 1** under Uses.
3. Log in as a **second buyer** (you must use a different buyer account —
   buyer #1 is blocked by the one-use-per-buyer rule instead, which would
   be the wrong error). Apply `ONEUSE`.
   → Red: "This code has reached its usage limit."

### A6. One use per buyer

Still as buyer #1, put a new item in the cart and try `SAVE10` — the code
you already ordered with in A2 (if you completed that order; if you only
previewed it, place an order with `SAVE10` first).

→ Red: "You have already used this code."

### A7. The money lands in the right place — the important one

This is the part that matters most. A sitewide coupon is a **platform
marketing cost** — the vendor must still be paid in full.

After the `SAVE10` order, open Admin → **Orders** → that order.

- Buyer paid = subtotal **minus** the discount ✅
- **Vendor payout is calculated on the pre-discount subtotal** ✅
- **Royalty is calculated on the pre-discount subtotal** ✅

If the vendor's payout dropped because of your coupon, that's a bug —
write it down.

**Multi-vendor version:** build a cart with items from **two different
shops**, apply `SAVE10`, place the order. It splits into two orders. The
discount should be **split proportionally** between them (bigger order
absorbs the bigger share) and the two discount amounts should add up to
exactly the discount shown at checkout.

### A8. Admin housekeeping

Back on Admin → Coupons:

- A coupon **that has been used** has no Delete button — only
  **Deactivate**. Deactivate `ONEUSE`, then try applying it at checkout
  → "Invalid code."
- An **unused** coupon can be deleted. Delete one.
- Edit a live coupon's value/min order/max uses inline and hit **Save** →
  the change takes effect at checkout straight away.
- An **expired** coupon can't be edited, only deleted.
- Try creating a % coupon with value `150` → rejected, "percent must be
  100 or less."
- Try creating a coupon with a code that already exists → "That code
  already exists."

---

## Part B — Promo codes (vendor signup)

Completely separate feature. A promo code gives a **new vendor** a
3-month royalty-free trial (or until their first $100 of completed
sales, whichever lasts longer). It does nothing at checkout for buyers.

### B1. Create it

Admin → **Marketing → Promo Codes** (`/admin/promo-codes.php`).

Create: code `LAUNCH2026`, description "Test run", max uses `1`.

### B2. Use it

1. Register a **brand-new vendor** at `/register-vendor/` and enter
   `LAUNCH2026` in the promo code field.
2. Complete the signup and **submit a business**.
3. Admin → Vendor approvals → **approve** that vendor.

### B3. Check it took effect

- Admin → Promo Codes: `LAUNCH2026` now shows **1 / 1** uses.
- Log in as that vendor → the dashboard shows a **trial banner** with an
  end date roughly 3 months out and a $100 threshold.
- Buy something from that vendor as a buyer, then open the order in
  Admin → Orders: the **royalty is $0.00** and the vendor payout equals
  the full subtotal.

### B4. Limits

- Try registering another new vendor with `LAUNCH2026` → the code is now
  used up. It is **silently ignored** (signup still succeeds, the vendor
  just gets no trial). That's intended, not a bug — confirm no trial
  banner appears for them.
- **Deactivate** `LAUNCH2026`, register another new vendor with it →
  again ignored, no trial.
- A typo'd / nonexistent code is also silently ignored.

---

## Optional — vendor's own coupons

Not required by the checklist item, but it's the same screen so it's
cheap to check. A vendor creates their own codes at
`/products/?tab=coupons`.

The one thing that must be true: a **vendor coupon comes out of that
vendor's own payout**, unlike a sitewide one.

1. As a vendor, create `MYSHOP10` (10% off).
2. Admin → Coupons: it appears with the **shop's name** in the Shop
   column, not a dash.
3. As a buyer with a cart containing **that shop plus another shop**,
   apply `MYSHOP10`. The discount should only be calculated on that one
   shop's items, and checkout labels it "(Shop Name only)".
4. Place the order → in Admin, **that vendor's payout is reduced by the
   discount**; the other shop's order is untouched.
5. With a cart containing **none** of that shop's items, applying it
   gives: "This code only applies to items from a specific shop, which
   isn't in your cart."

---

## Tick the box when

- [ ] Sitewide coupon created and discounts an order correctly
- [ ] Min subtotal blocks below, allows above
- [ ] Expired rejected, not-yet-started rejected, expires-today accepted
- [ ] Max uses stops the code after the limit
- [ ] Same buyer can't reuse a code
- [ ] Vendor payout + royalty unaffected by a sitewide discount
- [ ] Multi-vendor discount splits proportionally and adds up
- [ ] Promo code gives a new vendor a trial, and $0 royalty on their sale
- [ ] Promo code stops working past its use limit / when deactivated
