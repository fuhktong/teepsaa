<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /messages-vendor/');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$threadId = (int)($_POST['thread_id'] ?? 0);
$body     = trim($_POST['body'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = \'vendor\'');
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(403);
    exit('Thread not found.');
}

if ($thread['status'] === 'closed') {
    $_SESSION['msg_error'] = 'This thread is closed.';
    header('Location: /messages-vendor/thread.php?id=' . $threadId);
    exit;
}

if ($body === '') {
    $_SESSION['msg_error'] = 'Reply cannot be empty.';
    header('Location: /messages-vendor/thread.php?id=' . $threadId);
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['msg_error'] = 'Reply must be 2000 characters or fewer.';
    header('Location: /messages-vendor/thread.php?id=' . $threadId);
    exit;
}

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, \'vendor\', ?)')
    ->execute([$threadId, $body]);

$pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')
    ->execute([$threadId]);

$_SESSION['msg_success'] = 'Reply sent.';
header('Location: /messages-vendor/thread.php?id=' . $threadId);
exit;
