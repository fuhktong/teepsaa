<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/buyers.php');
    exit;
}

csrf_verify();

$action  = $_POST['action'] ?? '';
$buyerId = (int)($_POST['buyer_id'] ?? 0);

if (!$buyerId) {
    header('Location: /admin/buyers.php');
    exit;
}

$returnUrl = '/admin/buyer.php?id=' . $buyerId;

if ($action === 'ban') {
    $reason = trim($_POST['ban_reason'] ?? '');
    if (!$reason) {
        $_SESSION['admin_error'] = 'A reason is required to suspend an account.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $pdo->prepare('UPDATE buyers SET banned = 1, ban_reason = ?, banned_at = NOW() WHERE id = ?')
        ->execute([$reason, $buyerId]);
    $_SESSION['admin_success'] = 'Account suspended.';

} elseif ($action === 'unban') {
    $pdo->prepare('UPDATE buyers SET banned = 0, ban_reason = NULL, banned_at = NULL WHERE id = ?')
        ->execute([$buyerId]);
    $_SESSION['admin_success'] = 'Suspension lifted.';

} elseif ($action === 'note') {
    $note = trim($_POST['admin_note'] ?? '');
    $pdo->prepare('UPDATE buyers SET admin_note = ? WHERE id = ?')
        ->execute([$note ?: null, $buyerId]);
    $_SESSION['admin_success'] = 'Note saved.';
}

header('Location: ' . $returnUrl);
exit;
