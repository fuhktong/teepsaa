<?php
// Home-screen app tags, shared by the four canvassing pages.
//
// Add to Home Screen on iOS needs nothing but these tags and an icon — there
// is deliberately no service worker, because canvassing is online-only and a
// stale cache on a live admin panel causes far more trouble than it saves.
//
// One thing to know: iOS gives a home-screen app its own cookie jar, separate
// from Safari's. The first launch always asks for the HTTP password and an
// admin login even if you are signed in in Safari — tick "Remember this
// device" there and it stays signed in.
?>
<!-- crossorigin="use-credentials" is not optional here: a manifest is fetched
     with credentials mode "omit" unless this attribute says otherwise, so the
     browser sends no Basic Auth header and the pre-launch gate answers 401.
     Without the manifest the home-screen app loses its name, icon and
     standalone display mode and just opens as a Safari tab. -->
<link rel="manifest" href="/admin/prospects/manifest.webmanifest" crossorigin="use-credentials">
<link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
<meta name="theme-color" content="#cc8a6c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Canvassing">
