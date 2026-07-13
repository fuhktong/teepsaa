<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/rate-limit.php';
require __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login-buyer/');
    exit;
}

$role = $_SESSION['role'] ?? $_SESSION['pending_role'] ?? null;
if (!in_array($role, ['buyer', 'vendor'], true)) {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /verify-email/');
    exit;
}

csrf_verify();
check_rate_limit($pdo);

$submitted = preg_replace('/\D/', '', $_POST['code'] ?? '');
$userId    = $_SESSION['user_id'];
$table     = $role === 'buyer' ? 'buyers' : 'vendors';
$loginUrl  = $role === 'vendor' ? '/login-vendor/' : '/login-buyer/';

if (strlen($submitted) !== 6) {
    $_SESSION['verify_error'] = 'Please enter all 6 digits.';
    header('Location: /verify-email/');
    exit;
}

$stmt = $pdo->prepare("SELECT verify_token, verify_code_expires, email_verified_at FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . $loginUrl);
    exit;
}

if ($user['email_verified_at']) {
    $_SESSION['auth_success'] = 'Your email is already verified.';
    header('Location: ' . $loginUrl);
    exit;
}

if (!$user['verify_token'] || !$user['verify_code_expires']) {
    $_SESSION['verify_error'] = 'No code found. Please request a new one.';
    header('Location: /verify-email/');
    exit;
}

if (new DateTime() > new DateTime($user['verify_code_expires'])) {
    $_SESSION['verify_error'] = 'Code expired. Please request a new one.';
    header('Location: /verify-email/');
    exit;
}

if (!hash_equals($user['verify_token'], $submitted)) {
    record_failed_attempt($pdo);
    $_SESSION['verify_error'] = 'Incorrect code. Please try again.';
    header('Location: /verify-email/');
    exit;
}

$pdo->prepare("UPDATE {$table} SET email_verified_at = NOW(), verify_token = NULL, verify_code_expires = NULL WHERE id = ?")
    ->execute([$userId]);

$stmt = $pdo->prepare("SELECT name, avatar, email FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if ($profile && $profile['email']) {
    $welcomeKey = $role === 'vendor' ? 'welcome_vendor' : 'welcome_buyer';
    $ctaUrl     = $role === 'vendor' ? 'https://teepsaa.com/submit/' : 'https://teepsaa.com/';
    [$subj, $html] = render_email_template($pdo, $welcomeKey, [
        'name'    => htmlspecialchars($profile['name'] ?? ''),
        'cta_url' => $ctaUrl,
    ]);
    if ($html !== '') send_email($profile['email'], $subj, $html);
}

unset($_SESSION['pending_role']);
$_SESSION['role']        = $role;
$_SESSION['user_name']   = $profile['name']   ?? '';
$_SESSION['user_avatar'] = $profile['avatar'] ?? '';

$dest = $role === 'vendor' ? '/dashboard-vendor/' : '/dashboard-buyer/';
header('Location: ' . $dest);
exit;
