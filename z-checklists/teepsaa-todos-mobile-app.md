# teepsaa — Mobile Apps

Rewritten 2026-08-23 with per-step instructions. Same plan, same two routes,
same two-app decision.

The whole thing stays in the current stack: PHP remains the backend on
Hostinger, the app frontend is HTML/JS/CSS, and **Capacitor** packages it into
real iOS and Android apps with native features (push, camera) callable from JS.
No Swift, Kotlin or React. Node and npm are used only as packaging tools.

**Do not start until the website has launched.** Route 1's own setup requires
removing the Basic Auth gate, because the app's WebView can't reliably show a
password prompt. Finish `teepsaa-todos-launch-readiness.md` first.

| Route | Time | What you get |
| --- | --- | --- |
| **1** | 1–2 weeks | Capacitor app that loads teepsaa.com, plus native icon, splash, push and camera. Ships to Google Play fast — Cambodia is ~90% Android and Google accepts this style of app. |
| **2** | 2–4 months, at your own pace | A JSON API plus dedicated app screens. This is what passes Apple review and makes the app fast. Same database, same PHP logic — the API mostly wraps queries that already exist. |

---

# Decisions to settle first

- [ ] **Confirm the website is launched and stable.** Not "deployed" — launched,
      with the gate off and a few real orders through it.
- [ ] **Choose which app ships first: buyer or Seller.** The argument for Seller
      is below and it's a strong one.
- [ ] **Check the store names are available** — "teepsaa" and "teepsaa Seller"
      on both Google Play and the App Store.
- [ ] **Decide which Google account owns Firebase.** This account owns your push
      infrastructure permanently, so don't use a throwaway.

## Two apps, not one — already decided

Build **teepsaa** (buyers) and **teepsaa Seller** (vendors) separately.

- It's the industry standard: Amazon Shopping / Amazon Seller, Etsy / Etsy
  Seller, Lazada / Lazada Seller Center, and Shopee — the marketplace Cambodian
  vendors already know — all split shopping from seller tools.
- It matches the architecture exactly. Buyers and vendors are already separate
  tables with separate login portals, and vendors can't use the cart at all. One
  app would bolt two disjoint experiences behind one login.
- The extra cost is modest — both share the same PHP backend and API. It's two
  store listings and two builds, and the Seller app is far smaller: dashboard,
  orders, products, messages, no browsing or checkout.
- It's a better pitch. "Install teepsaa Seller and hear a ding when you get an
  order" beats "log into a website" — and that's the line you'll be using with
  the prospects in your canvassing tool.

**Sequencing:** the Seller app arguably delivers the most value per hour of work
— push-on-new-order is the killer feature, and it pairs directly with the
canvassing you're doing now. It doesn't have to ship second just because it's
the "secondary" app.

What the split changes downstream:

- [ ] **Apply Route 1 and Route 2 per app** — the buyer app skips vendor screens
      and vice versa.
- [ ] **Use `device_tokens.role` to route pushes** — vendor notifications go to
      Seller, buyer notifications go to the shopping app.
- [ ] **Prepare two store listings on each store** — icons, screenshots and
      descriptions in English and Khmer, for both apps.
- [ ] **Register two Firebase apps.** They can live in one Firebase project.

---

# Route 1 — Capacitor app loading teepsaa.com (Android first)

## 1a. Setup

- [ ] **Install Node.js.** It's only the build tool — none of it ships to
      Hostinger.
- [ ] **Create the Capacitor project in a NEW folder outside this repo.**
      Critical: the deploy is an lftp mirror of the repo to `public_html`, so
      anything you add here gets published to the live site.
- [ ] **Set `server.url` to `https://teepsaa.com` in `capacitor.config`.** This
      is the trick that makes Route 1 cheap — the app's origin stays
      teepsaa.com, so sessions, cookies and CSRF all keep working with zero
      backend changes.
- [ ] **Remove the Basic Auth gate before testing** (or add a bypass). The
      WebView can't show the password prompt reliably, so the app will just look
      broken.
- [ ] **Test the whole flow in the Android emulator** — browse, log in, add to
      cart, check out. Anything broken here is broken on the real site too.

## 1b. Make it feel like an app, not a wrapped site

This section is the difference between an app people keep and one they delete.

- [ ] **Add an app icon and splash screen** at both densities. You already have
      `images/teepsaa-icon-180.png`, `-192.png` and `-512.png`, generated for
      the canvassing home-screen app — same source, same treatment.
- [ ] **Handle the Android back button.** By default it exits the app. It should
      navigate back through history and only exit from the home screen. This is
      the single most-noticed "this is just a website" tell.
- [ ] **Match the status bar colour to the header** so the top of the screen
      doesn't look like two different apps.
- [ ] **Open external links in the system browser** — Mapbox attribution, Telegram
      links, anything off-domain. Letting them load inside the WebView traps
      users with no way back.
- [ ] **Disable pinch-zoom and overscroll bounce.** Both read as "webby"
      instantly.
- [ ] **Add a friendly offline screen.** When data drops, the WebView shows a
      raw Chrome error page by default. Replace it with something that says
      "no connection" in teepsaa's voice.

## 1c. Push notifications

The single biggest "real app" feature, and the reason a vendor installs it.

- [ ] **Create a free Firebase project and register the Android app** in it.
- [ ] **Add the Capacitor push plugin and request notification permission.** Ask
      at a moment that makes sense — after login, not on first launch.
- [ ] **Create a `device_tokens` table** — user id, role, fcm_token, platform,
      created/last-seen timestamps. Role is what decides which app a push goes
      to.
- [ ] **Add an endpoint the app POSTs its FCM token to after login.** Tokens
      rotate, so upsert on the token rather than inserting blindly.
- [ ] **Write a `send_push()` helper** — a curl call to the FCM API, following
      the same shape as `send_email()` in `config/mail.php` so it's familiar and
      testable.
- [ ] **Call `send_push()` everywhere a notification row is already created.**
      That's the complete list and you don't need to invent new events: vendor
      gets new order, refund request, low stock; buyer gets order status
      changes, messages, refund updates.
- [ ] **Make tapping a notification open the right page** (deep link), not just
      the home screen. A push that dumps you on the homepage wastes the tap.

## 1d. Native camera

- [ ] **Wire product photo upload and ABA QR upload to the native picker**
      via the Capacitor camera plugin, falling back to the normal file input on
      web. Keep `js/photo-shrink.js` in the path — phone photos are still
      multi-megabyte and the byte budget still applies.

## 1e. Ship to Google Play

- [ ] **Buy a Google Play developer account** — $25, one time.
- [ ] **Make a signed release build, and back up the signing keystore in two
      places.** Losing the keystore means you can never update the app again;
      you'd have to publish a new listing and lose every install. This is the
      one irreversible step in the whole document.
- [ ] **Prepare the store listing** — screenshots in English and Khmer, the
      description, and a feature graphic.
- [ ] **Supply a privacy policy URL.** The site's existing `/privacy/` page
      works.
- [ ] **Fill in the data-safety form.** Declare what you actually collect:
      account data, delivery addresses, photos.
- [ ] **Release to the internal testing track first**, install it on a real
      phone from the Play link, then promote to production. Don't go straight to
      production — the internal track is free and catches signing and permission
      problems.

---

# Route 2 — JSON API and dedicated screens (this is what unlocks iOS)

## 2a. The API layer

Plain PHP added to the existing site. Session auth keeps working untouched, so
the website and the apps run side by side on the same database and the same
business logic.

- [ ] **Create `/api/v1/`.** Every endpoint returns JSON via `json_encode` and
      PDO, matching the style of the existing `api/` endpoints.
- [ ] **Add token auth alongside sessions** — an `api_tokens` table, tokens
      issued at login, checked from an `Authorization: Bearer` header. CSRF
      isn't needed on token-authenticated endpoints, since there's no cookie to
      ride on.
- [ ] **Build the auth endpoints first** — register, verify email, login,
      logout, password reset. Nothing else can be tested until these work.
- [ ] **Then catalog** — home sections, search, product detail, business page,
      categories. These are read-only, so they're the safest place to shake out
      your JSON shapes.
- [ ] **Then buyer** — cart CRUD, checkout, addresses, orders and status,
      wishlist, reviews, refunds, messages, notifications, settings.
- [ ] **Then vendor** — dashboard stats, products CRUD and photo upload, orders,
      dispatch, coupons, messages, settings.
- [ ] **Re-apply the same validation rules as the web forms**, and add rate
      limiting. An API endpoint is a form without a browser in front of it —
      every check the form does server-side must exist here too.

## 2b. The app UI

- [ ] **Build the screens in HTML/JS inside the Capacitor app**, reusing the
      site's CSS and design language so it reads as teepsaa immediately.
- [ ] **Have screens call the API instead of loading pages.** No full page
      reloads — that's the whole point of Route 2 and what makes it feel fast.
- [ ] **Reuse `lang/en.php` and `lang/km.php`** by exporting them to JSON, so
      you have one set of strings rather than two that drift apart.
- [ ] **Cache product images and last-viewed data** for slow connections.

## 2c. Ship to the App Store

- [ ] **Buy an Apple Developer account** — $99/year. Requires the Mac and Xcode.
- [ ] **Build for iOS via Capacitor and test on a real iPhone**, not just the
      simulator.
- [ ] **Submit for App Store review.** The dedicated-UI build is what satisfies
      Apple's "minimum functionality" rule — the rule that rejects bare website
      wrappers, and the entire reason Route 2 exists.
- [ ] **Release the same dedicated-UI build on Android**, replacing the Route 1
      wrapper.
