<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../uploads/';
$allowed   = ['image/jpeg', 'image/png'];

if (empty($_FILES['aba_qr']['name'])) {
    header('Location: /dashboard-vendor/');
    exit;
}

$tmp  = $_FILES['aba_qr']['tmp_name'];
$size = $_FILES['aba_qr']['size'];
$mime = image_type_from_magic($tmp);

if (!in_array($mime, $allowed, true) || $size > 2 * 1024 * 1024) {
    $_SESSION['vendor_error'] = 'Invalid file. JPG or PNG only, max 2MB.';
    header('Location: /dashboard-vendor/');
    exit;
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $ext;

if (move_uploaded_file($tmp, $uploadDir . $filename)) {
    $stmt = $pdo->prepare('UPDATE vendors SET aba_qr = ? WHERE id = ?');
    $stmt->execute([$filename, $userId]);
    $_SESSION['vendor_success'] = 'ABA QR code updated.';
} else {
    $_SESSION['vendor_error'] = 'Upload failed. Please try again.';
}

header('Location: /dashboard-vendor/');
exit;
