# Pre-Launch Audit Checklist

## Code Review
- [ ] Run `/code-review ultra` in Claude Code — deep multi-agent review covering bugs, security issues, and inefficiencies
- [ ] Start with the vendor section (most recent work): `products/`, `dashboard-vendor/`
- [ ] Then buyer flow: `cart/`, `checkout/`, `product/`, `search/`
- [ ] Then shared: `header/`, `footer/`, `config/`, `api/`

## PHP / Server
- [ ] Enable `display_errors = On` in dev, click through every page — catch notices and warnings that aren't fatal
- [ ] Run `php -l` on files that were heavily edited to catch any syntax issues
- [ ] Confirm all session role checks are consistent across vendor, buyer, and admin portals
- [ ] Confirm CSRF protection is on every POST form

## Security
- [ ] All user input sanitized with `htmlspecialchars()` before output
- [ ] All SQL uses prepared statements — no raw `$_POST` in queries
- [ ] File upload validation uses magic bytes (not just extension or mime type) — already done in `config/upload.php`
- [ ] `/uploads/` directory blocks PHP execution via `.htaccess` — already in place
- [ ] Admin routes reject non-admin sessions
- [ ] Vendor routes reject buyer sessions and vice versa

## Dead Code / Orphaned Files
- [ ] Check for PHP action files no longer linked from any form or page
- [ ] Check for CSS classes defined but never used (especially after the products/dashboard-vendor refactor)
- [ ] Check for JS files in `/js/` that are no longer imported anywhere
- [ ] Confirm `photo-set-primary.php` is still referenced (or can be deleted — star button was removed)

## Database
- [ ] Check for orphaned `product_photos` rows where `product_id` no longer exists
- [ ] Check for orphaned `cart_items` referencing deleted products or inactive buyers
- [ ] Check for orders with `product_id = NULL` in `order_items` (expected after deletes, just confirm it's handled gracefully in order displays)
- [ ] Confirm `archived = 0` filter is applied everywhere products are shown to buyers
- [ ] Confirm `is_primary` is set correctly — each product should have at most one primary photo

## Buyer Flow
- [ ] Search returns correct results, images load
- [ ] Homepage sections (featured, best sellers, new arrivals, recently viewed) all load without errors
- [ ] Business page shows correct products with images
- [ ] Product detail page loads, add to cart works, out-of-stock disables button
- [ ] Cart shows correct items and totals
- [ ] Checkout completes and order appears in buyer dashboard
- [ ] Buyer cannot access vendor routes

## Vendor Flow
- [ ] Login redirects correctly, wrong role is rejected
- [ ] Products list: sort works, archive/unarchive/delete work, status dropdown works
- [ ] Edit product: save changes works, photo gallery drag-to-reorder saves correctly
- [ ] Archive tab shows archived products, unarchive moves back to products tab
- [ ] Orders show only pending/paid on dashboard, full history in products orders tab
- [ ] Vendor cannot access buyer cart/checkout routes

## Admin Flow
- [ ] Admin login rejects non-admin sessions
- [ ] Business approvals, rejections work
- [ ] Order management works
- [ ] Messages work

## Frontend / UI
- [ ] Test on mobile — check header, product cards, forms, gallery grid
- [ ] Check all pages at narrow viewport (< 400px) for overflow issues
- [ ] Confirm images load on: homepage, search, business page, product detail, vendor products list, vendor dashboard
- [ ] Confirm no broken links in header/footer nav
- [ ] Check all flash messages (success/error) appear and clear correctly after redirect

## Final
- [ ] Set `display_errors = Off` before going live
- [ ] Set `PAYOUT_WINDOW_SECONDS` in `config/db.php` to `86400` (24h) for production
- [ ] Confirm `/uploads/` is writable by the web server user
- [ ] Review any `TODO` or `FIXME` comments left in code
