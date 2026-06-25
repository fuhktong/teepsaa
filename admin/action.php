<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

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

$redirect = $vendorId ? '/admin/vendor.php?id=' . $vendorId : '/admin/';
header('Location: ' . $redirect);
exit;
