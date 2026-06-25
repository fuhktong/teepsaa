<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact-buyer/');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$issueType = trim($_POST['issue_type'] ?? '');
$orderId   = (int)($_POST['order_id'] ?? 0);
$subject   = trim($_POST['subject'] ?? '');
$body      = trim($_POST['body'] ?? '');

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM support_threads WHERE sender_id = ? AND sender_role = 'buyer' AND status = 'pending'");
$pendingStmt->execute([$userId]);
if ((int)$pendingStmt->fetchColumn() > 0) {
    header('Location: /contact-buyer/');
    exit;
}

$validIssues = ['Order issue', 'Payment issue', 'Account issue', 'Other'];

if (!in_array($issueType, $validIssues) || $subject === '' || $body === '') {
    $_SESSION['contact_error'] = 'Please fill in all required fields.';
    $_SESSION['contact_old']   = $_POST;
    header('Location: /contact-buyer/');
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['contact_error'] = 'Message must be 2000 characters or fewer.';
    $_SESSION['contact_old']   = $_POST;
    header('Location: /contact-buyer/');
    exit;
}

// Verify order belongs to this buyer if provided
$orderRef = null;
if ($orderId) {
    $os = $pdo->prepare('SELECT id, created_at FROM orders WHERE id = ? AND buyer_user_id = ?');
    $os->execute([$orderId, $userId]);
    $order = $os->fetch();
    if ($order) {
        $orderRef = date('ymd', strtotime($order['created_at'])) . '-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
    }
}

$subject = mb_substr($subject, 0, 255);

$firstMessage = '';
if ($orderRef) {
    $firstMessage .= "Order: #$orderRef\n\n";
}
$firstMessage .= $body;

$pdo->beginTransaction();

$stmt = $pdo->prepare('INSERT INTO support_threads (sender_id, sender_role, subject, issue_type, status) VALUES (?, \'buyer\', ?, ?, \'pending\')');
$stmt->execute([$userId, $subject, $issueType]);
$threadId = (int)$pdo->lastInsertId();

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, \'buyer\', ?)')
    ->execute([$threadId, $firstMessage]);

$pdo->commit();

$_SESSION['msg_success'] = 'Your request has been submitted. We\'ll review it and get back to you soon.';
header('Location: /messages-buyer/thread.php?id=' . $threadId);
exit;
