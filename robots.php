<?php
// Generated rather than static because all three hosts — teepsaa.com,
// vendor.teepsaa.com and admin.teepsaa.com — share one document root, so a
// plain robots.txt file would hand the vendor and admin sites the buyer
// site's crawl rules. .htaccess rewrites /robots.txt here.
header('Content-Type: text/plain; charset=utf-8');

$host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

// Anything that isn't the public buyer site is closed to crawlers outright.
if (!in_array($host, ['teepsaa.com', 'www.teepsaa.com', 'localhost', '127.0.0.1'], true)) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}
?>
User-agent: *

# Staff and machine-only areas
Disallow: /admin/
Disallow: /api/
Disallow: /config/
Disallow: /database/
Disallow: /cron/

# Buying flow — per-person, never useful in search results
Disallow: /cart/
Disallow: /checkout/
Disallow: /orders-buyer/
Disallow: /settings-buyer/
Disallow: /wishlist/
Disallow: /messages-buyer/
Disallow: /order-status/
Disallow: /refund-status/
Disallow: /review/

# Vendor portal (also lives on vendor.teepsaa.com, which is fully disallowed)
Disallow: /analytics/
Disallow: /settings-vendor/
Disallow: /business-vendor/
Disallow: /orders-vendor/
Disallow: /messages-vendor/
Disallow: /products/
Disallow: /submit/

# Sign-in, sign-up and one-time links
Disallow: /login-buyer/
Disallow: /login-vendor/
Disallow: /login-admin/
Disallow: /register-buyer/
Disallow: /register-vendor/
Disallow: /logout/
Disallow: /verify-email/
Disallow: /resend-verification/
Disallow: /forgot-password-buyer/
Disallow: /forgot-password-vendor/
Disallow: /reset-password-buyer/
Disallow: /reset-password-vendor/
Disallow: /unsubscribe/
Disallow: /support-thread/

# Preference endpoints — they redirect back and have no content
Disallow: /currency/
Disallow: /lang/

# /uploads/ is deliberately NOT disallowed: product photos belong in Google
# Images, and the sitemap lists them.

Sitemap: https://teepsaa.com/sitemap.xml
