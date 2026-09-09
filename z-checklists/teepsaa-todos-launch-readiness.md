# teepsaa — Launch Readiness

Everything between now and taking the pre-launch Basic Auth gate off. Work top
to bottom: each part assumes the one before it passed.

Consolidated 2026-08-23 from `todos-functional-testing`, `todos-audit`,
`todos-device-testing`, `todos-email`.

Companion files: `teepsaa-todos-mobile-app.md` (after launch),
`teepsaa-todos-seed-content.md` (the full seed comes last, after both apps —
but read its "minimum you need earlier" section, because several checks below
need products to exist), `teepsaa-production-deploy.md`,
`teepsaa-open-questions.md`.

**Build order:** website → buyer app → Seller app → full content seed → pitch.

**Test on the live Hostinger site, not local MAMP** — real emails, real
uploads, real `.htaccess`. Use Gmail +aliases for throwaway accounts
(`dustint505+test1@gmail.com`).

## What's left

| Part                   | Checks | What it is                             |
| ---------------------- | ------ | -------------------------------------- |
| 3. Real-device testing | 27     | Mobile only — sweep first, then phones |

**Part 3 is the only part left.** Everything else is finished and archived in
`teepsaa-completed.md`:

- **Parts 1 and 2** — the last functional-testing gaps and the whole code &
  security audit, 34 checks, closed out between 2026-08-23 and 2026-09-04.
- **Part 4 — Email**, closed out 2026-09-09: the deploy, the 31-template seed,
  the digest crons and all five live sends.
- **Part 5 — Flip to production**, closed out 2026-09-09: the payout window,
  PHP error settings, the cron interpreter, `/uploads/`, and the pre-launch
  gate. **The site is now publicly reachable** — the gate came off that day.

The part numbers here are deliberately not renumbered, so every "Part 2b"
reference still points at the same thing.

Below Part 3 sit two Findings sections. Nothing in them blocks launch: one
hardening item, one deferred re-run of the POST-only paths, and a
post-launch cleanup list.

**Also already done and not repeated here:** 96 of 101 functional tests, and the
whole of `teepsaa-completed.md`. Audit sections for Buyer Flow, Vendor Flow and
Admin Flow (17 checks) were dropped during consolidation because every one of
them is already ticked in functional testing — cross-role rejection, cart and
checkout, product CRUD, archive, approvals, order management, messages.

---

# Part 3 — Real-device testing

Cambodia is overwhelmingly mobile and mostly Android. Khmer script rendering
and touch behaviour genuinely differ from the desktop browser's device mode —
test on real hardware.

## 3a. Devices to cover

Desktop and tablet are deliberately not listed: the whole site was built and
used on a MacBook and an iPad throughout, so anything broken at those widths
would have shown up months ago. What is left is mobile.

- [ ] **Android phone, Chrome** — the single most important combination. No
      Android hardware here, so this is the Android Studio emulator: an arm64
      system image, a low-RAM device profile, Android 10 and 13/14. The
      emulator is what makes it worth doing — it carries Android's own Khmer
      font stack (Noto Sans Khmer), which stacks glyphs differently from iOS,
      and that is the one thing a Mac cannot fake.
- [ ] **iPhone, Safari** — the iPhone 6s. It tops out at iOS 15, which makes
      it the oldest WebKit and the slowest CPU you will realistically test on,
      so it is the worst case rather than a compromise. Newer WebKit is
      covered by desktop Safari, which runs essentially the same engine.
- [ ] **A cheap or old Android** if you can borrow one — slow CPU, small
      screen. This is what a lot of your buyers actually have. The emulator's
      low-RAM profile approximates it; ten minutes with a real one is better.

## 3a-i. Viewport sweep — do this first, on the MacBook

Cheapest pass available and it finds most layout bugs before you touch a
device. Only two engines matter: **Blink** (Chrome, Edge, Samsung Internet,
Opera, Brave, and DuckDuckGo on Android) and **WebKit** (Safari, and _every_
browser on iOS — Chrome and DuckDuckGo included, because Apple forces the
engine). Firefox's Gecko is a rounding error here. So the sweep runs twice,
once in each, and no other browser needs its own pass.

**Chrome:** ⌘⌥I, then the phone icon in the toolbar.
**Safari:** Settings → Advanced → "Show features for web developers", then
Develop → Enter Responsive Design Mode (⌘⌃R). In both, type an exact width
rather than picking a device preset — the number is the thing being tested.

Chrome — four from that list, plus one typed:

- Samsung Galaxy S8+ = 360px — this is the important one, the most common Android width in Cambodia
- iPhone SE = 375px
- iPhone 12 Pro = 390px
- iPhone 14 Pro Max = 430px
- Responsive (top of the list) → type 320 for the stress test

Skip every iPad, Surface, Nest Hub, and the folds. Pixel 7/8/9/10 and Galaxy S20 Ultra are all ~412 — close enough to 430 that they add nothing.

Safari — it has no Android widths, so:

- iPhone SE size = 375
- iPhone size = 393 (the readout beside the dropdown shows it, as in your screenshot)
- iPhone Pro Max size = ~430
- Custom size → type 360, and 320 if you want the stress test

Widths, and what each one is for:

| Width | What it represents                                     |
| ----- | ------------------------------------------------------ |
| 320px | Smallest phone still in use — where things break first |
| 360px | **The most common Android width in Cambodia**          |
| 390px | iPhone 14 / Pixel, the modern typical                  |
| 430px | Pro Max / large Android                                |

- [ ] **Chrome — all four widths.** Homepage, search, a product page, cart,
      checkout, a vendor product form.
- [ ] **Safari — all four widths.** Same pages. Same engine as every iOS
      browser, so this one pass covers all of them.
- [ ] **Drag the width slowly from 320 up to ~500 in each**, rather than only
      stopping at the four numbers. Breakpoints fail _between_ the presets,
      and dragging is what finds them.
- [ ] **Watch for horizontal scroll at every width** — see the 3b check. If
      the page slides sideways, something has a fixed width.

## 3b. Layout, on each device

- [ ] **Homepage** — header, search bar, banner carousel and the product rows
      all scroll horizontally without breaking out of the page.
- [ ] **No horizontal page scroll at any width.** Spot-check below 400px. If
      the page slides sideways, something has a fixed width.
- [ ] **Product cards** — long names truncate cleanly, including long Khmer
      names, rather than pushing the card out of shape.
- [ ] **Product detail** — the gallery swipes and taps, and variant buttons are
      big enough to hit with a thumb.
- [ ] **Forms** (register, address, add product) — usable with a phone keyboard,
      labels stay visible, and validation errors appear where you can see them
      without hunting.
- [ ] **Maps** (address pin, business pin) — pan and zoom by touch, the pin
      drops where you tap, and the map doesn't hijack page scrolling when you
      try to scroll past it.
- [ ] **Photo gallery drag-to-reorder works by touch** on the vendor edit
      product page. Drag-and-drop is the classic thing that works with a mouse
      and not a finger.
- [ ] **Header nav and menus are thumb-usable**, and the notification dropdown
      fits on screen instead of running off the edge.
- [ ] **Footer stacks correctly** and the tagline font (Pacifico / Metal) loads.
      A brief fallback flash is fine; a wrong font that never corrects is not.
- [ ] **Khmer text renders cleanly** — no overlapping or clipped characters.
      Khmer stacks glyphs vertically, so line-height problems show up on phones
      first. Check dates render in Khmer numerals where they should.

## 3c. Function, on each device

- [ ] **Run the full buyer flow on a phone** — register, verify, add to cart,
      set address and pin, check out. Do it as a first-time user would, without
      shortcuts.
- [ ] **Upload a photo from the phone camera** (vendor add product, and the ABA
      QR). Large camera images must either be accepted or rejected with a clear
      message — never fail silently.
- [ ] **Upload a resume from a phone** on the careers form.
- [ ] **Currency and language switchers** are reachable and work on mobile.

## 3d. Slow connections

- [ ] **Throttle to 3G / slow 4G in dev tools** and load the homepage. It should
      be usable in reasonable time, with images lazy-loading rather than
      blocking the page.
- [ ] **Check total homepage weight** in dev tools → Network. More than a few MB
      means filler product photos need compressing before you add more.
- [ ] **Confirm checkout on a slow connection.** Tap the confirm button twice
      while it's waiting — you must not get two orders.

## 3e. Worth checking once

- [ ] **Add to Home Screen on Android** — the icon and title look right.
- [ ] **Share a site link in Telegram** — huge in Cambodia. The preview title,
      description and image come from the OG tags in `config/seo.php`.
- [ ] **Open a teepsaa email in the Gmail phone app** and check mixed Khmer and
      English blocks render properly.

---

# Findings from the display_errors sweep (2026-09-04)

Two live bugs, from 384 URLs / 88 pages swept as public, buyer, vendor and
admin. The first — `/sitemap.php` fatalling on every request — was fixed
2026-09-04 and is in `teepsaa-completed.md`. This is the other one.

- [ ] **`/order-status/order-status.php` and `/refund-status/refund-status.php`
      are directly web-reachable but are include-fragments, not pages.** Both
      open with `// Expects $orderStatus (string) to be set before including.`,
      so a direct GET renders a broken partial plus
      `Warning: Undefined variable $orderStatus`, which leaks the absolute
      server path. Harmless to the app's own flows (every real include sets the
      variable first) — this is hardening, not a launch blocker. Either guard
      the top of each file with a `defined()`/`isset()` bail-out, or deny them
      in `.htaccess`.

Explicitly NOT covered by this sweep, and still to do by hand with errors
visible: every POST-only path — checkout, cart mutations, product submit,
registration, file uploads, admin action endpoints (`*-action.php`). Those only
execute on a real form submission. Re-run the Part 1 functional flows once with
`display_errors` on.
**Note this now costs more than it did.** `display_errors` was set to `Off` on
2026-09-09 as part of Part 5, and the site is live, so doing this means turning
errors back on in hPanel while real visitors are on the site. Either accept
that for a short window at a quiet hour, or read `~/.logs/error_log_teepsaa_com`
instead — `log_errors` is `On` and `error_reporting` is `E_ALL`, so the same
warnings are being written there without being shown to anyone.

Deliberate behaviour confirmed as correct, not bugs: `/support-thread/` returns
404 on a missing/invalid `?t=` token (`http_response_code(404)`), `/admin/` 302s
to `/admin/orders.php`, and `/product/` + `/business/` 302 to `/search/` when the
`public_id` does not match — note those two key on a UUID `public_id`, never a
numeric id, so `?id=1` never reaches the page body.

---

# Findings from the static audit pass (2026-08-31)

Everything in Part 2 that could be checked by reading the code rather than
clicking the site was run; eleven checks passed and are archived in
`teepsaa-completed.md`. What follows is what those checks turned up and is
still outstanding. The two public-facing findings — the `/browse/` sitemap
entry and the dead footer social links — were both fixed and are archived with
the rest. None of what remains blocks launch.

## Cut from launch scope 2026-09-03 — post-launch cleanup, not launch work

None of these block launch and none are tests. Kept as one-liners so the
findings aren't lost; do them whenever, after launch:

- CSRF tokens on 4 minor POST handlers (`api/notifications/mark-read.php`,
  `api/wishlist/toggle.php`, `lang/set.php`, `currency/set.php`) — already
  mitigated by the `SameSite=Strict` cookie; worst case is nuisance writes on
  the attacker's victim's own account.
- `products/toggle.php` — add `AND archived = 0` to its UPDATE. Nothing leaks
  publicly today; it just allows an odd `archived=1, active=1` row.
- Delete the dead CSS classes in the table below.
- Host-scoped Basic Auth on `admin.teepsaa.com` (was an "optional" Part 5
  item) — already tracked in `teepsaa-open-questions.md`.

## Dead CSS

`--modifier` classes composed at runtime and the `mapboxgl-*` library classes
were excluded, so these are genuinely unreferenced:

| File                                     | Dead classes                                                                                                                                                                                                                                                                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `admin/admin.css`                        | `add-cat-form`, `admin-card-actions`, `admin-card-info`, `admin-list`, `cat-desc`, `cat-section`, `cat-table`, `order-card-business`, `payout-card`, `payout-no-qr`, `payout-note`, `payout-qr`, `refund-popup-note`, `refund-popup-reason`, `review-vendor-sub`, `section-divider`, `suspend-details`, `suspend-summary` |
| `popup/popup.css`                        | `popup-close`, `popup-inline-form`, `popup-modal`, `popup-overlay`, `popup-payout-box`, `popup-photos`, `popup-status-bar`, `popup-title`, `popup-total--payout`                                                                                                                                                          |
| `orders-buyer/orders-buyer.css`          | `order-card-action`, `order-card-business`, `order-track-link`                                                                                                                                                                                                                                                            |
| `settings-buyer/settings-buyer.css`      | `avatar-form`, `settings-field-row`                                                                                                                                                                                                                                                                                       |
| `privacy/privacy.css`, `terms/terms.css` | `legal-effective`, `legal-note` (both files)                                                                                                                                                                                                                                                                              |
| `cart/cart.css`                          | `cart-total-row`                                                                                                                                                                                                                                                                                                          |
| `checkout/checkout.css`                  | `checkout-total-row`                                                                                                                                                                                                                                                                                                      |
| `admin/order-detail.css`                 | `od-back`                                                                                                                                                                                                                                                                                                                 |
| `header/header.css`                      | `lang-chevron`                                                                                                                                                                                                                                                                                                            |
| `style.css`                              | `flash-badge`                                                                                                                                                                                                                                                                                                             |

(Post-launch cleanup — see "Cut from launch scope" above. `popup.css` is the
interesting one: the modal shell itself (`popup-modal`, `popup-overlay`,
`popup-close`, `popup-title`) is dead while the contents (`popup-row`,
`popup-items`, `popup-total`) are live, so the shell was reimplemented
somewhere else and the old rules were left behind. Worth a look before
deleting, in case the new shell is the duplicate.)

# Appendix — what emails exist today

Reference, not a checklist. Kept so you don't have to grep for it.

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
