<?php
$current  = basename($_SERVER['PHP_SELF']);
$lang     = $_SESSION['lang']     ?? 'km';
$currency = $_SESSION['currency'] ?? 'USD';

if (!isset($t)) {
    $t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';
}

// The header reads the DB for a logged-in user's cart count and unread
// badges. Static pages (privacy, about, terms, …) don't load the DB
// config, so ensure $pdo exists when it's actually needed.
if (!isset($pdo) && !empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';
}
if (!function_exists('admin_can') && ($_SESSION['role'] ?? '') === 'admin') {
    require_once __DIR__ . '/../config/admin-auth.php';
}

if (!function_exists('_avatar_svg')) {
    function _avatar_svg(int $uid, ?int $colorIdx = null, int $size = 26): string {
        $p = [
            ['#4a86e8', '#a4c2f4'],
            ['#e06055', '#f4b8b4'],
            ['#f6b026', '#ffd966'],
            ['#57bb8a', '#a8d5b5'],
            ['#8e63ce', '#c3a6e8'],
        ][($colorIdx !== null ? $colorIdx : abs($uid)) % 5];
        $cid = 'avc' . abs($uid) . 's' . $size;
        return '<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" aria-hidden="true">'
            . '<defs><clipPath id="' . $cid . '"><circle cx="20" cy="20" r="20"/></clipPath></defs>'
            . '<circle cx="20" cy="20" r="20" fill="' . $p[0] . '"/>'
            . '<g clip-path="url(#' . $cid . ')">'
            . '<circle cx="20" cy="15" r="8" fill="' . $p[1] . '"/>'
            . '<ellipse cx="20" cy="36" rx="13" ry="10" fill="' . $p[1] . '"/>'
            . '</g>'
            . '</svg>';
    }
}

$activeFlag = $lang === 'km'
    ? '<img src="/flags/kh.svg" width="28" height="18" alt="Khmer" style="display:block">'
    : '<img src="/flags/us.svg" width="28" height="18" alt="English" style="display:block">';
?>
<script>window.T = <?= json_encode([
    'st_pending'           => $t['js_st_pending'],
    'st_paid'              => $t['js_st_paid'],
    'st_dispatched'        => $t['js_st_dispatched'],
    'st_delivered'         => $t['js_st_delivered'],
    'st_completed'         => $t['js_st_completed'],
    'st_refund_requested'  => $t['js_st_refund_requested'],
    'st_return_approved'   => $t['js_st_return_approved'],
    'st_return_dispatched' => $t['js_st_return_dispatched'],
    'st_return_received'   => $t['js_st_return_received'],
    'st_refunded'          => $t['js_st_refunded'],
    'order_cancelled'      => $t['js_order_cancelled'],
    'refund_rejected'      => $t['js_refund_rejected'],
    'session_expired'      => $t['js_session_expired'],
    'login_again'          => $t['js_login_again'],
    'order_updated'        => $t['js_order_updated'],
    'refresh_error'        => $t['js_refresh_error'],
    'no_notifications'     => $t['js_no_notifications'],
    'loading'              => $t['js_loading'],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php
$isVendorHeader = ($_SESSION['role'] ?? '') === 'vendor';
$isBuyerHeader  = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer';
?>
<header<?= ($_SESSION['role'] ?? '') === 'admin' ? ' class="admin-header"' : ($isVendorHeader ? ' class="vendor-header"' : ($isBuyerHeader ? ' class="buyer-header"' : '')) ?>>
    <div class="header-inner">
        <a href="/" class="site-name"><img src="/images/<?= $lang === 'km' ? 'teepsaa_logo_khm.png' : 'teepsaa_logo_eng_myriad.png' ?>" alt="teepsaa"></a>
        <form class="header-search" method="GET" action="/search/">
            <input type="search" name="q" placeholder="<?= $t['search_placeholder'] ?>" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Search"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
        </form>
        <button class="hamburger-btn" id="hamburger-btn" aria-label="Menu" aria-expanded="false">
            <svg class="hamburger-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            <svg class="close-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <nav>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <?php
                        $amStmt = $pdo->prepare("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL");
                        $amStmt->execute();
                        $adminUnread = (int)$amStmt->fetchColumn();
                        $aAvStmt = $pdo->prepare('SELECT avatar, avatar_color FROM admins WHERE id = ?');
                        $aAvStmt->execute([$_SESSION['user_id']]);
                        $aAvRow = $aAvStmt->fetch(PDO::FETCH_ASSOC);
                        $adminAvatarFile  = $aAvRow['avatar'] ?? '';
                        $adminAvatarColor = isset($aAvRow['avatar_color']) ? (int)$aAvRow['avatar_color'] : null;
                        $adminSection = $adminSection ?? '';
                    ?>
                    <?php if (admin_can('vendors')): ?><a href="/admin/" <?= $adminSection === 'admin' ? 'class="active"' : '' ?>><?= $t['nav_admin'] ?></a><?php endif; ?>
                    <?php if (admin_can('orders')): ?><a href="/admin/orders.php" <?= $adminSection === 'orders' ? 'class="active"' : '' ?>><?= $t['nav_orders'] ?></a><?php endif; ?>
                    <?php if (admin_can('promo-codes')): ?><a href="/admin/promo-codes.php" <?= $adminSection === 'marketing' ? 'class="active"' : '' ?>><?= $t['nav_marketing'] ?></a><?php endif; ?>
                    <?php if (admin_can('content')): ?><a href="/admin/content.php" <?= $adminSection === 'content' ? 'class="active"' : '' ?>><?= $t['nav_content'] ?></a><?php endif; ?>
                    <?php if (admin_can('messages')): ?><a href="/admin/messages/" <?= $adminSection === 'messages' ? 'class="active"' : '' ?>><?= $adminUnread ? $t['nav_messages'] . '&nbsp;<span class="nav-msg-badge">' . $adminUnread . '</span>' : $t['nav_messages'] ?></a><?php endif; ?>
                    <div class="user-menu">
                        <button class="user-avatar" id="user-avatar-btn" type="button" aria-label="Account menu">
                            <?php if ($adminAvatarFile): ?>
                                <img src="/uploads/<?= htmlspecialchars($adminAvatarFile) ?>" alt="">
                            <?php else: ?>
                                <?= _avatar_svg((int)$_SESSION['user_id'], $adminAvatarColor) ?>
                            <?php endif; ?>
                        </button>
                        <div class="user-dropdown" id="user-dropdown">
                            <?php if (admin_can('admins')): ?><a href="/admin/admins.php">Manage Admins</a><?php endif; ?>
                            <a href="/admin/settings.php"><?= $t['nav_settings'] ?></a>
                            <a href="/logout/logout.php"><?= $t['nav_logout'] ?></a>
                        </div>
                    </div>
                <?php elseif (($_SESSION['role'] ?? '') === 'vendor'): ?>
                    <?php
                        $initial    = strtoupper(mb_substr($_SESSION['user_name'] ?? 'V', 0, 1));
                        $avatarFile = $_SESSION['user_avatar'] ?? '';
                        $vmStmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages sm JOIN support_threads t ON t.id = sm.thread_id WHERE t.sender_id = ? AND t.sender_role = \'vendor\' AND sm.sender = \'admin\' AND sm.read_at IS NULL');
                        $vmStmt->execute([$_SESSION['user_id']]);
                        $vendorUnread = (int)$vmStmt->fetchColumn();
                        $vendorPath    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
                        $vendorSection = '';
                        if (strpos($vendorPath, '/orders-vendor/') === 0)        $vendorSection = 'orders';
                        elseif (strpos($vendorPath, '/products/') === 0)        $vendorSection = 'products';
                        elseif (strpos($vendorPath, '/messages-vendor/') === 0) $vendorSection = 'messages';
                        elseif (strpos($vendorPath, '/dashboard-vendor/') === 0) $vendorSection = 'analytics';
                        $vNotifStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
                        $vNotifStmt->execute(['vendor', $_SESSION['user_id']]);
                        $vNotifCount = (int)$vNotifStmt->fetchColumn();
                    ?>
                    <a href="/orders-vendor/" class="<?= $vendorSection === 'orders' ? 'active' : '' ?>"><?= $t['nav_orders'] ?></a>
                    <a href="/products/" class="<?= $vendorSection === 'products' ? 'active' : '' ?>"><?= $t['nav_products'] ?></a>
                    <a href="/messages-vendor/" class="<?= $vendorSection === 'messages' ? 'active' : '' ?>"><?= $vendorUnread ? $t['nav_messages'] . '&nbsp;<span class="nav-msg-badge">' . $vendorUnread . '</span>' : $t['nav_messages'] ?></a>
                    <a href="/dashboard-vendor/" class="<?= $vendorSection === 'analytics' ? 'active' : '' ?>"><?= $t['nav_vendor'] ?></a>
                    <div class="bell-wrap">
                        <button class="bell-btn" id="bell-btn" type="button" aria-label="Notifications">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </button>
                        <span class="bell-badge" id="bell-badge"<?= $vNotifCount === 0 ? ' style="display:none"' : '' ?>><?= $vNotifCount > 9 ? '9+' : $vNotifCount ?></span>
                        <div class="bell-dropdown" id="bell-dropdown">
                            <div class="bell-dropdown-head">
                                <span class="bell-dropdown-title"><?= $t['nav_notifications'] ?></span>
                                <button class="bell-mark-all" id="bell-mark-read" type="button"><?= $t['nav_mark_all_read'] ?></button>
                            </div>
                            <div class="bell-items" id="bell-items"><p class="bell-empty">Loading…</p></div>
                        </div>
                    </div>
                    <div class="user-menu">
                        <button class="user-avatar" id="user-avatar-btn" type="button" aria-label="Account menu">
                            <?php if ($avatarFile): ?>
                                <img src="/uploads/<?= htmlspecialchars($avatarFile) ?>" alt="">
                            <?php else: ?>
                                <?= _avatar_svg((int)$_SESSION['user_id'], $_SESSION['user_avatar_color'] ?? null) ?>
                            <?php endif; ?>
                        </button>
                        <div class="user-dropdown" id="user-dropdown">
                            <a href="/dashboard-vendor/settings/"><?= $t['nav_settings'] ?></a>
                            <a href="/logout/logout.php"><?= $t['nav_logout'] ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                        $cartStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE buyer_user_id = ?');
                        $cartStmt->execute([$_SESSION['user_id']]);
                        $cartCount = (int)$cartStmt->fetchColumn();
                        $cartBadge = $cartCount > 9 ? '9+' : ($cartCount > 0 ? (string)$cartCount : '');
                        $bmStmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages sm JOIN support_threads t ON t.id = sm.thread_id WHERE t.sender_id = ? AND t.sender_role = \'buyer\' AND sm.sender = \'admin\' AND sm.read_at IS NULL');
                        $bmStmt->execute([$_SESSION['user_id']]);
                        $buyerUnread = (int)$bmStmt->fetchColumn();
                        $initial    = strtoupper(mb_substr($_SESSION['user_name'] ?? 'B', 0, 1));
                        $avatarFile = $_SESSION['user_avatar'] ?? '';
                        $bNotifStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
                        $bNotifStmt->execute(['buyer', $_SESSION['user_id']]);
                        $bNotifCount = (int)$bNotifStmt->fetchColumn();
                        $buyerPath    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
                        $buyerSection = '';
                        if (strpos($buyerPath, '/dashboard-buyer/') === 0)   $buyerSection = 'orders';
                        elseif (strpos($buyerPath, '/wishlist/') === 0)     $buyerSection = 'wishlist';
                        elseif (strpos($buyerPath, '/messages-buyer/') === 0) $buyerSection = 'messages';
                    ?>
                    <a href="/dashboard-buyer/" class="<?= $buyerSection === 'orders' ? 'active' : '' ?>"><?= $t['nav_orders'] ?></a>
                    <a href="/wishlist/" class="<?= $buyerSection === 'wishlist' ? 'active' : '' ?>"><?= $t['nav_wishlist'] ?></a>
                    <a href="/messages-buyer/" class="<?= $buyerSection === 'messages' ? 'active' : '' ?>"><?= $buyerUnread ? $t['nav_messages'] . '&nbsp;<span class="nav-msg-badge">' . $buyerUnread . '</span>' : $t['nav_messages'] ?></a>
                    <div class="bell-wrap">
                        <button class="bell-btn" id="bell-btn" type="button" aria-label="Notifications">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </button>
                        <span class="bell-badge" id="bell-badge"<?= $bNotifCount === 0 ? ' style="display:none"' : '' ?>><?= $bNotifCount > 9 ? '9+' : $bNotifCount ?></span>
                        <div class="bell-dropdown" id="bell-dropdown">
                            <div class="bell-dropdown-head">
                                <span class="bell-dropdown-title"><?= $t['nav_notifications'] ?></span>
                                <button class="bell-mark-all" id="bell-mark-read" type="button"><?= $t['nav_mark_all_read'] ?></button>
                            </div>
                            <div class="bell-items" id="bell-items"><p class="bell-empty">Loading…</p></div>
                        </div>
                    </div>
                    <div class="cart-wrap">
                        <a href="/cart/" aria-label="Cart" class="cart-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></a>
                        <?php if ($cartBadge !== ''): ?>
                            <span class="cart-badge"><?= $cartBadge ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-menu">
                        <button class="user-avatar" id="user-avatar-btn" type="button" aria-label="Account menu">
                            <?php if ($avatarFile): ?>
                                <img src="/uploads/<?= htmlspecialchars($avatarFile) ?>" alt="">
                            <?php else: ?>
                                <?= _avatar_svg((int)$_SESSION['user_id'], $_SESSION['user_avatar_color'] ?? null) ?>
                            <?php endif; ?>
                        </button>
                        <div class="user-dropdown" id="user-dropdown">
                            <a href="/dashboard-buyer/settings/"><?= $t['nav_settings'] ?></a>
                            <a href="/logout/logout.php"><?= $t['nav_logout'] ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <a href="/login-buyer/"><?= $t['nav_login'] ?></a>
            <?php endif; ?>

            <div class="lang-menu">
                <button class="lang-btn" id="lang-menu-btn" type="button" aria-label="Language and currency">
                    <span id="lang-btn-flag"><?= $activeFlag ?></span>
                </button>
                <div class="lang-dropdown" id="lang-dropdown">
                    <p class="lang-dropdown-label"><?= $t['lang_label'] ?></p>
                    <button class="lang-option <?= $lang === 'km' ? 'lang-option--active' : '' ?>" data-action="lang" data-value="km">
                        <img src="/flags/kh.svg" width="22" height="14" alt=""> ខ្មែរ
                    </button>
                    <button class="lang-option <?= $lang === 'en' ? 'lang-option--active' : '' ?>" data-action="lang" data-value="en">
                        <img src="/flags/us.svg" width="22" height="14" alt=""> English
                    </button>
                    <p class="lang-dropdown-label"><?= $t['currency_label'] ?></p>
                    <button class="lang-option <?= $currency === 'KHR' ? 'lang-option--active' : '' ?>" data-action="currency" data-value="KHR">
                        ៛ KHR – Cambodian Riel
                    </button>
                    <button class="lang-option <?= $currency === 'USD' ? 'lang-option--active' : '' ?>" data-action="currency" data-value="USD">
                        $ USD – US Dollar
                    </button>
                </div>
            </div>
        </nav>
    </div>

    <div class="mobile-nav" id="mobile-nav">
        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <?php if (admin_can('vendors')): ?><a href="/admin/" class="mobile-nav-link <?= ($adminSection ?? '') === 'admin' ? 'active' : '' ?>"><?= $t['nav_admin'] ?></a><?php endif; ?>
                <?php if (admin_can('orders')): ?><a href="/admin/orders.php" class="mobile-nav-link <?= ($adminSection ?? '') === 'orders' ? 'active' : '' ?>"><?= $t['nav_orders'] ?></a><?php endif; ?>
                <?php if (admin_can('promo-codes')): ?><a href="/admin/promo-codes.php" class="mobile-nav-link <?= ($adminSection ?? '') === 'marketing' ? 'active' : '' ?>"><?= $t['nav_marketing'] ?></a><?php endif; ?>
                <?php if (admin_can('content')): ?><a href="/admin/content.php" class="mobile-nav-link <?= ($adminSection ?? '') === 'content' ? 'active' : '' ?>"><?= $t['nav_content'] ?></a><?php endif; ?>
                <?php if (admin_can('messages')): ?><a href="/admin/messages/" class="mobile-nav-link <?= ($adminSection ?? '') === 'messages' ? 'active' : '' ?>"><?= ($adminUnread ?? 0) > 0 ? $t['nav_messages'] . ' (' . ($adminUnread ?? 0) . ')' : $t['nav_messages'] ?></a><?php endif; ?>
                <?php if (admin_can('admins')): ?><a href="/admin/admins.php" class="mobile-nav-link">Manage Admins</a><?php endif; ?>
                <a href="/admin/settings.php" class="mobile-nav-link"><?= $t['nav_settings'] ?></a>
                <a href="/logout/logout.php" class="mobile-nav-link"><?= $t['nav_logout'] ?></a>
            <?php elseif (($_SESSION['role'] ?? '') === 'vendor'): ?>
                <a href="/orders-vendor/" class="mobile-nav-link <?= $vendorSection === 'orders' ? 'active' : '' ?>"><?= $t['nav_orders'] ?></a>
                <a href="/products/" class="mobile-nav-link <?= $vendorSection === 'products' ? 'active' : '' ?>"><?= $t['nav_products'] ?></a>
                <a href="/messages-vendor/" class="mobile-nav-link <?= $vendorSection === 'messages' ? 'active' : '' ?>"><?= $vendorUnread ? $t['nav_messages'] . ' (' . $vendorUnread . ')' : $t['nav_messages'] ?></a>
                <a href="/dashboard-vendor/" class="mobile-nav-link <?= $vendorSection === 'analytics' ? 'active' : '' ?>"><?= $t['nav_vendor'] ?></a>
                <a href="/dashboard-vendor/settings/" class="mobile-nav-link"><?= $t['nav_settings'] ?></a>
                <a href="/logout/logout.php" class="mobile-nav-link"><?= $t['nav_logout'] ?></a>
            <?php else: ?>
                <a href="/cart/" class="mobile-nav-link"><?= $t['nav_cart'] ?><?= ($cartBadge ?? '') !== '' ? ' (' . $cartBadge . ')' : '' ?></a>
                <a href="/dashboard-buyer/" class="mobile-nav-link <?= ($buyerSection ?? '') === 'orders' ? 'active' : '' ?>"><?= $t['nav_orders'] ?></a>
                <a href="/wishlist/" class="mobile-nav-link <?= ($buyerSection ?? '') === 'wishlist' ? 'active' : '' ?>"><?= $t['nav_wishlist'] ?></a>
                <a href="/messages-buyer/" class="mobile-nav-link <?= ($buyerSection ?? '') === 'messages' ? 'active' : '' ?>"><?= ($buyerUnread ?? 0) > 0 ? $t['nav_messages'] . ' (' . $buyerUnread . ')' : $t['nav_messages'] ?></a>
                <a href="/dashboard-buyer/settings/" class="mobile-nav-link"><?= $t['nav_settings'] ?><?= ($bNotifCount ?? 0) > 0 ? ' (' . ($bNotifCount ?? 0) . ')' : '' ?></a>
                <a href="/logout/logout.php" class="mobile-nav-link"><?= $t['nav_logout'] ?></a>
            <?php endif; ?>
        <?php else: ?>
            <a href="/login-buyer/" class="mobile-nav-link"><?= $t['nav_login'] ?></a>
        <?php endif; ?>
    </div>
</header>

<script>
(function () {
    var SCROLL_KEY = 'teepsaa_scroll';

    function postAndReload(url, data) {
        sessionStorage.setItem(SCROLL_KEY, window.scrollY);
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fetch(url, { method: 'POST', body: fd }).then(function () { location.reload(); });
    }

    var hamburgerBtn = document.getElementById('hamburger-btn');
    var mobileNav    = document.getElementById('mobile-nav');

    if (hamburgerBtn && mobileNav) {
        hamburgerBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = mobileNav.classList.toggle('open');
            hamburgerBtn.setAttribute('aria-expanded', open);
            hamburgerBtn.classList.toggle('open', open);
        });
        document.addEventListener('click', function () {
            mobileNav.classList.remove('open');
            hamburgerBtn.classList.remove('open');
            hamburgerBtn.setAttribute('aria-expanded', 'false');
        });
        mobileNav.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    var avatarBtn  = document.getElementById('user-avatar-btn');
    var avatarDrop = document.getElementById('user-dropdown');
    var langBtn    = document.getElementById('lang-menu-btn');
    var langDrop   = document.getElementById('lang-dropdown');

    if (avatarBtn && avatarDrop) {
        avatarBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            avatarDrop.classList.toggle('open');
            langDrop.classList.remove('open');
        });
    }

    langBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        langDrop.classList.toggle('open');
        if (avatarDrop) avatarDrop.classList.remove('open');
    });

    document.addEventListener('click', function () {
        langDrop.classList.remove('open');
        if (avatarDrop) avatarDrop.classList.remove('open');
    });

    langDrop.addEventListener('click', function (e) { e.stopPropagation(); });

    document.querySelectorAll('.lang-option').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = this.dataset.action;
            var value  = this.dataset.value;
            if (action === 'lang') {
                var newFlag = value === 'km'
                    ? '<img src="/flags/kh.svg" width="28" height="18" alt="Khmer" style="display:block">'
                    : '<img src="/flags/us.svg" width="28" height="18" alt="English" style="display:block">';
                document.getElementById('lang-btn-flag').innerHTML = newFlag;
                postAndReload('/lang/set.php', { lang: value });
            } else {
                postAndReload('/currency/set.php', { currency: value });
            }
        });
    });
})();
</script>
<script src="/js/notifications.js"></script>
