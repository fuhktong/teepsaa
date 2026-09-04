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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /register-buyer/');
    exit;
}

csrf_verify();

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

if (!$name) {
    $_SESSION['auth_error'] = 'Full name is required.';
    header('Location: /register-buyer/');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = 'Invalid email address.';
    header('Location: /register-buyer/');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['auth_error'] = 'Password must be at least 8 characters.';
    header('Location: /register-buyer/');
    exit;
}

if ($password !== $confirm) {
    $_SESSION['auth_error'] = 'Passwords do not match.';
    header('Location: /register-buyer/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, deleted_at FROM buyers WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();
if ($existing && $existing['deleted_at'] === null) {
    $_SESSION['auth_error'] = 'An account with that email already exists.';
    header('Location: /register-buyer/');
    exit;
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

if ($existing) {
    // Re-registration with a soft-deleted account's email: revive that same row
    // so the buyer's retained orders stay linked to them. They re-verify the
    // email (deleted_at cleared, email_verified_at reset) before it's usable.
    $stmt = $pdo->prepare('UPDATE buyers SET name = ?, password = ?, verify_token = ?, verify_code_expires = ?, email_verified_at = NULL, deleted_at = NULL WHERE id = ?');
    $stmt->execute([$name, password_hash($password, PASSWORD_DEFAULT), $code, $expires, $existing['id']]);
    $newId = (int)$existing['id'];
} else {
    $stmt = $pdo->prepare('INSERT INTO buyers (email, name, password, verify_token, verify_code_expires) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), $code, $expires]);
    $newId = $pdo->lastInsertId();
}

if (DEV_MODE) {
    $_SESSION['dev_otp'] = $code;
}

$codeHtml = '<div style="font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;margin:12px 0;color:#111">' . $code . '</div>';
[$subj, $html] = render_email_template($pdo, 'verify_code', [
    'name' => htmlspecialchars($name, ENT_QUOTES),
    'code' => $codeHtml,
]);
if ($html !== '') send_email($email, $subj, $html);

// The session id changes here, so the CSRF token must change with it —
// otherwise a token minted for the pre-login anonymous session stays valid
// afterwards, which is the fixation half of a session-fixation attack.
// csrf_token() mints a fresh one on the next render.
session_regenerate_id(true);
unset($_SESSION['csrf_token']);
$_SESSION['user_id']      = $newId;
$_SESSION['pending_role'] = 'buyer';
header('Location: /verify-email/');
exit;
