<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$role    = $_SESSION['role'] ?? '';
$userId  = $_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

$threadId = (int)($_POST['thread_id'] ?? 0);
$body     = trim($_POST['body'] ?? '');

if (!$threadId || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

if (mb_strlen($body) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 2000 characters)']);
    exit;
}

if ($role === 'buyer') {
    $stmt = $pdo->prepare("SELECT id, status, sender_role, guest_email FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = 'buyer'");
    $stmt->execute([$threadId, $userId]);
    $senderEnum = 'buyer';
} elseif ($role === 'vendor') {
    $stmt = $pdo->prepare("SELECT id, status, sender_role, guest_email FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = 'vendor'");
    $stmt->execute([$threadId, $userId]);
    $senderEnum = 'vendor';
} elseif ($isAdmin) {
    $stmt = $pdo->prepare("SELECT id, status, sender_role, guest_email FROM support_threads WHERE id = ?");
    $stmt->execute([$threadId]);
    $senderEnum = 'admin';
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$thread = $stmt->fetch();
if (!$thread) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($thread['status'] === 'closed') {
    http_response_code(400);
    echo json_encode(['error' => 'Thread is closed']);
    exit;
}

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, ?, ?)')
    ->execute([$threadId, $senderEnum, $body]);

$msgId = (int)$pdo->lastInsertId();

// Auto-open pending threads when admin replies
if ($senderEnum === 'admin' && $thread['status'] === 'pending') {
    $pdo->prepare("UPDATE support_threads SET status = 'open', updated_at = NOW() WHERE id = ?")
        ->execute([$threadId]);
} else {
    $pdo->prepare('UPDATE support_threads SET updated_at = NOW() WHERE id = ?')
        ->execute([$threadId]);
}

// Email guest when admin replies
if ($senderEnum === 'admin' && $thread['sender_role'] === 'guest' && !empty($thread['guest_email'])) {
    require_once __DIR__ . '/../../config/app.php';
    $subjectStmt = $pdo->prepare('SELECT subject FROM support_threads WHERE id = ?');
    $subjectStmt->execute([$threadId]);
    $threadSubject = $subjectStmt->fetchColumn();

    $emailSubject = 'Re: ' . $threadSubject;
    $emailBody    = "Hello,\n\nYou have a new reply from the teepsaa support team:\n\n" . $body . "\n\n---\nYou can reply to this email to continue the conversation.\n\nteepsaa Support";
    $headers      = "From: " . FROM_EMAIL . "\r\nReply-To: " . ADMIN_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    mail($thread['guest_email'], $emailSubject, $emailBody, $headers);
}

echo json_encode([
    'id'         => $msgId,
    'sender'     => $senderEnum,
    'body'       => $body,
    'created_at' => date('Y-m-d H:i:s'),
]);
