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

| Part                         | Checks | What it is                         |
| ---------------------------- | ------ | ---------------------------------- |
| 1. Finish functional testing | 13     | The last gaps in the flow testing  |
| 2. Code & security audit     | 23     | The pass that hasn't been started  |
| 3. Real-device testing       | 25     | Phones, not device mode            |
| 4. Email deploy + tests      | 8      | 14 built templates awaiting deploy |
| 5. Flip to production        | 6      | Config changes and the gate        |

**Already done and not repeated here:** 96 of 101 functional tests, and the
whole of `teepsaa-completed.md`. Audit sections for Buyer Flow, Vendor Flow and
Admin Flow (17 checks) were dropped during consolidation because every one of
them is already ticked in functional testing — cross-role rejection, cart and
checkout, product CRUD, archive, approvals, order management, messages.

---

# Part 1 — Finish functional testing

## 1a. Vendor account lifecycle

These were the never-started tests. All need a fresh vendor registered with a
+alias; two of them need a second throwaway vendor as well.

- [x] **Before approval, the business is invisible.** Register a vendor, submit
      a business, then in a logged-out browser search for the business name. It
      must not appear anywhere: search, homepage, category pages.
      (verified live 2026-08-23)
- [ ] **Before approval, products cannot be created at all.** You can't add a
      product to an unapproved business — the form isn't offered. Confirm the
      *server* enforces that too, not just the UI: with the vendor logged in and
      their business still pending, POST directly to `/products/save.php` with
      any product fields. It must redirect to `/products/` and create nothing.
      Check the products table afterwards to be sure. The gate is
      `SELECT ... WHERE user_id = ? AND approved = 1` followed by an early exit
      when empty, repeated in all nine product action files.
- [ ] **Approve, and the store goes live.** As admin, approve the business.
      The vendor should get the `business_approved` email, and the business
      page should now be reachable to a logged-out visitor. Add a product as the
      vendor and confirm it appears in search and on the business page.
- [ ] **Reject, and the vendor sees why.** Use a second throwaway vendor.
      Reject the business as admin, then log in as that vendor and confirm the
      dashboard shows a rejection state rather than a blank or broken page.
      They should get the `business_rejected` email.
- [ ] **Rejecting an already-approved business hides its products.** This is the
      only way a product can exist under a non-approved business, so it's the
      real test of the invisibility rule. Approve a business, add a product,
      confirm the product is publicly visible — then reject the business as
      admin (`approved` goes to `-1`) and confirm in a logged-out browser that
      both the business page and the product disappear from search, homepage
      and category pages. A stale product still reachable by direct URL is the
      failure to watch for.
- [ ] **The vendor portal rejects buyer credentials.** Enter a known-good
      _buyer_ email and password at `/login-vendor/`. It must fail with the
      same generic "Invalid email or password" — it must not reveal that the
      account exists as a buyer. (The mirror of this, vendor creds at
      `/login-buyer/`, already passed.)
- [ ] **Vendor forgot-password works end to end.** Request a reset at
      `/forgot-password-vendor/`, click the emailed link, set a new password.
      Then confirm three things: the old password no longer works, the new one
      does, and clicking the same reset link a second time is rejected.

## 1b. Seven gaps hiding inside ticked boxes

These items are ticked, but their notes admit part of the test never ran.
They're listed here as real work.

- [ ] **Live status polling.** Order statuses did advance correctly in the live
      run, but the auto-refresh itself was never watched. Open a buyer's order
      page and leave it sitting. In another browser, advance the order as
      vendor/admin. The buyer's page should update on its own, with no manual
      reload.
- [ ] **Notification bell actions.** Bells were confirmed _arriving_ during the
      refund run. Now test the actions: click a single notification and confirm
      it goes read and the count drops by one; then use mark-all-read and
      confirm the count hits zero and stays zero after a reload.
- [ ] **Vendor new-order and low-stock bells.** Only the refund-request bell was
      confirmed. Place an order against a vendor and check the bell fires; set a
      product's low-stock threshold just above its stock, sell one, and check
      that bell fires too.
- [ ] **The final payout click.** The payout _window_ gating was verified in
      both states with a backdated `delivered_at`, and the server-side guard was
      added — but "mark paid out" was never actually clicked. Click it on a real
      delivered order and confirm the order moves to completed, the vendor gets
      the `payout_sent` email, and the order leaves the payouts queue.
- [ ] **The refund reject path.** The full happy-path refund cycle passed. Run
      the other branch: buyer requests a refund, admin _rejects_ it. The buyer
      should get the `refund_rejected` email and the order should return to a
      sane status rather than getting stuck.
- [ ] **Vendor wrong-code and resend on verification.** The vendor verification
      email arrived in the live registration, but only the happy path ran. Enter
      a wrong code (must be rejected), then hit resend and confirm the new code
      works _and_ the old one has stopped working.
- [ ] **Buyer credentials at the vendor portal** — this is the same test as the
      last item in 1a. Tick both together.

---

# Part 2 — Code & security audit

Not started. This is the last chance to catch something before real money moves
through the site.

## 2a. Automated review

- [ ] **Run `/code-review ultra` in Claude Code.** It's a multi-agent review of
      the branch covering bugs, security and inefficiency. You have to trigger
      it yourself — Claude can't launch it.
- [ ] **Feed it in sections rather than all at once**, most-recent work first:
      `products/` and `dashboard-vendor/`, then `cart/ checkout/ product/
    search/`, then `header/ footer/ config/ api/`.

## 2b. PHP and server

- [ ] **Turn on `display_errors` in dev and click through every page.** You're
      hunting notices and warnings that don't fatal but reveal bugs — undefined
      array keys, null property reads. Remember to turn it back off (Part 5).
- [ ] **Run `php -l` over the whole tree** to catch syntax errors in anything
      heavily edited:
      `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'`
      Silence means clean.
- [ ] **Confirm every `session_start()` has the full options block.** `.user.ini`
      is disabled on Hostinger so nothing is inherited. Each one needs
      `gc_maxlifetime => 28800` and the `cookie_domain` line. Find the odd ones
      out: `grep -rn "session_start" --include="*.php" . | wc -l` then compare
      against `grep -rn "gc_maxlifetime" --include="*.php" . | wc -l` — the two
      counts should match.
- [ ] **Confirm CSRF is on every POST form.** List the forms
      (`grep -rln "method=\"post\"" --include="*.php" .`) and check each one
      calls `csrf_input()`, and that its action file calls `csrf_verify()`.

## 2c. Security

Four of the original six are already in place — magic-byte upload validation in
`config/upload.php`, `/uploads/` blocking PHP execution via `.htaccess`, and
both cross-role rejection tests passing in functional testing. These two remain:

- [ ] **Check output escaping.** Every place user-supplied text is printed needs
      `htmlspecialchars()`. The risky spots are product names/descriptions,
      business names, review text, support messages and admin notes — anywhere a
      vendor or buyer's own words get rendered.
- [ ] **Check every query is prepared.** Search for string interpolation into
      SQL: `grep -rn 'query("' --include="*.php" .` and
      `grep -rnE '\$(_POST|_GET)\[' --include="*.php" . | grep -i "select\|insert\|update\|delete"`.
      Anything that concatenates a variable into SQL instead of binding it is a
      bug.

## 2d. Dead code

Cheap to do and it shrinks what you have to maintain forever.

- [ ] **Find orphaned action files.** For each `*-action.php` or similar, grep
      the tree for its filename. If nothing references it, no form posts to it
      and it can go.
- [ ] **Confirm `photo-set-primary.php` is still used.** The star button that
      called it was removed. If nothing references it, delete it.
- [ ] **Find unused CSS classes**, especially after the `products/` and
      `dashboard-vendor/` refactors. Pull the class names out of a page's CSS
      and grep the matching PHP for each.
- [ ] **Find unused JS.** `js/` currently holds `boundary.js`, `geo-capture.js`,
      `notifications.js`, `photo-shrink.js`, `square-cropper.js`,
      `status-refresh.js`. Grep for each filename; anything nothing includes is
      dead.

## 2e. Database integrity

Run each query against the live database. Each should return zero rows, or a
result you can explain.

- [ ] **Orphaned product photos:**
      `SELECT COUNT(*) FROM product_photos p LEFT JOIN products pr ON pr.id = p.product_id WHERE pr.id IS NULL;`
- [ ] **Orphaned cart items:**
      `SELECT COUNT(*) FROM cart_items c LEFT JOIN products p ON p.id = c.product_id WHERE p.id IS NULL;`
- [ ] **Order items with a deleted product.** Expect some — products get
      deleted. What matters is that order pages still render the snapshot name
      instead of blowing up. Find one and open the order as buyer, vendor and
      admin.
- [ ] **Confirm `archived = 0` is filtered everywhere buyers see products.**
      Grep every buyer-facing query — homepage, search, business page, category
      — and check the filter is present. An archived product leaking into search
      is the failure.
- [ ] **Confirm at most one primary photo per product:**
      `SELECT product_id, COUNT(*) FROM product_photos WHERE is_primary = 1 GROUP BY product_id HAVING COUNT(*) > 1;`

## 2f. Frontend

Mobile layout checks live in Part 3. These are the desktop ones.

- [ ] **Confirm images load everywhere**: homepage, search, business page,
      product detail, vendor products list, vendor dashboard. Open dev tools and
      watch for 404s rather than trusting your eyes — a broken image can look
      like an intentional gap.
- [ ] **Click every link in the header and footer** in both languages. Broken
      footer links are the easiest thing to ship and the most embarrassing.

---

# Part 3 — Real-device testing

Cambodia is overwhelmingly mobile and mostly Android. Khmer script rendering
and touch behaviour genuinely differ from the desktop browser's device mode —
test on real hardware.

## 3a. Devices to cover

- [ ] **Android phone, Chrome** — the single most important combination
- [ ] **iPhone, Safari**
- [ ] **A cheap or old Android** if you can borrow one — slow CPU, small screen.
      This is what a lot of your buyers actually have.
- [ ] **Desktop: Chrome, Safari, Firefox**
- [ ] **Tablet, or a desktop window dragged to half width** — the in-between
      widths where layouts break

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

# Part 4 — Email

SMTP is live (Hostinger, `contact@teepsaa.com`). Templates are bilingual and
staff-editable at Admin → Messages → Emails, with fallback defaults in
`config/email-templates.php` so sends work even before seeding.

**14 new templates are built but not deployed.** Do the deploy steps first,
then the tests.

## 4a. Deploy the new templates

- [ ] **Deploy the code** — lftp mirror per `deploycode.txt`.
- [ ] **Run `database/seed-email-templates.php` against the live database.**
      It only inserts missing keys and never overwrites staff edits, so it's
      safe to re-run. Afterwards the 14 new templates should be listed in
      Admin → Messages → Emails.
- [ ] **Register the digest cron in hPanel** — `/usr/bin/php` plus the full
      path to `cron/admin-digest.php`, once daily around 07:00 Phnom Penh. Use
      PHP CLI, not an HTTP hit: HTTP is blocked by the Basic Auth gate.

## 4b. Live tests

- [ ] **Verify a new account** and confirm the welcome email arrives.
- [ ] **Do a password reset** and confirm that email arrives and the link works.
- [ ] **Place a test order** and confirm the order confirmation arrives with
      correct items, business names, totals, discount line and delivery note.
- [ ] **Approve a business** and confirm the vendor gets `business_approved`.
- [ ] **Confirm a payment as admin** and check the vendor gets the new-order
      email — this is what finally makes the "Vendors have been notified"
      message on screen actually true.

## 4c. If email misbehaves

- [ ] **If anything lands in spam:** hPanel → Emails → confirm the mailbox
      exists and SPF/DKIM records are set. Hostinger adds these automatically
      when DNS is hosted with them, but check hPanel → Emails → DNS settings
      rather than assuming.
- [ ] **If sends fail outright:** read `mail.log` on the server. SMTP errors are
      logged there together with the server's own reply, which usually names the
      problem.

---

# Part 5 — Flip to production

Do these last, together, in one sitting. Several checklists listed the same
items — they're deduplicated here.

- [ ] **Set `PAYOUT_WINDOW_SECONDS` to `86400`** in the server's
      `config/db.php`. It is currently **`60`**, the dev value. Leaving it means
      vendors can be paid out a minute after delivery.
- [ ] **Set `display_errors = Off`.** If you turned it on for Part 2b, this is
      where it goes back.
- [ ] **Confirm `/uploads/` is writable by the web server user**, and that its
      `.htaccess` PHP-execution block is still in place after the deploy.
- [ ] **Search for leftover `TODO` and `FIXME` comments** —
      `grep -rn "TODO\|FIXME" --include="*.php" . | grep -v z-checklists` — and
      either fix or delete each one.
- [ ] **Remove the pre-launch gate** — delete the Basic Auth block from
      `.htaccess` and remove `.htpasswd` from the server. The exposure fix this
      was waiting on (removing `z-checklists/` and `database/`) was completed
      2026-07-10.
- [ ] **Optionally add host-scoped Basic Auth on `admin.teepsaa.com`** at the
      same moment — the same `.htpasswd` technique scoped by host
      (`SetEnvIf Host ^admin\.teepsaa\.com ADMIN_HOST`). Doing it now rather
      than earlier avoids two password prompts on admin pages. See
      `teepsaa-open-questions.md`.

---

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
| Order dispatched                 | `order_dispatched`  | `dashboard-vendor/dispatch.php`                       |
| Abandoned cart reminder          | `abandoned_cart`    | `cron/abandoned-cart.php` (daily)                     |
| Review reminder after delivery   | `review_reminder`   | `cron/review-reminder.php` (daily)                    |
| Welcome after verification ⚠     | `welcome_buyer`     | `verify-email/verify.php`                             |
| Order cancelled ⚠                | `order_cancelled`   | `admin/order-action.php`, `admin/payments-action.php` |
| Return approved ⚠                | `refund_approved`   | `admin/refund-action.php`                             |
| Refund declined ⚠                | `refund_rejected`   | `admin/refund-action.php`                             |
| Refund sent via ABA ⚠            | `refund_sent`       | `admin/refund-action.php`                             |
| Password changed ⚠               | `password_changed`  | `dashboard-buyer/settings/password-action.php`        |
| Account deleted ⚠                | `account_deleted`   | `dashboard-buyer/settings/delete-action.php`          |

## Vendor

| Event                            | Template             | Sent from                                                                         |
| -------------------------------- | -------------------- | --------------------------------------------------------------------------------- |
| Registration → verification code | `verify_code`        | `register-vendor/register-vendor.php`                                             |
| Resend verification code         | `verify_code`        | `resend-verification/resend.php`                                                  |
| Password reset link              | `reset_password`     | `forgot-password-vendor/request.php`                                              |
| Low stock after a sale           | `low_stock`          | `checkout/confirm.php`                                                            |
| Buyer confirmed delivery         | `delivery_confirmed` | `dashboard-buyer/confirm-delivery.php`                                            |
| Payout sent                      | `payout_sent`        | `admin/payouts-action.php`                                                        |
| Welcome after verification ⚠     | `welcome_vendor`     | `verify-email/verify.php`                                                         |
| Business submitted ⚠             | `business_submitted` | `submit/submit.php`                                                               |
| Business approved ⚠              | `business_approved`  | `admin/action.php`                                                                |
| Business rejected ⚠              | `business_rejected`  | `admin/action.php`                                                                |
| Business deleted ⚠               | `business_deleted`   | `dashboard-vendor/settings/business-delete-action.php`, `admin/vendor-action.php` |
| New paid order ⚠                 | `vendor_new_order`   | `admin/payments-action.php`                                                       |
| Refund requested ⚠               | `refund_requested`   | `dashboard-buyer/refund-request.php`                                              |
| Password changed ⚠               | `password_changed`   | `dashboard-vendor/settings/password-action.php`                                   |
| Account deleted ⚠                | `account_deleted`    | `dashboard-vendor/settings/delete-action.php`                                     |

## Admin

| Event               | Template                    | Sent from                                                                                                                                                |
| ------------------- | --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| New job application | inline HTML, not a template | `careers/apply.php` → `ADMIN_EMAIL`                                                                                                                      |
| Daily digest ⚠      | `cron/admin-digest.php`     | pending payments, refund requests, business approvals, unread support threads, payouts due, canvassing follow-ups — sends only when a queue is non-empty |

⚠ = built but awaiting the Part 4a deploy.

Before the digest existed, the job application was the admin's _only_ email —
everything else was dashboard-badge only and required logging in to notice.
