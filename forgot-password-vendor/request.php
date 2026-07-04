<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot-password-vendor/');
    exit;
}

csrf_verify();

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = 'Invalid email address.';
    header('Location: /forgot-password-vendor/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name FROM vendors WHERE email = ?');
$stmt->execute([$email]);
$vendor = $stmt->fetch();

if ($vendor) {
    $pdo->prepare('DELETE FROM password_resets WHERE role = ? AND user_id = ? AND used_at IS NULL')
        ->execute(['vendor', $vendor['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_resets (role, user_id, token) VALUES (?, ?, ?)')
        ->execute(['vendor', $vendor['id'], $token]);

    $link = SITE_URL . '/reset-password-vendor/?token=' . urlencode($token);
    $name = htmlspecialchars($vendor['name'] ?? '', ENT_QUOTES);
    $linkHtml = "<p><a href=\"{$link}\">{$link}</a></p>";
    [$subj, $html] = render_email_template($pdo, 'reset_password', [
        'name' => $name,
        'link' => $linkHtml,
    ]);
    if ($html !== '') send_email($email, $subj, $html);
}

// Always show the same message to prevent email enumeration
$_SESSION['auth_success'] = "If that email is registered, you'll receive a reset link shortly.";
header('Location: /forgot-password-vendor/');
exit;
