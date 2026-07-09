<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('vendors');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

csrf_verify();

$action   = $_POST['action'] ?? '';
$vendorId = (int)($_POST['vendor_id'] ?? 0);

if (!$vendorId) {
    header('Location: /admin/');
    exit;
}

$returnUrl = '/admin/vendor.php?id=' . $vendorId;

if ($action === 'suspend') {
    $reason = trim($_POST['ban_reason'] ?? '');
    if (!$reason) {
        $_SESSION['admin_error'] = 'A reason is required to suspend an account.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $pdo->prepare('UPDATE vendors SET banned = 1, ban_reason = ?, banned_at = NOW() WHERE id = ?')
        ->execute([$reason, $vendorId]);
    $_SESSION['admin_success'] = 'Vendor account suspended.';

} elseif ($action === 'unsuspend') {
    $pdo->prepare('UPDATE vendors SET banned = 0, ban_reason = NULL, banned_at = NULL WHERE id = ?')
        ->execute([$vendorId]);
    $_SESSION['admin_success'] = 'Vendor suspension lifted.';

} elseif ($action === 'save_note') {
    $note = trim($_POST['admin_note'] ?? '');
    $pdo->prepare('UPDATE vendors SET admin_note = ? WHERE id = ?')
        ->execute([$note ?: null, $vendorId]);
    $_SESSION['admin_success'] = 'Note saved.';

} elseif ($action === 'set_royalty_waived') {
    $businessId = (int)($_POST['business_id'] ?? 0);
    if (!$businessId) {
        header('Location: ' . $returnUrl);
        exit;
    }
    $waived = isset($_POST['royalty_waived']) ? 1 : 0;
    $pdo->prepare('UPDATE businesses SET royalty_waived = ? WHERE id = ?')
        ->execute([$waived, $businessId]);
    $_SESSION['admin_success'] = $waived ? 'Royalty waived for this vendor.' : 'Royalty waiver removed.';

} elseif ($action === 'set_company_royalty_add_on') {
    $businessId = (int)($_POST['business_id'] ?? 0);
    if (!$businessId) {
        header('Location: ' . $returnUrl);
        exit;
    }
    $rate = round((float)($_POST['royalty_add_on'] ?? 0) / 100, 4);
    if ($rate < 0 || $rate > 1) {
        $_SESSION['admin_error'] = 'Rate must be between 0% and 100%.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $pdo->prepare('UPDATE businesses SET royalty_add_on = ? WHERE id = ?')
        ->execute([$rate, $businessId]);
    $_SESSION['admin_success'] = 'Company royalty add-on saved.';
}

header('Location: ' . $returnUrl);
exit;
