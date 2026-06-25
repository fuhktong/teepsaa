<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /messages-buyer/');
    exit;
}

csrf_verify();

$userId  = $_SESSION['user_id'];
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body'] ?? '');

if ($subject === '' || $body === '') {
    $_SESSION['msg_error']       = 'Subject and message are required.';
    $_SESSION['msg_old_subject'] = $subject;
    $_SESSION['msg_old_body']    = $body;
    header('Location: /messages-buyer/new.php');
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['msg_error']       = 'Message must be 2000 characters or fewer.';
    $_SESSION['msg_old_subject'] = $subject;
    $_SESSION['msg_old_body']    = $body;
    header('Location: /messages-buyer/new.php');
    exit;
}

$subject = mb_substr($subject, 0, 255);

$pdo->beginTransaction();

$stmt = $pdo->prepare('INSERT INTO support_threads (sender_id, sender_role, subject) VALUES (?, \'buyer\', ?)');
$stmt->execute([$userId, $subject]);
$threadId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, \'buyer\', ?)');
$stmt->execute([$threadId, $body]);

$pdo->commit();

$_SESSION['msg_success'] = 'Message sent. We\'ll get back to you soon.';
header('Location: /messages-buyer/thread.php?id=' . $threadId);
exit;
