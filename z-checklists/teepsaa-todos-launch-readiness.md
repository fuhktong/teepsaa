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
| 2. Code & security audit     | 12     | 11 static checks done 2026-08-31   |
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
- [x] **Before approval, products cannot be created at all.** You can't add a
      product to an unapproved business — the form isn't offered. Confirm the
      _server_ enforces that too, not just the UI: with the vendor logged in and
      their business still pending, POST directly to `/products/save.php` with
      any product fields. It must redirect to `/products/` and create nothing.
      Check the products table afterwards to be sure. The gate is
      `SELECT ... WHERE user_id = ? AND approved = 1` followed by an early exit
      when empty, repeated in all nine product action files.
- [x] **Approve, and the store goes live.** As admin, approve the business.
      The vendor should get the `business_approved` email, and the business
      page should now be reachable to a logged-out visitor. Add a product as the
      vendor and confirm it appears in search and on the business page.
- [x] **Reject, and the vendor sees why.** Use a second throwaway vendor.
      Reject the business as admin, then log in as that vendor and confirm the
      dashboard shows a rejection state rather than a blank or broken page.
      They should get the `business_rejected` email.
- [x] **Rejecting an already-approved business hides its products.** This is the
      only way a product can exist under a non-approved business, so it's the
      real test of the invisibility rule. Approve a business, add a product,
      confirm the product is publicly visible — then reject the business as
      admin (`approved` goes to `-1`) and confirm in a logged-out browser that
      both the business page and the product disappear from search, homepage
      and category pages. A stale product still reachable by direct URL is the
      failure to watch for.
- [x] **The vendor portal rejects buyer credentials.** Enter a known-good
      _buyer_ email and password at `/login-vendor/`. It must fail with the
      same generic "Invalid email or password" — it must not reveal that the
      account exists as a buyer. (The mirror of this, vendor creds at
      `/login-buyer/`, already passed.)
- [x] **Vendor forgot-password works end to end.** Request a reset at
      `/forgot-password-vendor/`, click the emailed link, set a new password.
      Then confirm three things: the old password no longer works, the new one
      does, and clicking the same reset link a second time is rejected.

## 1b. Six tests that were marked done but never fully ran

In the old checklists these were ticked off, but the note next to each one
admitted part of the test was skipped. That skipped part is what you're
testing here. Each item says which accounts you need and where to click.

- [x] **Clicking a bell notification marks it read.** Any account with unread
      notifications works — easiest is the vendor from the test above, who just
      got one. 1. Click the bell in the header. Note the unread count. 2. Click one notification. The count should drop by one. 3. Click "mark all read". Count goes to zero. 4. Reload the page. Count must still be zero — if the unread count comes
      back after a reload, the "read" never reached the database.
- [x] **The vendor bell fires for a new order and for low stock.** Needs a
      buyer, a vendor, and the admin. 1. New-order bell: as a buyer, order one of the vendor's products; as
      admin, confirm the payment (admin → Orders → Payments). Log in as the
      vendor — the bell should show the new paid order. 2. Low-stock bell: as the vendor, edit a product so its low-stock
      threshold is _higher_ than its current stock (e.g. stock 3,
      threshold 5). As the buyer, buy one. The vendor's bell should show a
      low-stock notification.
- [x] **"Mark paid out" actually works.** As admin, on an order the buyer has
      confirmed as delivered (the server's payout window is currently 60
      seconds, so a minute after delivery is enough). 1. Admin → Orders → Payouts. The order should be listed. 2. Click "mark paid out". 3. Confirm three things: the order's status becomes completed, it is
      gone from the payouts list, and the vendor receives the `payout_sent`
      email.
- [x] **Rejecting a refund doesn't strand the order.** Needs a buyer with a
      delivered order, and the admin. 1. As the buyer, open the order at `/orders-buyer/` and request a refund. 2. As admin, go to Orders → Refunds and _reject_ it (the approve path
      already passed — you're testing reject). 3. The buyer should get the `refund_rejected` email, and the buyer's
      order page should show a normal status again (delivered), not be
      stuck saying refund requested or show anything broken.
- [x] **A wrong vendor verification code is rejected, and resend works.**
      Register a fresh vendor with a +alias to get to the "enter the code we
      emailed you" screen. 1. Type a wrong code — it must be rejected with an error. 2. Click resend. A second email arrives with a new code. 3. The old code from the first email must now fail, and the new code
      must work.

(The old list had a seventh item — buyer credentials rejected at
`/login-vendor/` — but that's the same test as the last item in 1a, which
already passed.)

---

# Part 2 — Code & security audit

The last chance to catch something before real money moves through the site.

**Status:** the static half was run 2026-08-31 — eleven checks passed and are
ticked below. What is left needs a browser, a running database, or a paid tool:
`/code-review ultra` (2a), the `display_errors` click-through (2b), the five
integrity queries (2e), and the image-404 sweep (2f). What the static pass
turned up is written up under **Findings** near the end of this file.

## 2a. Automated review

- [x] **Run `/code-review ultra` in Claude Code.** It's a multi-agent review of
      the branch covering bugs, security and inefficiency. You have to trigger
      it yourself — Claude can't launch it.
- [x] **Feed it in sections rather than all at once**, most-recent work first:
      `products/`, `analytics/`, `settings-vendor/` and `business-vendor/`, then `cart/ checkout/ product/
search/`, then `header/ footer/ config/ api/`.

## 2b. PHP and server

- [x] **Turn on `display_errors` in dev and click through every page.** You're
      hunting notices and warnings that don't fatal but reveal bugs — undefined
      array keys, null property reads. Remember to turn it back off (Part 5).
      (verified 2026-09-04 — 384 URLs across 88 distinct pages swept by curl as
      public/buyer/vendor/admin. **`display_errors` alone was a false pass:**
      hPanel had it `On` but `error_reporting` was `-32768` (= report nothing),
      so PHP printed no diagnostics regardless. Proved with a throwaway probe,
      then forced with `php_value error_reporting 32767` in the server's root
      `.htaccess` — `php_value` does work under Hostinger's LiteSpeed. Two live
      bugs found, both listed under Findings. Note the web SAPI is PHP 8.3.33
      while the CLI is 8.1.34, so the Part 2b `php -l` pass ran on a different
      PHP than serves the site. POST-only paths — checkout, submit, register,
      uploads — are NOT covered by this sweep and still need the Part 1 flows
      re-run by hand with errors visible.)
- [x] **Run `php -l` over the whole tree** to catch syntax errors in anything
      heavily edited:
      `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'`
      Silence means clean.
      (verified 2026-08-31 — clean, zero syntax errors across the tree)
- [x] **Confirm every `session_start()` has the full options block.** `.user.ini`
      is disabled on Hostinger so nothing is inherited. Each one needs
      `gc_maxlifetime => 28800` and the `cookie_domain` line. Find the odd ones
      out: `grep -rn "session_start" --include="*.php" . | wc -l` then compare
      against `grep -rn "gc_maxlifetime" --include="*.php" . | wc -l` — the two
      counts should match.
      (verified 2026-08-31 — 187 real `session_start(` calls, 187 `gc_maxlifetime`
      lines, per-file counts match. The raw grep shows 190 vs 187: the extra
      three are the word `session_start` inside comments in `config/subdomain.php`
      and `admin/go.php`, not calls.)
- [x] **Confirm CSRF is on every POST form.** List the forms
      (`grep -rln "method=\"post\"" --include="*.php" .`) and check each one
      calls `csrf_input()`, and that its action file calls `csrf_verify()`.
      (verified 2026-08-31 — every file containing `method="post"` calls
      `csrf_input()`. Four `$_POST` handlers verify no token: see Findings.)

## 2c. Security

Four of the original six are already in place — magic-byte upload validation in
`config/upload.php`, `/uploads/` blocking PHP execution via `.htaccess`, and
both cross-role rejection tests passing in functional testing. These two remain:

- [x] **Check output escaping.** Every place user-supplied text is printed needs
      `htmlspecialchars()`. The risky spots are product names/descriptions,
      business names, review text, support messages and admin notes — anywhere a
      vendor or buyer's own words get rendered.
      (verified 2026-08-31 — zero unescaped echoes of a user-text column
      (`name`, `description`, `body`, `comment`, `notes`, `reason`, `message`,
      `subject`, `answer`, `*_name`, `address`, …) in either `<?=` or `echo`
      form, and no variable assigned raw from one of those columns is echoed
      unescaped. `render_markdown()` calls `htmlspecialchars()` before parsing;
      `$storeName` in `business/index.php` is escaped at assignment.)
- [x] **Check every query is prepared.** Search for string interpolation into
      SQL: `grep -rn 'query("' --include="*.php" .` and
      `grep -rnE '\$(_POST|_GET)\[' --include="*.php" . | grep -i "select\|insert\|update\|delete"`.
      Anything that concatenates a variable into SQL instead of binding it is a
      bug.
      (verified 2026-08-31 — every `$pdo->query()` is a static string; the only
      interpolations are `PAYOUT_WINDOW_SECONDS` (an int constant), generated
      `?` placeholder lists (all 24 built with `array_fill(…, '?')`), and
      `{$table}` which is always a hardcoded `'buyers'`/`'vendors'` ternary.
      `admin/refunds.php` whitelists its filter _and_ `$pdo->quote()`s it;
      `admin/audit.php` builds only `?` placeholders with bound params.)

## 2d. Dead code

Cheap to do and it shrinks what you have to maintain forever.

- [x] **Find orphaned action files.** For each `*-action.php` or similar, grep
      the tree for its filename. If nothing references it, no form posts to it
      and it can go.
      (verified 2026-08-31 — none. The three deleted this session
      (`photo-delete-action.php`, `photo-upload-action.php`,
      `storefront-action.php`) have no remaining references either.)
- [x] **Confirm `photo-set-primary.php` is still used.** The star button that
      called it was removed. If nothing references it, delete it.
      (resolved 2026-08-31 — the file no longer exists anywhere in the tree and
      nothing references it. Already deleted.)
- [x] **Find unused CSS classes**, especially after the `products/` and
      vendor-portal refactors. Pull the class names out of a page's CSS
      and grep the matching PHP for each.
      (verified 2026-08-31 — ~40 dead classes found, listed under Findings.
      Note `--modifier` classes built by string concatenation
      (`thread-badge--<?= $th['status'] ?>`) and the `mapboxgl-*` library
      classes are live, not dead.)
- [x] **Find unused JS.** `js/` currently holds `boundary.js`, `geo-capture.js`,
      `notifications.js`, `photo-shrink.js`, `square-cropper.js`,
      `status-refresh.js`. Grep for each filename; anything nothing includes is
      dead.
      (verified 2026-08-31 — all seven are referenced. `cat-cascade.js` also
      exists and is referenced; it was missing from the list above.)

## 2e. Database integrity

Run each query against the live database. Each should return zero rows, or a
result you can explain.

- [x] **Orphaned product photos:**
      `SELECT COUNT(*) FROM product_photos p LEFT JOIN products pr ON pr.id = p.product_id WHERE pr.id IS NULL;`
      (verified 2026-09-04 — returns **0**.)
- [x] **Orphaned cart items:**
      `SELECT COUNT(*) FROM cart_items c LEFT JOIN products p ON p.id = c.product_id WHERE p.id IS NULL;`
      (verified 2026-09-04 — returns **0**.)
- [x] **Order items with a deleted product.** Expect some — products get
      deleted. What matters is that order pages still render the snapshot name
      instead of blowing up. Find one and open the order as buyer, vendor and
      admin.
      (verified 2026-09-04 — 2 such rows: `order_items` 35 ("Sunflower Dress",
      order 34 / `09ca8911…`) and 36 ("White Dress", order 35 / `3ec66fc1…`),
      both with a NULL `product_id`. All six views fetched with `display_errors`
      on — buyer `/orders-buyer/order.php`, vendor `/orders-vendor/order.php`,
      admin `/admin/order.php` — every one returned 200, rendered the
      `order_items.product_name` snapshot, and emitted zero PHP diagnostics. The
      snapshot columns (`product_name`, `product_name_km`, `variant_label`,
      `price_at_purchase`) are doing their job.)
- [x] **Confirm `archived = 0` is filtered everywhere buyers see products.**
      Grep every buyer-facing query — homepage, search, business page, category
      — and check the filter is present. An archived product leaking into search
      is the failure.
      (verified 2026-08-31 — filter present in `index.php`, `search/index.php`,
      `api/search/index.php`, `business/index.php`, `product/index.php`,
      `api/recently-viewed/index.php`. `wishlist/index.php` deliberately selects
      `archived`/`active`/`approved` and renders an unavailable state instead of
      filtering — correct for a wishlist. `cart/add.php` gates on `active`, and
      `archive.php` forces `active = 0`, so archived items cannot be carted.
      One gap: see the `products/toggle.php` finding.)
- [x] **Confirm at most one primary photo per product:**
      `SELECT product_id, COUNT(*) FROM product_photos WHERE is_primary = 1 GROUP BY product_id HAVING COUNT(*) > 1;`
      (verified 2026-09-04 — 0 rows.)

## 2f. Frontend

Mobile layout checks live in Part 3. These are the desktop ones.

- [x] **Confirm images load everywhere**: homepage, search, business page,
      product detail, vendor products list, vendor dashboard. Open dev tools and
      watch for 404s rather than trusting your eyes — a broken image can look
      like an intentional gap.
- [x] **Click every link in the header and footer** in both languages. Broken
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
- [ ] **Set `display_errors = Off`, and undo the Part 2b `.htaccess` block.**
      Two separate places: hPanel's `display_errors`, _and_ the temporary block
      appended to the server's root `.htaccess` on 2026-09-04:
      `     ssh teepsaa
  cd domains/teepsaa.com/public_html
  cp .htaccess.bak-errortest .htaccess && rm .htaccess.bak-errortest
  `
      Running `./deploy-sftp.sh` also clears it, since `.htaccess` is not in the
      rsync exclude list. Leave `log_errors` on — it is what catches `/api/` and
      `/cron/` problems, which never render in a browser.
- [ ] **Confirm `/uploads/` is writable by the web server user**, and that its
      `.htaccess` PHP-execution block is still in place after the deploy.
- [x] **Search for leftover `TODO` and `FIXME` comments** —
      `grep -rn "TODO\|FIXME" --include="*.php" . | grep -v z-checklists` — and
      either fix or delete each one.
      (verified 2026-08-31 — zero matches across php/js/css.)
- [ ] **Remove the pre-launch gate** — delete the Basic Auth block from
      `.htaccess` and remove `.htpasswd` from the server. The exposure fix this
      was waiting on (removing `z-checklists/` and `database/`) was completed
      2026-07-10.
      (Host-scoped Basic Auth on `admin.teepsaa.com` was listed here as optional —
      cut from launch scope 2026-09-03; it stays tracked in
      `teepsaa-open-questions.md`.)

---

# Findings from the display_errors sweep (2026-09-04)

Two live bugs, from 384 URLs / 88 pages swept as public, buyer, vendor and admin.

- [x] **`/sitemap.php` fatals on every single request.** Not an edge case — the
      page is 100% dead:
      `Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown
  column 'p.updated_at'`. Line 9 selects `p.updated_at` from `products` and
      line 18 selects `updated_at` from `businesses`; neither column exists —
      `database/migration.sql` only ever defines `created_at`. Consequence:
      Google Search Console reads the sitemap as unparseable, so product pages
      do not get indexed. Fix is `updated_at` → `created_at` in both queries
      (`created_at` is a fine `lastmod` value), or add the columns if you want
      true modification times.
      (fixed 2026-09-04 — both queries and both `lastmod` reads now use
      `created_at`. **A second bug in the same file was found while fixing it:**
      the sitemap listed `https://teepsaa.com/browse/`, which is a 404 — there is
      no `browse/` folder in the repo — so that entry was removed. Uploaded to
      the server and live-verified: `/sitemap.php` returns valid XML that parses
      cleanly, 31 `<url>` entries (19 products, 7 businesses, 5 static pages),
      26 `<lastmod>` tags, zero PHP diagnostics. The local file is fixed but
      **not yet committed** — it will go out with the next normal deploy.)
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
`display_errors` still on.

Deliberate behaviour confirmed as correct, not bugs: `/support-thread/` returns
404 on a missing/invalid `?t=` token (`http_response_code(404)`), `/admin/` 302s
to `/admin/orders.php`, and `/product/` + `/business/` 302 to `/search/` when the
`public_id` does not match — note those two key on a UUID `public_id`, never a
numeric id, so `?id=1` never reaches the page body.

---

# Findings from the static audit pass (2026-08-31)

Everything in Part 2 that can be checked by reading the code rather than
clicking the site has been run. Eleven checks passed and are ticked above.
What follows is what those checks turned up. None of it blocks launch on its
own, but the first two are visible to the public and cheap to fix.

## Public-facing

- [ ] **`sitemap.php` lists `/browse/`, which does not exist.** Line 34. There
      is no `browse/` directory anywhere in the tree and nothing else in the
      site links to it. Google will fetch it, get a 404, and log a sitemap
      error on the first crawl after the gate comes off. Delete the `<url>`
      block (one-line fix, not a new page).

- [ ] **Three footer social links are `href="#"` placeholders** —
      `footer/footer.php`, the Instagram/Facebook/Telegram row. Telegram
      especially, given Part 3e already flags Telegram sharing as important in
      Cambodia. Point them at real accounts or drop the row before launch.

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

## What could not be checked from here, and why

- **Part 2e database integrity** — the five queries need a running database.
  Local MySQL is not up, and the checklist calls for the live one anyway. Run
  them in hPanel → phpMyAdmin.
- **Part 2b `display_errors` click-through** — needs a browser against the
  running site. Note there is no `display_errors` directive anywhere in the
  repo, and the top of `.htaccess` records that `php_flag`/`php_value` do not
  work under PHP-FPM — so this has to be set in hPanel's PHP config, not in a
  file, and Part 5's "set it back to Off" means the same place.
- **Part 2f image 404s** — needs dev tools. Static check done instead: every
  `/images/`, `/flags/`, `/fonts/`, `/icons/` path referenced from PHP or CSS
  resolves to a file that exists, including the two built by a language
  ternary. What remains is uploaded product photos, which only the live site
  can answer.
- **Part 2f header/footer links** — every internal `href` in `header.php` and
  `footer.php` resolves to a real file or folder (30 of them). Both languages
  share one set of URLs — only the `$t[…]` labels differ — so this covers both.
  The three `href="#"` placeholders above are the only broken ones.
- **Part 5 `PAYOUT_WINDOW_SECONDS`** — the _local_ `config/db.php` already
  derives it from the host (60 on localhost, 86400 everywhere else), so it
  needs no edit. But `config/db.php` is gitignored and excluded from the
  deploy, so the server holds its own older copy — that is the one stuck at
  60, and it has to be edited by hand in hPanel. Copying the local
  host-derived block up would make it self-configuring and remove this item
  from every future launch checklist.
- **Part 5 `/uploads/` `.htaccess`** — the block is present and correct in the
  repo (`FilesMatch` denying `php|php\d|phtml|phar|shtml`). Whether it
  survived on the server is a post-deploy check.

Also confirmed while looking: `.htpasswd` and `config/db.php` are both
gitignored and untracked, and the tracked `config/smtp.php` carries an empty
`SMTP_PASS`, so no secret is in the repo.

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
| Order dispatched                 | `order_dispatched`  | `analytics/dispatch.php`                              |
| Abandoned cart reminder          | `abandoned_cart`    | `cron/abandoned-cart.php` (daily)                     |
| Review reminder after delivery   | `review_reminder`   | `cron/review-reminder.php` (daily)                    |
| Welcome after verification ⚠     | `welcome_buyer`     | `verify-email/verify.php`                             |
| Order cancelled ⚠                | `order_cancelled`   | `admin/order-action.php`, `admin/payments-action.php` |
| Return approved ⚠                | `refund_approved`   | `admin/refund-action.php`                             |
| Refund declined ⚠                | `refund_rejected`   | `admin/refund-action.php`                             |
| Refund sent via ABA ⚠            | `refund_sent`       | `admin/refund-action.php`                             |
| Password changed ⚠               | `password_changed`  | `settings-buyer/password-action.php`                  |
| Account deleted ⚠                | `account_deleted`   | `settings-buyer/delete-action.php`                    |

## Vendor

| Event                            | Template             | Sent from                                                               |
| -------------------------------- | -------------------- | ----------------------------------------------------------------------- |
| Registration → verification code | `verify_code`        | `register-vendor/register-vendor.php`                                   |
| Resend verification code         | `verify_code`        | `resend-verification/resend.php`                                        |
| Password reset link              | `reset_password`     | `forgot-password-vendor/request.php`                                    |
| Low stock after a sale           | `low_stock`          | `checkout/confirm.php`                                                  |
| Buyer confirmed delivery         | `delivery_confirmed` | `orders-buyer/confirm-delivery.php`                                     |
| Payout sent                      | `payout_sent`        | `admin/payouts-action.php`                                              |
| Welcome after verification ⚠     | `welcome_vendor`     | `verify-email/verify.php`                                               |
| Business submitted ⚠             | `business_submitted` | `submit/submit.php`                                                     |
| Business approved ⚠              | `business_approved`  | `admin/action.php`                                                      |
| Business rejected ⚠              | `business_rejected`  | `admin/action.php`                                                      |
| Business deleted ⚠               | `business_deleted`   | `settings-vendor/business-delete-action.php`, `admin/vendor-action.php` |
| New paid order ⚠                 | `vendor_new_order`   | `admin/payments-action.php`                                             |
| Refund requested ⚠               | `refund_requested`   | `orders-buyer/refund-request.php`                                       |
| Password changed ⚠               | `password_changed`   | `settings-vendor/password-action.php`                                   |
| Account deleted ⚠                | `account_deleted`    | `settings-vendor/delete-action.php`                                     |

## Admin

| Event               | Template                    | Sent from                                                                                                                                                |
| ------------------- | --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| New job application | inline HTML, not a template | `careers/apply.php` → `ADMIN_EMAIL`                                                                                                                      |
| Daily digest ⚠      | `cron/admin-digest.php`     | pending payments, refund requests, business approvals, unread support threads, payouts due, canvassing follow-ups — sends only when a queue is non-empty |

⚠ = built but awaiting the Part 4a deploy.

Before the digest existed, the job application was the admin's _only_ email —
everything else was dashboard-badge only and required logging in to notice.
