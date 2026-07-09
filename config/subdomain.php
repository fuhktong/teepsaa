<?php
// Three-domain layout: teepsaa.com (buyers + public), vendor.teepsaa.com
// (vendor portal), admin.teepsaa.com (admin panel). Same codebase, same
// folder — this file decides which paths answer on which host and bounces
// requests that arrive at the wrong one. Loaded on every page via
// config/i18n.php (config/db.php is unmanaged on the server, so the hook
// must live in a deployed file).
//
// Flip to true ONLY after vendor.teepsaa.com and admin.teepsaa.com exist in
// hPanel and point at this same public_html folder. While false (and always
// on localhost/CLI) everything below is inert and the site behaves as a
// single domain. Session cookie sharing across subdomains is NOT here
// either — Hostinger disables .user.ini (user_ini.filename is empty), so
// every session_start() options block sets 'cookie_domain' to
// '.teepsaa.com' on *.teepsaa.com hosts (empty on localhost).
define('SUBDOMAINS_ENABLED', true);

$sdHost  = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$sdLocal = $sdHost === '' || in_array($sdHost, ['localhost', '127.0.0.1'], true);
$sdActive = SUBDOMAINS_ENABLED && !$sdLocal && PHP_SAPI !== 'cli';

define('IS_VENDOR_SUBDOMAIN', $sdActive && $sdHost === 'vendor.teepsaa.com');
define('IS_ADMIN_SUBDOMAIN',  $sdActive && $sdHost === 'admin.teepsaa.com');

// Cross-domain link prefixes. Empty while the layout is off so existing
// relative links keep working unchanged; use these for any link that must
// land on a specific domain (e.g. vendor links in emails).
define('BASE_URL_MAIN',   $sdActive ? 'https://teepsaa.com' : '');
define('BASE_URL_VENDOR', $sdActive ? 'https://vendor.teepsaa.com' : '');
define('BASE_URL_ADMIN',  $sdActive ? 'https://admin.teepsaa.com' : '');

if ($sdActive) {
    $sdPath  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    $sdQuery = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

    // Note /products/ (vendor management) vs /product/ (public detail page).
    $sdVendorPaths = [
        '/dashboard-vendor/', '/products/', '/orders-vendor/', '/submit/',
        '/messages-vendor/', '/login-vendor/', '/register-vendor/',
        '/forgot-password-vendor/', '/reset-password-vendor/', '/contact-vendor/',
    ];
    $sdAdminPaths = ['/admin/', '/login-admin/'];
    // Same-origin endpoints every domain needs (ajax, logout, shared flows).
    $sdNeutralPaths = [
        '/api/', '/lang/', '/currency/', '/logout/', '/cron/',
        '/verify-email/', '/resend-verification/',
    ];

    $sdIn = function (array $prefixes) use ($sdPath): bool {
        foreach ($prefixes as $p) {
            if (str_starts_with($sdPath, $p) || $sdPath === rtrim($p, '/')) return true;
        }
        return false;
    };
    $sdGo = function (string $target): void {
        header('Location: ' . $target, true, 302);
        exit;
    };

    if (!$sdIn($sdNeutralPaths)) {
        if ($sdIn($sdAdminPaths)) {
            // Admin paths exist only on the admin subdomain — 404 elsewhere.
            if (!IS_ADMIN_SUBDOMAIN) {
                http_response_code(404);
                exit('Not Found');
            }
        } elseif ($sdIn($sdVendorPaths)) {
            if (!IS_VENDOR_SUBDOMAIN) $sdGo(BASE_URL_VENDOR . $sdPath . $sdQuery);
        } else {
            // Buyer/public path.
            if (IS_ADMIN_SUBDOMAIN) {
                $sdGo($sdPath === '/' ? '/admin/' : BASE_URL_MAIN . $sdPath . $sdQuery);
            } elseif (IS_VENDOR_SUBDOMAIN) {
                $sdGo($sdPath === '/' ? '/dashboard-vendor/' : BASE_URL_MAIN . $sdPath . $sdQuery);
            } elseif ($sdPath === '/' && ($_SESSION['role'] ?? '') === 'vendor') {
                // Wrong door, right person: vendor on the main homepage goes to
                // their dashboard. Only the bare homepage — vendors may still
                // preview their public product/business pages on teepsaa.com.
                $sdGo(BASE_URL_VENDOR . '/dashboard-vendor/');
            }
        }
    }
}

unset($sdHost, $sdLocal, $sdActive, $sdPath, $sdQuery, $sdVendorPaths, $sdAdminPaths, $sdNeutralPaths, $sdIn, $sdGo);
