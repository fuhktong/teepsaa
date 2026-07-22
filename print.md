# Test the notification sound (chime)

## First: which actions actually ding?

The chime plays when a NEW bell notification arrives while the recipient's tab
is open. Not every action makes a notification. Quick map:

- **Placing an order** — no ding for anyone (except a low-stock ding to the
  vendor if that item crosses its stock threshold). The order sits unpaid
  (`pending`) until admin confirms the ABA payment, so no one is asked to act yet.
- **Admin confirms payment** — dings the **buyer** (`payment_confirmed`) AND the
  **vendor** (`new_order`, "pack and dispatch it"). This is the vendor's cue to
  fulfill — the headline use case for the chime.
- **Buyer requests a refund** — dings the **vendor**.
- **Admin approves / rejects / completes a refund** — dings the **buyer**.
- **Buyer ships a return back** — dings the **vendor**.
- **Vendor marks return received** — dings the **buyer**.
- **Vendor dispatches an order** — dings the **buyer**.
- **Admin sends payout** — dings the **vendor**.

So to test a VENDOR ding, have a buyer request a refund.
To test a BUYER ding, have admin confirm payment or approve a refund.

## Three things must ALL be true or you hear nothing

1. The recipient's tab is open and in the FOREGROUND during the ~15s after the
   event. You cannot do the action and check the same account in one tab — the
   page must already be open when the notification arrives.
2. The new `js/notifications.js` is deployed AND hard-refreshed (browser caches
   the old one). Verify: DevTools > Sources > /js/notifications.js, search for
   `playChime`. If it's not there, you're on the old cached file.
3. You clicked the page at least once first (browsers block audio until one
   interaction).

## Reliable test recipe

1. Deploy `js/notifications.js`, then hard-refresh both browsers (Cmd-Shift-R).
2. Browser A (e.g. Chrome): log in as the BUYER. Leave the dashboard open and
   in front. Click somewhere on the page once.
3. Browser B (e.g. Safari, or an incognito window): log in as ADMIN. Approve
   the payment / a refund for that buyer's order.
4. Watch Browser A for up to 15 seconds — the red badge should appear AND the
   chime should play together.
5. Vendor side: same setup, but Browser A logged in as the VENDOR, and in
   Browser B request a refund as the buyer -> vendor dings.

## Isolation check — is it the sound or the timing?

Open DevTools console on any page with the bell, click the page once, then
paste this. It tests your browser/speakers directly, independent of the site
code. If you hear a short beep, audio works and the issue is deploy/timing
(items above). If silent, it's your system audio/output, not the code.

```js
var c=new(window.AudioContext||window.webkitAudioContext)(),o=c.createOscillator(),g=c.createGain();o.frequency.value=880;g.gain.value=0.2;o.connect(g);g.connect(c.destination);o.start();o.stop(c.currentTime+0.3);
```

# Why the chime isn't firing (diagnostic)

## Step 1 — is the new JS actually deployed?
The isolation beep above works even on the OLD file, so it does NOT prove the
new code is live. Check directly:
DevTools > Sources > /js/notifications.js > Cmd-F > search `playChime`.
- Not found  -> old/cached file. Deploy, then hard-refresh (Cmd-Shift-R).
- Found      -> code is live; go to Step 2.

## Step 2 — does the RED BADGE update live?
Trigger the event and watch the recipient's bell for ~15s WITHOUT refreshing.
- Badge does NOT appear/increment -> the new notification isn't seen live:
  JS not deployed, OR the tab was backgrounded (polling stalls when hidden),
  OR you refreshed the recipient page after triggering (resets the baseline).
- Badge updates but still SILENT -> detection works, but the audio context
  isn't unlocked on that tab. Click directly on the recipient page once, keep
  both windows visible side-by-side (don't minimize/cover it), trigger again.

# Change the sound — audition presets

Paste each into the DevTools console (click the page once first). Tell me the
letter you want and I'll set it as the notification chime.

## A — current: two-note bell (880 -> 1320, sine)
```js
(function(){var c=new(window.AudioContext||window.webkitAudioContext)();var n=c.currentTime;[[880,0],[1320,0.12]].forEach(function(p){var t=n+p[1],o=c.createOscillator(),g=c.createGain();o.type='sine';o.frequency.value=p[0];g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.18,t+0.02);g.gain.exponentialRampToValueAtTime(0.0001,t+0.5);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+0.55);});})();
```

## B — single soft bloop (gentle, low)
```js
(function(){var c=new(window.AudioContext||window.webkitAudioContext)();var t=c.currentTime,o=c.createOscillator(),g=c.createGain();o.type='sine';o.frequency.setValueAtTime(660,t);o.frequency.exponentialRampToValueAtTime(440,t+0.15);g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.2,t+0.02);g.gain.exponentialRampToValueAtTime(0.0001,t+0.4);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+0.45);})();
```

## C — three-note rising (cheerful)
```js
(function(){var c=new(window.AudioContext||window.webkitAudioContext)();var n=c.currentTime;[[659,0],[784,0.1],[1047,0.2]].forEach(function(p){var t=n+p[1],o=c.createOscillator(),g=c.createGain();o.type='sine';o.frequency.value=p[0];g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.16,t+0.02);g.gain.exponentialRampToValueAtTime(0.0001,t+0.35);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+0.4);});})();
```

## D — marimba pluck (woody, short, triangle)
```js
(function(){var c=new(window.AudioContext||window.webkitAudioContext)();var n=c.currentTime;[[880,0],[1174,0.09]].forEach(function(p){var t=n+p[1],o=c.createOscillator(),g=c.createGain();o.type='triangle';o.frequency.value=p[0];g.gain.setValueAtTime(0.0001,t);g.gain.exponentialRampToValueAtTime(0.22,t+0.005);g.gain.exponentialRampToValueAtTime(0.0001,t+0.25);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+0.3);});})();
```
