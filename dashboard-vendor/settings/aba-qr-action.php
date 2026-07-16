<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=aba-qr');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../../uploads/';
$allowed   = ['image/jpeg', 'image/png'];

$accountName = trim($_POST['aba_account_name'] ?? '');
if ($accountName === '') {
    $_SESSION['settings_error'] = 'Account name is required.';
    header('Location: /dashboard-vendor/settings/?tab=aba-qr');
    exit;
}
$accountName = mb_substr($accountName, 0, 100);

// New QR file is optional once one exists — the account name can be updated alone
if (empty($_FILES['aba_qr']['name'])) {
    $stmt = $pdo->prepare('SELECT aba_qr FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['settings_error'] = 'Please upload your bank QR code.';
        header('Location: /dashboard-vendor/settings/?tab=aba-qr');
        exit;
    }
    $pdo->prepare('UPDATE vendors SET aba_account_name = ? WHERE id = ?')
        ->execute([$accountName, $userId]);
    $_SESSION['settings_success'] = 'Account name updated.';
    header('Location: /dashboard-vendor/settings/?tab=aba-qr');
    exit;
}

$tmp  = $_FILES['aba_qr']['tmp_name'];
$size = $_FILES['aba_qr']['size'];
$mime = image_type_from_magic($tmp);

if (!in_array($mime, $allowed, true) || $size > 2 * 1024 * 1024) {
    $_SESSION['settings_error'] = 'Invalid file. JPG or PNG only, max 2MB.';
    header('Location: /dashboard-vendor/settings/?tab=aba-qr');
    exit;
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $ext;

if (move_uploaded_file($tmp, $uploadDir . $filename)) {
    $stmt = $pdo->prepare('UPDATE vendors SET aba_qr = ?, aba_account_name = ? WHERE id = ?');
    $stmt->execute([$filename, $accountName, $userId]);
    $_SESSION['settings_success'] = 'Bank QR code updated.';
} else {
    $_SESSION['settings_error'] = 'Upload failed. Please try again.';
}

header('Location: /dashboard-vendor/settings/?tab=aba-qr');
exit;
