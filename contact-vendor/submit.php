<?php
session_start([
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
    header('Location: /contact-vendor/');
    exit;
}

csrf_verify();

$userId    = $_SESSION['user_id'];
$issueType = trim($_POST['issue_type'] ?? '');
$orderId   = (int)($_POST['order_id'] ?? 0);
$subject   = trim($_POST['subject'] ?? '');
$body      = trim($_POST['body'] ?? '');

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM support_threads WHERE sender_id = ? AND sender_role = 'vendor' AND status = 'pending'");
$pendingStmt->execute([$userId]);
if ((int)$pendingStmt->fetchColumn() > 0) {
    header('Location: /contact-vendor/');
    exit;
}

$validIssues = ['Order dispute', 'Payout issue', 'Product/listing issue', 'Account issue', 'Other'];

if (!in_array($issueType, $validIssues) || $subject === '' || $body === '') {
    $_SESSION['contact_error'] = 'Please fill in all required fields.';
    $_SESSION['contact_old']   = $_POST;
    header('Location: /contact-vendor/');
    exit;
}

if (mb_strlen($body) > 2000) {
    $_SESSION['contact_error'] = 'Message must be 2000 characters or fewer.';
    $_SESSION['contact_old']   = $_POST;
    header('Location: /contact-vendor/');
    exit;
}

// Verify order belongs to this vendor's businesses if provided
$orderRef = null;
if ($orderId) {
    $os = $pdo->prepare('
        SELECT DISTINCT o.id, o.created_at
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON p.id = oi.product_id
        JOIN businesses b ON b.id = p.business_id
        WHERE o.id = ? AND b.user_id = ?
        LIMIT 1
    ');
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

$stmt = $pdo->prepare('INSERT INTO support_threads (sender_id, sender_role, subject, issue_type, status) VALUES (?, \'vendor\', ?, ?, \'pending\')');
$stmt->execute([$userId, $subject, $issueType]);
$threadId = (int)$pdo->lastInsertId();

$pdo->prepare('INSERT INTO support_messages (thread_id, sender, body) VALUES (?, \'vendor\', ?)')
    ->execute([$threadId, $firstMessage]);

$pdo->commit();

$_SESSION['msg_success'] = 'Your request has been submitted. We\'ll review it and get back to you soon.';
header('Location: /messages-vendor/thread.php?id=' . $threadId);
exit;
