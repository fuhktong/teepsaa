<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot-password-buyer/');
    exit;
}

csrf_verify();

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
    send_email(
        $email,
        "Reset your Teepsaa password",
        "<p>Hi {$name},</p>"
        . "<p>Click the link below to reset your password. This link is valid for 1 hour.</p>"
        . "<p><a href=\"{$link}\">{$link}</a></p>"
        . "<p>If you didn't request a password reset, you can ignore this email.</p>"
    );
}

// Always show the same message to prevent email enumeration
$_SESSION['auth_success'] = "If that email is registered, you'll receive a reset link shortly.";
header('Location: /forgot-password-buyer/');
exit;
