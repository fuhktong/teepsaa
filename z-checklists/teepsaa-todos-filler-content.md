# Filler Content — Make the Site Look Populated Before Pitching Vendors

Goal: a homepage and search results that look like an active marketplace when
you demo the site to real vendors in Cambodia.

## What the homepage actually needs (from the code)

Each section only appears if it has data. Requirements to fill every section:

| Section | Fills from | Needs |
| --- | --- | --- |
| Banner carousel | banners table | 1–3 banners (en + km versions) |
| Featured | random products | any 8+ products |
| Best sellers | SUM of order_items all time | seeded orders |
| Trending this week | orders in last 7 days (not pending/cancelled) | recent seeded orders |
| New arrivals | newest products | any products |
| Top rated | products with ≥1 review | seeded reviews |
| Under $15 | products priced < $15 | 8+ cheap products |
| Category tiles | top 10 categories by product count | products spread across 10+ categories |

Key insight: **products alone won't fill Best Sellers, Trending, or Top
Rated** — those need orders and reviews to exist. Plan for seeded orders and
reviews from test buyer accounts.

## Step 1 — Plan the catalog

- [ ] Decide vendor count: 10–12 filler businesses (enough that the site
      doesn't look like one shop)
- [ ] Decide products per vendor: 6–10 each → 70–100 products total
- [ ] Spread across at least 10 categories so the category tiles row is full
- [ ] Ensure 10+ products priced under $15 (fills the Under $15 row)
- [ ] Give 5–10 products a sale price + `sale_ends_at` (sale badges make the
      site look alive)
- [ ] Give some products variants (sizes) so demos can show variant selection
- [ ] Write names + short descriptions in BOTH English and Khmer for every
      product and business (goes on the Khmer verification checklist too)

## Step 2 — Collect photos

- [ ] Source royalty-free product photos (Unsplash / Pexels / Pixabay) that
      fit a Cambodian marketplace — clothing, food, crafts, electronics,
      homeware
- [ ] 2–4 photos per product (gallery looks better than a single shot),
      under the upload size limit
- [ ] A banner/logo image per filler business (vendor settings → banner)
- [ ] 1–3 homepage carousel banners (design simple ones — even solid color +
      tagline text works)

## Step 3 — Create the vendors

For each filler business:

- [ ] Register at `/register-vendor/` with a real-looking business name
      (en + km)
- [ ] Verify the email (use +aliases of your own email, e.g.
      `dustint505+vendor1@gmail.com`)
- [ ] Set business address + map pin somewhere in Phnom Penh — **required**:
      checkout distance check fails if the business has no lat/lng
- [ ] Upload avatar + business banner in vendor settings
- [ ] Admin → approve the business (unapproved businesses are invisible
      to buyers)

Alternative: `database/seed-vendors.php` already exists as a starting point —
Claude can extend it to bulk-create vendors + products + photos in one script
instead of doing all of the above by hand. Decide manual vs script before
starting Step 3.

## Step 4 — Create the products

- [ ] Add products via `/products/` on each vendor account (or via the seed
      script)
- [ ] Every product: en + km name, category, price, stock > 0, primary photo
- [ ] Confirm each product's primary photo shows on: homepage, search,
      business page, product detail

## Step 5 — Seed activity (orders + reviews)

- [ ] Create 2–3 test buyer accounts (email +aliases again)
- [ ] Place 10–15 orders across different products/vendors, and move them to
      delivered/completed (vendor dispatch → buyer confirm, or admin) —
      fills Best Sellers
- [ ] Make sure some orders are from the last 7 days — fills Trending
- [ ] Leave 4–5 star reviews (mix of Khmer and English text) on 10+ delivered
      products — fills Top Rated and puts star ratings on product cards
- [ ] Reviews should read like real Khmer shoppers wrote them — have your
      Khmer speaker write/check a few

## Step 6 — Final look check

- [ ] Homepage: every section has a full row, no empty sections, no broken
      images
- [ ] Search page with no query: looks full, filters have options
- [ ] Each category tile shows a sensible sample photo and count
- [ ] Business pages look real: banner, avatar, products, description
- [ ] Check in BOTH languages — km homepage is what Cambodian vendors will see
- [ ] Check on a phone — that's how you'll demo it in person

## Before real launch (not before the pitch)

- [ ] Decide what happens to filler content at launch: keep as "house"
      vendors, or archive them as real vendors join
- [ ] Delete/hide seeded reviews before real buyers arrive — fake reviews on
      real products is a trust problem
- [ ] Delete test buyer accounts and test orders (or accept them in the
      accounting numbers)
