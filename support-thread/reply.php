<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact/');
    exit;
}

csrf_verify();

$token = trim($_POST['t'] ?? '');
$body  = trim($_POST['body'] ?? '');
$back  = '/support-thread/?t=' . urlencode($token);

// Honeypot — bots fill hidden fields, humans don't
if (($_POST['website'] ?? '') !== '') {
    header('Location: ' . $back);
    exit;
}

if ($token === '' || strlen($token) > 64) {
    http_response_code(404);
    exit('Not found.');
}

$stmt = $pdo->prepare("SELECT id, status FROM support_threads WHERE guest_token = ? AND sender_role = 'guest'");
$stmt->execute([$token]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    exit('Not found.');
}

if ($thread['status'] === 'closed') {
    $_SESSION['gt_error'] = 'This conversation is closed.';
    header('Location: ' . $back);
    exit;
}

// Rate limit — 1 reply per 60 seconds per session (same as the contact form)
$lastReply = $_SESSION['gt_reply_last'] ?? 0;
if (time() - $lastReply < 60) {
    $_SESSION['gt_error'] = 'Please wait a moment before replying again.';
    header('Location: ' . $back);
    exit;
}

if ($body === '') {
    $_SESSION['gt_error'] = 'Reply cannot be empty.';
    header('Location: ' . $back);
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['gt_error'] = 'Reply must be 2000 characters or fewer.';
    header('Location: ' . $back);
    exit;
}

$pdo->prepare("INSERT INTO support_messages (thread_id, sender, body) VALUES (?, 'guest', ?)")
    ->execute([$thread['id'], $body]);

$pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')
    ->execute([$thread['id']]);

$_SESSION['gt_reply_last'] = time();
$_SESSION['gt_success']    = 'Reply sent.';
header('Location: ' . $back);
exit;
