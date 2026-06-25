<?php
session_start();
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

$stmt = $pdo->prepare('SELECT id FROM buyers WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['auth_error'] = 'An account with that email already exists.';
    header('Location: /register-buyer/');
    exit;
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/mail.php';

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$stmt = $pdo->prepare('INSERT INTO buyers (email, name, password, verify_token, verify_code_expires) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), $code, $expires]);
$newId = $pdo->lastInsertId();

$_SESSION['dev_otp'] = $code;

send_email(
    $email,
    "Your Teepsaa verification code",
    "<p>Hi " . htmlspecialchars($name, ENT_QUOTES) . ",</p>"
    . "<p>Your verification code is:</p>"
    . "<p style=\"font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;\">{$code}</p>"
    . "<p>This code expires in 15 minutes. If you didn't create a Teepsaa account, ignore this email.</p>"
);

session_regenerate_id(true);
$_SESSION['user_id']      = $newId;
$_SESSION['pending_role'] = 'buyer';
header('Location: /verify-email/');
exit;
