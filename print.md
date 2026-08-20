# Vendor access-control test — URL list

Checklist item:

> Vendor CANNOT open /cart/, /checkout/, /dashboard-buyer/, /wishlist/
> (rejected), nor /admin/

**Setup:** log in as a vendor. Stay logged in as that vendor the whole time.
Paste each URL into the address bar by hand. Nothing below should ever show
you the actual page.

---

## 1. Buyer-only pages

| URL | Expected result |
|---|---|
| `https://teepsaa.com/cart/` | bounced to vendor dashboard |
| `https://teepsaa.com/checkout/` | bounced to vendor dashboard |
| `https://teepsaa.com/dashboard-buyer/` | bounced to vendor dashboard |
| `https://teepsaa.com/wishlist/` | bounced to vendor dashboard |
| `https://teepsaa.com/dashboard-buyer/settings/` | bounced to vendor dashboard |
| `https://teepsaa.com/orders/` | bounced to vendor dashboard |
| `https://teepsaa.com/messages-buyer/` | bounced to vendor dashboard |

Each of these is a three-hop redirect chain, so it happens fast and may just
look like the URL snapped to the dashboard:

1. page sees `role !== 'buyer'` → sends you to `/login-buyer/`
2. `/login-buyer/` sees you're already a vendor → sends you to `/dashboard-vendor/`
3. `/dashboard-vendor/` is a vendor path → sends you to `vendor.teepsaa.com`

**Pass** = you land on the vendor dashboard and never see cart/checkout
contents. **Fail** = the page renders, OR the browser says "too many
redirects" (that chain is the kind that loops if one side is wrong).

Also try them on the vendor subdomain — same expected result:

- `https://vendor.teepsaa.com/cart/`
- `https://vendor.teepsaa.com/checkout/`
- `https://vendor.teepsaa.com/dashboard-buyer/`
- `https://vendor.teepsaa.com/wishlist/`

## 2. Admin — from the wrong host

Admin paths only exist on the admin subdomain. Everywhere else they are a
hard **404**, not a redirect.

| URL | Expected result |
|---|---|
| `https://teepsaa.com/admin/` | 404 Not Found |
| `https://teepsaa.com/login-admin/` | 404 Not Found |
| `https://vendor.teepsaa.com/admin/` | 404 Not Found |
| `https://vendor.teepsaa.com/login-admin/` | 404 Not Found |

## 3. Admin — from the admin subdomain

Here the guard is the session check: admin pages require `admin_id`, which a
vendor session never has. Expect the admin login page every time.

Top level:

- `https://admin.teepsaa.com/admin/`

Deeper pages — test these too, since a missed guard hides on a child page,
not the index:

- `https://admin.teepsaa.com/admin/orders.php`
- `https://admin.teepsaa.com/admin/buyers.php`
- `https://admin.teepsaa.com/admin/products.php`
- `https://admin.teepsaa.com/admin/payouts.php`
- `https://admin.teepsaa.com/admin/payments.php`
- `https://admin.teepsaa.com/admin/accounting.php`
- `https://admin.teepsaa.com/admin/refunds.php`
- `https://admin.teepsaa.com/admin/coupons.php`
- `https://admin.teepsaa.com/admin/promo-codes.php`
- `https://admin.teepsaa.com/admin/admins.php`
- `https://admin.teepsaa.com/admin/settings.php`
- `https://admin.teepsaa.com/admin/vendor-map.php`
- `https://admin.teepsaa.com/admin/buyer-map.php`
- `https://admin.teepsaa.com/admin/careers.php`
- `https://admin.teepsaa.com/admin/careers-applications.php`
- `https://admin.teepsaa.com/admin/content.php`
- `https://admin.teepsaa.com/admin/banners.php`
- `https://admin.teepsaa.com/admin/categories.php`
- `https://admin.teepsaa.com/admin/faq.php`
- `https://admin.teepsaa.com/admin/reviews.php`
- `https://admin.teepsaa.com/admin/messages/`
- `https://admin.teepsaa.com/admin/messages/emails.php`

**Pass** = admin login page, no admin content, no data visible behind it.

**The one to watch:** `admin/resume.php` serves job-applicant CVs from a
private folder. Confirm it does not hand back a file:

- `https://admin.teepsaa.com/admin/resume.php?id=1`

## 4. API endpoints — the `as=admin` flag

These three accept `as=admin`, but must only honour it when `admin_id` is
set. As a vendor the flag should be **silently ignored** — you get normal
vendor-level data back, never admin data, and never an error that leaks
whether the record exists.

- `https://teepsaa.com/api/order-status.php?id=1&as=admin`
- `https://teepsaa.com/api/messages/poll.php?as=admin`

`api/messages/reply.php` is POST-only, so it can't be tested from the address
bar. Skip it here, or test it from the browser console on a page that already
has your CSRF token.

## 5. Sanity check (make sure the test is real)

If every URL above "passes" but you were actually logged out the whole time,
you proved nothing. Before starting, confirm your vendor session is live:

- `https://vendor.teepsaa.com/dashboard-vendor/` → should load normally

Re-check this at the end too. A vendor session should survive the whole test;
if you got silently logged out partway, run the list again.

---

# Part B — same URLs, admin session

Yes, reuse the addresses. **No, do not reuse the expected results** — an
admin session behaves differently from a vendor session, so copying Part A's
"pass" conditions would give you false passes.

**Setup:** log in as an admin, and be logged in as *nothing else*. Admin and
buyer sessions can be active at the same time by design, so if a buyer login
is lingering, the buyer pages will open and that is correct behaviour — but
it isn't the thing you're testing. Use a fresh browser or a private window.

## B1. Buyer + vendor pages — expect a login *form*

An admin session sets `admin_id` only. It never sets `user_id`, so buyer and
vendor pages see a logged-out visitor and show their login page. There is no
bounce back to a dashboard, because there's no buyer/vendor session to bounce.

| URL | Expected result |
|---|---|
| `https://teepsaa.com/cart/` | buyer login form |
| `https://teepsaa.com/checkout/` | buyer login form |
| `https://teepsaa.com/dashboard-buyer/` | buyer login form |
| `https://teepsaa.com/wishlist/` | buyer login form |
| `https://vendor.teepsaa.com/dashboard-vendor/` | vendor login form |
| `https://vendor.teepsaa.com/products/` | vendor login form |
| `https://vendor.teepsaa.com/orders-vendor/` | vendor login form |

**Pass** = a login form. **Fail** = cart contents, a dashboard, or any real
data. Contrast with Part A, where a vendor gets bounced to their dashboard —
if you see that here, you still have a vendor session open.

**Also check the chrome:** on any of those buyer pages, the admin nav must
NOT appear. Admin chrome follows the URL, not the session, so a buyer page
should look like an ordinary buyer page even while you're signed in as admin.

## B2. Admin pages — expect them to WORK

Part A section 3 flips over here. As a super admin every page in that list
should load normally. Walk the same list and confirm each one renders with
its data:

`/admin/` · `orders.php` · `buyers.php` · `products.php` · `payouts.php` ·
`payments.php` · `accounting.php` · `refunds.php` · `coupons.php` ·
`promo-codes.php` · `admins.php` · `settings.php` · `vendor-map.php` ·
`buyer-map.php` · `careers.php` · `careers-applications.php` · `content.php` ·
`banners.php` · `categories.php` · `faq.php` · `reviews.php` ·
`messages/` · `messages/emails.php`

## B3. Permission levels — the real admin test

This is the part Part A doesn't cover at all, and it's where the bugs live.
Every admin page guards on a named permission. A non-super admin who lacks a
section must be turned away and sent to their own home page with `?denied=1`
in the URL.

The permission names, one per page:

`vendors` · `buyers` · `products` · `categories` · `reviews` · `orders` ·
`refunds` · `accounting` · `payments` · `payouts` · `promo-codes` ·
`coupons` · `banners` · `careers` · `vendor-map` · `buyer-map` · `content` ·
`faq` · `messages`

How to test: create a limited admin in `/admin/admins.php` with only one or
two permissions ticked — say `orders` only. Log in as that admin, then:

- `https://admin.teepsaa.com/admin/orders.php` → loads (they have it)
- `https://admin.teepsaa.com/admin/payouts.php` → denied, redirected to
  `/admin/orders.php?denied=1`
- `https://admin.teepsaa.com/admin/accounting.php` → denied
- `https://admin.teepsaa.com/admin/buyers.php` → denied

Three rules worth confirming separately:

- **`admins.php` is super-admin only.** A limited admin is refused even if
  someone ticks every box for them — the code hard-denies it for non-supers.
- **`settings.php` is always allowed**, for every admin, no permission needed.
  That's intentional (they need to change their own password).
- **The nav should match.** A limited admin's admin tabs should only show
  sections they can reach. A visible tab that then denies you is a bug.

**Worth confirming in the browser:** `admin/resume.php` serves applicant CVs.
The code guards it with `admin_require('careers')`, so an admin without that
permission should be refused:

- `https://admin.teepsaa.com/admin/resume.php?id=1`

## B4. Action endpoints — already audited, no action needed

The `*-action.php` files do the writes, and a guard on the page but not on
the action is the classic miss. This was checked in code across all 22 action
endpoints — **every one is guarded**, each with the same permission as its
page (`order-action.php` → `orders`, `payouts-action.php` → `payouts`, and
so on). `admin/resume.php`, `admin/messages/reply.php` and
`admin/messages/status.php` are guarded too.

Two files have no `admin_require()`, and both are correct that way — they act
on your own account, not on a permissioned section, and both still check
`admin_id`:

- `admin/avatar-action.php` — your own admin avatar
- `admin/settings-password-action.php` — your own password (matches the rule
  that `settings` is open to every admin)

Nothing to test by hand here. Re-run the check if new action files get added.

---

## Note on localhost

On localhost the subdomain layer is switched off entirely — everything is one
host. So the section 2 "404 from the wrong host" behaviour **cannot be tested
locally**, and the redirect chains are shorter (no hop to another domain).
Test sections 2 and 3 on the live domains.

Local equivalents for sections 1, 4, 5:

- `http://localhost/cart/`
- `http://localhost/checkout/`
- `http://localhost/dashboard-buyer/`
- `http://localhost/wishlist/`
- `http://localhost/admin/`
- `http://localhost/api/order-status.php?id=1&as=admin`
