<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login-buyer/');
    exit;
}

csrf_verify();
check_rate_limit($pdo);

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, name, password, avatar, avatar_color, banned, email_verified_at, lang FROM buyers WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_attempt($pdo);
    $_SESSION['auth_error'] = 'Invalid email or password.';
    header('Location: /login-buyer/');
    exit;
}

if ($user['banned']) {
    $_SESSION['auth_error'] = 'This account has been suspended. Please contact support.';
    header('Location: /login-buyer/');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];

if (!$user['email_verified_at']) {
    $_SESSION['pending_role'] = 'buyer';
    header('Location: /resend-verification/');
    exit;
}

$_SESSION['role']              = 'buyer';
$_SESSION['user_name']         = $user['name'];
$_SESSION['user_avatar']       = $user['avatar'] ?? '';
$_SESSION['user_avatar_color'] = isset($user['avatar_color']) ? (int)$user['avatar_color'] : null;
if (!empty($user['lang'])) $_SESSION['lang'] = $user['lang']; // restore saved language preference
header('Location: /dashboard-buyer/');
exit;
