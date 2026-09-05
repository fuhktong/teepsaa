# SEO for teepsaa — what exists, what's broken, what to add

Written 2026-09-05 from a read of the live codebase. SEO is absent from
`teepsaa-todos-launch-readiness.md`; this is the missing chapter, split the
way that file splits — **things to do before the gate comes off**, then
**things that come after launch**.

Nothing here is speculative "best practice" filler. Every item names the file
and line it applies to.

---

## What's already built (credit where due)

- `config/seo.php` — one helper emitting description, canonical, og:title,
  og:description, og:image, og:url, og:site_name, og:type, and the four
  twitter card tags. Escapes everything, truncates descriptions to 160 chars.
- Wired into 5 pages: `index.php:187`, `product/index.php:109`,
  `business/index.php:80`, `search/index.php:295`, `wishlist/index.php:44`.
- `sitemap.php` — dynamic XML, all live products + approved businesses, using
  UUID `public_id`s, with `lastmod`.
- `robots.txt` — blocks admin/api/checkout/dashboards, points at the sitemap.
- Fonts are preloaded on every page (`woff2`, `crossorigin`) — genuinely good.
- URLs already use unguessable `public_id`s, so nothing below requires
  reopening the enumeration decision.

That's a better starting point than most sites at launch. The gaps below are
what stands between "has meta tags" and "ranks".

---

# Part A — Before the gate comes off

These are small, they touch code that already exists, and several are
outright bugs. Doing them after launch means re-crawling and re-indexing
work you could have got right the first time.

## A1. The gate itself

`.htaccess` lines 24-35 password-protect the whole site. While it is up,
Googlebot gets `401` on `/`, on `/robots.txt` and on `/sitemap.php`. Nothing
indexes, and nothing below matters. Already tracked as Part 5 of the launch
readiness file — just noting that every SEO item is downstream of it.

**On the day the gate drops:** submit the sitemap in Search Console the same
hour. Indexing a new domain takes weeks; start the clock immediately.

## A2. `images/og-default.png` does not exist — 5 min

`config/seo.php:16` falls back to `$base . '/images/og-default.png'` for any
page without its own photo. That file is not in `images/`:

```
teepsaa logo.png            teepsaa_logo_eng.png       teepsaa_logo_khm.png
teepsaa-icon-180.png        teepsaa_logo_eng_2.png     ...
teepsaa-icon-192.png        teepsaa_logo_eng_myriad.png
teepsaa-icon-512.png        teepsaa_logo_eng_optima.png
```

So every share of the homepage, the search page, the wishlist, and any
product without a photo produces a broken preview card on Facebook, Telegram,
Messenger and X. In Cambodia that is where most of your traffic will come
from, so this is the highest value-per-minute item on the list.

**Fix:** make a 1200×630 PNG (logo on brand background, under 300 KB), save
as `images/og-default.png`. Validate with Facebook's Sharing Debugger.

## A3. No favicon on the public site — 5 min

`grep -rn favicon --include="*.php" .` returns nothing outside
`admin/prospects/app-head.php:19`. The icons already exist
(`teepsaa-icon-180/192/512.png`). Browser tabs, bookmarks and Google's
mobile results all show a favicon; yours will be blank.

Add to the `<head>` of every public page (ideally by moving the shared head
block into one include — see A8):

```html
<link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
```

## A4. Bots see Khmer content under `lang="en"` with English titles — 30 min

This is the biggest correctness problem in the current SEO setup.

- `config/db.php:54` — `lang_field()` defaults to **km**:
  `($_SESSION['lang'] ?? 'km') === 'km'`
- `header/header.php:3` — same default.
- Every public page hardcodes `<html lang="en">`.
- `product/index.php:64` — `<title><?= $product['name'] ?></title>` uses the
  raw **English** column, and `:111` passes `$product['description']`
  (English) to `seo_meta()`.
- `product/index.php:187` — `<h1><?= lang_field($product, 'name') ?></h1>`
  renders **Khmer**.

Googlebot has no session, so it takes the default: a Khmer `<h1>`, Khmer body
copy, an English `<title>`, an English meta description, and a `lang="en"`
declaration saying all of it is English. Google resolves that mismatch by
trusting the body — so your English titles are describing a page it has
classified as Khmer, and the snippet it writes may ignore your description
entirely.

**Minimum fix (do before launch):** make the three agree.

1. Declare the real language:
   `<html lang="<?= ($_SESSION['lang'] ?? 'km') === 'km' ? 'km' : 'en' ?>">`
2. Feed `seo_meta()` the same language the body uses — swap
   `$product['name']` for `lang_field($product, 'name')` and
   `$product['description']` for `lang_field($product, 'description')` in
   `product/index.php:63-115`, and the equivalent in `business/index.php`.
3. Decide, deliberately, which language anonymous visitors (and therefore
   Google) get. Khmer is the current default by accident, not by decision.
   For a Phnom Penh marketplace Khmer is defensible — but competition for
   Khmer product terms is thin and the English terms ("delivery Phnom Penh")
   have volume. Whichever you pick, pick it on purpose.

**Proper fix (post-launch, C1):** one URL currently serves both languages, so
only one can ever be indexed. Splitting them is a real project — see below.

## A5. Missing products 302 to `/search/` — 20 min

`product/index.php:28-31` and the same pattern in `business/index.php`:

```php
if (!$product) {
    header('Location: /search/');
    exit;
}
```

Once a vendor archives a product or a shop is suspended, its indexed URL
starts redirecting to the search page. Google calls this a soft 404: it keeps
the dead URL in the index, keeps re-crawling it, and treats the redirect as a
weak signal that `/search/` duplicates the product page.

**Fix:** return a real status and a real page.

- Product never existed / bad UUID → `http_response_code(404)`
- Product existed but is archived → `http_response_code(410)` (Gone — Google
  drops it faster than a 404)

Render a small "this product is no longer available" page with the header,
footer and a link back to search. Do the same for `business/`.

Also add a site-wide 404 page — `.htaccess` has no `ErrorDocument`, so a typo
URL currently gets Apache's grey default with no header, no nav, no way back.

```apache
ErrorDocument 404 /404.php
```

## A6. Static pages have no meta at all — 20 min

`about, help, privacy, terms, contact, careers, returns, shipping` each have
a `<title>` and an `<h1>` but never call `seo_meta()` — no description, no
canonical, no OG tags. `/about/` and `/help/` in particular are pages people
land on from brand searches and share links.

One line in each `<head>`:

```php
<?php
    require_once __DIR__ . '/../config/seo.php';
    echo seo_meta('About — teepsaa',
        'teepsaa connects Phnom Penh shoppers with local businesses …',
        '', 'https://teepsaa.com/about/');
?>
```

## A7. Homepage and search have no `<h1>` at all — 15 min

`index.php` jumps straight to `<h2>` at line 341. `search/index.php` has no
`<h1>` anywhere. Your homepage is the page that must rank for the brand and
for the head terms, and it has no top-level heading.

Add one above the banner carousel, sourced from `$t` so it translates:

```html
<h1 class="home-h1"><?= $t['home_h1'] ?></h1>
<!-- en: "Shop local Phnom Penh businesses — delivered" -->
```

If it doesn't fit the design, style it small or visually-hidden (a
`clip-path` sr-only class, **not** `display:none`, which Google discounts).
On `/search/`, use the query: `Results for "<?= $q ?>"` — or "All products"
when bare.

## A8. Every product image has `alt=""` — 1 hr

42 occurrences across the codebase. The ones that matter:

| File | Line | Image |
|---|---|---|
| `index.php` | 14 | product card photo |
| `index.php` | 347 | category tile |
| `index.php` | 506 | JS-rendered card photo |
| `product/index.php` | 170, 174 | main photo + gallery thumbs |
| `business/index.php` | 111, 171, 204 | banner, featured, grid |
| `search/index.php` | 423, 441 | shop banner, card photo |

On a shopping site the product photo *is* the content. Empty alt forfeits
Google Images entirely, and image search is a real product-discovery channel.

```php
alt="<?= htmlspecialchars(lang_field($p, 'name')) ?>"
```

Leave `alt=""` on the genuinely decorative ones — `product/index.php:307`
(the lightbox `<img>` filled by JS) and the avatar SVGs, which correctly
carry `aria-hidden`.

## A9. robots.txt is shared by all three subdomains — 30 min

`config/subdomain.php:16` sets `SUBDOMAINS_ENABLED = true`, so
`vendor.teepsaa.com` and `admin.teepsaa.com` are live — and they point at
**the same `public_html`**, which means all three hosts serve the same
`/robots.txt`, the one written for the public site. Google can crawl
`vendor.teepsaa.com` and `admin.teepsaa.com` under buyer-site rules.

The redirects in `subdomain.php:63-84` do protect the content (admin paths
404 off-host, vendor paths bounce), but they're **302s**, which tell Google
the move is temporary and to keep the source URL indexed.

**Fix:**

1. Make robots host-aware. Rename to `robots.php`, add to `.htaccess`:
   ```apache
   RewriteRule ^robots\.txt$ /robots.php [L]
   ```
   and in `robots.php`, return `User-agent: *` + `Disallow: /` for any host
   that isn't `teepsaa.com`.
2. Change the `$sdGo()` redirects in `config/subdomain.php:58-61` from `302`
   to `301` — they are permanent routing rules, not temporary ones.

## A10. robots.txt gaps on the main site — 10 min

Currently missing. These are all thin, duplicate or per-user pages that
should never enter the index:

```
Disallow: /logout/
Disallow: /verify-email/
Disallow: /resend-verification/
Disallow: /forgot-password-buyer/
Disallow: /forgot-password-vendor/
Disallow: /reset-password-buyer/
Disallow: /reset-password-vendor/
Disallow: /unsubscribe/
Disallow: /support-thread/
Disallow: /order-status/
Disallow: /refund-status/
Disallow: /review/
Disallow: /submit/
Disallow: /products/
Disallow: /currency/
Disallow: /lang/
```

Do **not** disallow `/uploads/` — you want product photos in Google Images.

## A11. Faceted search will bloat the crawl — 20 min

`search/index.php:16-24` accepts `q`, `sort`, `min_price`, `max_price`,
`category`, `min_rating` and `variant_values[]` (an array). That's an
effectively infinite URL space, and every combination renders a page.

You're partly covered already: `seo_meta()` strips the query string
(`strtok($_SERVER['REQUEST_URI'], '?')`), so every variant self-canonicalises
to `/search/`. Make it explicit:

```php
<?php if ($q !== '' || $hasActiveFilters): ?>
<meta name="robots" content="noindex,follow">
<?php endif; ?>
```

`$hasActiveFilters` already exists at `search/index.php:31`. Use `noindex,
follow` — **not** a robots.txt `Disallow` — because a blocked URL can't be
read, so Google never sees the noindex and keeps the URL as a bare listing.
Keep bare `/search/` indexable.

## A12. www and apex both serve everything — 5 min

`.htaccess:6-8` forces HTTPS but preserves the host:

```apache
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [L,R=301]
```

So `www.teepsaa.com` stays on www and serves the same catalogue as the apex,
while every canonical tag says `https://teepsaa.com`. The canonicals will
probably sort it out, but don't make Google guess:

```apache
RewriteCond %{HTTP_HOST} ^www\.teepsaa\.com$ [NC]
RewriteRule ^(.*)$ https://teepsaa.com/$1 [L,R=301]
```

## A13. Sitemap fixes — 30 min

`sitemap.php` works (and the `/browse/` 404 noted at launch-readiness:491
appears to be already gone from the current file). Remaining gaps:

- **Missing static pages.** It lists `/`, `/search/`, `/help/`, `/privacy/`,
  `/terms/` — but not `/about/`, `/contact/`, `/shipping/`, `/returns/`,
  `/careers/`.
- **`lastmod` uses `created_at`.** Neither `products` nor `businesses` has an
  `updated_at` column (checked every migration in `database/`). So a vendor
  can rewrite a listing and the sitemap still claims the original date, and
  Google has no reason to re-crawl. Add the column:
  ```sql
  ALTER TABLE products   ADD updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
  ALTER TABLE businesses ADD updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
  ```
  then swap `created_at` for `updated_at` in both queries. (Note: `database/`
  is excluded from deploys — apply this by hand on the server.)
- **`/sitemap.xml` doesn't resolve.** Every tool and half the crawlers look
  there first. Add `RewriteRule ^sitemap\.xml$ /sitemap.php [L]` and point
  `robots.txt` at the `.xml` form.
- **No image entries.** Adding `<image:image>` per product is the cheapest
  route into Google Images:
  ```xml
  <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
          xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  ...
    <image:image><image:loc>https://teepsaa.com/uploads/x.jpg</image:loc></image:image>
  ```

## A14. Search Console and analytics — 30 min

There is no GA4 tag, no GTM container, and no verification file anywhere in
the public pages. Without Search Console you are blind: no index coverage, no
query data, no Core Web Vitals field data, no manual-action alerts.

Do all of these on launch day:

1. **Google Search Console** — verify `teepsaa.com` as a *Domain* property
   (DNS TXT record — covers all three subdomains and both protocols at once).
   Submit `https://teepsaa.com/sitemap.xml`.
2. **Bing Webmaster Tools** — import from GSC, one click.
3. **GA4** (or a lighter option if you'd rather not carry Google's tag) in
   `footer/footer.php` so it lands on every page.
4. **Google Merchant Center** — free product listings put marketplace
   products in the Shopping tab at no cost. Your sitemap already has clean
   per-product URLs and the schema from B1 supplies the feed data.

---

# Part B — Structured data (the biggest single win)

`grep -rn "ld+json"` returns **zero results**. Nothing in the codebase emits
structured data. This matters more than everything in Part A combined,
because it's what turns a plain blue link into a result with a star rating, a
price and an in-stock badge — and because every field it needs is already
sitting in your database.

I'd do B1 before launch if there's time; B2-B5 can follow within the first
weeks. Build them as one helper, `config/schema.php`, alongside `seo.php`.

## B1. `Product` on `/product/` — the one that pays

You already compute everything at `product/index.php:85-88`:
`$avgRating`, `$reviewCount`, plus `$product['price']`, `stock`,
`sale_percent`, and the business name.

```php
$schema = [
  '@context' => 'https://schema.org',
  '@type'    => 'Product',
  'name'        => lang_field($product, 'name'),
  'description' => lang_field($product, 'description'),
  'image'       => array_map(fn($f) => 'https://teepsaa.com/uploads/' . $f, $allPhotos),
  'sku'         => $product['public_id'],
  'offers' => [
    '@type'         => 'Offer',
    'url'           => 'https://teepsaa.com/product/?id=' . $product['public_id'],
    'price'         => number_format(sale_price_for((float)$product['price'], $product), 2, '.', ''),
    'priceCurrency' => 'USD',
    'availability'  => $product['stock'] > 0
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
    'seller' => ['@type' => 'Organization',
                 'name'  => pick_lang($product['business_name'], $product['business_name_km'])],
  ],
];
if ($reviewCount > 0) {
  $schema['aggregateRating'] = [
    '@type'       => 'AggregateRating',
    'ratingValue' => round($avgRating, 1),
    'reviewCount' => $reviewCount,
  ];
}
echo '<script type="application/ld+json">'
   . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
   . '</script>';
```

**The `if ($reviewCount > 0)` guard is not optional.** Emitting
`aggregateRating` with `reviewCount: 0` — which most of your catalogue will
be at launch — is a structured-data violation and can cost you rich results
site-wide, not just on that page. Same rule for the `review` array: only
include it when reviews exist.

`sale_price_for()` already lives in `config/currency.php:24` and handles the
`sale_percent` / `sale_ends_at` logic, so the sale price comes out right for
free. Match `priceCurrency` to what the page actually displays (`config/currency.php` supports USD and KHR — the
schema must state the currency of the price it quotes).

## B2. `Organization` + `WebSite` on the homepage

The `WebSite` + `SearchAction` pair is what makes Google render a search box
inside your brand result:

```php
[
 '@context' => 'https://schema.org',
 '@type'    => 'WebSite',
 'url'      => 'https://teepsaa.com/',
 'name'     => 'teepsaa',
 'potentialAction' => [
   '@type'       => 'SearchAction',
   'target'      => ['@type' => 'EntryPoint',
                     'urlTemplate' => 'https://teepsaa.com/search/?q={search_term_string}'],
   'query-input' => 'required name=search_term_string',
 ],
]
```

Plus an `Organization` block with `logo`, `url` and `sameAs` (your Facebook,
Telegram and Instagram links) — that's what populates the knowledge panel.

## B3. `Store` on `/business/`

Vendor pages are your local-SEO surface. You have address data already
(`config/phnom-penh-locations.php`, the khan/sangkat columns, Mapbox
coordinates). A `Store` block with `address` (`PostalAddress`, `addressLocality`
Phnom Penh, `addressCountry` KH), `geo`, `image`, and — guarded the same way —
`aggregateRating` from `$bizRatingRow`, makes those pages eligible for local
results.

## B4. `BreadcrumbList` — and actual breadcrumbs

`grep -rin breadcrumb` returns nothing: no breadcrumb markup, and no
breadcrumb UI either. Adding a visible trail on product and business pages —
`Home › Category › Product` — helps in three ways at once: it's an internal
link that spreads crawl depth, it's a UX improvement, and with
`BreadcrumbList` schema Google shows the trail instead of the raw URL in
results. Given your URLs are `/product/?id=<uuid>`, replacing that ugly
string in the SERP is worth real clicks on its own.

## B5. `FAQPage` on `/help/`

`help/index.php:25` already reads a `faq_items` table with bilingual
question/answer columns, grouped by section. That is a `FAQPage` block
practically pre-built — loop `$faqs` into `mainEntity`. FAQ rich results eat
extra vertical space in the SERP.

---

# Part C — After launch

Bigger changes. None of them should hold up the gate, but C1 and C2 get
harder the longer you wait, because they change URLs that will have external
links pointing at them.

## C1. Give each language its own URL

Today one URL serves both languages, chosen by `$_SESSION['lang']`. That
caps you at one indexable language — currently Khmer, by accident (A4). The
entire Khmer catalogue you've built in `lang/km.php` (81 KB of translations)
and the `*_km` columns are invisible to search if you switch the default to
English, and your English copy is invisible today.

**The fix is prefixed paths:** `teepsaa.com/en/product/…` and
`teepsaa.com/km/product/…`, each self-canonical, cross-linked with
`hreflang`:

```html
<link rel="alternate" hreflang="en" href="https://teepsaa.com/en/product/?id=…">
<link rel="alternate" hreflang="km" href="https://teepsaa.com/km/product/?id=…">
<link rel="alternate" hreflang="x-default" href="https://teepsaa.com/en/product/?id=…">
```

Sitemap lists both. The language switcher swaps the prefix rather than
POSTing to `lang/set.php`. Session preference still drives which one you get
redirected to from a bare URL.

This roughly doubles your indexable surface and is the single largest
long-term item on this list. It's also a week of work touching every page, a
rewrite layer, and every internal link — hence post-launch.

## C2. Slug URLs

`/product/?id=8f14e45f-ab3c-…` has no keyword, doesn't survive being pasted
into a chat, and looks untrustworthy next to a competitor's tidy URL. Keep
the UUID (nothing about the enumeration decision changes) but put a slug in
front of it:

```
/product/khmer-silk-scarf-8f14e45f/
/shop/angkor-crafts-3ac91b7d/
```

A rewrite maps the trailing segment back to `public_id`; the slug is
cosmetic and the lookup is unchanged. Old `?id=` URLs 301 to the new form.
Moderate work, mostly in `.htaccess` and `config/url.php`.

## C3. Category landing pages

Right now `index.php:345` links category tiles to `/search/?q=<name>` — a
free-text search over `p.name` and `p.description`, not even the
`category_id` filter the search page already supports at `search/index.php:56`.
So a "Bags" tile misses every bag whose title doesn't contain the word.

Category pages are the pages that rank for head terms — "bags Phnom Penh",
"ថង់" — because they're stable, they accumulate links, and they're topically
focused in a way a product page isn't. Give them real URLs
(`/category/bags/`), an `<h1>`, two or three sentences of intro copy, and the
filtered grid. This is probably the highest-value *content* work available to
you, and it costs you nothing you don't already have.

## C4. Search results are invisible past the first batch

`search/index.php:534-592` loads more results by fetching
`/api/search/?offset=…` on scroll. Bots don't scroll. They see the first
batch and nothing else, and there are no `<a href>` links to deeper pages, so
crawl never reaches product 25+ through browsing.

The sitemap does list every product, so they'll get *found* — but internal
links are what pass ranking signal, and right now almost none flows to the
long tail of your catalogue. Add real pagination links (`?page=2` …),
rendered server-side. They can live in a `<noscript>` or a visually-quiet
footer row; they just have to be `<a href>` in the HTML.

## C5. Images — the Core Web Vitals problem

`uploads/` is 16 MB and holds PNGs up to **1.7 MB**
(`3fa5c41968a3af3866caba1e00aff5e7.png`), served at full resolution into
~200 px grid cards. `config/upload.php` validates magic bytes but never
re-encodes or downscales. Across the whole codebase there are **4**
`loading="lazy"` attributes and **6** `<img>` tags with an explicit `width`.

For a homepage that renders eight product carousels, that's several megabytes
of images on a mobile connection in Phnom Penh, plus layout shift as each one
lands. Core Web Vitals is a ranking factor, and mobile is your entire
audience.

1. **Derivatives on upload** — write a 400 w and a 1200 w WebP (with a JPEG
   fallback) next to the original in `config/upload.php`; serve the 400 w in
   cards, the 1200 w on product pages.
2. **`width` and `height` on every `<img>`** — kills cumulative layout shift
   outright. Cheapest CWV fix that exists.
3. **`loading="lazy"`** on everything below the fold (all card grids);
   `fetchpriority="high"` on the product hero and the first banner slide.
4. **Backfill** the existing 16 MB with a one-off script.

## C6. Compression and cache headers

`.htaccess` has no `mod_deflate` and no `mod_expires`, so HTML and CSS ship
uncompressed and every upload and font re-downloads on every visit.

```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/javascript \
      application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png  "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType font/woff2 "access plus 1 year"
  ExpiresByType text/css   "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

Both modules are standard on Hostinger's LiteSpeed. Verify after deploying —
`curl -I` and look for `content-encoding: gzip` and `cache-control`.

## C7. Content and off-page

Technical SEO gets you eligible to rank. It doesn't get you ranked. For a
brand-new domain with no backlinks, these matter more than anything above:

- **Google Business Profile** for teepsaa itself, and encourage approved
  vendors to claim their own — Cambodian local search leans on Maps heavily.
- **Vendor stories** — a short interview per shop is genuinely useful
  content, is naturally keyword-rich, gives vendors something to share (a
  backlink and a social post each), and makes the marketplace feel inhabited.
- **Khmer keyword research.** Cambodian shoppers search in a mix of Khmer
  script, romanised Khmer and English. That's three ways to spell the same
  intent and your competitors probably only cover one. There's very little
  competition for well-targeted Khmer-script product terms.
- **Local directories and Facebook groups** — the realistic first backlinks
  for a Cambodian marketplace, more than any outreach campaign.

---

## Order of work

| # | Item | Effort | Impact | When |
|---|------|--------|--------|------|
| A2 | `og-default.png` doesn't exist | 5 min | High | Pre-launch |
| A3 | No favicon | 5 min | Medium | Pre-launch |
| A4 | Khmer body / English title / `lang="en"` | 30 min | High | Pre-launch |
| A5 | Soft 404s + `ErrorDocument` | 20 min | Medium | Pre-launch |
| A6 | Static pages have no meta | 20 min | Medium | Pre-launch |
| A7 | No `<h1>` on home or search | 15 min | High | Pre-launch |
| A8 | `alt=""` on every product image | 1 hr | High | Pre-launch |
| A9 | Shared robots.txt across subdomains; 302s | 30 min | Medium | Pre-launch |
| A10 | robots.txt gaps | 10 min | Low | Pre-launch |
| A11 | `noindex` on filtered search | 20 min | Medium | Pre-launch |
| A12 | www → apex 301 | 5 min | Low | Pre-launch |
| A13 | Sitemap: static pages, `updated_at`, `.xml`, images | 30 min | Medium | Pre-launch |
| A14 | Search Console, GA4, Merchant Center | 30 min | High | Launch day |
| B1 | `Product` schema | 2 hr | **Highest** | Pre-launch if possible |
| B2-B5 | Organization, Store, Breadcrumb, FAQ schema | 3 hr | High | Week 1-2 |
| C3 | Category landing pages | 1-2 days | High | Month 1 |
| C5 | Image derivatives + CWV | 1-2 days | High | Month 1 |
| C6 | Compression + cache headers | 20 min | Medium | Month 1 |
| C1 | Per-language URLs + hreflang | ~1 week | **Highest** | Month 2 |
| C2 | Slug URLs | 1 day | Medium | Month 2 |
| C4 | Crawlable pagination | half day | Medium | Month 2 |
| C7 | Content, GBP, backlinks | ongoing | High | Ongoing |

Everything in Part A totals roughly **four hours**.

---

## The short version

- **Three things are broken, not just missing.** `images/og-default.png` is
  referenced by every page and doesn't exist, so every social share shows a
  broken preview. There's no favicon anywhere on the public site. And missing
  products 302 to `/search/` instead of returning 404, which keeps dead URLs
  in Google's index.

- **Google currently sees a confused page.** Anonymous visitors — including
  Googlebot — default to Khmer, but every `<title>` and meta description is
  English and every page declares `lang="en"`. Make the three agree, and
  decide on purpose which language is the default.

- **There is zero structured data.** No `ld+json` anywhere. Adding `Product`
  schema is the biggest single win available: it puts star ratings, price and
  stock status directly in search results, and every field it needs is
  already in your database and already computed on the page.

- **Your homepage has no `<h1>` and your product photos have no `alt` text.**
  Two of the oldest, simplest signals there are, and both are free to fix.

- **Part A is about four hours of work** and all of it is pre-launch, because
  redoing it after Google has crawled the site costs more than doing it now.

- **The two big post-launch projects** are per-language URLs (doubles your
  indexable pages — right now only one language can ever rank) and category
  landing pages (the pages that actually rank for head terms; today the
  category tiles just run a text search).
