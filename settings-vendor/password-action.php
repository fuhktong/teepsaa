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
    header('Location: /settings-vendor/?tab=password');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$stmt = $pdo->prepare('SELECT name, email, password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

// The session can outlive the row — an admin can delete the vendor mid-session.
if (!$row) {
    header('Location: /login-vendor/');
    exit;
}

if (!password_verify($current, $row['password'])) {
    $_SESSION['settings_error'] = 'Current password is incorrect.';
    header('Location: /settings-vendor/?tab=password');
    exit;
}

if (strlen($new) < 8) {
    $_SESSION['settings_error'] = 'New password must be at least 8 characters.';
    header('Location: /settings-vendor/?tab=password');
    exit;
}

if ($new !== $confirm) {
    $_SESSION['settings_error'] = 'New passwords do not match.';
    header('Location: /settings-vendor/?tab=password');
    exit;
}

$stmt = $pdo->prepare('UPDATE vendors SET password = ? WHERE id = ?');
$stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

[$subj, $html] = render_email_template($pdo, 'password_changed', [
    'name' => htmlspecialchars($row['name']),
]);
if ($html !== '') send_email($row['email'], $subj, $html);

$_SESSION['settings_success'] = 'Password updated.';
header('Location: /settings-vendor/?tab=password');
exit;
