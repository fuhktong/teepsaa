<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /settings-vendor/?tab=danger');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT name, email, password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($password, $row['password'])) {
    $_SESSION['settings_error'] = 'Incorrect password.';
    header('Location: /settings-vendor/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('
    SELECT COUNT(*) FROM orders o
    JOIN businesses b ON b.id = o.business_id
    WHERE b.user_id = ? AND b.deleted_at IS NULL AND o.status NOT IN (\'completed\', \'cancelled\', \'refunded\', \'refund_rejected\')
');
$stmt->execute([$userId]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['settings_error'] = 'Cannot delete account — you have open orders. Wait until all orders are completed or cancelled.';
    header('Location: /settings-vendor/?tab=danger');
    exit;
}

// Soft delete: keep the vendor row (and its businesses, orders, reviews, payouts)
// for accounting. Login is blocked while deleted_at is set; re-registering with the
// same email revives this same row. Their businesses are soft-deleted too so their
// products stop showing (mirrors business-delete-action.php).
$stmt = $pdo->prepare('UPDATE vendors SET deleted_at = NOW() WHERE id = ?');
$stmt->execute([$userId]);

$stmt = $pdo->prepare('UPDATE businesses SET deleted_at = NOW(), approved = -1 WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);

[$subj, $html] = render_email_template($pdo, 'account_deleted', [
    'name' => htmlspecialchars($row['name']),
]);
if ($html !== '') send_email($row['email'], $subj, $html);

// Sign the deleted account out without dropping an admin session that may be
// signed in in the same browser (see /logout/logout.php).
unset(
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['user_name'],
    $_SESSION['user_avatar'],
    $_SESSION['user_avatar_color'],
    $_SESSION['pending_role']
);
if (empty($_SESSION['admin_id'])) {
    session_destroy();
} else {
    session_regenerate_id(true);
}

header('Location: /');
exit;
