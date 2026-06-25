<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($password, $row['password'])) {
    $_SESSION['settings_error'] = 'Incorrect password.';
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('
    SELECT COUNT(*) FROM orders o
    JOIN businesses b ON b.id = o.business_id
    WHERE b.user_id = ? AND o.status NOT IN (\'completed\', \'cancelled\')
');
$stmt->execute([$userId]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['settings_error'] = 'Cannot delete account — you have open orders. Wait until all orders are completed or cancelled.';
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM vendors WHERE id = ?');
$stmt->execute([$userId]);

session_destroy();
header('Location: /');
exit;
