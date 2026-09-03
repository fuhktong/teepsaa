<?php

// Sections a "custom" admin can be granted, grouped the same way the nav is.
// 'admins' (manage other admins) is intentionally excluded — that stays super-only,
// so a super can never accidentally hand out the ability to create more supers.
// 'audit' (the activity log) is excluded for the same reason inverted: it is the
// oversight tool, so the people being overseen must not be able to hold it.
const ADMIN_SECTION_GROUPS = [
    'Admin'     => ['vendors' => 'Vendors', 'buyers' => 'Buyers', 'products' => 'Products', 'categories' => 'Categories', 'reviews' => 'Reviews'],
    'Orders'    => ['orders' => 'Orders', 'refunds' => 'Refunds', 'accounting' => 'Accounting', 'payments' => 'Payments', 'payouts' => 'Payouts'],
    'Marketing' => ['promo-codes' => 'Promo Codes', 'coupons' => 'Coupons', 'banners' => 'Banners', 'careers' => 'Careers', 'vendor-map' => 'Vendor Map', 'buyer-map' => 'Buyer Map', 'prospects' => 'Canvassing'],
    'Content'   => ['content' => 'Pages', 'faq' => 'FAQ'],
    'Messages'  => ['messages' => 'Messages'],
];

// Landing page for each permission, used to find a safe landing spot for a
// denied request or a bare /admin/ visit. Ordered like the subnav tab row —
// the first permission the admin holds is where they land.
const ADMIN_SECTION_HOME = [
    'orders' => '/admin/orders.php', 'refunds' => '/admin/refunds.php',
    'payments' => '/admin/payments.php', 'payouts' => '/admin/payouts.php',
    'vendors' => '/admin/vendors.php', 'messages' => '/admin/messages/',
    'buyers' => '/admin/buyers.php', 'products' => '/admin/products.php',
    'reviews' => '/admin/reviews.php', 'categories' => '/admin/categories.php',
    'accounting' => '/admin/accounting.php',
    'promo-codes' => '/admin/promo-codes.php', 'coupons' => '/admin/coupons.php', 'banners' => '/admin/banners.php',
    'prospects' => '/admin/prospects/',
    'vendor-map' => '/admin/vendor-map.php', 'buyer-map' => '/admin/buyer-map.php',
    'careers' => '/admin/careers.php',
    'content' => '/admin/content.php', 'faq' => '/admin/faq.php',
];

function admin_all_sections(): array {
    return array_merge(...array_values(ADMIN_SECTION_GROUPS));
}

// ── Admin identity ────────────────────────────────────────────────────
// The admin session is namespaced: 'admin_id' / 'admin_role' /
// 'admin_permissions', never 'user_id' / 'role' / 'is_admin'. Buyer and
// vendor logins write the latter set, so all three roles can be signed in
// in one browser at once and no login knocks another one out. It also
// closes the hole where a stale is_admin flag left admin pages reachable
// with a buyer's user_id.
function admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function admin_id(): int {
    return (int)($_SESSION['admin_id'] ?? 0);
}

// True when the current request is for the admin panel itself, which is what
// decides whether the header/footer render in admin mode — not session state,
// since the visitor may hold a buyer session in the same browser.
function admin_area_request(): bool {
    if (defined('IS_ADMIN_SUBDOMAIN') && IS_ADMIN_SUBDOMAIN) {
        return true;
    }
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    return str_starts_with($path, '/admin/') || str_starts_with($path, '/login-admin/');
}

// Defaults to NOT super. Both writers of admin_role (login-admin.php and
// admin_device_restore) always set it, so this default is unreachable today —
// but if a future path ever sets admin_id without admin_role, the safe answer
// is "least privilege", not "full access".
function admin_is_super(): bool {
    return ($_SESSION['admin_role'] ?? 'custom') === 'super';
}

function admin_can(string $section): bool {
    if ($section === 'settings') {
        return true;
    }
    if (admin_is_super()) {
        return true;
    }
    if ($section === 'admins' || $section === 'audit') {
        return false;
    }
    return in_array($section, $_SESSION['admin_permissions'] ?? [], true);
}

function admin_home_url(): string {
    // Never '/admin/' — that is the dispatcher calling this function.
    if (admin_is_super()) {
        return '/admin/orders.php';
    }
    foreach (ADMIN_SECTION_HOME as $section => $url) {
        if (admin_can($section)) {
            return $url;
        }
    }
    return '/admin/settings.php';
}

function admin_require(string $section): void {
    if (!admin_can($section)) {
        header('Location: ' . admin_home_url() . '?denied=1');
        exit;
    }
}

// ── Remembered devices ────────────────────────────────────────────────
require_once __DIR__ . '/admin-device.php';

// Rebuild an admin session from a "remember this device" cookie.
//
// This runs at include time so the ~60 admin pages that already do
// `if (empty($_SESSION['admin_id'])) redirect` need no change at all. It is
// guarded hard on purpose: only inside the admin area, only when nobody is
// signed in, only when the cookie is actually there, and only when the page
// has already loaded a database handle. A buyer page can never mint an admin
// session as a side effect of including this file.
if (empty($_SESSION['admin_id'])
    && isset($_COOKIE[ADMIN_DEVICE_COOKIE])
    && session_status() === PHP_SESSION_ACTIVE
    && isset($pdo) && $pdo instanceof PDO
    && admin_area_request()) {
    admin_device_restore($pdo);
}
