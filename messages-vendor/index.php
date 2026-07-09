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

$userId  = $_SESSION['user_id'];
$success = $_SESSION['msg_success'] ?? '';
$error   = $_SESSION['msg_error'] ?? '';
unset($_SESSION['msg_success'], $_SESSION['msg_error']);

$pendingThread = $pdo->prepare("SELECT id FROM support_threads WHERE sender_id = ? AND sender_role = 'vendor' AND status = 'pending' LIMIT 1");
$pendingThread->execute([$userId]);
$pendingThread = $pendingThread->fetch();

$stmt = $pdo->prepare('
    SELECT t.id, t.subject, t.status, t.updated_at,
           (SELECT body FROM support_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT sender FROM support_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_sender,
           (SELECT COUNT(*) FROM support_messages WHERE thread_id = t.id AND sender = \'admin\' AND read_at IS NULL) AS unread_count
    FROM support_threads t
    WHERE t.sender_id = ? AND t.sender_role = \'vendor\'
    ORDER BY t.updated_at DESC
');
$stmt->execute([$userId]);
$threads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Messages — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/messages-vendor/messages-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="msg-header">
        <h1><?= $t['messages_title'] ?></h1>
        <?php if ($pendingThread): ?>
            <a href="/messages-vendor/thread.php?id=<?= $pendingThread['id'] ?>" class="msg-contact-btn"><?= $t['messages_pending'] ?></a>
        <?php else: ?>
            <a href="/contact-vendor/" class="msg-contact-btn"><?= $t['messages_contact'] ?></a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?><p class="msg-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <?php if (empty($threads)): ?>
        <p class="msg-empty"><?= $t['messages_empty'] ?></p>
    <?php else: ?>
    <div class="thread-list">
        <?php foreach ($threads as $t): ?>
        <a href="/messages-vendor/thread.php?id=<?= $t['id'] ?>" class="thread-row <?= $t['unread_count'] > 0 ? 'thread-row--unread' : '' ?>">
            <?php if ($t['unread_count'] > 0): ?>
                <span class="thread-unread-dot"></span>
            <?php else: ?>
                <span style="width:8px;flex-shrink:0;"></span>
            <?php endif; ?>
            <span class="thread-subject"><?= htmlspecialchars($t['subject']) ?></span>
            <?php if ($t['last_body']): ?>
            <span class="thread-preview"><?= htmlspecialchars(mb_substr($t['last_body'], 0, 80)) ?></span>
            <?php endif; ?>
            <span class="thread-meta">
                <span class="thread-badge thread-badge--<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span>
                <span class="thread-date"><?= fmt_date('M j', strtotime($t['updated_at'])) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
