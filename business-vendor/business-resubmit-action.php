<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business-vendor/');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];

// Only a rejected business can go back in the queue — approved = 0 is already
// waiting, and this must never pull an approved shop off the marketplace.
$stmt = $pdo->prepare('UPDATE businesses SET approved = 0, rejection_reason = NULL WHERE user_id = ? AND deleted_at IS NULL AND approved = -1');
$stmt->execute([$userId]);

$_SESSION['settings_success'] = $stmt->rowCount() > 0
    ? 'Your business has been sent back for review.'
    : 'Your business is not awaiting a resubmission.';

header('Location: /business-vendor/');
exit;
