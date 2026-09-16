# teepsaa — Building the app with bundled Capacitor

Written 2026-09-14 for someone who has never built a mobile app. Nothing here
has been built yet. Work top to bottom, one checkbox at a time.

This is **Route 2** from `teepsaa-todos-mobile-app.md`, written out step by
step. The vendor app goes first (already decided), and the buyer app repeats the
same steps at the end.

---

# The big picture, in one minute

Right now `teepsaa-vendor-app` is a **wrapper**. It's a phone app with a hidden
browser inside that opens vendor.teepsaa.com. Every tap loads a web page from
the server, just like Chrome would.

A **bundled** app works differently. The screens (HTML, CSS, JS) live **inside
the app itself**, on the phone. The app only goes to the server for **data**,
like "give me my orders" or "save this product." The server sends back plain
data (JSON), not web pages, and the app draws the screen.

Think of a restaurant:

- **Wrapper** = the phone orders a finished meal (a whole page) every time.
- **Bundled** = the phone has its own kitchen (the screens) and only orders
  ingredients (the data).

Why bother:

- **Apple accepts it.** Apple rejects apps that are just a website in a frame.
- **It's faster.** Screens open instantly because they're already on the phone.
- **It feels like a real app** — no white flashes between pages.

The cost: you build the screens a second time in JavaScript, and the PHP site
needs a small set of new data endpoints (the **API**). The website itself does
not change and keeps working exactly as it does today.

---

# Table of contents

| #   | Chapter                                            | What it does, simply                                                                 |
| --- | -------------------------------------------------- | ------------------------------------------------------------------------------------ |
| 1   | **Words you'll see**                               | A tiny dictionary so the rest of this file makes sense.                              |
| 2   | **Your Mac tools**                                 | Checks the programs you need are installed. Mostly done already.                     |
| 3   | **Turn the wrapper into a bundled app**            | Changes the app folder so it carries its own screens instead of loading the website. |
| 4   | **The daily routine**                              | The 3 commands you run every time you change the app and want to see it.             |
| 5   | **Teach the website to talk to the app (the API)** | Adds data endpoints to the PHP site that answer in JSON.                             |
| 6   | **Logging in from the app**                        | Lets a vendor sign in and stay signed in.                                            |
| 7   | **Build the screens**                              | Recreates each vendor page as an app screen, one at a time.                          |
| 8   | **English and Khmer**                              | Reuses the site's existing translations inside the app.                              |
| 9   | **Make it feel like a phone app**                  | Icon, splash screen, back button, status bar, offline message.                       |
| 10  | **Camera and photos**                              | Lets vendors take product photos with the phone camera.                              |
| 11  | **Push notifications**                             | Makes the phone ding when a vendor gets a new order.                                 |
| 12  | **Test on a real phone**                           | Puts the app on your own Android phone before anyone else sees it.                   |
| 13  | **Publish on Google Play**                         | Gets the app into the Android store.                                                 |
| 14  | **Publish on the App Store**                       | Gets the app onto iPhones.                                                           |
| 15  | **Updating the app later**                         | The rules for changing things once real people have it installed.                    |
| 16  | **Build the buyer app**                            | Repeats everything for the shopping app.                                             |

---

# Chapter 1 — Words you'll see

- **Capacitor** — the tool that takes HTML/CSS/JS and turns it into a real
  Android and iPhone app. It also lets your JS use phone features like the
  camera.
- **Bundled** — the screens are packed inside the app, not loaded from the
  website.
- **API** — a set of PHP files on the server that answer with data instead of
  pages. Example: `/api/v1/orders.php` answers with a list of orders.
- **JSON** — the format that data comes back in. It looks like
  `{"id": 12, "status": "shipped"}`.
- **Endpoint** — one API file. `login.php` is an endpoint, `orders.php` is
  another.
- **Token** — a long random password the server gives the app after login. The
  app sends it with every request to prove who it is. It replaces the session
  cookie the website uses.
- **Vite** — a small tool that packs your app's HTML/JS/CSS into one tidy
  folder (`dist`) for Capacitor to put in the app. You never have to understand
  how it works.
- **npm / npx** — commands that come with Node. `npm` installs tools, `npx`
  runs them.
- **Emulator** — a fake phone that runs on your Mac, inside Android Studio.
- **Android Studio** — the program that builds the Android app and runs the
  emulator.
- **Xcode** — the same thing for iPhone. Mac only.
- **Plugin** — an add-on that gives your app a phone feature (camera, push,
  back button).
- **AAB** — the file you upload to Google Play. It's the finished Android app.

---

# Chapter 2 — Your Mac tools

Most of this was already done for the wrapper app. Just confirm each one.

- [x] **Node is installed.** Open Terminal and type `node -v`. You should see a
      version number (you have v23.7.0).
- [x] **Android Studio is installed** and opens without errors.
- [x] **Java 21 is the one in use.** Type `java -version` in a **new** Terminal
      window. It must say 21. If it doesn't, read the "Java 21" box in
      `teepsaa-vendor-app-todos.md` — that problem already cost an hour once.
- [x] **An emulator exists.** In Android Studio: Device Manager → Pixel 7 →
      pick an **arm64** system image.
- [x] **Don't install Xcode yet.** It's about 12GB and you only need it in
      Chapter 14.

---

# Chapter 3 — Turn the wrapper into a bundled app

You're changing the existing `teepsaa-vendor-app` folder. It lives **next to**
the website folder, never inside it — the deploy uploads everything inside
`teepsaa/` to the live site.

```
043 teepsaa/
├── teepsaa/               ← the website (PHP). The API goes here.
└── teepsaa-vendor-app/    ← the app (HTML/JS). The screens go here.
```

## 3a. Take a safety snapshot first

- [ ] **Open Terminal and go to the app folder:**
      `    cd "/Users/dustintaylor/Documents/programming/mywebsites/043 teepsaa/teepsaa-vendor-app"`
- [ ] **Turn it into a git repo so you can always undo:**
      `    git init
printf "node_modules/\ndist/\n" > .gitignore
git add .
git commit -m "Wrapper app before switching to bundled"`

## 3b. Add Vite

- [ ] **Install it:**
      `    npm install --save-dev vite`
- [ ] **Open `package.json`** and replace the `"scripts"` section with:
      `json
"scripts": {
  "dev": "vite",
  "build": "vite build"
},
`
      Now `npm run dev` shows the app in your Mac browser, and `npm run build`
      packs it into the `dist` folder.

## 3c. Create the app's first screen

- [ ] **Create `index.html` in the app folder** (the top level, not in `www`):
      `html
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
      <title>teepsaa vendor</title>
      <link rel="stylesheet" href="/src/style.css">
    </head>
    <body>
      <div id="app">Hello teepsaa</div>
      <script type="module" src="/src/main.js"></script>
    </body>
    </html>
    `
- [ ] **Create a `src` folder** with two empty files in it: `main.js` and
      `style.css`. All your app code will go in `src`.

## 3d. Point Capacitor at the new folder

- [ ] **Open `capacitor.config.json`** and make it look exactly like this:
      `json
{
  "appId": "com.teepsaa.vendor",
  "appName": "teepsaa vendor",
  "webDir": "dist"
}
`
      Two changes: `webDir` is now `dist`, and the whole `server` block is
      **gone**. That `server.url` line is what made it a wrapper. Removing it is
      the moment the app becomes bundled.
- [ ] **Delete the old `www` folder.** Nothing uses it anymore.
- [ ] **Never change `appId`.** Once the app is on Google Play it's permanent.

## 3e. See it on the emulator

- [ ] **Build and copy into Android:**
      `    npm run build
npx cap sync android`
- [ ] **Open Android Studio:** `npx cap open android`, then press the green ▶
      Run button.
- [ ] **You should see "Hello teepsaa"** on the fake phone. That's a bundled
      app. Everything from here is filling it in.
- [ ] **Commit:** `git add . && git commit -m "Switch to bundled"`

---

# Chapter 4 — The daily routine

Two ways to look at your work. Use the fast one most of the time.

**Fast way — in your Mac browser** (for layout, text, styling):

```
npm run dev
```

Open the address it prints (`http://localhost:5173`). Save a file and the page
updates by itself. Phone features (camera, push) won't work here — that's
normal.

**Real way — on the emulator** (for anything phone-related, and before every
commit):

```
npm run build
npx cap sync android
npx cap run android
```

**The #1 mistake:** changing something and forgetting `npm run build` and
`npx cap sync`. The emulator keeps showing the old version and you'll think
your change didn't work. When in doubt, run all three again.

**Don't run `npm audit fix`.** The warnings are about Mac-only build tools, and
"fixing" them is a known way to break Capacitor.

---

# Chapter 5 — Teach the website to talk to the app (the API)

This chapter happens in the **website** folder (`teepsaa/`), not the app folder.
You're adding new files only. No existing page changes.

**Why the app can't just use the website's login:** the website remembers you
with a session cookie set to `SameSite=Strict`. A bundled app runs from its own
address (`https://localhost` on Android), so the phone refuses to send that
cookie. The fix is a **token** — the app gets one at login and sends it with
every request.

## 5a. One address for the API

- [ ] **Every endpoint lives under `https://teepsaa.com/api/v1/`.** `/api/` is
      already allowed on every subdomain in `config/subdomain.php`, so nothing
      redirects.
- [ ] **`v1` matters.** Old versions of the app stay on people's phones for
      months. If you ever need to change how an endpoint works in a breaking
      way, you make `v2` and leave `v1` alone.

## 5b. The shared helper — `config/api.php`

One file that every API endpoint `require`s. It does four jobs:

- [ ] **Allow the app to call the server (CORS).** Browsers and apps block
      calls to another address unless the server says yes. Send
      `Access-Control-Allow-Origin` for exactly these three origins, and no
      others: - `https://localhost` — the Android app - `capacitor://localhost` — the iPhone app - `http://localhost:5173` — your Mac browser during `npm run dev`
- [ ] **Answer the "preflight" check.** Before a real request, the phone sends
      an `OPTIONS` request asking permission. If the method is `OPTIONS`, reply
      with status 204 and the allowed headers (`Authorization, Content-Type`)
      and stop.
- [ ] **Always reply in JSON.** A small `api_json($data, $status)` function
      that sets `Content-Type: application/json`, echoes, and exits.
- [ ] **Check the token.** An `api_require_vendor($pdo)` function that reads
      `Authorization: Bearer <token>`, looks it up, and returns the vendor id —
      or replies 401 and stops. No `session_start()` and no CSRF check in API
      files: there's no cookie, so there's nothing for CSRF to attack.

## 5c. The tokens table

- [ ] **Create an `api_tokens` table:** `id`, `user_id`, `role` (`vendor` or
      `buyer`), `token_hash`, `device_name`, `created_at`, `last_used_at`.
- [ ] **Store only a hash of the token** (`hash('sha256', $token)`), never the
      token itself. If the database ever leaks, the tokens are useless.
- [ ] **Apply it by hand on the server.** `database/` is excluded from the
      deploy, same as every other schema change.

## 5d. Your first endpoints, in this order

Test each one before starting the next. Nothing else works until login works.

- [ ] **`api/v1/ping.php`** — returns `{"ok": true}`. Deploy, open
      `https://teepsaa.com/api/v1/ping.php` in a browser, see the JSON. This
      proves the folder, the helper and the deploy all work.
- [ ] **`api/v1/auth/login.php`** — takes email + password. Copy the checks
      from `login-vendor/login-vendor.php` exactly: rate limit, password check,
      suspended check, email-verified check, `deleted_at IS NULL`. On success,
      make a token with `bin2hex(random_bytes(32))`, save its hash, and return
      the token plus the vendor's name and language.
- [ ] **`api/v1/auth/logout.php`** — deletes this token's row.
- [ ] **`api/v1/me.php`** — returns who the token belongs to. This is how the
      app checks "am I still logged in?" when it opens.

**The one rule for every endpoint after this:** an API endpoint is a form with
no browser in front of it. Every check the website's PHP does for that action
— ownership, required fields, limits, rate limiting — must be copied into the
endpoint. If a website page only lets a vendor see their own orders, the
endpoint must check that too.

---

# Chapter 6 — Logging in from the app

Back in the **app** folder.

- [ ] **Install the storage plugin** (it remembers the token after the app
      closes):
      `    npm install @capacitor/preferences
npx cap sync`
- [ ] **Make one file, `src/api.js`, that every screen uses to talk to the
      server.** It holds the address `https://teepsaa.com/api/v1/`, adds the
      `Authorization: Bearer` header when a token exists, and turns the reply
      into JSON. If any reply is 401, it wipes the token and shows the login
      screen. Keeping all of this in one file means you fix a problem once.
- [ ] **Build the login screen** — email, password, a button. On success, save
      the token with Preferences and show the dashboard.
- [ ] **When the app opens**, check for a saved token and call `me.php`. Valid →
      dashboard. Missing or rejected → login screen.
- [ ] **Logout** calls `logout.php`, deletes the saved token, shows login.
- [ ] **Test it on the emulator:** log in, close the app completely, reopen it.
      You should still be logged in.

---

# Chapter 7 — Build the screens

The website's PHP pages can't be reused directly — they build HTML on the
server. Each one gets rebuilt as a JS screen that asks the API for data and
draws it. You **can** copy CSS straight from the site so it looks like teepsaa.

## 7a. How the app switches screens

- [ ] **Use one HTML page and swap what's inside `#app`.** Each screen is a JS
      file in `src/screens/` (`dashboard.js`, `orders.js`, …) with one function
      that draws it. A tiny `src/router.js` decides which screen to show.
- [ ] **Keep a bottom tab bar** — Dashboard, Orders, Products, Messages,
      Settings. That's what makes it feel like an app instead of a website.

## 7b. The recipe for every screen

Do this for each screen, one at a time, start to finish before the next:

1. **Write the API endpoint** in the website folder (Chapter 5 rules apply).
2. **Deploy** and test it in the browser or with `curl`.
3. **Write the screen** in the app folder. Show "Loading…", call the endpoint,
   draw the result.
4. **Handle the three unhappy cases:** no internet, an error from the server,
   and an empty list ("No orders yet").
5. **Test on the emulator**, then commit both folders.

## 7c. The vendor app's screens, in order

Easiest and most useful first. Each one matches a folder in the website.

- [ ] **Dashboard** — sales numbers and recent orders (`analytics/`)
- [ ] **Orders list** (`orders-vendor/index.php`)
- [ ] **One order** — details, mark as dispatched (`orders-vendor/order.php`)
- [ ] **Refunds** (`orders-vendor/refund.php`)
- [ ] **Notifications** (`notifications/`, `api/notifications/`)
- [ ] **Messages** — thread list, one thread, reply (`messages-vendor/`)
- [ ] **Products list** — show, hide, archive, delete (`products/`)
- [ ] **Add / edit product** — without photos for now (`submit/`,
      `products/save.php`)
- [ ] **Coupons** (`products/coupon-action.php`)
- [ ] **Business profile** — address, banner, ABA QR (`business-vendor/`)
- [ ] **Settings** — profile, password, avatar (`settings-vendor/`)
- [ ] **Delete account** (`settings-vendor/delete-action.php`). **Both stores
      require this inside the app.** Apple will reject the app without it.
- [ ] **Register and forgot password** — or, simpler for version 1, a button
      that opens those website pages in the phone's browser (Chapter 9).

---

# Chapter 8 — English and Khmer

- [ ] **Make a small PHP endpoint `api/v1/lang.php?l=en`** that loads
      `lang/en.php` (or `km.php`) and returns it as JSON. One set of
      translations, used by both the website and the app — they can never drift
      apart.
- [ ] **In the app, load the strings once at startup** and save a copy with
      Preferences so the app still has words when offline.
- [ ] **Make a `t('nav_orders')` helper** in `src/i18n.js` that every screen
      uses instead of typing English.
- [ ] **Add a language switch in Settings**, and send the choice to the server
      so emails come in the same language.
- [ ] **Bundle a Khmer font** in the app (copy it from the site's `fonts/`
      folder into the app) so Khmer looks right even when the phone's own font
      doesn't.

---

# Chapter 9 — Make it feel like a phone app

Install these plugins together, then do each item:

```
npm install @capacitor/app @capacitor/status-bar @capacitor/splash-screen @capacitor/network @capacitor/browser
npm install --save-dev @capacitor/assets
npx cap sync
```

- [ ] **App icon and splash screen.** Create an `assets` folder in the app with
      `icon.png` (1024×1024) and `splash.png` (2732×2732, logo centered). Start
      from the teepsaa logo. Then run `npx capacitor-assets generate` — it makes
      every size both stores need.
- [ ] **Back button (Android).** Use the `App` plugin's `backButton` event: go
      back one screen, and only exit the app from the dashboard. By default it
      exits immediately, which feels broken.
- [ ] **Status bar.** Use `StatusBar` to colour the very top strip of the phone
      to match the app header.
- [ ] **No pinch-zoom.** Already handled by the `viewport` line from Chapter 3c.
      Add `overscroll-behavior: none;` to `body` in `src/style.css` to stop the
      rubber-band bounce.
- [ ] **Offline message.** Use `Network` to notice when the connection drops and
      show a friendly "No connection" bar in teepsaa's voice. The screens are on
      the phone already, so the app still opens — only the data is missing.
- [ ] **Outside links open in the phone's browser.** Telegram, Mapbox, the
      privacy page, register: open them with `Browser.open()`, never inside the
      app.
- [ ] **The notch.** On newer phones, add
      `padding-top: env(safe-area-inset-top)` to the header and
      `padding-bottom: env(safe-area-inset-bottom)` to the tab bar so nothing
      hides behind the camera cutout or the home bar.

---

# Chapter 10 — Camera and photos

- [ ] **Install:** `npm install @capacitor/camera` then `npx cap sync`.
- [ ] **Add a "Take photo / Choose photo" button** on the add/edit product screen
      and on the ABA QR screen.
- [ ] **Shrink before uploading.** Phone photos are several megabytes. Copy the
      logic from the website's `js/photo-shrink.js` into the app so the same
      size limits apply.
- [ ] **Write the upload endpoint** (`api/v1/products/photo.php`) using the same
      checks as the website's upload code in `config/upload.php` — file type,
      size, and that the product belongs to this vendor.
- [ ] **Test on the emulator** — it has a fake camera — and later on a real phone.
- [ ] **Explain why you need the camera.** Android asks automatically. For
      iPhone (Chapter 14) you must write a one-line reason in the iOS settings,
      or Apple rejects the app.

---

# Chapter 11 — Push notifications

This is the feature that makes vendors install the app. Do it after the core
screens work.

## 11a. Firebase (Google's free push service)

- [ ] **Decide which Google account owns it** — it owns your push setup forever,
      so not a throwaway.
- [ ] **Go to console.firebase.google.com**, create a project called `teepsaa`.
- [ ] **Add an Android app** with package name `com.teepsaa.vendor`. Download
      the `google-services.json` it gives you and put it in
      `teepsaa-vendor-app/android/app/`.
- [ ] **Make a service account key** — Project settings → Service accounts →
      Generate new private key. This file lets the **server** send pushes.
      Treat it like a password: put it on the server by hand, add it to the
      deploy's exclude list like `config/smtp.php`, never commit it.

## 11b. The app side

- [ ] **Install:** `npm install @capacitor/push-notifications` then
      `npx cap sync`.
- [ ] **Ask permission after login**, not the moment the app opens. Say why
      first: "Get a ding when you receive an order."
- [ ] **Send the phone's push token to the server** — a new endpoint
      `api/v1/device-token.php`.
- [ ] **When a notification is tapped**, open the right screen (that order, that
      message), not just the dashboard.

## 11c. The server side

- [ ] **Create a `device_tokens` table** — user id, role, fcm_token, platform,
      created and last-seen times. Apply by hand. If the same fcm_token arrives
      again, update the row instead of adding a new one.
- [ ] **Write `send_push()` in `config/push.php`**, shaped like `send_email()`
      in `config/mail.php`. It uses the service account key to get a Google
      access token, then calls Firebase's send API with curl.
- [ ] **Call `send_push()` from inside `notify()` in `config/notify.php`.**
      Every notification on the site (new order, refund request, dispatch…)
      already goes through that one function, so one line there covers them
      all. No new events to invent.
- [ ] **Delete tokens Firebase says are dead** (uninstalled app) so the table
      doesn't fill with junk.

---

# Chapter 12 — Test on a real phone

The emulator lies a little. Your own phone doesn't.

- [ ] **Turn on Developer mode** on your Android phone: Settings → About phone →
      tap "Build number" 7 times.
- [ ] **Turn on USB debugging** in the new Developer options menu.
- [ ] **Plug the phone into the Mac**, tap "Allow" on the phone.
- [ ] **Pick your phone** in Android Studio's device menu and press ▶ Run.
- [ ] **Walk through a real vendor day:** log in, get a test order from a buyer
      account, feel the push arrive, dispatch it, add a product with a camera
      photo, reply to a message, switch to Khmer, turn on airplane mode and
      back.
- [ ] **Try it on slow data**, not wifi. Most vendors in Cambodia will be on
      mobile data.

---

# Chapter 13 — Publish on Google Play

## 13a. The account

- [ ] **Sign up at play.google.com/console** — $25, one time.
- [ ] **Know the testing rule before you start.** New _personal_ developer
      accounts must run a **closed test with at least 12 testers for 14 days in
      a row** before Google lets you publish to everyone. Line up 12 people
      (vendors you've canvassed are perfect) early. An _organization_ account
      skips this but needs a D-U-N-S business number.

## 13b. The signing key — the one step you can't undo

- [ ] **In Android Studio:** Build → Generate Signed App Bundle → Android App
      Bundle → create a new keystore.
- [ ] **Write the keystore password down** in your password manager.
- [ ] **Back up the keystore file in two places** (e.g. `teepsaa-private` and a
      cloud drive). If you lose it, you can never update the app again. Never
      put it in either git repo.
- [ ] **Accept Google Play App Signing** when the console offers it. Google then
      keeps a backup of the real key, which makes a lost upload key recoverable.

## 13c. The store listing

- [ ] **Screenshots** from the emulator or your phone, in English and Khmer.
- [ ] **Short and full description**, English and Khmer.
- [ ] **Feature graphic** — 1024×500.
- [ ] **Privacy policy URL** — `https://teepsaa.com/privacy/`.
- [ ] **Account deletion URL** — Google asks for a web page where people can
      delete their account too. The settings page works.
- [ ] **Data safety form** — declare what you really collect: name, email,
      phone, addresses, photos, messages.
- [ ] **Content rating questionnaire** — a few minutes of yes/no questions.

## 13d. Release

- [ ] **Internal testing first** — upload the `.aab`, install it from the Play
      link on your own phone. Catches signing and permission problems for free.
- [ ] **Closed testing** — add your 12+ testers, wait the 14 days.
- [ ] **Production** — apply for access, then promote the build. Google's
      review usually takes a few days for a first app.

---

# Chapter 14 — Publish on the App Store

Only start once Android is live. Most of the work is already done — the same
screens run on iPhone.

- [ ] **Join the Apple Developer Program** at developer.apple.com — $99 a year.
- [ ] **Install Xcode** from the Mac App Store (~12GB, takes a while).
- [ ] **Add iPhone to the project:**
      `    npm install @capacitor/ios
npx cap add ios
npx cap sync ios
npx cap open ios`
- [ ] **Push on iPhone:** in the Apple developer site make an **APNs key**, then
      upload it to Firebase (Project settings → Cloud Messaging), and add the
      iOS app to the same Firebase project.
- [ ] **Camera reason:** in Xcode, add a "Privacy – Camera Usage Description"
      and "Privacy – Photo Library Usage Description" line, e.g. "teepsaa uses
      the camera to photograph your products."
- [ ] **Run it on a real iPhone** — plug it in and pick it in Xcode.
- [ ] **Create the app in App Store Connect**, fill in the listing (screenshots,
      descriptions, privacy details, privacy URL).
- [ ] **Give Apple a demo vendor login** in the review notes. Apple's reviewers
      must be able to sign in, or they reject.
- [ ] **Upload from Xcode** (Product → Archive → Distribute) and submit for
      review. Rejections are normal on a first app — read the reason, fix,
      resubmit.

---

# Chapter 15 — Updating the app later

- [ ] **Changing PHP (the API) = just deploy.** Phones get it immediately.
- [ ] **Changing screens (the app) = a new store upload.** Bump `versionCode`
      and `versionName` in `android/app/build.gradle` (and the version in Xcode
      for iPhone), build, upload, wait for review.
- [ ] **Never break `v1`.** Some people won't update for months. You can _add_
      fields to a JSON reply safely. Removing or renaming one breaks their app.
- [ ] **Add a minimum version check** — `me.php` can return
      `"min_version": "1.0.0"`, and the app shows "Please update" if it's
      older. Build this into version 1 so you have it when you need it.

---

# Chapter 16 — Build the buyer app

Same steps, one folder over. Do this only after the vendor app is live.

- [ ] **Create `teepsaa-buyer-app`** next to the other two folders, following
      Chapter 3 with `appId` `com.teepsaa.buyer` and `appName` `teepsaa`.
- [ ] **Copy `src/api.js`, `src/router.js`, `src/i18n.js`** and the Chapter 9
      polish from the vendor app.
- [ ] **Add the buyer API endpoints** — home, search, categories, product page,
      business page, cart, checkout, addresses, orders, wishlist, reviews,
      refunds, messages, settings. Login checks `role = 'buyer'` exactly.
- [ ] **Add a second Android app and iOS app in the same Firebase project**, and
      route pushes using `device_tokens.role`.
- [ ] **Chapters 12–14 again**, with its own store listings.
