# teepsaa — Launch day: signing up with Google

Written 2026-09-07. This is item **1o** of `teepsaa-todos-seo-visibility.md`,
pulled out on its own because it's the only part of that checklist you do on
launch day itself, in a browser, with no code involved.

Companion files: `teepsaa-todos-seo-visibility.md` (the full SEO checklist),
`teepsaa-manual-actions.md` (everything else that needs a human).

---

## Do this first, or none of it works

**Take the password gate off.** Delete the whole `# ── Pre-launch gate ──`
block at the bottom of `.htaccess`, and delete `.htpasswd` from the server.

Every tool below fetches your pages over the internet. None of them can type
a password. While the gate is up, Search Console will say it can't reach the
site, the Rich Results Test will fail, and Google will index nothing.

Check it worked by opening `https://teepsaa.com` in a **private/incognito**
window. No popup means you're live. (Don't test on MAMP — local dev bypasses
the gate, so it never prompted you there anyway.)

---

## 1. Google Search Console — 15 minutes, do this the same hour you launch

This is Google's free dashboard. It tells you which of your pages Google has
found, what people searched to reach you, and what went wrong. Without it you
are guessing. Everything else on this page is optional; this one isn't.

1. Go to <https://search.google.com/search-console>. Sign in with the Google
   account you want to own this long-term — not a personal one you might lose
   access to.

2. It asks how you want to prove you own teepsaa. Pick the **Domain** box on
   the left, not "URL prefix".

   Domain covers `teepsaa.com`, `www.teepsaa.com`, both subdomains, and http
   and https, all in one property. URL prefix covers exactly one of those and
   you'd end up with four properties reporting different numbers.

3. Type `teepsaa.com` (no `https://`, no `www.`). It gives you a long TXT
   record that looks like `google-site-verification=abc123...`.

4. Add that record to your DNS:
   - hPanel → Domains → `teepsaa.com` → **DNS / Nameservers**
   - Add record → Type **TXT** → Name **@** → Value: paste the string
   - Save.

5. Back in Search Console, click **Verify**. If it fails, wait 15 minutes and
   click it again — DNS changes take a little while to spread. It can take up
   to an hour. This is normal and does not mean you did it wrong.

6. Once verified: left menu → **Sitemaps** → in the box type `sitemap.xml`
   → Submit.

   The full address is `https://teepsaa.com/sitemap.xml`, which works because
   `.htaccess` forwards that name to `sitemap.php`.

### What to expect afterwards

Nothing, for a while. A brand-new domain takes **days to weeks** to get
indexed, and the reports stay empty until then. That's why this is a launch-day
job and not a "next month" job — you're starting a clock.

Come back after a week and look at:

- **Pages** → how many are indexed, and the reasons given for any that aren't.
- **Sitemaps** → it should say "Success" and a discovered-URL count roughly
  matching your live product count.

Two things you'll see that are **not** problems:

- Vendor and admin subdomain pages reported as "Excluded by robots.txt" —
  that's deliberate, `robots.php` blocks them on purpose.
- Filtered search addresses reported as "Excluded by noindex tag" — also
  deliberate, item 1l.

---

## 2. Bing Webmaster Tools — 2 minutes

Worth doing purely because it's almost free effort. Bing also feeds a share
of the AI assistants people now search with.

1. Go to <https://www.bing.com/webmasters>.
2. Sign in and choose **Import from Google Search Console**.
3. Approve the permission prompt. It copies the site and the sitemap across.

That's the whole job. Do it right after step 1 while you're already logged in.

---

## 3. Google Analytics 4 — 20 minutes

Search Console tells you how people *found* you. Analytics tells you what they
did once they arrived. There's no analytics on the site at all right now.

1. Go to <https://analytics.google.com> → Admin → **Create** → Property.
2. Name it `teepsaa`, set the timezone to **(GMT+07:00) Phnom Penh** and the
   currency to **US Dollar**.
3. When it asks for a platform, choose **Web**, and enter `https://teepsaa.com`.
4. It hands you a snippet that starts `<!-- Google tag (gtag.js) -->` and
   contains an ID shaped like `G-XXXXXXXXXX`. Copy the whole thing.

### Where the snippet goes

**`head/head.php`** — that one file is the `<head>` of all 51 pages, so a
single paste covers the entire site.

Paste it just before the closing `</head>` line, after the stylesheet block.
Google's own instructions say "as high in the head as possible", but putting
it after the CSS means the page still draws at full speed if Google's server
is slow — a fair trade here.

One thing to decide: `head/head.php` is used by the vendor pages too (13 of
the 51). Analytics on those means your reports mix shop-owners doing admin
work in with actual shoppers. If you'd rather keep the numbers clean, wrap the
snippet so it only fires on the buyer site:

```php
<?php if (($_SERVER['HTTP_HOST'] ?? '') === 'teepsaa.com'): ?>
    ... paste the Google tag here ...
<?php endif; ?>
```

### Check it works

Open teepsaa.com in one tab and, in Analytics, go to **Reports → Realtime**.
You should appear as 1 active user within about thirty seconds. If you don't,
the snippet isn't on the page — view source and search for `gtag`.

---

## 4. Google Merchant Center — leave until last

This puts your products in Google's **Shopping** tab for free. For a
marketplace that's a direct line to people already trying to buy, so it's
genuinely worth having — but it's the most work of the four and it wants a
site that's already live and stable. Don't do it on launch day.

Come back to it a week or two in, once Search Console shows products being
indexed.

1. <https://merchants.google.com> — sign in with the same Google account.
2. Business info: name, country **Cambodia**, website `https://teepsaa.com`.
   It verifies the site through Search Console automatically if you used the
   same account, which is one reason to do step 1 first.
3. Set up shipping and returns policies. This is the slow part and it's
   form-filling, not code.
4. Products: choose **Website crawl / structured data** as the source rather
   than building a feed file.

   The reason this is easy: item 2a already put a full `Product` block on
   every product page — name, description, image, price, currency, stock,
   brand, rating. That's the same data a feed would carry, so Google can read
   your products straight off the pages.

### Two things Merchant Center will complain about

Both are **warnings, not errors**, and products list fine without them:

- `shippingDetails` missing
- `hasMerchantReturnPolicy` missing

You fill those in through the Merchant Center forms in step 3, not in code.

---

## The order, and why

| # | Task | Time | When |
|---|------|------|------|
| 0 | Remove the password gate | 5 min | Launch day, first |
| 1 | Search Console + submit sitemap | 15 min | Launch day, same hour |
| 2 | Bing (imports from #1) | 2 min | Launch day |
| 3 | Google Analytics 4 | 20 min | Launch day or the day after |
| 4 | Merchant Center | 1–2 hrs | A week or two later |

Search Console is first because indexing a new domain is slow and everything
else reports on data it collects. Bing is second because it just copies
Search Console. Merchant Center is last because it verifies through Search
Console and wants a settled site.

---

## While you're in there — the other launch-day test

Once the gate is off, run the **Rich Results Test** (item 2f). It's not a
sign-up, just a check:

<https://search.google.com/test/rich-results>

Paste in one product page, one shop page, `/help/`, and a category page.
You're looking for **zero errors**. Warnings about `shippingDetails` and
`hasMerchantReturnPolicy` on product pages are expected — see above.

---

## If you only read one paragraph

Delete the password block from `.htaccess`, then go to Search Console, verify
`teepsaa.com` with the **Domain** option and a TXT record in hPanel, and
submit `sitemap.xml`. That single job is most of the value on this page, it
takes about fifteen minutes, and doing it on launch day rather than a month
later is worth weeks of being found. Bing is a two-minute import from it.
Analytics is one paste into `head/head.php`. Merchant Center can wait.
