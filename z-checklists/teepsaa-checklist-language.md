# Teepsaa — Khmer / English Language Toggle

## How it works

A toggle button in the header sets a session variable (`$_SESSION['lang']`). Every page loads a language file based on that variable. All UI text is pulled from the language file rather than hardcoded.

---

## Done

- [x] Logo swap — header shows `teepsaa_logo_khm.png` when `lang=km`, `teepsaa_logo_eng.png` when `lang=en`
- [x] Create `lang/en.php` — lookup table of keyed strings (100+ keys covering buyer/vendor/public surfaces: homepage, search, product, cart, checkout, login, register, orders, wishlist, settings, vendor dashboard, messages, about, footer)
- [x] Create `lang/km.php` — matching Khmer translations for every key (real Khmer, not machine-translated placeholders)
- [x] Add language loader to header (`header/header.php`) — loads `$t` from the right file based on `$_SESSION['lang']`, defaults to `km`
- [x] Toggle button already existed in header (flag dropdown, `lang/set.php`) — sets session lang and reloads
- [x] **Header wired** (2026-06-19) — every hardcoded string in `header/header.php` now reads from `$t[...]`: search placeholder, Login, admin nav (Admin/Orders/Marketing/Messages), buyer/vendor/admin dropdown menus (Orders/Products/Messages/Vendor/Settings/Logout/Wishlist/Cart), notifications dropdown (Notifications/Mark all read), Language/Currency labels, and the mobile-nav duplicate of all of the above. Added 6 new keys to both lang files to support this: `nav_admin`, `nav_marketing`, `nav_notifications`, `nav_mark_all_read`, `lang_label`, `currency_label`.

---

## Build Steps (remaining — one page at a time, user reviews each before moving on)

- [ ] Footer (`footer/footer.php`) — keys already exist in lang files (`footer_*`), just needs wiring
- [ ] Homepage (`index.php`) — keys already exist (`home_*`), just needs wiring
- [ ] Search page
- [ ] Product page
- [ ] Cart / checkout
- [ ] Login / register (buyer + vendor)
- [ ] Buyer dashboard (orders, wishlist, settings, messages)
- [ ] Vendor dashboard (products, orders, settings, messages)
- [ ] About / static pages
- [ ] Test both languages across all wired pages

---

## How to resume in a new session

Tell Claude: *"Continue the Khmer/English language wiring — header is done, do the footer next."* Point it at this checklist file and `lang/en.php` / `lang/km.php` for the existing keys. Approach so far: wire one page/section at a time, swap hardcoded text for `$t['key']`, no structural/CSS/JS changes, user reviews each page before moving to the next.

---

## Notes

- Most remaining work is just swapping hardcoded text for `$t['key']` — the string keys for many pages already exist in `lang/en.php` and `lang/km.php` from earlier work, so check there before adding new keys
- Khmer font rendering may need a specific web font — **Noto Sans Khmer** (Google Fonts, free) is the standard choice
- User language preference could optionally be saved to the `users` table so it persists across sessions instead of just `$_SESSION['lang']`
