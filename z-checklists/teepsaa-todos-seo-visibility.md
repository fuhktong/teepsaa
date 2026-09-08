# teepsaa — Getting Found on Google (SEO)

Written 2026-09-05. SEO was missing from `teepsaa-todos-launch-readiness.md`;
this is that missing piece, written to be followed without prior SEO
knowledge.

Companion files: `teepsaa-todos-launch-readiness.md` (do that first),
`teepsaa-production-deploy.md`.

---

## What this file is about

When someone in Phnom Penh types "buy silk scarf" into Google, Google shows a
list of websites. **SEO is the work of making teepsaa one of the websites on
that list** — ideally near the top.

Google does this by sending an automated program (people call it a "robot",
"crawler", "spider", or "Googlebot" — same thing) around the internet. The
robot visits your pages, reads them, and files them away in Google's giant
list of known web pages. Being on that list is called **being indexed**. If
you are not indexed, you cannot appear in search results at all.

The robot is not a person. It cannot look at your site and understand it the
way you do. It only reads the text and the hidden instructions in your page
files. So the whole job comes down to: **make the hidden instructions correct
and clear.**

There are three kinds of work in this file:

| Part       | What it is                                                               | When                      |
| ---------- | ------------------------------------------------------------------------ | ------------------------- |
| **Part 1** | Fixing things that are currently wrong or missing                        | Before launch             |
| **Part 2** | Adding "product info cards" so Google shows your prices and star ratings | Before launch if possible |
| **Part 3** | Bigger improvements                                                      | After launch              |

**Part 1 is about four hours of work in total.** Do it before the site goes
public. Once Google has looked at your site and written down what it found,
changing its mind takes weeks. It is much cheaper to be right the first time.

---

## Words you'll see, in plain English

Read this once. Everything below uses these terms.

**Indexed** — Google knows your page exists and can show it in results. Not
indexed means invisible.

**Title** — the blue clickable line in a Google result. It comes from a
hidden line in your page file, not from anything visible on the page.

**Description** — the grey line of text under the blue link in a Google
result. Also from a hidden line in your page file.

**Preview card** — when someone pastes a teepsaa link into Facebook,
Telegram or Messenger, the app shows a box with a picture, a title and a
sentence. That box is built from hidden lines in your page file. If those
lines are wrong you get an ugly bare link instead, and people click it far
less.

**Sitemap** — a list of every page on your site, written in a format built
for robots, not people. You hand it to Google and say "here's everything I
have." teepsaa already has one: `sitemap.php`.

**robots.txt** — a plain text file at `teepsaa.com/robots.txt` that tells the
robot which parts of the site to stay out of (the checkout, the admin panel,
and so on). teepsaa already has one.

**Heading** — the big title text at the top of a page. In the page files it
is written as `<h1>`. Google treats it as the strongest clue about what the
page is about. Each page should have exactly one.

**Alt text** — a short written description attached to a picture, meant for
people who can't see it and for search engines. Without it, Google has no
idea what your product photos show.

**Structured data** — extra hidden information that spells out facts in a
format Google reads directly: "this is a product", "it costs $12.50", "it
has 4.5 stars from 8 reviews". Google uses it to show prices and star
ratings right in the search results. Explained fully in Part 2.

**301 / 302 / 404 / 410** — numeric codes a web page sends back invisibly
alongside its content, telling the browser and the robot what kind of
response this is:

- **200** = here's the page, all fine (the normal one)
- **301** = this page has permanently moved, here's the new address
- **302** = this page has temporarily moved
- **404** = there's nothing at this address
- **410** = there used to be something here and it's been deleted for good

These matter because the robot behaves completely differently for each one.

---

# Part 1 — Fix what's broken

## 1a. The password gate

- [x] **Nothing in this file works until the site is public.** ~~The site is
      currently behind a password.~~ **Done 2026-09-07** — the "Pre-launch
      gate" block in `.htaccess` is commented out and the site answers
      publicly. Verified live: `curl -I https://teepsaa.com/` returns
      `HTTP/2 200`, and Googlebot, TelegramBot and WhatsApp user agents all
      get a normal page rather than the old `401`.

      Still worth doing when convenient: delete `.htpasswd` from the server.
      It is inert now, but there is no reason to keep a password file in the
      web root, and the deploy script uses no `--delete`, so nothing will
      remove it for you.

- [ ] **On the day the password comes off, tell Google immediately.** See
      item 1n below for the sign-up steps. Getting a brand-new website into
      Google takes weeks, so start that clock the same hour you go live,
      not a month later.

## 1b. The missing share picture — 5 minutes, do this one first

- [x] **Every teepsaa link shared on Facebook or Telegram shows a broken
      picture.** This is the best five minutes of work in the whole file.

      **What's wrong:** open `config/seo.php`. Around line 16 there's a line
      that reads:

      ```php
      $image = $base . '/images/og-default.png';
      ```

      That says: when a page has no picture of its own, use the file
      `images/og-default.png` as the preview picture. **That file does not
      exist.** The `images/` folder has your logos and app icons, but nothing
      named `og-default.png`.

      **Why it matters:** it affects the homepage, the search page, the
      wishlist, and any product a vendor uploaded without a photo. In
      Cambodia most of your traffic will arrive from links pasted into
      Facebook, Messenger and Telegram groups. A link with a proper picture
      gets clicked several times more often than a bare blue link.

      **How to fix it:**
      1. Make an image exactly **1200 pixels wide by 630 pixels tall**. The
         teepsaa logo centred on a plain brand-coloured background is fine —
         this is a signpost, not artwork.
      2. Save it as a PNG under 300 KB.
      3. Name it exactly `og-default.png` (all lowercase) and put it in the
         `images/` folder.
      4. After the site is live, paste `https://teepsaa.com` into Facebook's
         "Sharing Debugger" (search that phrase) and confirm the picture
         shows up.

## 1c. No small logo in the browser tab — 5 minutes

- [x] **teepsaa has no favicon on the public site.** A favicon is the tiny
      logo in a browser tab, next to a bookmark, and — this is the part that
      matters — next to your listing in Google's mobile results.

      **What's wrong:** the icon files already exist
      (`images/teepsaa-icon-180.png`, `-192.png`, `-512.png`), but no public
      page ever points at them. Only one admin page does.

      **How to fix it:** open each of these files, find the block near the
      top that has lines starting with `<link rel="stylesheet"`, and add
      these two lines just above them:

      ```html
      <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
      <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
      ```

      Files to add it to: `index.php`, `product/index.php`,
      `business/index.php`, `search/index.php`, `about/index.php`,
      `help/index.php`, `privacy/index.php`, `terms/index.php`,
      `contact/index.php`, `careers/index.php`, `returns/index.php`,
      `shipping/index.php`, `wishlist/index.php`.

      It's the same two lines every time. (Every page repeating the same
      head lines is exactly the kind of thing that becomes one shared file
      later — see item 3g — but copy-paste is fine for now.)

## 1d. Google is seeing Khmer text labelled as English — 30 minutes

This is the most confusing problem on the site and worth reading slowly.

- [x] **Right now the robot reads a page whose visible words are Khmer,
      whose hidden title is English, and which announces itself as an
      English page.**

      **What's wrong:** three separate things disagree.

      1. **The visible text comes out Khmer.** In `config/db.php` around
         line 54 there's a function called `lang_field`, and inside it:

         ```php
         ($_SESSION['lang'] ?? 'km') === 'km'
         ```

         In plain terms: "if this visitor has picked a language, use it;
         **if they haven't picked one, use Khmer**." Google's robot has
         never clicked your language switcher, so it always falls into the
         "hasn't picked one" case and gets Khmer. Same default sits in
         `header/header.php` around line 3.

      2. **The hidden title comes out English.** In `product/index.php`
         around line 64:

         ```php
         <title><?= htmlspecialchars($product['name']) ?> — teepsaa</title>
         ```

         `$product['name']` is the plain English name column straight from
         the database — no language check at all. Around line 111 the
         description is fed in the same English-only way. Meanwhile the
         visible heading on the same page (line 187) uses `lang_field`, so
         it comes out **Khmer**.

      3. **The page claims to be English.** Every public page starts with:

         ```html
         <html lang="en">
         ```

         `lang="en"` is a statement to the robot: "everything below is in
         English." It's hard-coded, so it says English even when every
         visible word is Khmer.

      **Why it matters:** Google has to decide what language your page is
      in, so it can decide which searches to show it for. Given a
      contradiction it trusts the visible words. So it files your pages as
      Khmer — while the title and description you carefully wrote in English
      describe a page Google has decided isn't English. Google may then
      ignore your description and write its own from the page text.

      **How to fix it — three steps:**

      1. **Make the page tell the truth about its language.** In every
         public page file, replace:

         ```html
         <html lang="en">
         ```

         with:

         ```php
         <html lang="<?= ($_SESSION['lang'] ?? 'km') === 'km' ? 'km' : 'en' ?>">
         ```

         That reads: "if the visitor's language is Khmer, say km, otherwise
         say en." Now the label always matches the words. (If you change the
         default in step 3, change `?? 'km'` here to match.)

      2. **Make the title and description use the same language as the
         page.** In `product/index.php`, change:

         ```php
         <title><?= htmlspecialchars($product['name']) ?> — teepsaa</title>
         ```

         to:

         ```php
         <title><?= htmlspecialchars(lang_field($product, 'name')) ?> — teepsaa</title>
         ```

         and a few lines below, inside the `seo_meta(` call, change
         `$product['name']` to `lang_field($product, 'name')` and
         `$product['description']` to `lang_field($product, 'description')`.

         Do the same in `business/index.php` — its `seo_meta(` call uses
         `$business['name']` and should use
         `lang_field($business, 'name')`.

      3. **Decide, on purpose, which language a stranger gets.** Right now
         it's Khmer because of a default nobody chose deliberately. Google
         only ever sees the default, so that decision decides which language
         teepsaa ranks in.

         - **Khmer** matches your audience and almost nobody competes for
           Khmer-script product terms — but far fewer people type Khmer
           script into Google than speak Khmer.
         - **English** has more search traffic in Cambodia (lots of people
           search in English even when they speak Khmer) but more
           competition.

         There's a real answer that gets you both, and it's item 3a in Part
         3 — it's a week of work, so not now. For launch, just pick one
         knowingly. If you pick English, change `'km'` to `'en'` in both
         `config/db.php` and `header/header.php`.

      **Status: done — all three steps.** All 49 public pages declare
      their real language, and the product page, shop page and all eight
      info pages take their title and description from the same language
      the body renders in.

      Step 3 turned out not to need a sacrifice. **Khmer is the deliberate
      default** — a stranger, Googlebot included, gets Khmer — but 3a below
      was built in a cheap form at the same time, so English is no longer
      shut out of Google. Adding `?lang=en` to any address renders that
      page in English, and Google is told about both. The either/or in the
      two bullets above no longer applies: you get the uncontested Khmer
      terms *and* the English search traffic.

      The default now lives in one place, `DEFAULT_LANG` in
      `config/i18n.php`. Changing it there changes every page, every
      canonical address and the sitemap together — the `?? 'km'` defaults
      scattered across 49 files are gone.

## 1e. Deleted products send visitors to the search page — 20 minutes

- [x] **When a product is removed, its old web address quietly forwards to
      the search page instead of saying "this is gone".**

      **What's wrong:** in `product/index.php` around line 28:

      ```php
      if (!$product) {
          header('Location: /search/');
          exit;
      }
      ```

      In plain terms: "if there's no such product, send the visitor to the
      search page." The same thing happens in `business/index.php` twice.

      **Why it matters:** a forward is not the same as "this is gone".
      Google keeps the dead address in its list, keeps coming back to check
      it, and starts to suspect your search page is a duplicate of your
      product pages — because dozens of different product addresses all lead
      there. Meanwhile a real person who clicked a link to a specific
      scarf lands on a generic search page with no explanation, which reads
      as a broken site.

      **How to fix it:** send the correct code, and show a real page.
      Replace the block above with something like:

      ```php
      if (!$product) {
          http_response_code(404);
          require __DIR__ . '/../404.php';
          exit;
      }
      ```

      If you can tell the difference between "never existed" and "existed
      and was archived", use `410` for the archived case — that's the code
      meaning "deleted on purpose", and Google drops those from its list
      faster than a plain 404.

      Do the same for both spots in `business/index.php`.

## 1f. There's no "page not found" page at all — 20 minutes

- [x] **A mistyped teepsaa address shows Apache's grey default error page.**
      No teepsaa header, no menu, no way back. It looks like the site is
      broken rather than like a typo.

      **How to fix it:**
      1. Create `404.php` in the project root. Copy the structure of a
         simple existing page like `about/index.php`: the session block at
         the top, the `<head>`, then the header include, then a short
         message ("We couldn't find that page") and links to the homepage
         and to search, then the footer include.
      2. Open `.htaccess` in the project root and add this line near the
         top, after `RewriteEngine On`:

         ```apache
         ErrorDocument 404 /404.php
         ```

## 1g. Your info pages have no Google description — 20 minutes

- [x] **`about`, `help`, `privacy`, `terms`, `contact`, `careers`,
      `returns` and `shipping` have a title but no description and no
      preview-card information.**

      **What's wrong:** those eight pages never call `seo_meta()` — the
      helper in `config/seo.php` that writes the description line, the
      preview-card lines and the "this is the official address of this page"
      line. Only the homepage, product, business, search and wishlist pages
      call it.

      **Why it matters:** without a description, Google grabs a random
      sentence from the page. `/about/` and `/help/` are pages people reach
      by searching your brand name, and pages people share links to. Worth
      controlling what they say.

      **How to fix it:** in each of the eight files, find the `<title>` line
      in the `<head>` and add this straight after it, changing the two bits
      of text each time:

      ```php
      <?php
          require_once __DIR__ . '/../config/seo.php';
          echo seo_meta(
              'About — teepsaa',
              'teepsaa connects Phnom Penh shoppers with local businesses. Browse thousands of products from verified Cambodian sellers, delivered across the city.',
              '',
              'https://teepsaa.com/about/'
          );
      ?>
      ```

      The four things you're passing in are: the title, the description (aim
      for 140–155 characters — Google cuts it off past about 160), an empty
      slot for a picture (leave it empty, it falls back to the one you made
      in item 1b), and the page's own address.

      Note `privacy`, `terms`, `returns` and `shipping` pull their body text
      from the database (the `content_pages` table), so their descriptions
      can be plain fixed sentences — they don't need to match the body word
      for word.

## 1h. The homepage has no main heading — 15 minutes

- [x] **Neither the homepage nor the search page has an `<h1>`.**

      **What's wrong:** `index.php` goes straight from the banner carousel
      to `<h2>` section headings ("Shop by category", "Featured products").
      `search/index.php` has no `<h1>` anywhere.

      **Why it matters:** the `<h1>` is the single strongest on-page clue
      about what a page is about. Your homepage is the page that most needs
      to rank, and it currently tells Google nothing at the top level.

      **How to fix it:**
      1. Add a line to both `lang/en.php` and `lang/km.php`, near the
         homepage section entries. In `lang/en.php`:

         ```php
         'home_h1' => 'Shop local Phnom Penh businesses — delivered',
         ```

         and the Khmer equivalent in `lang/km.php`.

      2. In `index.php`, find this line:

         ```php
         <?php require __DIR__ . '/includes/banner-carousel.php'; ?>
         ```

         and add just above it:

         ```php
         <h1 class="home-h1"><?= $t['home_h1'] ?></h1>
         ```

      3. If it doesn't suit the design, style it small and quiet in the
         page's CSS. If you truly want it invisible, use a "screen reader
         only" CSS class (a tiny `clip-path` trick — search "sr-only css").
         **Do not use `display: none`** — Google knows that trick and
         discounts text hidden that way.

      4. On `search/index.php`, add a heading that uses the search term.
         The page already builds a `$title` variable around line 206; do
         something similar for a visible `<h1>` — "Results for *scarf*", or
         "All products" when there's no search term.

## 1i. Your product photos have no description text — about 1 hour

- [x] **Every product image on the site has an empty `alt`.** There are 42
      of them.

      **What's wrong:** each image is written like this:

      ```php
      <img src="/uploads/<?= $p['photo'] ?>" alt="" class="card-photo">
      ```

      The `alt=""` is an empty description. On a shopping site the photo
      *is* the product, so an empty description throws away everything
      Google could learn from it.

      **Why it matters:** Google Images is a genuine way people find
      products to buy — someone searches "krama scarf", browses pictures,
      clicks one, lands on your product page. With empty `alt` text, none of
      your 16 MB of product photos can ever appear there. It also matters
      for blind visitors using screen readers.

      **How to fix it:** replace `alt=""` with the product's name. In files
      where a product row is available as `$p`:

      ```php
      alt="<?= htmlspecialchars(lang_field($p, 'name')) ?>"
      ```

      The places that matter:

      | File | Around line | What the picture is |
      |---|---|---|
      | `index.php` | 14 | product card photo |
      | `index.php` | 347 | category tile |
      | `index.php` | 506 | product card built by JavaScript |
      | `product/index.php` | 170, 174 | main photo and thumbnails |
      | `business/index.php` | 111, 171, 204 | shop banner, featured item, grid |
      | `search/index.php` | 423, 441 | shop banner, product card |

      For the shop banner use the shop name; for a category tile use the
      category name.

      **Leave these ones empty on purpose:** `product/index.php` line 307
      (the lightbox image, which JavaScript fills in) and the avatar
      pictures. An empty `alt` is the correct answer for decoration — it
      tells a screen reader "skip this, it carries no information." The
      problem is only using it on pictures that *do* carry information.

## 1j. All three websites share one robots.txt — 30 minutes

- [x] **`vendor.teepsaa.com` and `admin.teepsaa.com` are handing Google the
      buyer site's rules.**

      **What's wrong:** `config/subdomain.php` line 16 turns on the
      three-address layout: `teepsaa.com` for buyers, `vendor.teepsaa.com`
      for sellers, `admin.teepsaa.com` for you. All three point at the
      **same folder on the server**. `robots.txt` is a single ordinary file
      in that folder — so all three addresses serve the identical file, the
      one written for the buyer site.

      The redirects in `subdomain.php` do stop the robot reaching the actual
      admin pages, so this is a tidiness problem rather than a leak. But it
      leaves both extra addresses looking open, and they should be shut.

      Those redirects also use code **302** ("temporarily moved") where they
      mean **301** ("permanently moved"). 302 tells Google to keep the old
      address in its list; 301 tells it to transfer everything to the new
      one and forget the old.

      **How to fix it:**
      1. Rename `robots.txt` to `robots.php`.
      2. At the very top of the new `robots.php`, add:

         ```php
         <?php
         header('Content-Type: text/plain; charset=utf-8');
         $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
         if ($host !== 'teepsaa.com' && $host !== 'www.teepsaa.com') {
             echo "User-agent: *\nDisallow: /\n";
             exit;
         }
         ?>
         ```

         That reads: "if this request came to any address other than the
         main site, reply 'robots keep out of everything' and stop." The
         existing rules stay below it for the main site.

      3. In `.htaccess`, add this so the file still answers at the address
         Google looks for:

         ```apache
         RewriteRule ^robots\.txt$ /robots.php [L]
         ```

      4. In `config/subdomain.php`, find the `$sdGo` function (around line
         58) and change `302` to `301`.

## 1k. More pages that should be kept out — 10 minutes

- [x] **Add these lines to `robots.txt`.** They're pages that are either
      per-person, one-time-use, or duplicates — none should be in search
      results.

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

      **Do not add `/uploads/`.** That's where the product photos live, and
      you want those in Google Images (see item 1i).

## 1l. Filtered searches shouldn't be in Google — 20 minutes

- [x] **Every filter combination on the search page is a separate web
      address, and there are effectively infinite combinations.**

      **What's wrong:** `search/index.php` accepts a search term, a sort
      order, a minimum price, a maximum price, a category, a minimum rating,
      and any set of variant options — all as part of the address. Every
      combination produces a page that mostly duplicates the others. Left
      alone, Google's robot can spend all its time wandering that maze
      instead of looking at your actual products.

      You're already half protected: `seo_meta()` strips everything after
      the `?` when it writes the "official address of this page" line, so
      all those variants point back at the plain `/search/` page. Make it
      explicit.

      **How to fix it:** in `search/index.php`, inside the `<head>`, add:

      ```php
      <?php if ($q !== '' || $hasActiveFilters): ?>
      <meta name="robots" content="noindex,follow">
      <?php endif; ?>
      ```

      `$hasActiveFilters` already exists in that file around line 31, so
      there's nothing new to work out. `noindex,follow` means "don't list
      this page in results, but do follow the links on it to find products."

      **Important:** do this with `noindex` and **not** with a `Disallow`
      line in `robots.txt`. Those two feel like the same thing but aren't.
      `Disallow` means "don't look at this page" — so Google never sees the
      `noindex` instruction inside it, and may leave the address listed
      anyway with no description. Blocking a page is not the same as
      removing it.

## 1m. The www version of the site — 5 minutes

- [x] **`www.teepsaa.com` and `teepsaa.com` both serve the whole site.**

      **What's wrong:** `.htaccess` has a rule forcing https, but it keeps
      whatever address the visitor typed:

      ```apache
      RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [L,R=301]
      ```

      `%{HTTP_HOST}` means "the address they typed", so someone arriving at
      `www.teepsaa.com` stays on www — while every page tells Google its
      official address is `https://teepsaa.com` with no www. Google will
      probably work it out, but there's no reason to make it guess.

      **How to fix it:** add these two lines to `.htaccess` just above the
      https rule:

      ```apache
      RewriteCond %{HTTP_HOST} ^www\.teepsaa\.com$ [NC]
      RewriteRule ^(.*)$ https://teepsaa.com/$1 [L,R=301]
      ```

      That permanently sends every www visitor to the plain address.

## 1n. Improve the sitemap — 30 minutes

- [x] **Add the five missing pages.** `sitemap.php` lists the homepage,
      search, help, privacy and terms — but not `/about/`, `/contact/`,
      `/shipping/`, `/returns/` or `/careers/`. Copy one of the existing
      blocks in that file and change the address.

- [ ] **Make the "last changed" dates truthful.** The sitemap tells Google
      when each page last changed, which is how Google decides whether to
      come back and re-read it. Right now it sends `created_at` — the date
      the product was first added. So a vendor can rewrite a listing
      completely and Google is told nothing changed.

      Neither the `products` nor the `businesses` table has a
      last-updated column at all (I checked every file in `database/`). Add
      one to each:

      ```sql
      ALTER TABLE products   ADD updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
      ALTER TABLE businesses ADD updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
      ```

      MySQL fills these in and keeps them current by itself — no PHP changes
      needed. Then in `sitemap.php`, change both `created_at` mentions to
      `updated_at`.

      **Remember the `database/` folder is excluded from deploys** — run
      these two commands by hand on the server in phpMyAdmin.

      **Status: the code half is done, the database half is not.** The SQL is
      written for you in `database/migration-seo-updated-at.sql` (it also
      backfills existing rows to their `created_at`, so nothing suddenly
      claims to have changed today). `sitemap.php` now checks whether the
      column exists and uses it if so, falling back to `created_at` if not —
      so the sitemap keeps working either way, and starts telling the truth
      the moment you paste that file into phpMyAdmin. **That paste is the one
      thing still to do here.**

- [x] **Make `/sitemap.xml` work.** Every SEO tool and a good few crawlers
      look for `teepsaa.com/sitemap.xml` first. Yours is at `/sitemap.php`.
      Add to `.htaccess`:

      ```apache
      RewriteRule ^sitemap\.xml$ /sitemap.php [L]
      ```

      Then update the last line of `robots.txt` to point at
      `https://teepsaa.com/sitemap.xml`.

- [x] **List the product photos in the sitemap.** This is the cheapest way
      to get pictures into Google Images. In `sitemap.php`, change the
      opening `<urlset ...>` tag to:

      ```xml
      <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
              xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
      ```

      and inside each product's block, add:

      ```xml
      <image:image><image:loc>https://teepsaa.com/uploads/FILENAME.jpg</image:loc></image:image>
      ```

      You'll need to pull the primary photo filename into the sitemap's
      product query — it isn't fetched there today.

## 1o. Sign up for the free Google tools — 30 minutes, launch day

- [ ] **Google Search Console.** This is Google's free dashboard telling you
      which of your pages it has found, which searches people used to reach
      you, and any problems it hit. Without it you are guessing.

      1. Go to `search.google.com/search-console`.
      2. Choose the **Domain** option (not "URL prefix"). Domain covers
         `teepsaa.com`, both subdomains, and http and https all at once.
      3. It gives you a TXT record to add to your DNS settings in
         Hostinger's hPanel. Add it, come back, click verify.
      4. Once verified, go to Sitemaps and submit
         `https://teepsaa.com/sitemap.xml`.

- [ ] **Bing Webmaster Tools.** Go to `bing.com/webmasters` and use the
      "import from Google Search Console" button. Two minutes, and it covers
      Bing plus a share of the AI assistants that use Bing's index.

- [ ] **Google Analytics (GA4).** Shows you how many people visit, where
      they came from, and what they do.

      **The code half is done (2026-09-07).** Create the GA4 property, then
      put the measurement ID — the `G-XXXXXXXXXX` string — into
      `GA_MEASUREMENT_ID` at the top of `config/seo.php`. That one line is
      the whole job; the tag is emitted from `head/head.php`, which is the
      `<head>` of all 51 pages.

      Two things it does for you. It emits **nothing at all** while the
      constant is empty, so the site ships clean until you're ready. And it
      fires **only on `teepsaa.com`** — vendors working in their dashboard
      are not shoppers, and counting them would corrupt every conversion
      figure you later look at.

      (The original note here said to paste into `footer/footer.php`. That
      predated `head/head.php` existing; the head is the better home and is
      where it now lives.)

- [ ] **Google Merchant Center.** This is the one people forget. It puts
      your products in Google's Shopping tab **for free**. For a marketplace
      that's a direct line to buyers already trying to buy. It wants a
      product feed, which is largely the same information as the product
      info cards in Part 2 — so do Part 2 first, then come back to this.

---

# Part 2 — Product info cards (structured data)

**This is the single most valuable thing in this file.** All of it now
exists — see the status notes under each item. `config/schema.php` builds the
blocks, and `product/`, `business/`, `help/` and the homepage emit them.

## What it is

Compare two results for the same product:

```
Silk Krama Scarf — teepsaa
teepsaa.com › product
Handwoven silk krama from a family workshop in Takeo...
```

```
Silk Krama Scarf — teepsaa
★★★★☆ Rating: 4.6 · 12 reviews · $18.00 · In stock
teepsaa.com › product
Handwoven silk krama from a family workshop in Takeo...
```

The second one gets clicked far more, and it costs nothing but the code
below. That extra line appears because the page carries a hidden block of
information saying, in a format Google reads directly: _this is a product, it
costs $18.00, it's in stock, it has 4.6 stars from 12 reviews._

That hidden block is called **structured data**. It's written in a format
called JSON-LD, and it sits in the page invisibly — no visitor ever sees it.

The good news: **every number it needs is already sitting in your database
and already worked out on the page.** This is packaging, not new work.

## How to build it

Make a new file `config/schema.php`, sitting next to `config/seo.php` and
working the same way: functions that build a block of hidden information,
which each page calls from its `<head>`.

**That file now exists.** Each page calls `schema_graph(...)` from its
`<head>`, which emits one combined block rather than several tags. Two things
in it are worth knowing about:

- `schema_json()` escapes `</` before printing. Without that, a product
  description containing the literal text `</script>` would close the tag
  early and spill the rest of the block onto the page as visible text.
- `schema_clean()` drops empty values, so a shop with no description or no
  map pin simply omits those properties instead of claiming a blank one.

- [x] **2a. Product cards on product pages** — the one that pays.

      `product/index.php` already calculates `$avgRating` and `$reviewCount`
      around line 85, and already has the price, stock and shop name. Add
      this to that page's `<head>`:

      ```php
      <?php
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
      ?>
      ```

      `sale_price_for()` already exists in `config/currency.php` (line 24)
      and handles the sale-percentage logic, so the price comes out right on
      sale items for free.

      **The `if ($reviewCount > 0)` line is not optional — do not remove
      it.** Telling Google a product has a star rating based on zero reviews
      is a rule violation, and at launch most of your catalogue will have
      zero reviews. Google's penalty is not limited to that one page: it can
      switch off star ratings across your whole site. Only claim a rating
      when a rating exists.

      One more thing to watch: `priceCurrency` must match the currency the
      page is actually showing. `config/currency.php` supports USD and KHR,
      so if the visitor is seeing riel, the hidden block must say KHR too.

      **Status: done**, as `schema_product()` in `config/schema.php`, called
      from `product/index.php`. It follows the sketch above with four
      corrections found while building it:

      1. **The zero-review guard is enforced in one place, not per page.**
         `schema_rating()` returns nothing below one review and
         `schema_clean()` then removes the key. No caller can forget it —
         which matters, because the penalty for getting this wrong reaches
         the whole domain, not just the offending page.
      2. **Variants make a price range, not a price.** A product whose
         variants override the price shows a range on the page, so a single
         figure would contradict what the visitor sees — the mismatch Google
         flags. It emits `AggregateOffer` with `lowPrice`/`highPrice`
         instead, and falls back to a plain `Offer` when every variant costs
         the same.
      3. **Stock follows the variants when there are any**, matching
         `$outOfStock` in the page body — with variants, the product row's
         own stock column is unused.
      4. **`sale_price_for()` is only applied when the sale is live.**
         Calling it unconditionally, as sketched above, reads a
         `sale_percent` that may not be set. `active_sale()` decides, and on
         a live sale `priceValidUntil` is set to the end date so Google
         stops advertising the discount when it expires rather than at its
         next crawl.

      The address in `offers.url` carries `?lang=en` on English pages, so it
      always names the same address as the canonical tag above it (item 3a).

- [x] **2b. A company card and a search box on the homepage.** Two blocks
      in `index.php`. The first says "this website is called teepsaa, here's
      its logo, here are its social media pages" (use the `Organization`
      type with `name`, `url`, `logo` and a `sameAs` list of your Facebook,
      Telegram and Instagram addresses). That's what fills the panel Google
      sometimes shows on the right for a known brand.

      The second (`WebSite` type with a `SearchAction`) tells Google how
      your search page works, so that when someone searches "teepsaa",
      Google can show a **search box for your site inside its own results**.
      Search "schema.org WebSite SearchAction" for the exact shape; your
      search address is `https://teepsaa.com/search/?q={search_term_string}`.

      **Status: done** — `schema_organization()` and `schema_website()`,
      emitted together from `index.php` only. Homepage-only on purpose:
      repeating them on every page tells Google nothing it doesn't know.

      **One thing left for you.** `sameAs` — the list of your social
      profiles — is the `SCHEMA_SOCIAL` constant at the top of
      `config/schema.php`, and it is deliberately **empty**, because the
      footer's three social icons are still `href="#"` placeholders. Fill in
      the real addresses there and in `footer/footer.php` at the same time.
      An empty list is simply omitted, which is correct; a made-up or `#`
      address would be worse than none.

- [x] **2c. Shop cards on business pages.** Same idea, using the `Store`
      type: the shop's name, photo, address (you already collect city, khan
      and sangkat) and map coordinates. Add the star rating too — with the
      **same zero-reviews guard as 2a**. This is what makes shop pages
      eligible to appear in local "near me" style results.

      **Status: done** — `schema_store()`, called from `business/index.php`.
      Street is `house_number` + `address`, locality is the sangkat (falling
      back to the city when the vendor left it blank), region is the khan,
      country is KH, and `geo` carries the map pin — skipped when the
      coordinates are 0,0 rather than dropping a shop into the Gulf of
      Guinea. Same shared rating guard as 2a.

- [x] **2d. Breadcrumb trails.** A breadcrumb is the little
      `Home › Bags › Silk Krama Scarf` trail near the top of a page.
      teepsaa has none — not visually, not in the code.

      Adding them does three good things at once: it gives visitors an easy
      way back up, it creates links that help Google move around your site,
      and with the matching `BreadcrumbList` hidden block, Google shows the
      trail in search results **instead of the raw web address**. Given your
      addresses look like `teepsaa.com/product/?id=8f14e45f-ab3c-...`,
      replacing that with `teepsaa.com › Bags › Silk Krama Scarf` is worth
      real clicks by itself.

      **Status: done, visible and hidden.** New `/breadcrumb/` folder
      (`breadcrumb.php` + `breadcrumb.css`, the same shape as `/header/` and
      `/footer/`), on the product, shop and help pages.

      A page sets one `$crumbs` array and both consumers read it — the
      visible trail and `schema_breadcrumb()`. That is deliberate: Google
      shows a breadcrumb in a result only when the visible trail and the
      hidden block agree, and two separately-maintained copies would drift.

      On a product page the trail is Home › parent category › category ›
      product, from the `categories.parent_id` hierarchy. The category links
      point at `/search/?category=N`, which is `noindex, follow` under item
      1l — so Google follows them but won't rank them. Item **3c** (real
      category pages) is what upgrades those links; when it lands, only the
      path in `$crumbs` changes.

- [x] **2e. Question-and-answer cards on the help page.** `help/index.php`
      already reads a `faq_items` table with questions and answers in both
      languages, grouped into sections. Wrapping those in an `FAQPage`
      hidden block lets Google show the questions themselves in the results,
      which takes up more room on the screen than a normal listing. This one
      is nearly free — the data is already loaded and looped over.

      **Status: done** — `schema_faq($faqs)` in `help/index.php`, reading
      the same `$faqs` array the body loops over, so it is automatically in
      whichever language the page rendered in. Answers are stripped of HTML;
      a row missing either its question or its answer is skipped rather than
      emitted blank.

      Worth knowing: Google narrowed FAQ rich results in 2023 and now shows
      them mainly for government and health sites. The block is correct and
      costs nothing, but don't expect the questions to appear in results the
      way they would have a few years ago.

- [x] **2f. Test everything.** **Done 2026-09-07, against the live site.**
      Every JSON-LD block was fetched from `https://teepsaa.com` as it is
      actually served and checked for parse errors and for the fields Google
      documents as required for each type:

      | Page | Blocks found | Result |
      | ---- | ------------ | ------ |
      | homepage | Organization, WebSite | pass |
      | product (km) | Product, BreadcrumbList (4 levels) | pass |
      | product (en) | Product, BreadcrumbList (4 levels) | pass |
      | shop | Store, BreadcrumbList | pass |
      | `/help/` | FAQPage (23 questions), BreadcrumbList | pass |
      | category | BreadcrumbList | pass |

      **Zero errors.** The only warnings were `shippingDetails` and
      `hasMerchantReturnPolicy`, both of which have since been filled in —
      see the note after this list.

      Worth still clicking through Google's own **Rich Results Test** once
      (<https://search.google.com/test/rich-results>) as a second opinion —
      it renders the result preview, which a field check cannot.

      **What was also checked locally:**

      - Every block parses as JSON, in Khmer and English, in USD and KHR.
      - All four pages render with no PHP notice or warning.
      - The visible breadcrumb and the hidden one produce identical text.
      - No star rating is ever emitted at zero reviews.
      - Prices match the page: sale price while a sale runs, base price
        after it expires, riel when the visitor is seeing riel, and a
        low/high range when variants disagree.
      - A description containing `</script>` cannot break out of the block.

      **Both were left empty at first, and both are now filled in**
      (2026-09-07). The original objection was that teepsaa could not state
      either one truthfully — no flat shipping rate, no fixed return window.
      That was right about the method and wrong about the facts: the numbers
      existed, they were just in the code rather than on the returns page.

      - **Shipping** — `schema_shipping_details()` in `config/schema.php`.
        There is still no flat rate to declare, so it declares the band
        instead: `config/delivery-calc.php` clamps every fee up to the
        vehicle's `min_fare` and refuses anything past `max_distance`, which
        puts every possible fee between **$0.66 and $8.40**. Both ends are
        computed from `config/delivery.php` at render time, so a rate change
        moves the schema with it and there is no second place to update.
        `shippingLabel` says it is a Grab fee estimated by distance.
      - **Returns** — `schema_return_policy()`, same file. The window is the
        24 hours `orders-buyer/order.php` already enforces
        (`PAYOUT_WINDOW_SECONDS`), so the category is
        `MerchantReturnFiniteReturnWindow` with `merchantReturnDays` derived
        from that constant rather than typed in. `returnFees` is
        `ReturnShippingFees` because the buyer pays for the Grab back.

      Schema.org has no way to say "requested within a day, then reviewed by
      teepsaa" — the closest true statement is the window plus who pays, and
      `merchantReturnLink` points at `/returns/`, which now carries the
      review step in full.

      **The page had to be rewritten for this to be honest.** The seeded
      `/returns/` copy said returns were "handled between the buyer and the
      individual vendor" on a "case-by-case basis", which described a
      marketplace teepsaa stopped being when the in-app refund flow was
      built. Merchant Center compares the schema against the page, so
      shipping a fixed window against that copy would have been worse than
      the warning it replaced. Both content pages and the four Returns &
      Refunds FAQ answers were rewritten to match — see
      `database/update-returns-policy.php`, which still has to be run
      against the live database (the copy lives in `content_pages`, not in
      the repo).

---

# Part 3 — After launch

None of these should hold up the launch. But 3a and 3b get harder the longer
you wait, because they change web addresses, and once other sites and
Facebook posts link to the old addresses those links have to be preserved.

- [x] **3a. Give each language its own web address.** Today one address
      shows either English or Khmer depending on a setting stored against
      the visitor. Google is one visitor with one setting, so **only one
      language can ever be in Google.** All the Khmer translation work in
      `lang/km.php` (81 KB of it) and every `_km` column in the database is
      invisible to search — or, if you switch the default, all your English
      is.

      The fix is to put the language in the address itself:
      `teepsaa.com/en/product/...` and `teepsaa.com/km/product/...`, with
      each page carrying a small hidden line pointing at its twin (that line
      is called `hreflang`, and it's how you tell Google "this is the same
      page in the other language, not a copy trying to game you"). Both go
      in the sitemap. The language switcher swaps the prefix instead of
      saving a setting.

      **This roughly doubles the number of pages you can appear for**, and
      it's the biggest long-term item here. It's also about a week of work
      touching every page and every internal link. Hence: after launch.

      **Status: done early, in the cheap form — about a day, not a week.**
      Built before launch because the week-long version is what's expensive,
      and there's a version that gets the same result from Google's side
      without renaming a single address.

      Instead of `/en/product/...` and `/km/product/...`, the language rides
      in the query: the bare address is Khmer and `?lang=en` is English.
      Nothing that exists today moves, so no old link ever breaks — which is
      exactly the reason this item warned about waiting.

      What's in place:

      - `current_lang()` in `config/i18n.php` — the address wins over the
        session, so a visitor arriving on an English link from Google gets
        English even though they never touched the switcher. `DEFAULT_LANG`
        (Khmer) is the single source of truth.
      - `seo_meta()` in `config/seo.php` emits the `hreflang` pair on every
        indexable page — `km`, `en`, and `x-default` pointing at Khmer —
        both directions, which is the reciprocity Google requires. Noindex
        pages (filtered search) deliberately get no pair.
      - `sitemap.php` lists every page twice, each entry naming its twin.
      - `lang_href()` wraps the links along the public crawl path — nav,
        footer, category tiles, product cards, shop cards, the shop link on
        a product page — so on an English page the crawler's next click
        stays English. Khmer links stay bare, so no address is duplicated.
      - `lang/set.php` strips `?lang=` from the page it returns to, so the
        switcher isn't silently undone by the address it came from.

      What the full `/en/` version would still buy you: a slightly cleaner
      address, and a marginal ranking preference Google has never actually
      confirmed. Not worth a week — revisit only if the English pages are
      earning traffic and something concrete argues for it.

      **One thing this does not fix:** it makes your English *visible*, not
      *good*. If `name_en` and `description_en` in the database are empty,
      or hold the Khmer text copied over, the English pages are thin and
      Google will treat them as such. Worth spot-checking a dozen products
      before expecting anything from this.

- [x] **3b. Put product names in the web address.** Today a product address
      is `teepsaa.com/product/?id=8f14e45f-ab3c-...`. It contains no words,
      can't be read aloud, and looks untrustworthy pasted into a chat next
      to a competitor's tidy link.

      Change it to `teepsaa.com/product/silk-krama-scarf-8f14e45f/` —
      keeping the random ID on the end so nothing about the "addresses can't
      be guessed" decision changes. The words are decoration; the ID still
      does the lookup. Old `?id=` addresses permanently forward to the new
      ones so nothing already shared breaks.

      **Status: done — products *and* shops.**

      `config/slug.php` holds the whole of it. `slugify()` folds a name down
      to an address (accents flatten, apostrophes vanish so "Men's" is
      `mens`, Khmer script is kept as-is because Khmer in an address is a
      feature here, not a problem). `product_path()` and `business_path()`
      glue that to the first 13 characters of the random ID:

          /product/silk-krama-scarf-8f14e45f-ab3c/
          /business/lucky-silk-3c1d9a77-2b4e/

      The lookup still runs on the ID, never the words — change a product's
      name and the old address still finds it, then forwards to the new one.
      A 13-character prefix is short enough to read and long enough that a
      clash is vanishingly unlikely; if one ever happened the page returns
      "not found" rather than guessing, because it only accepts a search
      that matched exactly one row.

      Old `?id=` addresses still work and send a permanent forward to the
      new address, so anything already shared or already in Google moves
      across on its own. Three rewrite rules in `.htaccess` do the routing.

      One thing worth knowing: the words come from the **English** name, so
      the same product has one address in both languages. That's deliberate
      — two addresses for one product is the duplicate-content problem 3a
      exists to avoid.

- [x] **3c. Build real category pages.** Right now the category tiles on
      the homepage link to `/search/?q=Bags` — which runs a **text search**
      for the word "Bags" across product names and descriptions. It doesn't
      even use the category filter the search page already supports. So the
      "Bags" tile misses every bag whose listing doesn't happen to contain
      that word.

      Category pages are the pages that rank for the big general searches —
      "bags Phnom Penh", "ថង់" — because they stay put, they collect links
      over time, and they're about one clear topic in a way a single product
      page isn't. Give each category a proper address (`/category/bags/`), a
      heading, two or three sentences of real intro text, and the correctly
      filtered product grid.

      **This is probably the highest-value content work available to you,
      and it uses only things you already have.**

      **Status: done.** `/category/dresses/`, `/category/bags-purses/`,
      `/category/khmer-traditional/` and 46 more — one for every row in the
      category table. The homepage tiles now point at these instead of at a
      text search.

      Each page has a heading, the intro text, the correctly filtered grid
      (24 per page, with the numbered page links from 3d), links down to its
      subcategories, and a Home › Clothing › Women's › Dresses trail that
      Google can show in a result.

      Two details that mattered:

      - Vendors file products against the **bottom** categories only, so
        "Women's" on its own would have shown an empty grid. A category page
        queries its whole branch — "Women's" shows everything in every
        category beneath it.
      - Seven names appear twice in your tree (Jeans, Shorts, Activewear,
        Sleepwear, Hoodies & Sweatshirts, Jackets & Coats, Trousers &
        Chinos — under both Men's and Women's). Where a name is shared,
        *both* take the parent as a prefix: `mens-jeans` and `womens-jeans`,
        never a bare `jeans` for whichever came first. That way adding
        "Kids' Jeans" later can't silently move an address Google has
        already indexed.

      **The intro text is the part that still needs you** — see the manual
      list. `category/intros.php` has 15 written and 34 blank, and every one
      of them wants a native Khmer speaker's eye before it goes live.

- [x] **3d. Make search results reachable without scrolling.** The search
      page loads the first batch of products, then fetches more as you
      scroll. Google's robot doesn't scroll, and there are no page-2 links
      in the page — so it sees the first batch and stops.

      The sitemap does list every product, so they'll all be *found*. But
      links between your own pages are also how ranking strength spreads
      around a site, and almost none currently reaches products past the
      first batch. Add ordinary numbered page links (`?page=2` and so on)
      rendered by the server. They can sit quietly at the bottom; they just
      have to be real links in the page.

      **Status: done.** `pagination/pagination.php` is a shared partial used
      by both the search page and the new category pages: numbered links,
      first and last always shown, a window around the current page, and
      `…` for the gap. Real `<a href>` links rendered by the server, so the
      crawler walks them.

      Infinite scroll still works for people — the two coexist, and the
      scroll now starts from the right offset so landing on `?page=3` and
      scrolling doesn't re-show products 21–40.

      Asking for a page that doesn't exist (`?page=99`) returns a genuine
      "not found" rather than quietly serving the last page again, which
      Google counts against you as a "soft 404".

- [x] **3e. Shrink the images.** The `uploads/` folder is 16 MB and holds
      PNGs up to **1.7 MB each** — served at full size into product cards
      about 200 pixels wide. `config/upload.php` checks that an upload is
      really an image but never shrinks or re-saves it. Across the whole
      site there are **4** images marked to load lazily and **6** with their
      size declared up front.

      For a homepage with eight product carousels, that's several megabytes
      down a Phnom Penh mobile connection, plus the page jumping around as
      each picture arrives. Google measures both loading speed and page
      jumping, and uses them in ranking — and your audience is almost
      entirely mobile.

      Four things, in order of value for effort:
      1. **Put `width` and `height` on every `<img>` tag.** The browser then
         reserves the right space before the picture arrives, so the page
         stops jumping. Cheapest fix that exists.
      2. **Add `loading="lazy"`** to every picture below the first screenful
         — all the card grids. The browser then doesn't download them until
         the visitor scrolls near them.
      3. **Make smaller copies when a vendor uploads.** In
         `config/upload.php`, save a 400-pixel-wide and a 1200-pixel-wide
         WebP version alongside the original. Serve the small one in card
         grids, the big one on product pages.
      4. **Run a one-off script** to make those smaller copies for the 16 MB
         already uploaded.

      **Status: all four done.**

      1. `width` and `height` are on 28 `<img>` tags across 14 files — every
         public grid, card, thumbnail, banner and avatar.
      2. `loading="lazy"` on everything below the first screenful. The three
         images that are *not* lazy are deliberate: the header logo, the
         main product photo and the shop banner are the first thing a
         visitor sees, and the last two carry `fetchpriority="high"` so the
         browser fetches them first.
      3. `image_make_derivatives()` in `config/upload.php` writes a 400px and
         a 1200px WebP copy on every upload — product photos, gallery
         photos, shop banners and avatars. `image_variant()` picks the right
         one per slot, and falls back to the original if a copy is missing,
         so nothing can break. It never upscales, and it throws a copy away
         if WebP came out *bigger* than the original (which happens with
         flat graphics like QR codes).
      4. `database/backfill-image-derivatives.php` does the existing 16 MB.
         **Run locally, the card images went from 15,946 KB to 794 KB — 95%
         smaller.** It's safe to re-run and picks up where it stopped.

      Deleting a photo now deletes its small copies too, so the folder can't
      silently fill with orphans.

- [x] **3f. Turn on compression and caching.** `.htaccess` doesn't compress
      anything or tell browsers to keep a copy of images and fonts. So every
      visitor re-downloads the same logo and the same font files on every
      single page. Add:

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

      Both are standard on Hostinger. Afterwards check it worked: run
      `curl -I https://teepsaa.com` and look for `content-encoding: gzip` in
      the reply.

      **Status: done, with two deliberate changes from the snippet above.**

      - **Images and fonts are not in the compression list.** Compressing a
        JPEG or a WebP again spends processor time to make the file slightly
        *bigger*; woff2 is already compressed internally.
      - **CSS and JavaScript get one week, not one month.** Your stylesheets
        are at fixed addresses (`/style.css`), so a long cache would strand
        returning visitors on the old design after a deploy. A week is the
        compromise. Shorten it while you're actively working on the CSS.

      HTML deliberately gets no caching rule at all — every page shows the
      cart count and the signed-in name, so a cached copy would show one
      visitor another's.

      **The `curl -I https://teepsaa.com` check is still yours to run** —
      it can only be checked on the live server.

- [x] **3g. Move the repeated head lines into one file.** Items 1c, 1d and
      1g all involve making the same edit in a dozen page files. That's a
      sign the shared part should live in one place — a `head/head.php` that
      each page includes with its own title and description passed in, the
      way `header/header.php` and `footer/footer.php` already work. Do it
      once and the next SEO change is a one-file change.

      **Status: done.** `head/head.php` works exactly like `header/` and
      `footer/`: set a few variables, require it.

          $headTitle = $t['about_title'] . ' — teepsaa';
          $headDesc  = $t['seo_desc_about'];
          $headUrl   = 'https://teepsaa.com/about/';
          $headCss   = ['/about/about.css'];
          require __DIR__ . '/../head/head.php';

      All 45 public and vendor pages now use it. (The admin panel is left
      alone — it's a separate English-only interface that no search engine
      ever sees.)

      This had already drifted, which is the argument for doing it: 87 pages
      carried the font preloads but only 50 carried the favicon lines, so
      which icon a visitor saw in their browser tab depended on which page
      they landed on first. That's now impossible.

- [ ] **3h. The part that isn't code.** Everything above makes teepsaa
      _eligible_ to rank. It doesn't make it rank. For a brand-new address
      with no other websites linking to it, these matter more than any of
      the technical work:

      - **Google Business Profile** for teepsaa itself, and encourage
        approved vendors to claim one for their own shop. Cambodian local
        search leans heavily on Google Maps.
      - **Vendor stories.** A short interview with each shop is genuinely
        interesting to read, naturally full of the words people search for,
        gives that vendor something to share (which is a link back to you
        and a social post, free), and makes the marketplace feel populated
        rather than empty.
      - **Khmer keyword research.** Cambodians search in a mix of Khmer
        script, romanised Khmer and English — three ways to write the same
        thing. Most competitors only cover one. There is very little
        competition for well-chosen Khmer-script product terms.
      - **Local directories and Facebook groups.** For a Cambodian
        marketplace these are the realistic first links from other websites,
        far more than any formal outreach.

---

## Suggested order

| Do this | Item                                        | Time                | Worth       | Done |
| ------- | ------------------------------------------- | ------------------- | ----------- | ---- |
| 1       | 1b — the missing share picture              | 5 min               | High        | ✅   |
| 2       | 1c — favicon                                | 5 min               | Medium      | ✅   |
| 3       | 1m — www redirect                           | 5 min               | Low         | ✅   |
| 4       | 1k — robots.txt additions                   | 10 min              | Low         | ✅   |
| 5       | 1h — homepage heading                       | 15 min              | High        | ✅   |
| 6       | 1e + 1f — deleted products and the 404 page | 40 min              | Medium      | ✅   |
| 7       | 1g — descriptions on info pages             | 20 min              | Medium      | ✅   |
| 8       | 1l — noindex on filtered searches           | 20 min              | Medium      | ✅   |
| 9       | 1d — the language mix-up                    | 30 min              | High        | ✅   |
| 10      | 1j — per-subdomain robots.txt               | 30 min              | Medium      | ✅   |
| 11      | 1n — sitemap improvements                   | 30 min              | Medium      | ✅   |
| 12      | 1i — alt text on product photos             | 1 hr                | High        | ✅   |
| 13      | 2a — product info cards                     | 2 hr                | **Highest** | ✅   |
| 14      | 1o — Search Console and friends             | 30 min              | High        | ⬜   |
| —       | _launch_                                    |                     |             |      |
| 15      | 2b–2e — the rest of the info cards          | 3 hr                | High        | ✅   |
| —       | 2f — Rich Results Test                      | 15 min              | High        | ✅   |
| 16      | 3c — category pages                         | 1–2 days            | High        | ✅   |
| 17      | 3e + 3f — images, compression               | 1–2 days            | High        | ✅   |
| 18      | 3a — one address per language               | ~1 day (cheap form) | **Highest** | ✅   |
| 19      | 3b, 3d, 3g                                  | 2 days              | Medium      | ✅   |
| —       | 3h — content and links                      | ongoing             | High        | ⬜   |

Items 1–14 come to roughly **six hours** and should all be done before the
password comes off.

**Status as of 2026-09-07: the password is off and every code item above is
done and verified against the live site.** What is left is not code —
`1o` (four Google sign-ups, needs your accounts and a DNS record), the
`updated_at` migration in item `1n` (one paste into phpMyAdmin), the image
resizer on the server, and `3h`. Those four are tracked in
`teepsaa-manual-actions.md`; the sign-ups have their own walkthrough in
`teepsaa-launch-day-google-signups.md`.

---

## If you only read one paragraph

Three things on the site are actually broken: the picture that shows when
someone shares a teepsaa link points at a file that doesn't exist, there's no
small logo for browser tabs, and deleted products quietly forward to the
search page instead of saying they're gone. Separately, Google is currently
being shown Khmer text with English titles on pages labelled as English,
which confuses it about what language teepsaa is even in. And the biggest
missed opportunity is that no page carries the hidden product information
that makes Google display your price and star rating in the results — every
number that needs is already in your database. Fix those, spend an hour
writing descriptions on your product photos, sign up for Google Search
Console the day you go live, and you'll have done the large majority of what
matters.


---

# Addendum — done 2026-09-07, beyond the original list

Three things surfaced while verifying the list against the live site. All are
in the codebase now; none needs anything from you.

- **Dead social links removed.** The footer shipped three `href="#"` icons —
  a dead link on every page of the site — while `SCHEMA_SOCIAL` in
  `config/schema.php` sat empty. They were two places that had to agree and
  didn't. The footer now draws its icons from `schema_social_links()`, so
  filling in an address there makes the icon appear, and leaving it empty
  renders no icon at all. Fill in `config/schema.php` when the accounts
  exist; nothing else to change.

- **`og:type` is now `product` on product pages.** It was hardcoded to
  `website` everywhere. Pass `$headType` to `head/head.php` to set it. This
  is what lets a shared product link render as a product card rather than a
  generic page card.

- **Analytics is wired but dormant** — see the GA4 item in 1o above.

Verified after the changes: `php -l` clean on every touched file, the footer
renders with zero `href="#"` links, and the GA block emits nothing while the
ID is empty.
