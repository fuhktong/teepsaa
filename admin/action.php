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
require __DIR__ . '/../config/notify.php';

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

$id       = (int) ($_POST['id'] ?? 0);
$action   = $_POST['action'] ?? '';
$vendorId = (int)($_POST['vendor_id'] ?? 0);

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: /admin/');
    exit;
}

$approved = $action === 'approve' ? 1 : -1;

$stmt = $pdo->prepare('UPDATE businesses SET approved = ? WHERE id = ?');
$stmt->execute([$approved, $id]);

// Start vendor trial clock on approval if business has a promo code
if ($action === 'approve') {
    $bStmt = $pdo->prepare('SELECT promo_code_id, trial_starts_at FROM businesses WHERE id = ?');
    $bStmt->execute([$id]);
    $biz = $bStmt->fetch();
    if ($biz && $biz['promo_code_id'] && !$biz['trial_starts_at']) {
        $trialStart = date('Y-m-d H:i:s');
        $trialEnd   = date('Y-m-d H:i:s', strtotime('+3 months'));
        $pdo->prepare('UPDATE businesses SET trial_starts_at = ?, trial_ends_at = ?, royalty_free_threshold = 100.00 WHERE id = ?')
            ->execute([$trialStart, $trialEnd, $id]);
    }
}

// Email the vendor about the decision
$vStmt = $pdo->prepare(
    'SELECT v.name AS vendor_name, v.email, b.name AS business_name
     FROM businesses b JOIN vendors v ON v.id = b.user_id
     WHERE b.id = ?'
);
$vStmt->execute([$id]);
$owner = $vStmt->fetch();
if ($owner && $owner['email']) {
    $templateKey = $action === 'approve' ? 'business_approved' : 'business_rejected';
    $ctaUrl      = $action === 'approve'
        ? 'https://teepsaa.com/products/'
        : 'https://teepsaa.com/dashboard-vendor/settings/';
    [$subj, $html] = render_email_template($pdo, $templateKey, [
        'name'     => htmlspecialchars($owner['vendor_name']),
        'business' => htmlspecialchars($owner['business_name']),
        'cta_url'  => $ctaUrl,
    ]);
    if ($html !== '') send_email($owner['email'], $subj, $html);
}

$redirect = $vendorId ? '/admin/vendor.php?id=' . $vendorId : '/admin/';
header('Location: ' . $redirect);
exit;
