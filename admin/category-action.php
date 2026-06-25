<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/categories.php');
    exit;
}

csrf_verify();

$action   = $_POST['action'] ?? '';
$name     = trim($_POST['name'] ?? '');
$rate     = round((float)($_POST['royalty_rate'] ?? 0) / 100, 4);
$parentId = (int)($_POST['parent_id'] ?? 0) ?: null;

if (!$name || $rate < 0 || $rate > 1) {
    $_SESSION['admin_error'] = 'Invalid category data.';
    header('Location: /admin/categories.php');
    exit;
}

if ($action === 'add') {
    $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name, royalty_rate) VALUES (?, ?, ?)');
    $stmt->execute([$parentId, $name, $rate]);
    $_SESSION['admin_success'] = 'Category "' . htmlspecialchars($name) . '" added.';

} elseif ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        $_SESSION['admin_error'] = 'Missing category ID.';
        header('Location: /admin/categories.php');
        exit;
    }
    $stmt = $pdo->prepare('UPDATE categories SET parent_id = ?, name = ?, royalty_rate = ? WHERE id = ?');
    $stmt->execute([$parentId, $name, $rate, $id]);
    $_SESSION['admin_success'] = 'Category updated.';
}

header('Location: /admin/categories.php');
exit;
