<?php

// Sections a "custom" admin can be granted, grouped the same way the nav is.
// 'admins' (manage other admins) is intentionally excluded — that stays super-only,
// so a super can never accidentally hand out the ability to create more supers.
const ADMIN_SECTION_GROUPS = [
    'Admin'     => ['vendors' => 'Vendors', 'buyers' => 'Buyers', 'products' => 'Products', 'categories' => 'Categories', 'reviews' => 'Reviews'],
    'Orders'    => ['orders' => 'Orders', 'refunds' => 'Refunds', 'accounting' => 'Accounting', 'payments' => 'Payments', 'payouts' => 'Payouts'],
    'Marketing' => ['promo-codes' => 'Promo Codes', 'coupons' => 'Coupons', 'banners' => 'Banners', 'careers' => 'Careers', 'vendor-map' => 'Vendor Map', 'buyer-map' => 'Buyer Map'],
    'Content'   => ['content' => 'Pages', 'faq' => 'FAQ'],
    'Messages'  => ['messages' => 'Messages'],
];

// First accessible page for each section, used to find a safe landing spot for a denied request.
const ADMIN_SECTION_HOME = [
    'vendors' => '/admin/', 'buyers' => '/admin/buyers.php', 'products' => '/admin/products.php',
    'categories' => '/admin/categories.php', 'reviews' => '/admin/reviews.php',
    'orders' => '/admin/orders.php', 'refunds' => '/admin/refunds.php', 'accounting' => '/admin/accounting.php',
    'payments' => '/admin/payments.php', 'payouts' => '/admin/payouts.php',
    'promo-codes' => '/admin/promo-codes.php', 'coupons' => '/admin/coupons.php', 'banners' => '/admin/banners.php',
    'careers' => '/admin/careers.php', 'vendor-map' => '/admin/vendor-map.php', 'buyer-map' => '/admin/buyer-map.php',
    'content' => '/admin/content.php', 'faq' => '/admin/faq.php',
    'messages' => '/admin/messages/',
];

function admin_all_sections(): array {
    return array_merge(...array_values(ADMIN_SECTION_GROUPS));
}

function admin_is_super(): bool {
    return ($_SESSION['admin_role'] ?? 'super') === 'super';
}

function admin_can(string $section): bool {
    if ($section === 'settings') {
        return true;
    }
    if (admin_is_super()) {
        return true;
    }
    if ($section === 'admins') {
        return false;
    }
    return in_array($section, $_SESSION['admin_permissions'] ?? [], true);
}

function admin_home_url(): string {
    if (admin_is_super()) {
        return '/admin/';
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
