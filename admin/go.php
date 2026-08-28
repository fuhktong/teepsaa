<?php
// Landing pad for links that arrive from outside the site — today that means
// the two daily emails, cron/admin-digest.php and cron/admin-activity-digest.php.
//
// Why it exists: the session cookie and the "remember this device" cookie are
// both SameSite=Strict, so the browser withholds them on any navigation that
// began on another site. An email client is another site, so clicking a digest
// link lands on an admin page with no session and bounces you to the login
// form even though you are signed in on that phone.
//
// This page needs no cookie of its own. It loads, and then *its own* script
// navigates to the real target. That second request is initiated by a document
// on admin.teepsaa.com, so it counts as same-site and both cookies ride along.
// A PHP redirect cannot do this — with a 302 the initiator is still the email.
//
// Two rules keep it safe:
//
//   1. Never start a session here. With the real cookie withheld,
//      session_start() would mint a fresh empty session and its Set-Cookie
//      would overwrite the real one — the click would genuinely sign you out.
//      Nothing this file includes calls session_start(); keep it that way.
//
//   2. `to` is a key into the list below, never a URL. There is no input that
//      reaches the Location, so this cannot become an open redirect.

require __DIR__ . '/../config/subdomain.php';   // 404s this path off the admin host

// Every destination the digest links to. Adding an email link means adding a
// key here first.
const GO_TARGETS = [
    'home'        => '/admin/',
    'payments'    => '/admin/payments.php',
    'refunds'     => '/admin/refunds.php?status=refund_requested',
    'refunds-pay' => '/admin/refunds.php?status=return_received',
    'businesses'  => '/admin/?status=pending',
    'messages'    => '/admin/messages/',
    'payouts'     => '/admin/payouts.php',
    'spot-checks' => '/admin/?status=spot_check',
    'prospects'   => '/admin/prospects/?sort=followup',
    'audit'       => '/admin/audit.php',
];

// An unknown key is a stale email, not an attack — send them to the dashboard.
$target = GO_TARGETS[(string)($_GET['to'] ?? '')] ?? GO_TARGETS['home'];

// A doorway, not a page.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<!-- The no-JS fallback. One second, so the script below normally wins: it uses
     replace() and so leaves nothing for the back button to bounce off. -->
<meta http-equiv="refresh" content="1; url=<?= htmlspecialchars($target, ENT_QUOTES) ?>">
<title>Opening admin…</title>
<style>
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    color: #374151;
    background-color: #f9fafb;
  }
  p { margin: 0; font-size: 0.95rem; }
  a { color: #1d4ed8; }
</style>
</head>
<body>
<p>Opening the admin panel…</p>
<p><a href="<?= htmlspecialchars($target, ENT_QUOTES) ?>">Continue</a></p>
<script>location.replace(<?= json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
</body>
</html>
