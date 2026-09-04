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
    header('Location: /login-vendor/');
    exit;
}

csrf_verify();
$email    = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

// All three portals share the 'login' budget: someone working through a stolen
// password list should not get a fresh five tries per portal.
check_rate_limit($pdo, 'login', $email);

$stmt = $pdo->prepare('SELECT id, name, password, avatar, avatar_color, suspended, email_verified_at, lang FROM vendors WHERE email = ? AND deleted_at IS NULL');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    record_failed_attempt($pdo, 'login', $email);
    $_SESSION['auth_error'] = 'Invalid email or password.';
    header('Location: /login-vendor/');
    exit;
}

if ($user['suspended']) {
    $_SESSION['auth_error'] = 'Your account has been suspended. Please contact support.';
    header('Location: /login-vendor/');
    exit;
}

// The session id changes here, so the CSRF token must change with it —
// otherwise a token minted for the pre-login anonymous session stays valid
// afterwards, which is the fixation half of a session-fixation attack.
// csrf_token() mints a fresh one on the next render.
session_regenerate_id(true);
unset($_SESSION['csrf_token']);
$_SESSION['user_id'] = $user['id'];

if (!$user['email_verified_at']) {
    $_SESSION['pending_role'] = 'vendor';
    header('Location: /resend-verification/');
    exit;
}

$_SESSION['role']             = 'vendor';
$_SESSION['user_name']        = $user['name'];
$_SESSION['user_avatar']      = $user['avatar'] ?? '';
$_SESSION['user_avatar_color'] = isset($user['avatar_color']) ? (int)$user['avatar_color'] : null;
if (!empty($user['lang'])) $_SESSION['lang'] = $user['lang']; // restore saved language preference
header('Location: /analytics/');
exit;
