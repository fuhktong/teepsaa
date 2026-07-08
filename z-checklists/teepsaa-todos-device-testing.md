# Device & Browser Testing — Especially Phones

Cambodia's market is overwhelmingly mobile and mostly Android. Test on real
phones, not just the desktop browser's device mode — Khmer script rendering
and touch behavior differ on real devices.

## Devices to cover

- [ ] Android phone, Chrome (the most common combination in Cambodia)
- [ ] iPhone, Safari
- [ ] A cheap/older Android if you can borrow one (slow CPU, small screen)
- [ ] Desktop: Chrome, Safari, Firefox
- [ ] Tablet or desktop half-window (in-between widths)

## Per device — layout

- [ ] Homepage: header, search bar, banner carousel, product rows scroll
      horizontally without breaking
- [ ] No horizontal page scroll at any width (spot-check < 400px wide)
- [ ] Product cards: names truncate cleanly (long Khmer names too)
- [ ] Product detail: gallery swipes/taps, variant buttons big enough to tap
- [ ] Forms: register, address, add-product — usable on a phone keyboard,
      field labels visible, errors visible without scrolling hunt
- [ ] Map (address pin, business pin): pans/zooms with touch, pin drop works,
      doesn't hijack page scrolling
- [ ] Photo gallery drag-to-reorder on touch (vendor edit product)
- [ ] Header nav / menus usable with a thumb; notification dropdown fits
      the screen
- [ ] Footer stacks correctly; tagline font (Pacifico/Metal) loads —
      brief fallback flash is OK, wrong font that never corrects is not
- [ ] Khmer text: no overlapping/clipped characters (Khmer stacks glyphs
      vertically — line-height issues show up on phones), dates render in
      Khmer numerals where expected

## Per device — function

- [ ] Full buyer flow on a phone: register → verify → add to cart →
      address + pin → checkout
- [ ] Photo upload from the phone camera (vendor add product, ABA QR) —
      large camera images accepted or clearly rejected
- [ ] File upload from phone for careers resume
- [ ] Currency + language switchers reachable and working on mobile

## Network conditions

- [ ] Throttle to 3G/slow 4G (browser dev tools): homepage usable in
      reasonable time; images lazy-load rather than block the page
- [ ] Check total homepage weight (dev tools → Network): if it's more than
      a few MB, filler product photos need compressing before adding more
- [ ] Payment/checkout confirm on a slow connection: no double-submit if
      the user taps twice while waiting

## Nice to check once

- [ ] Add to home screen (Android) — site icon and title look right
- [ ] Link preview when sharing the site in Telegram (huge in Cambodia) —
      title/description/image (SEO/OG tags from config/seo.php)
- [ ] Khmer + English mixed text in emails on the Gmail phone app
