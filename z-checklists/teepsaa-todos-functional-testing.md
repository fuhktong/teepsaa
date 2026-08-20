# Functional Testing — Every Flow, Every Role

Test on the live Hostinger site (real emails, real uploads, real .htaccess),
not just local MAMP. Use email +aliases (dustint505+test1@gmail.com) for the
throwaway accounts. Do the flows in order — later sections depend on
accounts/orders created earlier.

## Buyer — account lifecycle

- [x] Register: blank/invalid fields rejected with messages
- [x] Register: duplicate email rejected
- [x] Register: success → verification email arrives with code
- [x] Verify email: wrong code rejected, correct code verifies
- [x] Resend verification works (and old code stops working)
- [x] Login: wrong password shows error (and does NOT say which field was wrong)
- [x] Login: repeated wrong passwords triggers rate limit / lockout
- [x] Login: vendor credentials on buyer portal are rejected
      (verified live — "Invalid email or password"; mirror test buyer→vendor portal not run yet)
- [x] Forgot password: email arrives, reset link works, old password dead,
      new password logs in; used/expired reset link rejected
- [x] Logout works from every page

## Buyer — shopping

- [x] Recently viewed row appears on homepage after browsing products
- [x] Wishlist: heart toggles on/off, wishlist page lists items, unavailable
      items are marked (verified live Jul 2026)
- [x] Add to cart: works for simple product
- [x] Add to cart: product with variants requires choosing a size first
- [x] Add to cart: out-of-stock product/variant is blocked, button disabled
      on product page
- [x] Cart: quantities update, line + grand totals recalculate, remove works
- [x] Cart: cannot exceed available stock
- [x] Checkout blocked until email verified (redirects to resend-verification)
- [x] Checkout blocked until delivery address + map pin set
- [x] Set address: khan/sangkat dropdowns, map pin, address book (add a
      second address, switch between them, delete one)
- [x] Coupon: valid code applies discount; invalid/expired/over-max-uses
      rejected with clear message; discount survives to order total
- [x] Place order: succeeds, cart empties, success message shows
      (live order run-through, Jul 2026)
- [x] Order confirmation email arrives — items, business names, totals,
      discount line, delivery note all correct (recently fixed — verify!)
- [x] Order from 2 different vendors in one checkout → splits into 2 orders
- [x] Stock decremented after order (check product page / vendor side)

## Buyer — after ordering

- [x] Dashboard lists orders, newest first, correct statuses
- [x] Order detail: items, prices, status timeline all correct
- [x] Status updates appear (paid → dispatched → delivered) as vendor/admin
      advances the order — check the live status-refresh polling too
      (statuses advanced correctly in the live order test; polling itself
      not specifically checked yet)
- [x] Confirm delivery button works when dispatched arrives
      (live order run-through — buyer confirmed, delivered_at set)
- [x] Review: can review a delivered item once (form rejects a second review);
      rating + text appear on the product page
- [x] Refund request: submit with reason; status changes to Refund Requested
      (buyer submitted during refund test; vendor refund-requested email fired,
      which only sends after the order flips to refund_requested)
- [x] Return dispatch: after admin approves return, buyer can submit
      tracking; status advances
      (verified in full refund run-through, Jul 2026)
- [x] Refund status page shows the correct step at each stage
      (verified all three roles; buyer/vendor/admin status timeline now renders
      correctly after adding order-status.css to the vendor + admin refund pages)
- [x] Messages (support desk — buyer ↔ admin, NOT buyer↔vendor): from
      /contact-buyer/ send a support message (optionally attach one of your
      orders); it appears in /messages-buyer/; admin's reply shows up there
      with an unread count; the "one pending thread at a time" guard blocks a
      second message while one is still pending
- [x] Notifications bell: shows order updates, mark-as-read works,
      mark-all-read works
      (refund-stage bells confirmed arriving during the refund run-through;
      still verify mark-as-read + mark-all-read actions specifically)
- [x] Settings: change name/profile, avatar upload, avatar color, password
      change (old sessions still valid?), language preference persists
      across logout/login
- [x] Delete account: works, login afterwards impossible, orders retained
      for vendor/admin
- [x] Buyer CANNOT open /dashboard-vendor/, /products/, /orders-vendor/
      (rejected), nor /admin/

## Vendor — account lifecycle

- [x] Register with business details (en + km names)
      (registered live 2026-07-08 with a real email)
- [x] Email verification flow (same checks as buyer)
      (code arrived by email in the 2026-07-08 live registration;
      wrong-code/resend paths untested)
- [ ] Before admin approval: business/products invisible to buyers
- [ ] Admin approves → vendor notified, business page goes live
- [ ] Admin rejects → vendor sees rejection state
- [ ] Login portal rejects buyer credentials
- [ ] Forgot/reset password flow (vendor version)

## Vendor — products

- [x] Add product: all fields, en + km, category cascade, price, stock,
      delivery method, up to 9 photos
- [x] Photo upload rejects non-images (try a renamed .txt → should fail on
      magic-byte check)
- [x] Edit product: change fields, save, verify on buyer side
- [x] Photo gallery: drag to reorder, order persists after reload, first
      photo becomes the primary shown to buyers
- [x] Photo delete works
- [x] Variants: add sizes with stock/price overrides; buyer side shows them;
      deleting a variant removes it
- [x] Sale price + end date: badge shows for buyers, price reverts after end
      date; cancel sale works
- [x] Product status toggle (active/inactive): inactive product disappears
      from buyer surfaces
- [x] Archive: product moves to archive tab, invisible to buyers;
      unarchive returns it (inactive until re-activated)
- [x] Delete: gone from lists; existing orders still display its name
      (snapshot), buyer's cart handles it gracefully
- [x] Low stock: set threshold, sell past it → vendor notification + email
- [x] Coupons: create vendor coupon, buyer applies it, discount comes out of
      vendor payout (check the numbers in admin accounting)
- [x] Products list: sorting works, orders tab shows full history

## Vendor — orders & money

- [x] New order appears on dashboard (pending/paid only)
      (live order run-through; vendor Orders nav badge added since)
- [x] Dispatch flow: mark dispatched (+ tracking URL), buyer sees it
      (live order run-through — Grab link entered, buyer saw it and
      confirmed delivery)
- [x] Order detail shows royalty/payout breakdown correctly
- [x] Return received: vendor marks returned item received
      (verified in full refund run-through, Jul 2026)
- [x] ABA QR upload in settings (payout method)
- [x] Messages (support desk — vendor ↔ admin, NOT vendor↔buyer): from
      /contact-vendor/ send a support message (optionally attach an order);
      it appears in /messages-vendor/; admin's reply shows up there; same
      "one pending thread at a time" guard applies
- [x] Notifications: new order, low stock, refund request all arrive
      (refund request bell confirmed in the refund run-through;
      still verify new-order + low-stock bells)
- [x] Settings: profile, avatar, banner, business info edit, business
      address + map pin, password change, delete account
- [x] Vendor CANNOT open /cart/, /checkout/, /dashboard-buyer/, /wishlist/
      (rejected), nor /admin/

## Admin

- [x] Login: only admin accounts work; buyer/vendor creds rejected;
      rate-limited
- [x] Vendor approvals: pending list, approve (vendor + business go live),
      reject
- [x] Vendor detail page + vendor map load
- [x] Buyers: list, search, detail, ban/unban (banned buyer can't log in),
      notes
- [x] Products: list, search, view, moderate (deactivate a product → gone
      from buyer side)
- [x] Orders: list, filters by status, search by buyer/vendor/order id,
      date range
- [x] Order detail: confirm payment (pending → paid), advance/cancel status,
      buyer + vendor notified at each step
      (confirm payment live-tested in the order run-through)
- [x] Payments page reflects order payments correctly
      (reworked to a click-through list → order page; used in live test)
- [x] Payouts: delivered order appears after PAYOUT_WINDOW (24h in prod —
      test with a delivered order older than the window), mark paid out
      (window gating verified live in both states via backdated
      delivered_at; server-side guard added; final "mark paid out"
      click still to verify)
- [x] Refunds: full cycle — request appears → approve return → buyer
      dispatches → vendor received → mark refunded; also test reject
      (full happy-path cycle verified live Jul 2026; confirm the reject path
      was also exercised)
- [ ] Penalties: add a vendor penalty, verify it raises the effective
      royalty on the vendor's next order
- [ ] Coupons + promo codes: create sitewide coupon, limits (max uses,
      expiry, min subtotal) all enforced at checkout
- [ ] Banners: create/edit/delete, en + km, ordering, live on homepage
- [ ] Categories: create/edit, hierarchy (parent/child), Khmer names,
      royalty rate set per category
- [ ] Content: edit a page (e.g. About) in both languages, verify live
- [ ] FAQ: add/edit/delete, verify on Help page
- [ ] Careers: post a job (en + km), see application + resume download
- [ ] Reviews: moderate/remove a review, gone from product page
- [ ] Messages (support desk): all support threads visible — guest (via
      /contact/, replies to a token link at /support-thread/), buyer, and
      vendor; admin reply reaches the sender (buyer in /messages-buyer/,
      vendor in /messages-vendor/, guest by email/token link); status
      transitions pending → open → closed work
- [ ] Email templates: edit one, send test, verify the change
- [ ] Accounting: totals match the test orders you placed
- [ ] Admins: create a second admin, role restrictions apply
      (non-super admin only sees allowed sections), deactivate admin
- [ ] Admin password change
- [ ] Admin session CANNOT access buyer or vendor portals

## Cron jobs (run each one manually on the server, check the effect)

- [ ] `cron/auto-confirm.php` — dispatched orders older than the window
      auto-complete to delivered
- [ ] `cron/review-reminder.php` — buyer gets review reminder email after
      delivery
- [ ] `cron/abandoned-cart.php` — buyer with items sitting in cart gets
      the reminder email (once, not repeatedly)
- [ ] `cron/purge-password-resets.php` — expired reset tokens removed
- [x] Then: schedule all four in hPanel → Cron Jobs (use PHP CLI, not HTTP —
      HTTP is blocked by the pre-launch Basic Auth gate)
      (done — all four registered with /usr/bin/php, screenshot-verified)

## Cross-cutting

- [ ] Every flash message (success/error) appears once and clears on
      reload/next page
- [ ] Browser back button after form submits doesn't double-submit orders
- [ ] Session expiry mid-session: next action redirects to login without
      errors, ajax pages (notifications, status refresh) handle it
- [ ] A URL with a bad/foreign public_id (product, order) shows a sane
      not-found, not an error dump
- [ ] All emails render correctly in Gmail on a phone (Khmer + English blocks)
