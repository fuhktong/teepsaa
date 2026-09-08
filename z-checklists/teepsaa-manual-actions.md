# Manual actions — the SEO work I couldn't do for you

Everything in Parts 1, 2 and 3 of `teepsaa-todos-seo-visibility.md` that is
code is written and syntax-checked. This file is the remainder: the things
that need a live server, a Google account, a database, or a human who writes
Khmer.

Roughly: **an hour of clicking, then the writing.**

---

## A. Before the site goes live

- [ ] **1. Run the sitemap date migration.**
      phpMyAdmin → SQL tab → paste `database/migration-seo-updated-at.sql`.
      It adds an `updated_at` column to `products` and `businesses` so the
      sitemap can tell Google when a listing actually changed instead of
      when it was first posted.

      Not urgent — `sitemap.php` checks whether the column exists and falls
      back to `created_at` — but until it runs, an edited product looks
      unchanged to a crawler.

      Remember `database/` is excluded from deploys, so this file is on your
      machine, not the server. Copy the text out of it.

- [ ] **1b. Fix the returns and shipping copy on the live site.**
      The `/returns/` page currently tells buyers that returns are "handled
      between the buyer and the individual vendor" on a "case-by-case
      basis". That was true before the in-app refund flow was built and is
      not true now — a buyer requests a refund on the order, within 24 hours
      of delivery, and you decide. Four FAQ answers were wrong the same way,
      including one promising money back "within 3–5 business days to your
      original payment method", which is not how it works either.

      This is not just a tidiness problem. Product pages now declare that
      24-hour window to Google (`hasMerchantReturnPolicy`), and Merchant
      Center checks the declaration against the page. Until the page agrees,
      the schema is the risk rather than the benefit.

      The copy lives in the `content_pages` and `faq_items` tables, not in
      the repo, so a deploy cannot carry it. Two ways to apply it:

      **Easier —** upload `database/update-returns-policy.php` to the server
      and open it in your browser once. It rewrites both content pages and
      the four Returns & Refunds answers, in English and Khmer, and prints
      what it changed. Safe to run twice. Delete it afterwards.

      **Or by hand —** `/admin/content.php` for the two pages and
      `/admin/faq.php` for the four answers, pasting from
      `database/seed-content.php`. Twelve paste operations, half of them
      Khmer, which is why the script exists.

      One line to confirm before it goes out: the returns page now says you
      send the refund **by ABA transfer**. If you refund some other way, say
      so instead.

- [ ] **2. Make the small image copies on the server.**
      This is the single biggest speed win in the whole checklist — locally
      it took the card images from **15,946 KB to 794 KB**.

      `uploads/` isn't in git, so the copies I generated here do **not**
      deploy. You have to run the script where the images actually live:

      1. Upload `database/backfill-image-derivatives.php` to the server by
         hand (File Manager → into the `database` folder).
      2. Run it. Over SSH: `php database/backfill-image-derivatives.php`.
         No SSH: open `https://teepsaa.com/database/backfill-image-derivatives.php`
         in your browser once — it prints a line per image and a summary.
      3. **Delete the file from the server afterwards.**

      It's safe to re-run: images already converted are skipped, so if it
      times out halfway you just run it again.

- [ ] **3. Check the server's PHP can write WebP.**
      The script tells you — if it prints *"This PHP build has no WebP
      support in GD"*, nothing is broken and the site keeps serving the
      original images, but you get none of the saving. In that case ask
      Hostinger to enable the GD WebP extension, then re-run the script.

      Also confirm PHP can create folders under `uploads/` — the script
      makes `uploads/w400/` and `uploads/w1200/` on first run. If they don't
      appear, the folder permissions need to be 755.

- [x] **4. Take the password gate off** (item 1a). **Done 2026-09-07** —
      the block is commented out in `.htaccess` and deployed;
      `curl -I https://teepsaa.com/` returns `HTTP/2 200`.

      One loose end: **delete `.htpasswd` from the server**. It is inert
      now, but it is still a password file in your web root, and
      `deploy-sftp.sh` uses no `--delete`, so it will sit there until you
      remove it in File Manager.

- [ ] **5. Fill in your social links — one place now, and no rush.**
      **Fixed 2026-09-07:** this used to be two places that had to agree and
      didn't — the footer shipped three `href="#"` dead links while
      `SCHEMA_SOCIAL` sat empty. The footer now draws its icons from that
      same list.

      So: put your addresses in `SCHEMA_SOCIAL` at the top of
      `config/schema.php` and the icons appear in the footer and the
      profiles reach Google's brand panel, both at once. Leave them empty
      and **no icon renders** — which is the correct state until the
      accounts exist, and needs no action from you.

- [ ] **6. Spot-check that the English is real.**
      Open a dozen products and look at `name_en` and `description_en`. All
      of item 3a's work makes your English *visible* to Google; it can't
      make it *good*. If those columns are empty, or hold Khmer text copied
      across, the English half of the site is thin pages that will be
      treated as such.

---

## B. Sign-ups — item 1o, launch day

- [ ] **Google Search Console** — add `teepsaa.com`, verify by DNS record,
      submit `https://teepsaa.com/sitemap.xml`. This is the one that tells
      you what Google actually thinks. Don't bother adding the vendor or
      admin subdomains; `robots.php` already tells crawlers to stay out.
- [ ] **Bing Webmaster Tools** — it imports straight from Search Console, so
      it's about two minutes. Bing also feeds a chunk of AI search results.
- [ ] **Google Analytics 4** — property, then the tag on the site.
- [ ] **Google Merchant Center** — only if you want free product listings in
      the Shopping tab. The product data Google needs is already on your
      product pages from item 2a, so this is mostly form-filling.

---

## C. Check it worked — after deploy, in this order

- [x] **Compression** (item 3f). **Verified 2026-09-07: working**, and
      better than expected — LiteSpeed answers `content-encoding: br`
      (Brotli), which compresses smaller than gzip. No support ticket
      needed.
- [x] **Rich Results Test** (item 2f). **Verified 2026-09-07: zero
      errors** across the homepage, a product page in both languages, a shop
      page, `/help/` and a category page — every JSON-LD block parsed and
      carried its required fields. Full results in the 2f entry of
      `teepsaa-todos-seo-visibility.md`.

      Still worth pasting one product page into
      <https://search.google.com/test/rich-results> when you have a minute,
      purely because it renders the visual preview.
- [x] **The new addresses.** **Verified 2026-09-07.** Live product pages
      serve at the readable form — e.g.
      `/product/classic-white-tee-fdcaa416-6668/` — and the sitemap lists 19
      products, 12 shops and 12 categories, each in both languages.

      Note if you test the old form yourself: `?id=` takes the **full UUID**
      `public_id`, not the numeric row id, so `/product/?id=1` correctly
      returns 404 rather than redirecting. The 301 to the canonical address
      is at `product/index.php:75` and fires on a real UUID.
- [x] **A category page.** **Verified 2026-09-07.** `/category/clothing/`
      returns 200 with an `<h1>`, its intro paragraph and 19 products;
      `/category/womens/` renders its own intro and pulls products from
      beneath it rather than showing an empty grid.

      Leaf categories with no intro yet — `/category/mens-jeans/` was the one
      checked — render the heading and grid with no paragraph, exactly as
      designed. All 49 intros are written now, so that resolves on deploy.
- [x] **Page 2.** **Verified 2026-09-07**, with a caveat worth writing
      down: `/search/?page=2` currently returns **404, and that is correct** —
      there are 19 live products and the page holds 20, so there is no page
      2 to serve. `?page=999` also 404s properly.

      This means the pagination guard is proven but the pagination *links*
      aren't — nothing renders a "2" to click yet. Re-check once you pass 20
      live products.
- [ ] **A general click-through.** Still yours — it needs a browser and
      real logins, which I can't do from here.

      Now worth five minutes rather than two, because `footer/footer.php`
      and `head/head.php` both changed on 2026-09-07 and they are on **every
      page of the site**, buyer and vendor. Both lint clean and the footer
      was render-tested in isolation, but a walk around the real site — log
      in as a buyer, add to cart, check out, log in as a vendor — is the
      only thing that proves it end to end.

---

## D. The writing — only you can do this

- [x] **The category intros.** **Done 2026-09-07 — all 49 slots are now
      filled**, in English and Khmer. The remaining 34 were written to be
      specific rather than interchangeable, which is the whole point: each
      names what's actually in the category and gives one practical buying
      note. The recurring hooks are the ones that are true here and not
      elsewhere — heat and humidity driving fabric choice, rainy season and
      motorbike commuting, Khmer/Thai/Chinese sizing disagreeing, and
      measuring in centimetres rather than trusting a size letter.

      Checked: no blanks, no duplicated English, none padded to length.

- [ ] **Have a native Khmer speaker read all 49.** This is now the one
      content job left, and it matters — the `km` half is what most of your
      visitors actually read. **All 49 Khmer strings are machine-written and
      none has been reviewed by a native speaker.** They are a solid
      starting draft, not finished copy.

      Do this before they earn traffic, not after. Budget an hour with
      someone who writes Khmer well and have them read for register and
      naturalness, not just literal accuracy — several use retail phrasing
      where a Cambodian shopper might say something shorter.

- [ ] **Item 3h, in full.** None of it is code and all of it outranks the
      code for a brand-new site:
      Google Business Profile for teepsaa (and nudge approved vendors to
      claim one for their own shop) · vendor interview posts · Khmer keyword
      research across Khmer script, romanised Khmer and English · local
      directories and Facebook groups.

---

## E. Things I left alone on purpose

Not oversights — each of these was a decision, and here's the reasoning in
case you disagree with one.

- **The admin panel's images** have no `width`/`height` and get no small
  copies. It's a private English-only interface behind a login; no search
  engine ever sees it and the pages aren't slow enough to matter.
- **The lightbox and the bank QR codes still load the full original.** The
  lightbox is the "see it properly" view and only loads on a deliberate
  click. QR codes are flat graphics that come out *bigger* as WebP, so the
  code throws those copies away by design.
- **Vendor-side links still use `?id=`** in a couple of internal spots. They
  are noindex pages and the permanent forward catches them, so making them
  pretty would be churn.
- **Category addresses are derived from names, not stored.** This keeps
  everything in code and needs no migration, but it has one consequence:
  renaming a category changes its address, and *deleting* one can change a
  sibling's (because the mens-/womens- prefix only appears while a name is
  shared). If a category page is earning traffic by then, add a redirect for
  the old address rather than letting it 404.
- **CSS and JavaScript cache for one week, not a year.** They live at fixed
  addresses with no version in the filename, so a year-long cache would
  strand returning visitors on the old design. If you ever add versioned
  filenames, raise it.
- **Photos above ~40 megapixels are skipped** by the resizer, because
  decoding one can take the whole request down on shared hosting. They still
  upload and still work; they just stay large.
- **The `/en/` address prefix** from item 3a is still not built. `?lang=en`
  gets the same result from Google's side for about a day of work instead of
  a week. Revisit only if the English pages start earning traffic and
  something concrete argues for it.
