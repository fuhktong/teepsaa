<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $stmt = $pdo->prepare('SELECT avatar FROM admins WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $oldPath = __DIR__ . '/../uploads/' . $old;
        if (file_exists($oldPath)) @unlink($oldPath);
    }
    $pdo->prepare('UPDATE admins SET avatar = NULL WHERE id = ?')->execute([$userId]);
    $_SESSION['settings_success'] = 'Avatar photo removed.';
    header('Location: /admin/settings.php');
    exit;
}

if ($action === 'color') {
    $color = (int)($_POST['color'] ?? 0);
    if ($color < 0 || $color > 4) $color = 0;
    $pdo->prepare('UPDATE admins SET avatar_color = ? WHERE id = ?')->execute([$color, $userId]);
    $_SESSION['settings_success'] = 'Avatar color updated.';
    header('Location: /admin/settings.php');
    exit;
}

if ($action === 'photo') {
    $file = $_FILES['avatar'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['settings_error'] = 'Upload failed. Please try again.';
        header('Location: /admin/settings.php');
        exit;
    }

    $allowed = ['image/jpeg', 'image/png'];
    $mime    = image_type_from_magic($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        $_SESSION['settings_error'] = 'Only JPG or PNG files are allowed.';
        header('Location: /admin/settings.php');
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['settings_error'] = 'File must be under 2MB.';
        header('Location: /admin/settings.php');
        exit;
    }

    $ext      = $mime === 'image/png' ? 'png' : 'jpg';
    $filename = 'avatar_a_' . $userId . '_' . time() . '.' . $ext;
    $dest     = __DIR__ . '/../uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $_SESSION['settings_error'] = 'Could not save file. Please try again.';
        header('Location: /admin/settings.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT avatar FROM admins WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $oldPath = __DIR__ . '/../uploads/' . $old;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $pdo->prepare('UPDATE admins SET avatar = ? WHERE id = ?')->execute([$filename, $userId]);
    $_SESSION['settings_success'] = 'Avatar updated.';
    header('Location: /admin/settings.php');
    exit;
}

header('Location: /admin/settings.php');
exit;
