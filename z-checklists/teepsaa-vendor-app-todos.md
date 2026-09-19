# teepsaa vendor app — build checklist

Rewritten 2026-09-12 from `z-checklists/teepsaa-todos-mobile-app.md`, with the
setup that is already done ticked off and the real values filled in. Written to
live **inside the `teepsaa-vendor-app` folder**, not in the website repo — move
it there and work from it.

Same plan and same two routes as the original. Everything stays in the current
stack: PHP remains the backend on Hostinger, the app frontend is HTML/JS/CSS,
and **Capacitor** packages it into real iOS and Android apps with native
features (push, camera) callable from JS. No Swift, Kotlin or React. Node and
npm are build tools only — none of it ships to Hostinger.

| Route | Time | What you get |
| --- | --- | --- |
| **1** | 1–2 weeks | Capacitor app that loads vendor.teepsaa.com, plus native icon, splash, push and camera. Ships to Google Play fast — Cambodia is ~90% Android and Google accepts this style of app. |
| **2** | 2–4 months, at your own pace | A JSON API plus dedicated app screens. This is what passes Apple review and makes the app fast. Same database, same PHP logic — the API mostly wraps queries that already exist. |

---

# Where things stand — 2026-09-12

The project exists and Android is added. These are the real values, so you never
have to go digging for them:

| Thing | Value |
| --- | --- |
| Project folder | `~/Documents/programming/mywebsites/043 teepsaa/teepsaa-vendor-app` |
| Website repo | `.../043 teepsaa/teepsaa` — **sibling, never a parent** |
| App name (on the phone) | `teepsaa vendor` |
| App ID — permanent | `com.teepsaa.vendor` |
| Buyer app ID, for later | `com.teepsaa.buyer` |
| What the app loads | `https://vendor.teepsaa.com` |
| Java for building | Homebrew openjdk **21**, at `/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home` |
| Gradle | 8.14.3, as installed by Capacitor |

Done so far:

- [x] **Website launched and stable.** Gate came off 2026-09-09, real orders
      through it, launch-readiness checklist retired 2026-09-12.
- [x] **Node installed** — v23.7.0. Non-LTS; if npm ever misbehaves, switch to
      Node 22 rather than debugging it.
- [x] **Project created in a folder outside the website repo.** This matters:
      `deploy-sftp.sh` mirrors everything inside `teepsaa/` to `public_html`, so
      anything in there gets published to the live site.
- [x] **`server.url` set to `https://vendor.teepsaa.com`** in
      `capacitor.config.json`. This is the trick that makes Route 1 cheap — the
      app's origin *is* vendor.teepsaa.com, so sessions, cookies and CSRF all
      keep working with zero backend changes.
- [x] **`www/index.html` stub created.** Capacitor demands a local web dir even
      when loading a remote URL. Nothing ever reads it.
- [x] **Android platform added** — `npx cap add android`.
- [x] **Java sorted.** See the box below; this cost an hour, don't re-learn it.

Next up, in order:

- [ ] **Finish the Gradle sync in Android Studio**, selecting JDK 21 when asked.
- [ ] **Create an emulator** — Device Manager → Pixel 7 → an **arm64** system
      image (Apple Silicon). Then press ▶ Run.
- [ ] **See the vendor portal running with no address bar.** That's Route 1
      working. Everything after it is polish and push.

---

## Environment gotchas — read before debugging anything

**Java 21, not 25, not 1.8.** Three Javas are on this Mac and only one works:

- System Java is **1.8** from 2023. Android's build plugin needs 11+, so a
  Terminal build fails with "Dependency requires at least JVM runtime version
  11". Don't uninstall it; it's just not the one to use.
- Android Studio ships its own **JDK 25**. Gradle's `--version` accepts it, but
  the Android plugin caps at 24, so Android Studio itself refuses it: "Gradle
  8.14.3 is incompatible with the Gradle JVM version 25." Don't use it.
- **Homebrew openjdk 21 is the one.** Both Terminal and Android Studio are
  pointed at it. If Android Studio ever re-asks for a Gradle JVM, that path is
  in the table above.

Terminal is pointed at it by two lines at the end of `~/.zshrc`:

```bash
export JAVA_HOME="/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home"
export PATH="$JAVA_HOME/bin:$PATH"
```

`~/.zshrc` is only read when a Terminal window **starts**, so after editing it,
open a new window — checking in the old one shows the old Java and looks like
failure.

**`npx cap sync` after every config change.** Edit `capacitor.config.json` or
add a plugin, then run it. Skip it and you'll swear the change didn't work. This
is the most common snag in the whole workflow.

**`npm audit` warnings are noise.** The vulnerabilities it reports are in build
tooling that runs on your Mac and never reaches the app or the server. **Do not
run `npm audit fix`** — it changes dependency versions and is a known way to
break a working Capacitor install.

**The Basic Auth gate is not in the way.** The original checklist said to remove
it before testing. Ignore that — it was narrowed to `admin.teepsaa.com` only on
2026-09-09 (`.htaccess:105`), and `teepsaa.com` and `vendor.teepsaa.com` are
explicitly exempt. Leave the admin lock alone.

**Changing the app name later** means editing `appName` in
`capacitor.config.json` *and* `android/app/src/main/res/values/strings.xml`
(`app_name` and `title_activity_main`), because `cap add android` already copied
it in. The App ID cannot change at all once the app is on Google Play.

---

# Two apps, not one — already decided

Build **teepsaa** (buyers) and **teepsaa vendor** separately.

- It's the industry standard: Amazon Shopping / Amazon Seller, Etsy / Etsy
  Seller, Lazada / Lazada Seller Center, and Shopee — the marketplace Cambodian
  vendors already know — all split shopping from selling tools.
- It matches the architecture exactly. Buyers and vendors are already separate
  tables with separate login portals, and vendors can't use the cart at all. One
  app would bolt two disjoint experiences behind one login.
- The extra cost is modest — both share the same PHP backend and API. It's two
  store listings and two builds, and the vendor app is far smaller: dashboard,
  orders, products, messages, no browsing or checkout.
- It's a better pitch. "Install teepsaa vendor and hear a ding when you get an
  order" beats "log into a website" — and that's the line you'll be using with
  the prospects in your canvassing tool.

**Vendor app first**, and that's what this file is. Push-on-new-order is the
killer feature and it pairs directly with the canvassing. The buyer app is the
same work again with `server.url` and the App ID changed, so the toolchain gets
learned on the app that pays for itself.

What the split changes downstream:

- [ ] **Apply Route 1 and Route 2 per app** — the vendor app skips buyer screens
      and vice versa.
- [ ] **Use `device_tokens.role` to route pushes** — vendor notifications go to
      this app, buyer notifications to the shopping app.
- [ ] **Prepare two store listings on each store** — icons, screenshots and
      descriptions in English and Khmer, for both apps.
- [ ] **Register two Firebase apps.** They can live in one Firebase project.

## Still to settle

- [ ] **Check the store names are available** — "teepsaa" and "teepsaa vendor"
      on both Google Play and the App Store.
- [ ] **Decide which Google account owns Firebase.** This account owns your push
      infrastructure permanently, so don't use a throwaway.

---

# Route 1 — Capacitor app loading vendor.teepsaa.com (Android first)

## 1a. Setup — done

Kept for the record; see "Where things stand" above.

- [x] Install Node.js
- [x] Create the Capacitor project in a new folder outside the website repo
- [x] Set `server.url` to `https://vendor.teepsaa.com`
- [x] ~~Remove the Basic Auth gate~~ — not needed, admin-only since 2026-09-09
- [ ] **Test the whole flow in the Android emulator** — log in, view orders,
      dispatch one, add a product, send a message. Anything broken here is
      broken on the real site too.

## 1b. Make it feel like an app, not a wrapped site

This section is the difference between an app people keep and one they delete.

- [ ] **Add an app icon and splash screen** at both densities. You already have
      `images/teepsaa-icon-180.png`, `-192.png` and `-512.png` in the website
      repo, generated for the canvassing home-screen app — same source, same
      treatment.
- [ ] **Handle the Android back button.** By default it exits the app. It should
      navigate back through history and only exit from the top-level page. This
      is the single most-noticed "this is just a website" tell.
- [ ] **Match the status bar colour to the header** so the top of the screen
      doesn't look like two different apps.
- [ ] **Open external links in the system browser** — Mapbox attribution,
      Telegram links, anything off-domain. Letting them load inside the WebView
      traps users with no way back.
- [ ] **Disable pinch-zoom and overscroll bounce.** Both read as "webby"
      instantly.
- [ ] **Add a friendly offline screen.** When data drops, the WebView shows a
      raw Chrome error page by default. Replace it with something that says "no
      connection" in teepsaa's voice.

## 1c. Push notifications

The single biggest "real app" feature, and the reason a vendor installs it.

- [ ] **Create a free Firebase project and register the Android app** in it.
- [ ] **Add the Capacitor push plugin and request notification permission.** Ask
      at a moment that makes sense — after login, not on first launch.
- [ ] **Create a `device_tokens` table** — user id, role, fcm_token, platform,
      created/last-seen timestamps. Role is what decides which app a push goes
      to. Schema changes are hand-applied on the server; `database/` is excluded
      from the deploy.
- [ ] **Add an endpoint the app POSTs its FCM token to after login.** Tokens
      rotate, so upsert on the token rather than inserting blindly.
- [ ] **Write a `send_push()` helper** — a curl call to the FCM API, following
      the same shape as `send_email()` in `config/mail.php` so it's familiar and
      testable.
- [ ] **Call `send_push()` everywhere a notification row is already created.**
      That's the complete list and you don't need to invent new events: vendor
      gets new order, refund request, low stock.
- [ ] **Make tapping a notification open the right page** (deep link), not just
      the home screen. A push that dumps you on the dashboard wastes the tap.

## 1d. Native camera

- [ ] **Wire product photo upload and ABA QR upload to the native picker** via
      the Capacitor camera plugin, falling back to the normal file input on web.
      Keep `js/photo-shrink.js` in the path — phone photos are still
      multi-megabyte and the byte budget still applies.

## 1e. Ship to Google Play

- [ ] **Buy a Google Play developer account** — $25, one time.
- [ ] **Make a signed release build, and back up the signing keystore in two
      places.** Losing the keystore means you can never update the app again;
      you'd have to publish a new listing and lose every install. **This is the
      one irreversible step in the whole document.**
- [ ] **Prepare the store listing** — screenshots in English and Khmer, the
      description, and a feature graphic.
- [ ] **Supply a privacy policy URL.** The site's existing `/privacy/` page
      works.
- [ ] **Fill in the data-safety form.** Declare what you actually collect:
      account data, delivery addresses, photos.
- [ ] **Release to the internal testing track first**, install it on a real phone
      from the Play link, then promote to production. Don't go straight to
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
- [ ] **Then vendor** — dashboard stats, products CRUD and photo upload, orders,
      dispatch, coupons, messages, settings. This is the vendor app's whole
      surface.
- [ ] **Then catalog** — home sections, search, product detail, business page,
      categories. Read-only, so the safest place to shake out JSON shapes, but
      it's the buyer app that needs it.
- [ ] **Then buyer** — cart CRUD, checkout, addresses, orders and status,
      wishlist, reviews, refunds, messages, notifications, settings.
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
      Xcode is ~12GB; don't install it until you're actually here.
- [ ] **Build for iOS via Capacitor and test on a real iPhone**, not just the
      simulator.
- [ ] **Submit for App Store review.** The dedicated-UI build is what satisfies
      Apple's "minimum functionality" rule — the rule that rejects bare website
      wrappers, and the entire reason Route 2 exists.
- [ ] **Release the same dedicated-UI build on Android**, replacing the Route 1
      wrapper.

---

# Then the buyer app

Same folder pattern, one folder over: `teepsaa-buyer-app`. Change two things
from this project and the rest of Route 1 repeats.

- [ ] **`appId`: `com.teepsaa.buyer`**, `appName`: `teepsaa`
- [ ] **`server.url`: `https://teepsaa.com`**
- [ ] Then 1b through 1e again, plus the buyer half of the API in 2a.
