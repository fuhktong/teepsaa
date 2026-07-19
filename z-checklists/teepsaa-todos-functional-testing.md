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

- [ ] Dashboard lists orders, newest first, correct statuses
- [ ] Order detail: items, prices, status timeline all correct
- [ ] Status updates appear (paid → dispatched → delivered) as vendor/admin
      advances the order — check the live status-refresh polling too
      (statuses advanced correctly in the live order test; polling itself
      not specifically checked yet)
- [x] Confirm delivery button works when dispatched arrives
      (live order run-through — buyer confirmed, delivered_at set)
- [ ] Review: can review a delivered item once (form rejects a second review);
      rating + text appear on the product page
- [ ] Refund request: submit with reason; status changes to Refund Requested
- [ ] Return dispatch: after admin approves return, buyer can submit
      tracking; status advances
- [ ] Refund status page shows the correct step at each stage
- [ ] Messages: contact a vendor from an order/product, thread works,
      replies from vendor show up
- [ ] Notifications bell: shows order updates, mark-as-read works,
      mark-all-read works
- [ ] Settings: change name/profile, avatar upload, avatar color, password
      change (old sessions still valid?), language preference persists
      across logout/login
- [ ] Delete account: works, login afterwards impossible, orders retained
      for vendor/admin
- [ ] Buyer CANNOT open /dashboard-vendor/, /products/, /orders-vendor/
      (rejected), nor /admin/

## Vendor — account lifecycle

- [x] Register with business details (en + km names)
      (registered live 2026-07-08 with a real email)
- [ ] Email verification flow (same checks as buyer)
      (code arrived by email in the 2026-07-08 live registration;
      wrong-code/resend paths untested)
- [ ] Before admin approval: business/products invisible to buyers
- [ ] Admin approves → vendor notified, business page goes live
- [ ] Admin rejects → vendor sees rejection state
- [ ] Login portal rejects buyer credentials
- [ ] Forgot/reset password flow (vendor version)

## Vendor — products

- [ ] Add product: all fields, en + km, category cascade, price, stock,
      delivery method, up to 9 photos
- [ ] Photo upload rejects non-images (try a renamed .txt → should fail on
      magic-byte check)
- [ ] Edit product: change fields, save, verify on buyer side
- [ ] Photo gallery: drag to reorder, order persists after reload, first
      photo becomes the primary shown to buyers
- [ ] Photo delete works
- [ ] Variants: add sizes with stock/price overrides; buyer side shows them;
      deleting a variant removes it
- [ ] Sale price + end date: badge shows for buyers, price reverts after end
      date; cancel sale works
- [ ] Product status toggle (active/inactive): inactive product disappears
      from buyer surfaces
- [ ] Archive: product moves to archive tab, invisible to buyers;
      unarchive returns it (inactive until re-activated)
- [ ] Delete: gone from lists; existing orders still display its name
      (snapshot), buyer's cart handles it gracefully
- [ ] Low stock: set threshold, sell past it → vendor notification + email
- [ ] Coupons: create vendor coupon, buyer applies it, discount comes out of
      vendor payout (check the numbers in admin accounting)
- [ ] Products list: sorting works, orders tab shows full history

## Vendor — orders & money

- [x] New order appears on dashboard (pending/paid only)
      (live order run-through; vendor Orders nav badge added since)
- [x] Dispatch flow: mark dispatched (+ tracking URL), buyer sees it
      (live order run-through — Grab link entered, buyer saw it and
      confirmed delivery)
- [ ] Order detail shows royalty/payout breakdown correctly
- [ ] Return received: vendor marks returned item received
- [ ] ABA QR upload in settings (payout method)
- [ ] Messages: reply to buyer threads
- [ ] Notifications: new order, low stock, refund request all arrive
- [ ] Settings: profile, avatar, banner, business info edit, business
      address + map pin, password change, delete account
- [ ] Vendor CANNOT open /cart/, /checkout/, /dashboard-buyer/, /wishlist/
      (rejected), nor /admin/

## Admin

- [ ] Login: only admin accounts work; buyer/vendor creds rejected;
      rate-limited
- [ ] Vendor approvals: pending list, approve (vendor + business go live),
      reject
- [ ] Vendor detail page + vendor map load
- [ ] Buyers: list, search, detail, ban/unban (banned buyer can't log in),
      notes
- [ ] Products: list, search, view, moderate (deactivate a product → gone
      from buyer side)
- [ ] Orders: list, filters by status, search by buyer/vendor/order id,
      date range
- [x] Order detail: confirm payment (pending → paid), advance/cancel status,
      buyer + vendor notified at each step
      (confirm payment live-tested in the order run-through)
- [x] Payments page reflects order payments correctly
      (reworked to a click-through list → order page; used in live test)
- [ ] Payouts: delivered order appears after PAYOUT_WINDOW (24h in prod —
      test with a delivered order older than the window), mark paid out
      (window gating verified live in both states via backdated
      delivered_at; server-side guard added; final "mark paid out"
      click still to verify)
- [ ] Refunds: full cycle — request appears → approve return → buyer
      dispatches → vendor received → mark refunded; also test reject
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
- [ ] Messages: guest/buyer/vendor threads visible, reply works, buyer and
      vendor receive replies, status (open/closed) works
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
