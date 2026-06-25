<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/mail.php';

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
    header('Location: /resend-verification/');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];
$table  = $role === 'buyer' ? 'buyers' : 'vendors';

$stmt = $pdo->prepare("SELECT email, name, email_verified_at FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /resend-verification/');
    exit;
}

if ($user['email_verified_at']) {
    $_SESSION['resend_success'] = 'Your email is already verified.';
    header('Location: /resend-verification/');
    exit;
}

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$pdo->prepare("UPDATE {$table} SET verify_token = ?, verify_code_expires = ? WHERE id = ?")
    ->execute([$code, $expires, $userId]);

$_SESSION['dev_otp'] = $code;

$name = htmlspecialchars($user['name'] ?? '', ENT_QUOTES);
send_email(
    $user['email'],
    "Your Teepsaa verification code",
    "<p>Hi {$name},</p>"
    . "<p>Your verification code is:</p>"
    . "<p style=\"font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;\">{$code}</p>"
    . "<p>This code expires in 15 minutes. If you didn't create a Teepsaa account, ignore this email.</p>"
);

$_SESSION['verify_success'] = 'New code sent — check your inbox (or the server log for dev).';
header('Location: /verify-email/');
exit;
