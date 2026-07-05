# Teepsaa — Completed Build Archive

---

## Phase 1 — Foundation

- [x] Database migration — `database/migration.sql` with `users`, `businesses`, `photos` tables
- [x] DB config — `config/db.php` with PDO connection
- [x] Header component — `header/header.php` + `header/header.css`
- [x] Footer component — `footer/footer.php` + `footer/footer.css`
- [x] Homepage — `index.php` landing page
- [x] Global CSS — `style.css` base reset

---

## Phase 2 — Auth

- [x] Register page — `register/index.php` + `register/register.php` with CSRF, password_hash
- [x] Login page — `login/index.php` + `login/login.php`, session start, redirect on success
- [x] Logout — `logout/logout.php` — destroy session, redirect to home
- [x] Nav — Login/Register when logged out, Dashboard/Logout when logged in

---

## Phase 3 — Map

- [x] Businesses API — `api/businesses/index.php` — returns approved businesses as JSON
- [x] Map JS — `js/map.js` — Mapbox GL JS v2.15.0, locked to Phnom Penh bounds, markers with popups
- [x] City boundary mask — `js/boundary.js` — dark overlay outside Phnom Penh polygon
- [x] Browse page — `browse/index.php` — list/map toggle, header search bar

---

## Phase 4 — Business Submission

- [x] Photo upload — `api/upload/index.php` — jpg/png only, max 2MB, UUID filenames, saved to `/uploads/`
- [x] Submit page — `submit/index.php` — vendor-only, click map to set lat/lng, CSRF protected

---

## Phase 5 — Vendor Dashboard

- [x] Dashboard — `dashboard/index.php` — lists user's businesses with approval status
- [x] Products table — id, business_id, name, description, price, stock, photo, active
- [x] Product management — `products/index.php` — add, edit, deactivate (vendor-only)
- [x] Business profile — `business/index.php` — public page showing business info and active products

---

## Phase 6 — Admin

- [x] Admin panel — `admin/index.php` — pending business approvals, approve/reject actions
- [x] Admin login — `login-admin/index.php` — separate portal, only accepts `is_admin = 1`

---

## Phase 7 — Role Separation

- [x] `role ENUM('buyer','vendor')` on users table
- [x] `/login-buyer/` — hard wall, rejects vendors
- [x] `/login-vendor/` — hard wall, rejects buyers
- [x] `/login-admin/` — rejects anyone without `is_admin = 1`
- [x] `/register-buyer/` and `/register-vendor/` — separate registration flows
- [x] `/dashboard-buyer/` and `/dashboard-vendor/` — separate dashboards
- [x] Vendors can buy and sell — buyers can only buy

---

## Phase 8 — Cart & Orders

- [x] `cart_items`, `payments`, `orders`, `order_items` tables
- [x] `/cart/` — view cart grouped by vendor, update/remove items
- [x] `/cart/add.php` and `/cart/update.php` — cart handlers
- [x] `/checkout/` — order summary, ABA QR code, "I've paid" button
- [x] `/checkout/confirm.php` — transaction: payment + orders + order_items, stock decrement, cart clear

---

## Marketplace — Auth Tables

- [x] Separate `buyers`, `vendors`, and `admins` tables (split from single `users` table)
- [x] `buyers` — buyers only, login at `/login-buyer/`, role='buyer'
- [x] `vendors` — vendors only, login at `/login-vendor/`, role='vendor'; includes `aba_qr` column
- [x] `admins` — admins only, login at `/login-admin/`, role='admin' + is_admin=1
- [x] Name field on buyers and vendors (`name VARCHAR(255) NOT NULL DEFAULT ''`)
- [x] 1 vendor account = 1 shop enforced at submit

---

## Marketplace — Payment Confirmation

- [x] Admin orders tab: `pending_confirmation` payments shown in order popup at `/admin/orders.php`
- [x] Admin clicks Confirm payment — payment status → `confirmed`, all linked orders → `paid`
- [x] Reject option — payment → `rejected`, orders → `cancelled`
- [x] Vendor dashboard: incoming orders appear once status is `paid`
- [x] Vendor clicks Mark dispatched → order status → `dispatched`

---

## Marketplace — Delivery

- [x] Vendor sees paid orders in dashboard, marks as `dispatched`
- [x] Buyer sees all orders with status bar in `/dashboard-buyer/`
- [x] Buyer clicks Confirm delivery → order status → `delivered`
- [x] Auto-confirm after 24 hours — `cron/auto-confirm.php` marks stale `dispatched` orders as `delivered`, emails admin payout list

---

## Marketplace — Vendor Payout

- [x] `aba_qr` column on `vendors` table — vendors upload their ABA QR from vendor dashboard
- [x] Admin Orders popup — `delivered` orders show vendor ABA QR and Mark completed button
- [x] Admin clicks Mark completed → order status → `completed`
- [x] Vendor dashboard shows ABA QR upload section with current QR preview

---

## Marketplace — Admin Dashboard

- [x] Admin dashboard: 2 tabs — Vendors and Orders
- [x] Vendors tab — all vendors (approved/pending/rejected), click → popup with business info, approve/reject actions
- [x] Orders tab — all orders, filterable by status with counts, click → popup with full order detail
- [x] Payment confirm/reject and vendor payout both handled inside order popup
- [x] Order popups: itemized table, status bar, vendor ABA QR for payouts

---

## Marketplace — UX

- [x] Order detail popup — clicking any order (admin/vendor/buyer) shows full breakdown
- [x] Vendor pending visibility — vendors see orders in `pending` state before payment confirmed
- [x] Buyer payment status — "Payment submitted" label; amber note on pending orders
- [x] Shared 5-step order status bar (`/order-status/order-status.php`) across all stakeholders
- [x] Order ID format: `YYMMDD-0000` (e.g. `260514-0003`)
- [x] Popup modal pattern — `js/popup.js` + `/popup/popup.css` used across admin, vendor, buyer

---

## Marketplace — Account Settings

- [x] Schema — `phone`, `address`, `address_notes`, `lat`, `lng`, `lang`, `avatar` added to `buyers`; `phone`, `lang`, `avatar` added to `vendors`; `name` added to `admins`
- [x] Buyer settings — `/dashboard-buyer/settings/` with Account (name, phone, avatar upload), Address (street, notes, Mapbox drop pin), Password, Danger zone (hard delete with password confirm)
- [x] Vendor settings — `/dashboard-vendor/settings/` with Account (name, phone, avatar upload), Business (map pin reposition, ABA QR upload), Password, Danger zone (blocked if open orders)
- [x] Admin settings — password change form as a Settings tab in the admin panel (`/admin/settings.php`) alongside Vendors and Orders
- [x] Avatar dropdown — buyer and vendor headers replace Settings + Logout links with a circle avatar button (photo or name initial) that opens a Settings/Logout dropdown
- [x] Session sync — `user_name` and `user_avatar` stored in session at login, updated on save; avatar initial updates immediately without re-login
- [x] Vendor orders moved to Products tab — `/products/?tab=orders` replaces standalone vendor dashboard orders; header trimmed to Products + avatar dropdown

---

## Marketplace — Order Status Refresh

- [x] API endpoint — `api/order-status.php` — role-aware GET endpoint, returns `{"status":"…"}` or 401/404
- [x] JS module — `js/status-refresh.js` — `initStatusRefresh()`, re-renders status bar in-place, updates matching popup if open
- [x] Single refresh button per section — SVG icon next to "My Orders", "Incoming Orders", "Orders" headings; spins on click
- [x] Toast — green viewport-spanning bar on status change, red on error or session expiry
- [x] Action button sync — dispatch/confirm-delivery/payout buttons show/hide based on new status without page reload
- [x] Admin filter note — toast appends "Reload page to remove from this filter" when viewing a filtered status list

---

## Admin Filters & Search

- [x] Vendors tab — search by name or email; filter by All / Pending / Approved / Rejected / No business
- [x] Orders tab — search by order ID, buyer name, or business name; filter by date range; filter by status (`?status=` query param)

---

## Security

- [x] Brute force protection — `login_attempts` table + `config/rate-limit.php`; 5 failures per IP in 15 min triggers 15 min lockout on all three login portals
- [x] XSS in map popups — `escHtml()` in `map.js` escapes all vendor-supplied strings before DOM injection
- [x] File upload hardening — `config/upload.php` validates magic bytes (JPEG/PNG) in all 8 upload handlers; `uploads/.htaccess` blocks PHP execution in `/uploads/` (also closes off disguised-PHP-shell-via-upload and raw DB access via a web shell)
- [x] `/dev/` folder deleted
- [x] HTTPS enforcement — root `.htaccess` redirects `teepsaa.com`/`www.teepsaa.com` to HTTPS via `mod_rewrite`; `session.cookie_secure` set dynamically per-request (true only when the request is actually HTTPS), so local MAMP HTTP dev is unaffected
- [x] IDOR audit on orders — every buyer/vendor-facing file accepting a user-suppliable ID (`dashboard-buyer/`, `dashboard-vendor/`, `orders-vendor/`, `messages-*`, `review/`, `cart/`, `api/`, `products/`, `contact-*`, `wishlist/`, `checkout/`) scopes its query to the authenticated user (`buyer_user_id=?`, `b.user_id=?` vendor→business join, or `sender_id=?`/`role=?`)
- [x] Stock race condition — `checkout/confirm.php` checks `rowCount()` after stock decrement; rolls back if stock ran out mid-checkout
- [x] Session cookie hardening — all `session_start()` call sites (165+) pass `cookie_httponly`/`cookie_samesite=Strict`/`cookie_secure` directly, so it works under PHP-FPM/MAMP with no `php.ini`/`.htaccess` dependency
- [x] `config/db.php` exposure — `config/.htaccess` has `Deny from all`, blocking any request into `/config/` regardless of PHP misconfiguration
- [x] Sequential IDs → UUIDs — random `public_id` (UUID v4) column added to `businesses`, `products`, `orders` (`database/migration-public-ids.sql`), generated via `uuid_v4()` in `config/db.php`. All buyer/vendor-facing URLs, outgoing links, canonical/SEO URLs, sitemap entries, and notification/email links use `public_id` instead of the sequential int `id`; the int `id` stays as the internal PK for joins/FKs and ownership-scoped POST actions. Admin pages intentionally keep the int `id` (admin is fully trusted)
- [x] SQL injection — PDO prepared statements on all queries
- [x] CSRF — `csrf_verify()` on all POST forms
- [x] Password storage — bcrypt via `password_hash()`
- [x] Session fixation — `session_regenerate_id(true)` on every login
- [x] Role enforcement — hard walls between buyer, vendor, and admin logins
- [x] Ownership checks — vendors can only edit their own products and businesses

### Found & Fixed (2026-07-04 review)
- [x] Verification OTP leaked to browser in production — `$_SESSION['dev_otp']` set unconditionally in `register-buyer.php`/`register-vendor.php`/`resend-verification/resend.php`, echoed via `console.log()` in `verify-email/index.php` regardless of environment; gated behind `DEV_MODE` at both the set and display sites
- [x] Cart/checkout missing buyer-role check — `cart/add.php`, `cart/index.php`, `cart/update.php`, `checkout/index.php`, `checkout/confirm.php` only checked `isset($_SESSION['user_id'])`, not `role === 'buyer'`; a logged-in vendor could hit another user's cart/address/order data on an id collision between the separate `buyers`/`vendors` tables. Added the `role !== 'buyer'` guard used everywhere else
- [x] Dead legacy auth cluster (`/login/`, `/register/`, `/dashboard/`) — pre-role-split pages querying a `users` table that no longer exists; `footer/footer.php`'s logged-out links pointed here instead of `/login-buyer/`/`/register-buyer/`. Footer links fixed, dead folders removed
- [x] No throttling on email-verification code guesses — `verify-email/verify.php` wired into the existing `config/rate-limit.php` (5-per-15-min IP limiter)
- [x] No throttling on password-reset / resend-verification / job applications — added `check_rate_limit()`/`record_failed_attempt()` to `forgot-password-buyer/request.php`, `forgot-password-vendor/request.php`, `resend-verification/resend.php`, `careers/apply.php`
- [x] Open redirect via `redirect` POST param / `HTTP_REFERER` — `cart/add.php` echoed `$_POST['redirect']` straight into `header('Location: ...')`; `lang/set.php`/`currency/set.php` did the same with the raw referer. `cart/add.php` now requires a same-site relative path (rejects `//host` too); the other two verify the referer's host matches `HTTP_HOST`

---

## Privacy Policy & Terms of Service

- [x] `/privacy/index.php` — covers data collected, purpose, storage, third parties (Mapbox, Grab, ABA), cookies, retention, user rights, changes, contact
- [x] `/privacy/privacy.css`
- [x] `/terms/index.php` — covers acceptance, eligibility, buyer/vendor obligations, prohibited content, payments, delivery, royalty fees, refunds/disputes, termination, liability, governing law (Cambodia)
- [x] `/terms/terms.css`
- [x] Footer Help column — Privacy Policy and Terms of Service links added
- [x] Register buyer + vendor — "By registering you agree to our Terms of Service and Privacy Policy" added below submit button

---

## Guest Contact Form

- [x] `/contact/index.php` — name, email, subject, message; redirects logged-in buyers → `/contact-buyer/`, vendors → `/contact-vendor/`; honeypot hidden field
- [x] `/contact/contact.css`
- [x] `/contact/submit.php` — honeypot check, 60s session rate limit, stores thread + message to DB as sender_role='guest'
- [x] `/contact/thanks/index.php` — confirmation page
- [x] Footer: `/help/` is the entry point; contact accessible via Help Center CTA

---

## Support Messaging System

- [x] `support_threads` + `support_messages` tables — guest_name, guest_email columns added for contact form submissions
- [x] `/contact-buyer/` — structured intake form (issue type, order, subject, message) with pending barrier
- [x] `/contact-vendor/` — same for vendors
- [x] `/contact/` — guest contact form (name, email, subject, message); stores to DB as sender_role='guest'
- [x] `/contact/submit.php` — inserts thread + message, no DB auth required
- [x] `/contact/thanks/` — confirmation page
- [x] `/help/` — FAQ page with accordion sections; role-aware "Still need help?" CTA
- [x] `/messages-buyer/` — thread list with unread dot, status badge; Contact Support button (context-aware: pending → view thread, else → contact form)
- [x] `/messages-vendor/` — same
- [x] `/messages-buyer/thread.php` — pending: ticket view (labeled blocks, no input); open: chat bubble view with reply input + 10s polling; auto-reloads on first admin reply
- [x] `/messages-vendor/thread.php` — same
- [x] `/admin/messages/` — Buyers / Vendors / Contact Form tabs, Pending/Open/Closed filters, unread dot per thread
- [x] `/admin/messages/thread.php` — chat bubble view; pending notice + status badge update instantly on reply without reload; role tabs replace admin section tabs
- [x] `/api/messages/reply.php` — handles buyer/vendor/admin/guest senders; auto-opens pending threads on admin reply; emails guest at their address when admin replies
- [x] `/api/messages/poll.php` — used by all thread views for live updates
- [x] Messages link in header dropdown for buyer and vendor with unread count badge
- [x] Messages link in admin header with unread count badge
- [x] Support buttons removed from dashboard-buyer and dashboard-vendor (redundant with header link)
- [x] Footer: Help Center link added; Contact Support removed (friction layer via /help/ is intentional)

---

## Email Verification

- [x] Schema — `email_verified_at DATETIME NULL` and `verify_token VARCHAR(64) NULL` added to `buyers` and `vendors` → `database/add-email-verification.sql`
- [x] Buyer registration — generates token, stores in `verify_token`, sends verification email, redirects to `/resend-verification/`
- [x] Vendor registration — same flow
- [x] `/verify-email/` — accepts `?token=X&role=buyer|vendor`, sets `email_verified_at = NOW()`, clears token, redirects to login with success flash
- [x] Enforcement — buyer checkout (`checkout/confirm.php`) and vendor submit (`submit/submit.php`) block unverified accounts and redirect to `/resend-verification/`
- [x] `/resend-verification/` — logged-in buyer or vendor can request a new email; POST handler at `/resend-verification/resend.php`
- [x] Email — subject "Verify your Teepsaa email address"; token has no expiry, replaced on resend; uses `config/mail.php`
- [x] Edge cases — invalid token, already verified, unverified login allowed but checkout/submit blocked

---

## Forgot Password / Reset Password

- [x] Schema — `password_resets` table: `id`, `role ENUM('buyer','vendor')`, `user_id`, `token VARCHAR(64) UNIQUE`, `created_at`, `used_at` → `database/add-password-resets.sql`
- [x] Buyer flow — `/forgot-password-buyer/` email form + `request.php` handler; `/reset-password-buyer/` new password form + `reset.php` handler
- [x] Vendor flow — same as buyer, queries `vendors` table
- [x] Token — `bin2hex(random_bytes(32))`, expires after 1 hour, marked `used_at = NOW()` on use
- [x] Email — subject "Reset your Teepsaa password"; link valid 1 hour; always shows success on request (prevents enumeration); uses `config/mail.php`
- [x] Links — "Forgot password?" on `/login-buyer/`, `/login-vendor/`, and footer "Your Account" column
- [x] Edge cases — used token, expired token, not-found token (generic message), password < 8 chars rejected
- [x] Cleanup — `cron/purge-password-resets.php` purges old used/expired tokens

---

## Royalty Fee System

Rate = base category rate + sum of active vendor penalties, snapshotted on each order at checkout. Buyers never see it; vendors see it in payout breakdowns and the product price tool.

- [x] `categories` table — `id`, `parent_id`, `name`, `royalty_rate DECIMAL(5,4)` (default 0.0500)
- [x] `vendor_penalties` table — `id`, `business_id`, `rate_increase`, `admin_note`, `start_date`, `end_date`, `cleared_at`, `notified_at`
- [x] `vendor_notifications` table — for penalty expiry notices to vendors
- [x] `orders` — `royalty_rate`, `royalty_amount`, `vendor_payout` columns
- [x] Admin Categories tab — hierarchical tree view, add/edit/reparent, rates on leaf nodes only; parent categories show `—`
- [x] Vendor product form — leaf-only category dropdown with server-side enforcement; live payout preview as vendor types price
- [x] Checkout — effective rate computed as category rate + active penalty sum; all three columns snapshotted per order
- [x] Admin payout view — full breakdown: subtotal / royalty deduction / vendor payout
- [x] Admin penalty management — apply/remove penalties on vendor popup; multiple penalties stack additively, auto-expire by end date
- [x] Vendor penalty notice — banner on products page if penalty active; dismissible expiry notification on auto-expiry

---

## Product Reviews & Ratings

One review per order item (enforced by UNIQUE on `order_item_id`). Only available on `delivered` or `completed` orders.

- [x] `reviews` table — `id`, `order_item_id UNIQUE`, `buyer_id`, `product_id` (nullable), `business_id`, `rating TINYINT(1–5)`, `comment TEXT`, `created_at`; FKs: order_items ON DELETE CASCADE, buyers ON DELETE CASCADE, products ON DELETE SET NULL, businesses ON DELETE CASCADE
- [x] `database/migration-reviews.sql`
- [x] `/review/index.php` — review form: interactive star rating (1–5), optional comment (max 1000 chars), char counter
- [x] `/review/review.css` — CSS-only star highlight via `~` sibling selector on reversed radio inputs
- [x] `/review/submit.php` — CSRF, ownership check, status check, duplicate guard, inserts `product_id` + `business_id` snapshotted at insert
- [x] `/dashboard-buyer/order.php` — Reviews section per item: "Leave a review" button or "Reviewed ✓" label for delivered/completed orders; tracking link hidden after delivery
- [x] `/dashboard-buyer/index.php` — "★ Leave a review for this order" prompt on order cards with pending reviews
- [x] `/product/index.php` — rating summary (avg + count) + individual review list (newest first, buyer first name + last initial)
- [x] `/search/index.php` — avg rating + count on product cards
- [x] `index.php` (homepage) — avg rating + count on all product card sections (featured, best sellers, new arrivals, you might like)
- [x] `/business/index.php` — overall vendor rating in store header; per-product avg rating on product cards
- [x] `/products/index.php` (vendor) — Rating column in product table
- [x] `/admin/product.php` — Reviews card: all reviews for the product with delete button
- [x] `/admin/reviews.php` — standalone Reviews tab: all reviews across all products, searchable by vendor/business, delete per row
- [x] `/admin/review-action.php` — delete handler; redirects to product page or reviews tab based on `redirect_to` param

---

## Notifications

- [x] `database/migration-notifications.sql` — `notifications` table: role, user_id, type, message, link, read_at
- [x] `config/notify.php` — `notify()` in-app helper + `notification_email_html()` email template builder
- [x] `api/notifications/index.php` — GET, returns unread count + last 15 items for polling
- [x] `api/notifications/mark-read.php` — POST, marks one or all notifications read
- [x] `js/notifications.js` — polls every 15s, updates badge, renders dropdown on open, mark-read on click
- [x] Bell icon in header — buyer and vendor only; red badge with unread count; server-rendered initial count; dropdown with "Mark all read"
- [x] Email + in-app notification wired at all 4 order trigger points: payment confirmed (→ buyer), order dispatched (→ buyer), delivery confirmed (→ vendor), payout sent (→ vendor)

---

## Refunds & Returns

- [x] `database/migration-refunds.sql` — `refunds` table with status enum, reason, admin note
- [x] `/dashboard-buyer/refund-request.php` — buyer submits refund request on delivered/completed orders
- [x] `/dashboard-buyer/return-dispatch.php` — buyer marks return item dispatched with tracking URL
- [x] `/orders-vendor/refund.php` — vendor view of refund requests
- [x] `/products/return-received.php` — vendor marks returned item received
- [x] `/returns/index.php` — shared returns status page
- [x] `/admin/refunds.php`, `/admin/refund.php`, `/admin/refund-action.php` — admin refund management (approve/reject/complete)
- [x] `/refund-status/refund-status.php` — shared refund status bar component

---

## Delivery & Shipping

- [x] `config/delivery.php` — delivery config constants (base fee, per-km rate, etc.)
- [x] `config/delivery-calc.php` — distance/fee calculation helper
- [x] `/shipping/index.php` — shipping info page
- [x] `database/migration-add-delivery.sql` — delivery fee, distance columns on orders

---

## Admin Buyers Tab

- [x] `/admin/buyers.php` — all buyers list with search/filter
- [x] `/admin/buyer.php` — buyer detail popup (profile, orders, ban action)
- [x] `/admin/buyer-action.php` — ban/unban handler
- [x] `/admin/buyer-map.php` — buyer address map view

---

## Currency Switcher

- [x] `/currency/set.php` — POST handler sets `$_SESSION['currency']`; language/currency toggle in header switches between USD and KHR with live page reload

---

## Ecommerce Features

- [x] Order confirmation email — sent to buyer immediately on checkout submit; itemised receipt with total and delivery note; uses `contact@teepsaa.com`
- [x] Buyer order notes — `buyer_notes VARCHAR(500)` on `orders` table; textarea at checkout; shown to vendor (highlighted) and admin in order detail views
- [x] Wishlist — `wishlists` table; heart button on product detail page; `/wishlist/` page with remove; toggle API at `api/wishlist/toggle.php`; Wishlist link in buyer dropdown
- [x] SEO meta tags — `config/seo.php` helper; description, og:title, og:description, og:image, og:url, canonical wired to homepage, product, business, and search pages; product/business descriptions used as meta descriptions automatically
- [x] `sitemap.php` — dynamic XML sitemap listing all live products and approved businesses with `lastmod` dates
- [x] `robots.txt` — blocks admin/api/checkout/dashboards from indexing; points Google to `sitemap.php`

---

## Vendor Analytics & Low Stock Alert

- [x] Vendor sales analytics — `dashboard-vendor/index.php` analytics section (only shown when approved); 4 stat cards: all-time revenue, current-month revenue, total orders, current-month orders; best sellers table (top 5 by units sold from delivered/completed orders)
- [x] Low stock alert — `database/migration-low-stock.sql` adds `low_stock_threshold TINYINT DEFAULT 3` and `low_stock_notified_at DATETIME NULL` to `products`; `checkout/confirm.php` fires after commit: if any purchased product's new stock is ≤ threshold and no alert was sent in the last 24h, sends in-app notification + email to vendor via `notify()` + `send_email()`; `products/save.php` clears `low_stock_notified_at` when vendor restocks above threshold so they'll be alerted again next time it drops low
- [x] Low stock badge — "Low" / "Out" badge on stock column in vendor dashboard products table (`stock-low-badge` CSS class)

---

## Admin Accounting

- [x] `admin/accounting.php` — platform accounting page; date range filter; 6 summary stat cards: confirmed GMV, royalty earned, platform revenue (collected on completed orders), royalty pending (delivered not yet paid out), payouts made, payouts outstanding; top 10 vendors by royalty contribution; monthly breakdown table (last 24 months) with orders, GMV, royalty, payouts made, outstanding; "Accounting" tab added to all admin pages

---

## Search & Filtering

- [x] Infinite scroll on search — 20 products at a time via `/api/search/`, IntersectionObserver triggers next page
- [x] Sort — 5 options: Newest, Price low→high, Price high→low, Top rated, Most popular; auto-submits on change
- [x] Price range filter — Min/Max USD inputs with Apply button on search sidebar
- [x] Category filter — leaf categories with active products; auto-submits on change
- [x] Rating filter — ★4+, ★3+, ★2+; auto-submits on change
- [x] Vertical filter sidebar — sticky left sidebar on desktop; collapses behind Filters toggle on mobile
- [x] Responsive product grid — 4 columns → 3 → 2 → 1 as viewport shrinks
- [x] Active filter chips — pill tags above results; each chip removes just that filter on click; sort chip only appears when not default

---

## Flash Sales

- [x] `database/migration-flash-sale.sql` — `sale_price DECIMAL(10,2) NULL`, `sale_ends_at DATETIME NULL` added to `products`
- [x] `config/currency.php` — `active_sale(array $p): bool` and `price_html(array $p): string` helpers
- [x] `style.css` — `.price-sale` (red), `.price-original` (strikethrough grey), `.flash-badge` CSS classes
- [x] Vendor form — `products/index.php` split date + half-hour time select fields for sale end; `products/save.php` parses and saves both columns
- [x] Cancel sale — "Cancel sale" button in vendor preview card when sale is active; `products/cancel-sale.php` clears both columns
- [x] Buyer-facing price display — `price_html()` used on homepage, search, business page, product page, wishlist
- [x] Infinite scroll cards — JS `cardHtml()` in `search/index.php` and `index.php` renders sale price from API response
- [x] API responses — `api/search/index.php` and `api/recently-viewed/index.php` expose `sale_price` + `sale_ends_at`
- [x] Product page variant JS — price display updates correctly when variant selected; falls back to sale price when no variant override
- [x] Checkout pricing — `cart/index.php`, `checkout/index.php`, `checkout/confirm.php` use `COALESCE(variant_override, IF(sale active, sale_price, NULL), base_price)` as effective price

---

## Abandoned Cart Email

- [x] `database/migration-abandoned-cart.sql` — `abandoned_cart_notified_at DATETIME NULL` added to `buyers`
- [x] `cron/abandoned-cart.php` — queries buyers with 24h+ old cart items, skips if order placed since, sends email + in-app notification, marks notified
- [x] `checkout/confirm.php` — resets `abandoned_cart_notified_at = NULL` on successful checkout so buyers can be re-reminded on future abandonment

## Review Reminder Email

- [x] `database/migration-review-reminder.sql` — `review_reminder_sent_at DATETIME NULL` added to `orders`
- [x] `cron/review-reminder.php` — queries delivered orders 24h+ ago with unreviewed items, sends email + in-app notification, marks sent

## Buyer Address Book

- [x] `database/migration-address-book.sql` — `buyer_addresses` table with label, house_number, address, address_notes, khan, sangkat, lat, lng, is_default
- [x] `dashboard-buyer/settings/index.php` — "Saved addresses" tab: list with set-default/delete buttons; "Add new address" details panel with full Mapbox map; `updateNewSangkats()` JS for the add form
- [x] `dashboard-buyer/settings/address-book-action.php` — POST handler for add / set_default / delete actions; set_default syncs to `buyers` table
- [x] `dashboard-buyer/settings/settings.css` — styles for saved address list, items, labels, badges, action buttons
- [x] `checkout/index.php` — address switcher bar shows current delivery address + saved addresses; posting to `set-address.php` switches address mid-checkout
- [x] `checkout/set-address.php` — verifies ownership, syncs selected address to `buyers` table, marks as default
- [x] `checkout/checkout.css` — styles for checkout address switcher component

## Product Variants

- [x] Product variants — multi-dimensional option types (Size, Color, etc.); each combination is a variant with its own stock; buyer sees one selector per option type on the product page
- [x] `database/migration-product-variants.sql` — variant schema
- [x] `products/index.php` + `products/save.php` — vendor variant management
- [x] `product/index.php` — buyer-facing option selectors
- [x] `cart/add.php`, `cart/update.php`, `cart/index.php` — variant-aware cart
- [x] `checkout/index.php`, `checkout/confirm.php` — variant stock decrement at checkout
- [x] `dashboard-buyer/index.php`, `dashboard-buyer/order.php`, `orders-vendor/` — variant display in order views

---

## Khmer / English Localization (bilingual site) — completed 2026-07-04

Full EN/KM language toggle across the whole app. A header flag toggle sets `$_SESSION['lang']` (default `km`); every page loads `$t` from `lang/en.php` / `lang/km.php` (~581 keys, EN/KM parity maintained). Admin dashboard stays English by design.

### Infrastructure
- [x] `lang/en.php` + `lang/km.php` — keyed string dictionaries (real Khmer, not machine placeholders); `header/header.php` loads `$t` per session lang; `footer/footer.php` has its own `$t` guard-load
- [x] Toggle `lang/set.php`; **persistence** — writes choice to the `buyers`/`vendors` `lang` column, restored into the session at login (`login-buyer.php`/`login-vendor.php`); column default aligned to `km` (`migration-lang-default-km.sql`)
- [x] Khmer web font — **Noto Sans Khmer** in `style.css` `@import` + body font stack (per-glyph fallback)
- [x] Brand renders uniformly as **`ទីផ្សារ`** in Khmer; per-language footer tagline (Pacifico EN / Metal KM)
- [x] Shared display helpers in `config/db.php`: `lang_field($row,$field)` (for `field`/`field_km` rows) and `pick_lang($base,$km)` (for aliased columns) — Khmer optional, English fallback
- [x] Date localization — `config/i18n.php` `fmt_date($fmt,$when)` + `km_num()` (Khmer month/weekday names, am/pm, Khmer numerals); swept 12 non-admin display files (`date(` → `fmt_date(`), data/input `date('Y-m-d…')` left untouched
- [x] Global JS strings — `header/header.php` emits `window.T` (per-language `js_*` keys); `js/status-refresh.js` (status-bar labels, toasts) + `js/notifications.js` ("No notifications yet"/"Loading…") read it with English fallbacks

### UI wired to `$t` (every user-facing page)
- [x] Header, footer, homepage, search (+ sort labels/chips), product page (+ inline JS), cart, checkout
- [x] Login/register — all portals (`login/`, `login-buyer/`, `login-vendor/`, `register/`, `register-buyer/`, `register-vendor/`); auth recovery (`forgot-password-*`, `reset-password-*`, `verify-email/`, `resend-verification/`)
- [x] Buyer dashboard — wishlist, orders, settings (all tabs + address JS), messages, order detail + full refund/return flow; shared `order-status`/`refund-status` bars
- [x] Vendor dashboard — dashboard, orders-vendor (list/detail/refund), settings (all tabs), submit, messages, and the 1156-line `products/index.php` product manager (+ inline JS)
- [x] Contact forms (`contact/`, `contact-buyer/`, `contact-vendor/` — issue-type value→label maps), `business/` storefront, `review/` form (+ JS star labels)
- [x] Static content pages — `about/`, and `privacy/`, `terms/`, `shipping/`, `returns/`, `help/` (FAQ) as per-page bilingual `$lang` blocks (one block per page for native Khmer review)

### Bilingual content (vendor/admin-entered, KM optional + EN fallback)
- [x] Bilingual **banners** — `title_km`/`subtitle_km`; admin edit form with EN+KM fields
- [x] **Category names** — `categories.name_km`; admin editor; displayed on homepage tiles, search filter, vendor cascades
- [x] **Product name + description** — `products.name_km`/`description_km`; vendor form + save; displayed on product page, cards (homepage/search/`api/search`), storefront, wishlist, cart, checkout, product manager
- [x] **Business name + description** — `businesses.name_km`/`description_km`; vendor settings; storefront + all buyer-facing seller-name displays (incl. `api/recently-viewed` — added missing `session_start()`)
- [x] **Variant / option labels** — `product_option_types.name_km`, `product_option_values.label_km`, `product_variants.label_km`; KM box beside each EN box in both variant builders; composed variant `label_km` auto-built from values
- [x] **Job postings / careers** — `job_postings.title_km`/`location_km`/`description_km`; `employment_type` via `$t` map; admin form + public `careers/`/`apply.php`
- [x] **Order-item snapshots** — `order_items.product_name_km`/`variant_label_km` captured at checkout (`checkout/confirm.php`); order-detail/history/review pages show the language-correct snapshot (`dashboard-buyer/order.php`, `orders-vendor/order.php` + `refund.php`, `review/index.php`), old rows fall back to English

### Bilingual emails + notifications
- [x] **All user-facing emails are bilingual** — Khmer on top, English below. `notification_email_html_bi()` + `email_subject_bi()` in `config/notify.php`; covers order received, payment/dispatch/payout/delivery, low stock, abandoned cart, review reminder, verification code, password reset (job-application email → admin stays English)
- [x] **In-app notifications** render in the current toggle language — `notifications.data` JSON column stores params; `notification_text($row,$t)` translates by `type` (`notif_*` keys); `api/notifications/` translates message + time-ago; old rows fall back to stored English
- [x] **Staff-editable email templates** — `email_templates` table + `config/email-templates.php` defaults/fallback + `database/seed-email-templates.php` (10 templates); `render_email_template($pdo,$key,$data)` substitutes `{tokens}`; admin UI under `admin/messages/` (`emails.php` list → `email-edit.php` bilingual editor with live preview → `email-save.php` with required-token protection), tab in the messages role-tab bar

### Intentionally left English
- `<title>` tags (browser-tab text, brand convention); `/admin/*` dashboard; user-authored content (reviews, support messages, refund-reason free text, buyer addresses)

---

## Vendor Promo Trial

Early vendors get a 0% royalty trial via a promo code from vendor pitches; trial ends once BOTH 3 months have passed AND the vendor exceeds $100 in completed sales.

- [x] `promo_codes` table + `businesses.promo_code_id`/`trial_starts_at`/`trial_ends_at`/`royalty_free_threshold` — `database/migration-vendorpromo.sql`
- [x] `admin/promo-codes.php` — create/list codes, uses_count/uses_limit, active toggle
- [x] Vendor registration — optional promo code field, validated and captured
- [x] Trial starts on approval (not registration) — `admin/action.php`
- [x] Checkout royalty override — `checkout/confirm.php` sets `$effectiveRate = 0` while trial active
- [x] Vendor dashboard trial banner — progress toward $100/3-month trial end

---

## Notifications (Resend)

Transactional email via Resend — `config/mail.php`.

- [x] All 4 original trigger points — payment confirmed → buyer, order dispatched → buyer, delivery confirmed → vendor, payout sent → vendor
- [x] Plus more added since — abandoned cart, review reminders, low stock alerts (see their own sections above)

---

## Discount Codes / Coupons

Buyers apply a code at checkout for a percent/fixed discount, capped by min order and max uses. Discount is a platform-absorbed marketing cost — vendor royalty/payout stays on the pre-discount subtotal.

- [x] `coupons` + `coupon_uses` tables, `orders.coupon_id`/`coupon_code`/`discount_amount` — `database/migration-coupons.sql`
- [x] `config/coupon.php` — shared `validate_coupon()` (active/date-window/max-uses/min-order/one-use-per-buyer), used by checkout preview, confirm.php, and the API endpoint alike
- [x] `admin/coupons.php` + `admin/coupon-action.php` — inline-editable list (create/edit/toggle/delete); expired codes read-only; delete blocked once a code has been used
- [x] `api/coupon/validate.php` — JSON validation endpoint
- [x] `checkout/apply-coupon.php` + `checkout/index.php` — session-based apply/remove UX, live discount line on summary
- [x] `checkout/confirm.php` — re-validates server-side, atomic race-safe `used_count` increment, proportional discount allocation across multi-vendor order groups, `coupon_uses` row per order, discount line in confirmation email
- [x] Refund/total displays corrected for discount everywhere `orders.subtotal` was shown as the buyer-paid amount — `dashboard-buyer/order.php`, `orders-vendor/refund.php`, `admin/refunds.php`, `admin/refund.php`, `admin/order.php`
- [x] Bilingual UI strings — `lang/en.php` / `lang/km.php`
