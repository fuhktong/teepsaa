<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/audit.php';
require __DIR__ . '/../config/notify.php';

if (empty($_SESSION['admin_id'])) {
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

// Email the vendor about a suspension change, if the admin ticked "notify".
// Unticked is the silent path. Returns false when there is nobody to mail or
// no template; send_email() handles SMTP failure itself by logging to
// mail.log, so a bad mailbox never blocks the suspension.
$notifyVendor = function (string $template, array $tokens) use ($pdo, $vendorId): bool {
    $stmt = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
    if (!$vendor || !$vendor['email']) return false;

    [$subj, $html] = render_email_template($pdo, $template, $tokens + [
        'name'    => htmlspecialchars($vendor['name']),
        'cta_url' => 'https://teepsaa.com/contact/',
    ]);
    if ($html === '') return false;

    try {
        send_email($vendor['email'], $subj, $html);
        return true;
    } catch (Throwable $e) {
        return false;
    }
};

if ($action === 'suspend') {
    $reason = trim($_POST['ban_reason'] ?? '');
    if (!$reason) {
        $_SESSION['admin_error'] = 'A reason is required to suspend an account.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $pdo->prepare('UPDATE vendors SET banned = 1, ban_reason = ?, banned_at = NOW() WHERE id = ?')
        ->execute([$reason, $vendorId]);
    audit_log($pdo, 'vendor.suspend', 'vendor', $vendorId, ['reason' => $reason]);

    if (!empty($_POST['notify_user'])) {
        $sent = $notifyVendor('vendor_suspended', ['reason' => htmlspecialchars($reason)]);
        $_SESSION['admin_success'] = $sent
            ? 'Vendor account suspended. The vendor has been emailed.'
            : 'Vendor account suspended, but the email could not be sent.';
    } else {
        $_SESSION['admin_success'] = 'Vendor account suspended (no email sent).';
    }

} elseif ($action === 'unsuspend') {
    $pdo->prepare('UPDATE vendors SET banned = 0, ban_reason = NULL, banned_at = NULL WHERE id = ?')
        ->execute([$vendorId]);
    audit_log($pdo, 'vendor.unsuspend', 'vendor', $vendorId);

    if (!empty($_POST['notify_user'])) {
        $sent = $notifyVendor('vendor_reinstated', ['cta_url' => 'https://vendor.teepsaa.com/login-vendor/']);
        $_SESSION['admin_success'] = $sent
            ? 'Vendor suspension lifted. The vendor has been emailed.'
            : 'Vendor suspension lifted, but the email could not be sent.';
    } else {
        $_SESSION['admin_success'] = 'Vendor suspension lifted (no email sent).';
    }

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
    audit_log($pdo, 'vendor.royalty_waived', 'business', $businessId, ['waived' => (bool)$waived, 'vendor_id' => $vendorId]);
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
    audit_log($pdo, 'vendor.royalty_add_on', 'business', $businessId, ['rate' => $rate, 'vendor_id' => $vendorId]);
    $_SESSION['admin_success'] = 'Company royalty add-on saved.';

} elseif ($action === 'spot_check_done') {
    $businessId = (int)($_POST['business_id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE businesses SET spot_checked_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$businessId, $vendorId]);
    $_SESSION['admin_success'] = $stmt->rowCount() ? 'Spot check marked done.' : 'Business not found.';

} elseif ($action === 'delete_business') {
    $businessId = (int)($_POST['business_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id, name, banner FROM businesses WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$businessId, $vendorId]);
    $business = $stmt->fetch();
    if (!$business) {
        $_SESSION['admin_error'] = 'Business not found.';
        header('Location: ' . $returnUrl);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('completed','cancelled','refunded','refund_rejected')");
    $stmt->execute([$businessId]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['admin_error'] = 'Cannot delete business — it has open orders. All orders must be completed, cancelled, or refunded first.';
        header('Location: ' . $returnUrl);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/';

    $stmt = $pdo->prepare('SELECT pp.filename FROM product_photos pp JOIN products p ON p.id = pp.product_id WHERE p.business_id = ?');
    $stmt->execute([$businessId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $filename) {
        if ($filename && file_exists($uploadDir . $filename)) @unlink($uploadDir . $filename);
    }
    // order_items.product_id is set NULL by FK; product_photos and cart_items cascade
    $pdo->prepare('DELETE FROM products WHERE business_id = ?')->execute([$businessId]);

    $stmt = $pdo->prepare('SELECT filename FROM photos WHERE business_id = ?');
    $stmt->execute([$businessId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $filename) {
        if ($filename && file_exists($uploadDir . $filename)) @unlink($uploadDir . $filename);
    }
    $pdo->prepare('DELETE FROM photos WHERE business_id = ?')->execute([$businessId]);

    if ($business['banner'] && file_exists($uploadDir . $business['banner'])) {
        @unlink($uploadDir . $business['banner']);
    }

    // Soft delete: the row (and its orders, reviews, coupons, penalties) is kept for accounting
    $pdo->prepare('UPDATE businesses SET deleted_at = NOW(), approved = -1, banner = NULL WHERE id = ?')
        ->execute([$businessId]);
    audit_log($pdo, 'business.delete', 'business', $businessId, ['vendor_id' => $vendorId, 'name' => $business['name']]);

    $vStmt = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
    $vStmt->execute([$vendorId]);
    if ($owner = $vStmt->fetch()) {
        [$subj, $html] = render_email_template($pdo, 'business_deleted', [
            'name'     => htmlspecialchars($owner['name']),
            'business' => htmlspecialchars($business['name']),
        ]);
        if ($html !== '') send_email($owner['email'], $subj, $html);
    }

    $_SESSION['admin_success'] = 'Business deleted. Order history is kept for accounting; the vendor account is still active.';
}

header('Location: ' . $returnUrl);
exit;
