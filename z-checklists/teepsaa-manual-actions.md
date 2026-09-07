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

- [ ] **4. Take the password gate off** (item 1a).
      Delete the whole `# ── Pre-launch gate ──` block at the bottom of
      `.htaccess`, and delete the `.htpasswd` file from the server. The site
      is invisible to Google until this is gone — nothing else in this
      checklist matters while it's up.

- [ ] **5. Fill in your social links, or remove the icons.**
      Two places, and they should agree:

      - `config/schema.php` — `SCHEMA_SOCIAL` is three commented-out lines
        near the top. These are what Google reads to connect your Facebook
        page to your website in its brand panel.
      - `footer/footer.php` — the Instagram, Facebook and Telegram icons are
        all `href="#"` (lines 50, 57, 62), which is a dead link on every
        page of the site.

      If teepsaa has no social accounts yet, delete the three icons from the
      footer rather than shipping dead links.

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

- [ ] **Compression** (item 3f). Run `curl -I https://teepsaa.com` and look
      for `content-encoding: gzip` in the reply. If it's missing, LiteSpeed
      is ignoring the `mod_deflate` block and it's worth a support ticket.
- [ ] **Rich Results Test** (item 2f). Paste each of these into
      <https://search.google.com/test/rich-results>:
      a product page, a shop page, `/help/`, and a category page. You're
      looking for zero errors — warnings about optional fields are fine.
- [ ] **The new addresses.** Open a product from the homepage and check the
      address reads like `/product/silk-krama-scarf-8f14e45f-ab3c/`. Then
      paste an *old* `/product/?id=...` link and confirm it lands on the new
      one.
- [ ] **A category page.** `/category/dresses/` should show a heading, the
      intro text, subcategory links and a filtered grid. Try a parent like
      `/category/womens/` too — it should show everything beneath it, not an
      empty grid.
- [ ] **Page 2.** Scroll to the bottom of `/search/` and click "2". Then try
      `/search/?page=999` — that should be a proper "not found" page.
- [ ] **A general click-through.** The `<head>` of all 45 public and vendor
      pages was rewritten (item 3g). It's mechanical and it lints clean, but
      a five-minute walk around the site — log in as a buyer, add to cart,
      check out, log in as a vendor — is cheap insurance.

---

## D. The writing — only you can do this

- [ ] **The category intros.** `category/intros.php` has 49 slots. **15 are
      written, 34 are blank.** A blank one isn't broken — the page falls back
      to a generic sentence — but the intro is the entire reason a category
      page ranks for "bags Phnom Penh" instead of just existing.

      Two or three real sentences each. Text that would fit any category is
      worse than none: say what people actually buy in it, name the
      materials, name the occasions.

- [ ] **Have a native Khmer speaker read all 49.** The `km` half is what
      most of your visitors see and it's the half that matters most. The 15
      I wrote are a starting point, not finished copy. Do this before they
      go live, not after.

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
