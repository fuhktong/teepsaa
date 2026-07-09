<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/messages/');
    exit;
}

csrf_verify();

$threadId = (int)($_POST['thread_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if (!$threadId || !in_array($action, ['close', 'reopen'], true)) {
    header('Location: /admin/messages/');
    exit;
}

$newStatus = $action === 'close' ? 'closed' : 'open';

$pdo->prepare('UPDATE support_threads SET status = ? WHERE id = ?')
    ->execute([$newStatus, $threadId]);

$_SESSION['admin_msg_success'] = $action === 'close' ? 'Thread closed.' : 'Thread reopened.';
header('Location: /admin/messages/thread.php?id=' . $threadId);
exit;
