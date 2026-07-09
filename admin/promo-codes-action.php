<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('promo-codes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/promo-codes.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $code        = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($_POST['code'] ?? '')));
    $description = mb_substr(trim($_POST['description'] ?? ''), 0, 255) ?: null;
    $usesLimit   = ($_POST['uses_limit'] ?? '') !== '' ? max(1, (int)$_POST['uses_limit']) : null;

    if (!$code) {
        $_SESSION['admin_error'] = 'Code is required.';
        header('Location: /admin/promo-codes.php');
        exit;
    }

    try {
        $pdo->prepare('INSERT INTO promo_codes (code, description, uses_limit) VALUES (?, ?, ?)')
            ->execute([$code, $description, $usesLimit]);
        $_SESSION['admin_success'] = 'Code "' . $code . '" created.';
    } catch (\PDOException $e) {
        $_SESSION['admin_error'] = 'That code already exists.';
    }

} elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE promo_codes SET active = 1 - active WHERE id = ?')->execute([$id]);
    $_SESSION['admin_success'] = 'Code updated.';
}

header('Location: /admin/promo-codes.php');
exit;
