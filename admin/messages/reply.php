<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/messages/');
    exit;
}

csrf_verify();

$threadId = (int)($_POST['thread_id'] ?? 0);
$body     = trim($_POST['body'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM support_threads WHERE id = ?');
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    exit('Thread not found.');
}

if ($thread['status'] === 'closed') {
    $_SESSION['admin_msg_error'] = 'This thread is closed.';
    header('Location: /admin/messages/thread.php?id=' . $threadId);
    exit;
}

if ($body === '') {
    $_SESSION['admin_msg_error'] = 'Reply cannot be empty.';
    header('Location: /admin/messages/thread.php?id=' . $threadId);
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['admin_msg_error'] = 'Reply must be 2000 characters or fewer.';
    header('Location: /admin/messages/thread.php?id=' . $threadId);
    exit;
}

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, \'admin\', ?)')
    ->execute([$threadId, $body]);

$pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')
    ->execute([$threadId]);

$_SESSION['admin_msg_success'] = 'Reply sent.';
header('Location: /admin/messages/thread.php?id=' . $threadId);
exit;
