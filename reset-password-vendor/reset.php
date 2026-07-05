<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login-vendor/');
    exit;
}

csrf_verify();

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

$stmt = $pdo->prepare('
    SELECT id, user_id FROM password_resets
    WHERE token = ? AND role = ? AND used_at IS NULL
      AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
');
$stmt->execute([$token, 'vendor']);
$reset = $stmt->fetch();

if (!$reset) {
    $_SESSION['auth_error'] = 'This link has expired or has already been used.';
    header('Location: /reset-password-vendor/?token=' . urlencode($token));
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['auth_error'] = 'Password must be at least 8 characters.';
    header('Location: /reset-password-vendor/?token=' . urlencode($token));
    exit;
}

if ($password !== $confirm) {
    $_SESSION['auth_error'] = 'Passwords do not match.';
    header('Location: /reset-password-vendor/?token=' . urlencode($token));
    exit;
}

$pdo->prepare('UPDATE vendors SET password = ? WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);

$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
    ->execute([$reset['id']]);

$_SESSION['auth_success'] = 'Your password has been reset. You can now log in.';
header('Location: /login-vendor/');
exit;
