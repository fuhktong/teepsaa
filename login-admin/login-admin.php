<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
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

$stmt = $pdo->prepare('SELECT id, password, admin_role, is_active FROM admins WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_attempt($pdo);
    $_SESSION['auth_error'] = 'Invalid email or password.';
    header('Location: /login-admin/');
    exit;
}

if (!$user['is_active']) {
    $_SESSION['auth_error'] = 'This admin account has been deactivated.';
    header('Location: /login-admin/');
    exit;
}

$permissions = [];
if ($user['admin_role'] !== 'super') {
    $permStmt = $pdo->prepare('SELECT section FROM admin_permissions WHERE admin_id = ?');
    $permStmt->execute([$user['id']]);
    $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
}

session_regenerate_id(true);
$_SESSION['user_id']          = $user['id'];
$_SESSION['role']             = 'admin';
$_SESSION['is_admin']         = true;
$_SESSION['admin_role']       = $user['admin_role'];
$_SESSION['admin_permissions'] = $permissions;
header('Location: /admin/');
exit;
