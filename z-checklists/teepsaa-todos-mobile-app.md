# Mobile App — From Website to Real App (PHP + JS + HTML)

The whole plan stays in the current stack: PHP remains the backend on
Hostinger, the app frontend is HTML/JS/CSS, and **Capacitor** packages it
into real iOS/Android apps with native features (push, camera) callable
from JS. No Swift/Kotlin/React needed. Node/npm is used only as the
packaging tool.

Two routes, done in sequence:

- **Route 1** (1–2 weeks): Capacitor app that loads teepsaa.com directly,
  plus native icon/splash/push/camera. Ships to Google Play fast —
  Cambodia is ~90% Android and Google accepts this style of app.
- **Route 2** (2–4 months, at your own pace): add a JSON API to the PHP
  app and build dedicated app screens. This is what passes Apple's review
  and makes the app fast/offline-capable. Same database, same PHP logic —
  the API mostly wraps queries that already exist.

Do Route 1 after the website launches — not before.

---

## Route 1 — Capacitor app loading teepsaa.com (Android first)

### Setup

- [ ] Install Node.js (needed only for Capacitor tooling)
- [ ] Create the Capacitor project in a NEW folder (not inside the website
      repo — nothing from it should deploy to public_html)
- [ ] Set `server.url` to `https://teepsaa.com` in capacitor.config —
      the app's origin stays teepsaa.com, so sessions, cookies, and CSRF
      all keep working with zero backend changes
- [ ] Remove the .htaccess Basic Auth gate before testing (or add a bypass) —
      the app WebView can't show the password prompt reliably
- [ ] Test in Android emulator: browse, log in, add to cart, checkout

### Make it feel like a real app (not a wrapped site)

- [ ] App icon + splash screen (teepsaa logo, both densities)
- [ ] Handle Android back button (navigate history, don't exit app)
- [ ] Status bar color matched to the header
- [ ] External links (Mapbox attribution, etc.) open in the system browser,
      not inside the app
- [ ] Disable pinch-zoom/overscroll quirks that feel "webby"
- [ ] Offline screen: friendly "no connection" page instead of the WebView
      error when data drops

### Push notifications (the single biggest "real app" feature)

- [ ] Create a Firebase project (free) + add the Android app to it
- [ ] Add the Capacitor push plugin; request notification permission
- [ ] New table `device_tokens` (user id, role, fcm_token, platform)
- [ ] New endpoint: app POSTs its FCM token after login
- [ ] New PHP helper `send_push()` (curl to FCM API — same pattern as
      send_email in config/mail.php)
- [ ] Call `send_push()` everywhere a notification row is created today:
      vendor — new order, refund request, low stock;
      buyer — order status changes, messages, refund updates
- [ ] Tapping a notification opens the right page in the app (deep link)

### Native camera/photos

- [ ] Product photo upload + ABA QR upload use the native picker/camera
      (Capacitor camera plugin; falls back to normal file input on web)

### Ship to Google Play

- [ ] Google Play developer account ($25 one-time)
- [ ] Signed release build (keep the signing keystore backed up — losing it
      means you can never update the app)
- [ ] Store listing: screenshots (en + km), description, feature graphic
- [ ] Privacy policy URL (the site's privacy page works)
- [ ] Data-safety form (declare: account data, addresses, photos)
- [ ] Internal testing track first → then production release

---

## Route 2 — JSON API + dedicated app UI (unlocks iOS)

### API layer (plain PHP, added to the existing site)

- [ ] `/api/v1/` folder; all endpoints return JSON (json_encode + PDO —
      same code style as the existing api/ endpoints)
- [ ] Token auth alongside sessions: `api_tokens` table, tokens issued at
      login, checked via `Authorization: Bearer` header (CSRF not needed
      on token-authenticated endpoints)
- [ ] Endpoints, roughly in build order:
      - [ ] auth: register, verify email, login, logout, password reset
      - [ ] catalog: home sections, search, product detail, business page,
            categories
      - [ ] buyer: cart CRUD, checkout, addresses, orders + status,
            wishlist, reviews, refunds, messages, notifications, settings
      - [ ] vendor: dashboard stats, products CRUD + photo upload,
            orders, dispatch, coupons, messages, settings
- [ ] Rate limiting + the same validation rules as the web forms
- [ ] Keep session auth untouched — website and app run side by side on
      the same database and business logic

### App UI

- [ ] Build screens in HTML/JS inside the Capacitor app (reuse the site's
      CSS/design language so it looks like teepsaa immediately)
- [ ] Screens call the API instead of loading pages — no full page reloads
- [ ] i18n: reuse lang/en.php + lang/km.php (export to JSON)
- [ ] Cache product images + last-viewed data for slow connections

### Ship to the App Store

- [ ] Apple Developer account ($99/year) — requires the Mac + Xcode
- [ ] iOS build via Capacitor, test on a real iPhone
- [ ] App Store review: this dedicated-UI version is what satisfies the
      "minimum functionality" rule that rejects bare website wrappers
- [ ] Release the same dedicated-UI build on Android too (replaces Route 1)

---

## Two apps: teepsaa (buyers) + teepsaa Seller (vendors)

DECIDED: build two separate apps, not one combined app.

Why it's right:

- Industry standard — Amazon Shopping / Amazon Seller, Etsy / Etsy Seller,
  Lazada / Lazada Seller Center, and Shopee (the marketplace Cambodian
  vendors know best) all split shopping from seller tools
- Matches the architecture exactly: buyers and vendors are already separate
  accounts with separate login portals, and vendors can't use the cart —
  a single app would bolt two disjoint experiences behind one login
- Extra cost is modest: both apps share the same PHP backend/API; it's two
  store listings and two builds, but the Seller app is much smaller
  (dashboard, orders, products, messages — no browsing/cart/checkout)
- Pitch bonus: "install the teepsaa Seller app and hear a ding when you get
  an order" is a stronger line to a vendor than "log into a website"

Sequencing note: the Seller app arguably delivers the most value per effort
(push for new orders is the killer feature) — it doesn't have to come second
just because it's the "secondary" app. Decide which ships first.

What it changes in the plans above:

- [ ] Route 1 / Route 2 apply per app — buyer app skips vendor screens and
      vice versa
- [ ] Push: `device_tokens.role` decides which app a push goes to
      (vendor pushes → Seller app, buyer pushes → shopping app)
- [ ] Two store listings each on Google Play / App Store (icons,
      screenshots, descriptions in en + km for both)
- [ ] Two Firebase app registrations (can live in one Firebase project)

## Decision points to settle before starting

- [ ] Confirm website is launched and stable first
- [ ] Which app ships first: buyer app or Seller app?
- [ ] App names/branding in both stores ("teepsaa" and "teepsaa Seller"
      availability)
- [ ] Firebase account (Google account to own the push infrastructure)
