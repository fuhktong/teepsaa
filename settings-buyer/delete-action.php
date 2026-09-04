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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /settings-buyer/?tab=danger');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT name, email, password FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($password, $row['password'])) {
    $_SESSION['settings_error'] = 'Incorrect password.';
    header('Location: /settings-buyer/?tab=danger');
    exit;
}

// Soft delete: keep the row (and its orders/payments/reviews) for accounting.
// Login is blocked while deleted_at is set; re-registering with the same email
// revives this same row.
$stmt = $pdo->prepare('UPDATE buyers SET deleted_at = NOW() WHERE id = ?');
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
    // An admin is still signed in, so the session survives — but the id changes
    // and the CSRF token goes with it, so nothing minted for the account that
    // was just deleted is still accepted.
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

header('Location: /');
exit;
