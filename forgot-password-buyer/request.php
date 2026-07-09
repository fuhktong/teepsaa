<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot-password-buyer/');
    exit;
}

csrf_verify();
check_rate_limit($pdo);
record_failed_attempt($pdo);

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = 'Invalid email address.';
    header('Location: /forgot-password-buyer/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name FROM buyers WHERE email = ?');
$stmt->execute([$email]);
$buyer = $stmt->fetch();

if ($buyer) {
    $pdo->prepare('DELETE FROM password_resets WHERE role = ? AND user_id = ? AND used_at IS NULL')
        ->execute(['buyer', $buyer['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_resets (role, user_id, token) VALUES (?, ?, ?)')
        ->execute(['buyer', $buyer['id'], $token]);

    $link = SITE_URL . '/reset-password-buyer/?token=' . urlencode($token);
    $name = htmlspecialchars($buyer['name'] ?? '', ENT_QUOTES);
    $linkHtml = "<p><a href=\"{$link}\">{$link}</a></p>";
    [$subj, $html] = render_email_template($pdo, 'reset_password', [
        'name' => $name,
        'link' => $linkHtml,
    ]);
    if ($html !== '') send_email($email, $subj, $html);
}

// Always show the same message to prevent email enumeration
$_SESSION['auth_success'] = "If that email is registered, you'll receive a reset link shortly.";
header('Location: /forgot-password-buyer/');
exit;
