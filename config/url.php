<?php
// Guard for URLs that other people supply and we render as links.
//
// Grab tracking links are typed in by a vendor (analytics/dispatch.php) or a
// buyer (orders-buyer/return-dispatch.php) and are then shown as clickable
// hrefs to the *other* party and to admins. htmlspecialchars() escapes the
// quotes but does nothing about the scheme, and FILTER_VALIDATE_URL on its own
// happily passes "javascript://teepsaa.com/%0aalert(document.cookie)" and
// "data:text/html;base64,...". Clicking one of those in the admin panel runs
// the author's script in the session that releases payouts.
//
// So: validate on the way in, and run stored values back through this on the
// way out — rows written before this existed are still in the database.
function safe_external_url(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') return null;

    // "https:///foo" parses but has nowhere to go.
    if (!parse_url($url, PHP_URL_HOST)) return null;

    return $url;
}
