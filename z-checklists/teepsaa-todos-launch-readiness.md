# teepsaa — Launch Readiness

The work still outstanding. Everything already finished is in
`teepsaa-completed.md` — including Parts 1, 2, 4 and 5 of this file, so the
numbering starts at 3 and is deliberately not renumbered (every "Part 2b"
reference elsewhere still points at the same thing).

**The site is live.** The pre-launch gate came off 2026-09-09.

Companion files: `teepsaa-todos-mobile-app.md` (after launch),
`teepsaa-todos-seed-content.md` (the full seed comes last, after both apps —
but read its "minimum you need earlier" section, because several checks below
need products to exist), `teepsaa-production-deploy.md`,
`teepsaa-open-questions.md`, `../z-reference/teepsaa-email-reference.md`.

**Build order:** website → buyer app → Seller app → full content seed → pitch.

**Test on the live Hostinger site, not local MAMP** — real emails, real
uploads, real `.htaccess`. Use Gmail +aliases for throwaway accounts
(`dustint505+test1@gmail.com`).

---

# Part 3 — Real-device testing

Cambodia is overwhelmingly mobile and mostly Android. Khmer script rendering
and touch behaviour genuinely differ from the desktop browser's device mode —
test on real hardware.

The checks themselves are grouped by who they belong to — **site-wide**,
**buyer**, **vendor**, **admin** — starting at section 3f. The old 3b/3c/3d/3e
letters survive as tags on each item, because `teepsaa-completed.md` and other
files still refer to them (3b layout, 3c function, 3d slow connections, 3e
worth checking once). **3g (buyer) is finished and archived** — only the
site-wide and vendor sections carry open work, and the letters are not
renumbered so the archive's references keep pointing at the same things.

## 3a. Devices to cover

Desktop and tablet are deliberately not listed: the whole site was built and
used on a MacBook and an iPad throughout, so anything broken at those widths
would have shown up months ago. What is left is mobile.

- [ ] **Android phone, Chrome** — the single most important combination. No
      Android hardware here, so this is the Android Studio emulator: an arm64
      system image, a low-RAM device profile, Android 10 and 13/14. The
      emulator is what makes it worth doing — it carries Android's own Khmer
      font stack (Noto Sans Khmer), which stacks glyphs differently from iOS,
      and that is the one thing a Mac cannot fake. Covers 3f and 3h only — the
      buyer section is closed.
- [x] **iPhone, Safari** — the iPhone 6s. It tops out at iOS 15, which makes
      it the oldest WebKit and the slowest CPU you will realistically test on,
      so it is the worst case rather than a compromise. Newer WebKit is
      covered by desktop Safari, which runs essentially the same engine.
      _Done 2026-09-11: buyer side, full flow, order placed._
- [ ] **A cheap or old Android** if you can borrow one — slow CPU, small
      screen. This is what a lot of your buyers actually have. The emulator's
      low-RAM profile approximates it; ten minutes with a real one is better.
      Same narrowed scope as the emulator: 3f and 3h.

## 3a-i. Viewport sweep — how to run it, on the MacBook

Do this before touching a device: it is the cheapest pass available and it
finds most layout bugs. The checks it produces are in **3f** — they cross every
role, so they live in the site-wide section.

Only two engines matter: **Blink** (Chrome, Edge, Samsung Internet, Opera,
Brave, and DuckDuckGo on Android) and **WebKit** (Safari, and _every_ browser
on iOS — Chrome and DuckDuckGo included, because Apple forces the engine).
Firefox's Gecko is a rounding error here. So the sweep runs twice, once in
each, and no other browser needs its own pass.

**Chrome:** ⌘⌥I, then the phone icon in the toolbar.
**Safari:** Settings → Advanced → "Show features for web developers", then
Develop → Enter Responsive Design Mode (⌘⌃R). In both, type an exact width
rather than picking a device preset — the number is the thing being tested.

Chrome — four from that list, plus one typed:

- Samsung Galaxy S8+ = 360px — this is the important one, the most common Android width in Cambodia
- iPhone SE = 375px
- iPhone 12 Pro = 390px
- iPhone 14 Pro Max = 430px
- Responsive (top of the list) → type 320 for the stress test

Skip every iPad, Surface, Nest Hub, and the folds. Pixel 7/8/9/10 and Galaxy S20 Ultra are all ~412 — close enough to 430 that they add nothing.

Safari — it has no Android widths, so:

- iPhone SE size = 375
- iPhone size = 393 (the readout beside the dropdown shows it, as in your screenshot)
- iPhone Pro Max size = ~430
- Custom size → type 360, and 320 if you want the stress test

Widths, and what each one is for:

| Width | What it represents                                     |
| ----- | ------------------------------------------------------ |
| 320px | Smallest phone still in use — where things break first |
| 360px | **The most common Android width in Cambodia**          |
| 390px | iPhone 14 / Pixel, the modern typical                  |
| 430px | Pro Max / large Android                                |

---

**With 3g archived, what is left is site-wide and vendor.** A tick in 3f came
from the iPhone / Safari buyer pass, so the Android pass in 3a repeats 3f from
scratch — nothing ticked there counts for it. Nothing in 3h has been run on any
device yet. Each item left open says in its own text why.

## 3f. Site-wide — every role, and logged-out visitors

**Viewport sweep (3a-i)**

- [ ] **Chrome — all four widths.** Homepage, search, a product page, cart,
      checkout, a vendor product form.
- [ ] **Safari — all four widths.** Same pages. Same engine as every iOS
      browser, so this one pass covers all of them.
- [ ] **Drag the width slowly from 320 up to ~500 in each**, rather than only
      stopping at the four numbers. Breakpoints fail _between_ the presets,
      and dragging is what finds them.
- [ ] **Watch for horizontal scroll at every width.** The buyer pages passed
      this on the phone (3g); the vendor portal in 3h has not been swept. If the
      page slides sideways, something has a fixed width.

**Layout (3b)**

- [ ] **Header nav and menus are thumb-usable**, and the notification dropdown
      fits on screen instead of running off the edge. _Deliberately left open:
      the dropdown gained a "See all notifications" footer link and the mobile
      menu gained a Notifications entry on 2026-09-11, after this pass. Recheck
      both once that deploy is live._
- [x] **Footer stacks correctly** and the tagline font (Pacifico / Metal) loads.
      A brief fallback flash is fine; a wrong font that never corrects is not.
- [ ] **Khmer text renders cleanly** — no overlapping or clipped characters.
      Khmer stacks glyphs vertically, so line-height problems show up on phones
      first. Check dates render in Khmer numerals where they should. _Not
      covered by the buyer pass — switching language is not the same as reading
      a Khmer page end to end. Two Khmer strings on the checkout QR screen are
      also still waiting on a native-speaker read._

**Function (3c)**

- [x] **Currency and language switchers** are reachable and work on mobile.
- [ ] **Upload a resume from a phone** on the careers form. Public page — no
      account needed, so it belongs to nobody's portal.

**Slow connections (3d)**

- [ ] **Throttle to 3G / slow 4G in dev tools** and load the homepage. It should
      be usable in reasonable time, with images lazy-loading rather than
      blocking the page.
- [ ] **Check total homepage weight** in dev tools → Network. More than a few MB
      means filler product photos need compressing before you add more.

**Worth checking once (3e)**

- [ ] **Add to Home Screen on Android** — the icon and title look right.
      _Done on iOS/Safari 2026-09-11; the Android half is what this line is
      about, so it stays open._
- [x] **Share a site link in Telegram** — huge in Cambodia. The preview title,
      description and image come from the OG tags in `config/seo.php`.
- [x] **Open a teepsaa email in the Gmail phone app** and check mixed Khmer and
      English blocks render properly.

## 3h. Vendor

Nothing here has been swept on any device yet — the 2026-09-11 iPhone pass was
buyer-side only.

**Layout (3b)**

- [ ] **No horizontal page scroll at any width** across the vendor portal.
      Spot-check below 400px. If the page slides sideways, something has a
      fixed width.
- [ ] **Add product form** — usable with a phone keyboard, labels stay visible,
      and validation errors appear where you can see them without hunting.
- [ ] **Business pin map** — pan and zoom by touch, the pin drops where you tap,
      and the map doesn't hijack page scrolling when you try to scroll past it.
- [ ] **Photo gallery drag-to-reorder works by touch** on the vendor edit
      product page. Drag-and-drop is the classic thing that works with a mouse
      and not a finger.

**Function (3c)**

- [ ] **Upload a photo from the phone camera** — vendor add product, and the ABA
      QR in `business-vendor/`. Large camera images must either be accepted or
      rejected with a clear message — never fail silently.

## 3i. Admin

Nothing outstanding: no Part 3 item covers the admin portal. Admin work has
been done on the MacBook throughout, and the portal is not something a vendor
or buyer can reach. If you decide it needs its own mobile pass, the items go
here.

---

# Still to check — site-wide

- [ ] **Read the error log once, after the Part 3 device testing above.**
      `ssh teepsaa "tail -50 ~/.logs/error_log_teepsaa_com"` — look for
      anything newer than when you started. `log_errors` is on permanently, so
      there is nothing to switch on and nothing to re-run; normal use of the
      site is the test. As of 2026-09-09 the log is 2033 bytes and its newest
      entry is a pre-fix test of my own, so the site is currently clean.
      Leave `display_errors` off — the site is public.
