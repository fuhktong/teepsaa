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

// The admin identity lives under its own keys and never touches 'user_id' or
// 'role', so an admin, a buyer and a vendor can all be signed in in the same
// browser without clobbering each other. See config/admin-auth.php.
session_regenerate_id(true);
$_SESSION['admin_id']          = $user['id'];
$_SESSION['admin_role']        = $user['admin_role'];
$_SESSION['admin_permissions'] = $permissions;
// Office language — but don't yank a buyer/vendor already signed in here
// out of their chosen language.
if (empty($_SESSION['user_id'])) {
    $_SESSION['lang'] = 'en';
}
header('Location: /admin/');
exit;
