<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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

$defaultRedirect = $vendorId ? '/admin/vendor.php?id=' . $vendorId : '/admin/';

if ($action === 'add') {
    $businessId   = (int)($_POST['business_id'] ?? 0);
    $rateIncrease = round((float)($_POST['rate_increase'] ?? 0) / 100, 4);
    $note         = trim($_POST['admin_note'] ?? '') ?: null;
    $startDate    = $_POST['start_date'] ?? '';
    $endDate      = $_POST['end_date'] ?? '' ?: null;

    if (!$businessId || $rateIncrease <= 0 || $rateIncrease > 1) {
        $_SESSION['admin_error'] = 'Invalid penalty — rate must be between 0.1% and 100%.';
        header('Location: ' . $defaultRedirect);
        exit;
    }

    if (!$startDate || !strtotime($startDate)) $startDate = date('Y-m-d');
    if ($endDate && !strtotime($endDate)) $endDate = null;
    if ($endDate && $endDate <= $startDate) {
        $_SESSION['admin_error'] = 'End date must be after start date.';
        header('Location: ' . $defaultRedirect);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO vendor_penalties (business_id, rate_increase, admin_note, start_date, end_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$businessId, $rateIncrease, $note, $startDate, $endDate, $_SESSION['user_id']]);
    $_SESSION['admin_success'] = 'Penalty applied.';

} elseif ($action === 'remove') {
    $penaltyId = (int)($_POST['penalty_id'] ?? 0);
    if (!$penaltyId) {
        $_SESSION['admin_error'] = 'Invalid penalty.';
        header('Location: ' . $defaultRedirect);
        exit;
    }
    $pdo->prepare('UPDATE vendor_penalties SET cleared_at = NOW() WHERE id = ? AND cleared_at IS NULL')
        ->execute([$penaltyId]);
    $_SESSION['admin_success'] = 'Penalty removed.';
}

header('Location: ' . $defaultRedirect);
exit;
