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
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$name        = trim($_POST['business_name'] ?? '');
$nameKm      = trim($_POST['business_name_km'] ?? '');
$description = trim($_POST['description'] ?? '');
$descriptionKm = trim($_POST['description_km'] ?? '');
$rawCats     = $_POST['categories'] ?? [];

if (!$name) {
    $_SESSION['settings_error'] = 'Business name is required.';
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

$allParentNames = $pdo->query('SELECT name FROM categories WHERE parent_id IS NULL')->fetchAll(PDO::FETCH_COLUMN);
$safeCats = array_values(array_filter($rawCats, fn($c) => in_array($c, $allParentNames, true)));
$category = implode(', ', $safeCats);

$stmt = $pdo->prepare('UPDATE businesses SET name = ?, name_km = ?, description = ?, description_km = ?, category = ? WHERE user_id = ?');
$stmt->execute([$name, $nameKm ?: null, $description, $descriptionKm ?: null, $category, $userId]);

$_SESSION['settings_success'] = 'Business info updated.';
header('Location: /dashboard-vendor/settings/?tab=business');
exit;
