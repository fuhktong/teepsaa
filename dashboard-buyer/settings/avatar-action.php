<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'photo';

if ($action === 'delete') {
    $stmt = $pdo->prepare('SELECT avatar FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $oldPath = __DIR__ . '/../../uploads/' . $old;
        if (file_exists($oldPath)) @unlink($oldPath);
    }
    $pdo->prepare('UPDATE buyers SET avatar = NULL WHERE id = ?')->execute([$userId]);
    $_SESSION['user_avatar']      = '';
    $_SESSION['settings_success'] = 'Avatar photo removed.';
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

$file = $_FILES['avatar'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['settings_error'] = 'Upload failed. Please try again.';
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

$allowed = ['image/jpeg', 'image/png'];
$mime    = image_type_from_magic($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    $_SESSION['settings_error'] = 'Only JPG or PNG files are allowed.';
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    $_SESSION['settings_error'] = 'File must be under 2MB.';
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'avatar_b_' . $userId . '_' . time() . '.' . $ext;
$dest     = __DIR__ . '/../../uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $_SESSION['settings_error'] = 'Could not save file. Please try again.';
    header('Location: /dashboard-buyer/settings/?tab=account');
    exit;
}

$stmt = $pdo->prepare('SELECT avatar FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$old = $stmt->fetchColumn();
if ($old) {
    $oldPath = __DIR__ . '/../../uploads/' . $old;
    if (file_exists($oldPath)) @unlink($oldPath);
}

$pdo->prepare('UPDATE buyers SET avatar = ? WHERE id = ?')->execute([$filename, $userId]);

$_SESSION['user_avatar']      = $filename;
$_SESSION['settings_success'] = 'Avatar updated.';
header('Location: /dashboard-buyer/settings/?tab=account');
exit;
