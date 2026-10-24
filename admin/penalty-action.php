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
require __DIR__ . '/../config/strikes.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('vendors');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/vendors.php');
    exit;
}

csrf_verify();

$action   = $_POST['action'] ?? '';
$vendorId = (int)($_POST['vendor_id'] ?? 0);

$defaultRedirect = $vendorId ? '/admin/vendor.php?id=' . $vendorId : '/admin/vendors.php';

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
    $stmt->execute([$businessId, $rateIncrease, $note, $startDate, $endDate, $_SESSION['admin_id']]);
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

} elseif ($action === 'strike') {
    // The next strike and its penalty, worked out from the strikes still
    // counting (config/strikes.php). `expect_level` is the step the button
    // showed: if another admin gave one in between, nothing happens and the
    // page says so, rather than giving a harsher step than the one pressed.
    $businessId = (int)($_POST['business_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');
    $expect     = (int)($_POST['expect_level'] ?? 0);
    if (!$businessId || $reason === '') {
        $_SESSION['admin_error'] = 'A reason is required to give a strike.';
        header('Location: ' . $defaultRedirect);
        exit;
    }
    if ($expect && strike_next_level($pdo, $businessId) !== $expect) {
        $_SESSION['admin_error'] = 'The strikes changed since this page loaded. Check the log and press again.';
        header('Location: ' . $defaultRedirect);
        exit;
    }
    [$level, $err] = strike_give($pdo, $businessId, $vendorId, $reason, (int)$_SESSION['admin_id']);
    if ($err) {
        $_SESSION['admin_error'] = $err === 'not_found' ? 'Business not found.' : 'A reason is required to give a strike.';
    } else {
        $_SESSION['admin_success'] = 'Strike ' . $level . ' given: ' . strike_penalty_text($level, 'en') . ' The vendor has been emailed.';
    }

} elseif ($action === 'strike_remove') {
    // Stops the strike counting. Its penalty or suspension is left alone.
    $strikeId   = (int)($_POST['strike_id'] ?? 0);
    $businessId = (int)($_POST['business_id'] ?? 0);
    $upd = $pdo->prepare('UPDATE vendor_strikes SET revoked_at = NOW(), revoked_by = ? WHERE id = ? AND business_id = ? AND revoked_at IS NULL');
    $upd->execute([(int)$_SESSION['admin_id'], $strikeId, $businessId]);
    if ($upd->rowCount()) {
        audit_log($pdo, 'business.strike_remove', 'business', $businessId, ['strike_id' => $strikeId]);
        $_SESSION['admin_success'] = 'Strike removed. Any penalty or suspension it caused is still on — lift it on its own card.';
    }
}

header('Location: ' . $defaultRedirect);
exit;
