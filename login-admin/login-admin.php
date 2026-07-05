<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login-admin/');
    exit;
}

csrf_verify();
check_rate_limit($pdo);

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, password FROM admins WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_attempt($pdo);
    $_SESSION['auth_error'] = 'Invalid email or password.';
    header('Location: /login-admin/');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']  = $user['id'];
$_SESSION['role']     = 'admin';
$_SESSION['is_admin'] = true;
header('Location: /admin/');
exit;
