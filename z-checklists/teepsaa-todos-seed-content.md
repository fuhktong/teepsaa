# teepsaa — Seed the Site With Content

Goal: a homepage and search results that look like an **active marketplace**
when you show the site to a real vendor in Phnom Penh. An empty marketplace is
the hardest thing to sell.

Consolidated 2026-08-23 from `todos-filler-content`.

**This is the last phase before pitching**, done after the website and both
mobile apps are finished. Seeded content has a shelf life — Trending reads
orders from the last 7 days, and Best Sellers and Top Rated need activity that
looks recent — so seeding months ahead of the pitch just means doing it twice.

But you still need a **small test dataset from day one**, because most of the
launch checklist and the whole catalog half of the app API are untestable
against an empty site. See "The minimum you need earlier" below.

Related: `teepsaa-todos-launch-readiness.md`, `teepsaa-todos-mobile-app.md`,
`teepsaa-vendor-marketing.md`.

---

## Why products alone aren't enough

Each homepage section only renders if it has data, and three of them need
**orders and reviews**, not just products:

| Section | Fills from | What it needs |
| --- | --- | --- |
| Banner carousel | `banners` table | 1–3 banners (en + km) |
| Featured | random products | any 8+ products |
| Best sellers | all-time `SUM(order_items)` | **seeded orders** |
| Trending this week | orders in the last 7 days, excluding pending/cancelled | **recent seeded orders** |
| New arrivals | newest products | any products |
| Top rated | products with ≥1 review | **seeded reviews** |
| Under $15 | products under $15 | 8+ cheap products |
| Category tiles | top 10 categories by product count | products spread over 10+ categories |

So the plan has to include placing fake orders and leaving fake reviews, not
just uploading a catalog. Step 5 is the one people skip and then wonder why
half the homepage is missing.

---

## The minimum you need earlier

Don't confuse this with the full seed. The full seed is presentation — 10–12
businesses, 70–100 products, banners, sale badges — and it goes last. But a
handful of records has to exist from the start or you cannot test anything:

- **Launch readiness Part 1** needs two throwaway vendors with a product each,
  for the approval and rejection tests.
- **Launch readiness Part 3** (device testing) needs enough products that the
  homepage rows and search results actually populate. "Product rows scroll
  horizontally without breaking" is not a testable claim on an empty homepage,
  and neither is "check total homepage weight".
- **Route 2's catalog endpoints** — home sections, search, product detail,
  business page, categories — return empty arrays against an empty database.
  You'd be building the buyer app's entire browse experience blind.

Rough size: **2–3 vendors, 15–20 products, a few completed orders and reviews.**
That's Steps 3–5 at about a fifth scale, and much of it may already exist from
the live order and refund run-throughs. Keep it, test against it, then do the
full seed on top when the apps are done.

---

## Step 1 — Plan the catalog

Decide all of this before you create anything. Changing your mind after 60
products exist is expensive.

- [ ] **Pick a vendor count — 10 to 12 businesses.** Fewer and the site looks
      like one shop with a lot of stock.
- [ ] **Pick products per vendor — 6 to 10**, giving you roughly 70–100
      products total.
- [ ] **Spread them over at least 10 categories** so the category tiles row
      fills. The lead categories are Fashion, Beauty & Personal Care,
      Electronics, Phone Accessories, Skincare, Moto Accessories.
- [ ] **Make sure 10+ products are priced under $15** so the Under $15 row
      fills.
- [ ] **Give 5–10 products a sale price and a `sale_ends_at`.** Sale badges
      make a site look alive more than almost anything else.
- [ ] **Give some products size variants** so you can demo variant selection
      when you're sitting in front of a vendor.
- [ ] **Write every name and short description in both English and Khmer.**
      Not machine-translated and left alone — these go on
      `teepsaa-todos-khmer-verification.md` and a Khmer speaker will read them.

## Step 2 — Collect photos

- [ ] **Source royalty-free product photos** from Unsplash, Pexels or Pixabay
      that suit a Cambodian marketplace — clothing, food, crafts, electronics,
      homeware. Avoid obviously Western studio shots.
- [ ] **Get 2–4 photos per product.** A gallery looks real; a single photo
      looks like a placeholder. Keep each under the upload size limit.
- [ ] **Make a banner and logo for each filler business** (vendor settings →
      banner).
- [ ] **Make 1–3 homepage carousel banners.** These don't need design skill —
      a solid colour with tagline text reads fine.
- [ ] **Compress everything before uploading.** Check the homepage weight
      afterwards; see the slow-connection checks in the launch file.

## Step 3 — Create the vendors

**Decide manual or scripted before you start.** `database/seed-vendors.php`
already exists as a starting point and can be extended to bulk-create vendors,
products and photos in one run. Doing 12 vendors by hand is roughly a day;
extending the script is a few hours and repeatable.

- [ ] **Make that call — by hand or by script — and don't switch halfway.**

Then for each business:

- [ ] **Register at `/register-vendor/`** with a real-looking business name in
      English and Khmer.
- [ ] **Verify the email** using a +alias of your own address
      (`dustint505+vendor1@gmail.com`).
- [ ] **Set the business address and map pin somewhere in Phnom Penh.** This is
      **required, not cosmetic** — checkout runs a distance check and fails
      outright if a business has no lat/lng.
- [ ] **Upload the avatar and business banner** in vendor settings.
- [ ] **Approve the business as admin.** Unapproved businesses are invisible to
      buyers, so skipping this makes everything else look broken.

## Step 4 — Create the products

- [ ] **Add products from each vendor account** via `/products/`, or via the
      seed script.
- [ ] **Give every product all five essentials:** English and Khmer name,
      category, price, stock above zero, and a primary photo. A product missing
      any one of these will look broken somewhere.
- [ ] **Confirm each product's primary photo actually renders** on all four
      surfaces: homepage, search, business page, product detail. This is where
      a bad upload shows up.

## Step 5 — Seed activity

This is the step that fills Best Sellers, Trending and Top Rated.

- [ ] **Create 2–3 test buyer accounts**, again with +aliases.
- [ ] **Place 10–15 orders** spread across different products and vendors, and
      push each one through to delivered or completed — vendor dispatch then
      buyer confirm, or force it as admin. This is what Best Sellers reads.
- [ ] **Make sure several orders are dated within the last 7 days**, otherwise
      the Trending row stays empty no matter how many orders exist.
- [ ] **Leave 4–5 star reviews on 10+ delivered products**, mixing Khmer and
      English text. This fills Top Rated and puts star ratings on product cards
      everywhere.
- [ ] **Have your Khmer speaker write or check a few reviews** so they read like
      real shoppers rather than translated marketing copy. Reviews are the text
      a browsing vendor is most likely to actually read.

## Step 6 — Look at it the way a vendor will

- [ ] **Homepage** — every section has a full row, nothing empty, no broken
      images.
- [ ] **Search with no query** — looks populated and the filters have real
      options in them.
- [ ] **Category tiles** — each shows a sensible sample photo and a count that
      isn't 1.
- [ ] **Business pages look like real shops** — banner, avatar, a full product
      grid, a description.
- [ ] **Check the whole thing in Khmer.** The km homepage is what a Cambodian
      vendor sees, and it's the version you'll be demoing.
- [ ] **Check it on the actual phone you'll pitch with**, over mobile data, not
      office wifi. That's the real demo condition.

---

## Before real launch — cleaning up

Do these when real buyers are about to arrive, not before the pitch.

- [ ] **Decide what happens to the filler vendors.** Either keep them as "house"
      shops you genuinely fulfil, or archive them as real vendors join. Decide
      now; drifting into "they're still there" is how fake shops end up taking
      real orders.
- [ ] **Delete or hide the seeded reviews.** Fake reviews attached to real
      products is a trust problem and the kind of thing that gets screenshotted.
- [ ] **Delete the test buyer accounts and their orders**, or knowingly accept
      that they sit inside your accounting numbers forever. Either is fine —
      silently forgetting is not.
