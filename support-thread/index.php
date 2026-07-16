<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

// Token-gated guest view of a contact-form thread. No account needed —
// the unguessable token from the guest's email is the whole credential.
$token  = trim($_GET['t'] ?? '');
$thread = null;
if ($token !== '' && strlen($token) <= 64) {
    $stmt = $pdo->prepare("
        SELECT id, guest_name, subject, status, created_at
        FROM support_threads
        WHERE guest_token = ? AND sender_role = 'guest'
    ");
    $stmt->execute([$token]);
    $thread = $stmt->fetch();
}

$messages = [];
if ($thread) {
    $m = $pdo->prepare('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? ORDER BY id ASC');
    $m->execute([$thread['id']]);
    $messages = $m->fetchAll();
} else {
    http_response_code(404);
}

$success = $_SESSION['gt_success'] ?? '';
$error   = $_SESSION['gt_error']   ?? '';
unset($_SESSION['gt_success'], $_SESSION['gt_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Support — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/support-thread/support-thread.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!$thread): ?>

    <div class="gt-invalid">
        <h1><?= $t['support_thread_title'] ?></h1>
        <p><?= $t['support_thread_invalid'] ?></p>
        <a href="/contact/" class="gt-contact-btn"><?= $t['support_thread_contact_link'] ?></a>
    </div>

    <?php else: ?>

    <div class="gt-header">
        <div class="gt-header-top">
            <h1 class="gt-subject"><?= htmlspecialchars($thread['subject']) ?></h1>
            <span class="gt-status gt-status--<?= $thread['status'] ?>"><?= $t['messages_status_' . $thread['status']] ?? ucfirst($thread['status']) ?></span>
        </div>
        <div class="gt-meta"><?= $t['messages_submitted'] ?> <?= fmt_date('M j, Y', strtotime($thread['created_at'])) ?></div>
    </div>

    <?php if ($success): ?><p class="gt-flash gt-flash--success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="gt-flash gt-flash--error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="gt-thread">
        <?php foreach ($messages as $m): ?>
        <div class="gt-msg gt-msg--<?= $m['sender'] === 'admin' ? 'admin' : 'user' ?>">
            <div class="gt-msg-header">
                <span class="gt-msg-from"><?= $m['sender'] === 'admin' ? $t['messages_support_name'] : $t['messages_you'] ?></span>
                <span class="gt-msg-time"><?= fmt_date('M j, Y · g:ia', strtotime($m['created_at'])) ?></span>
            </div>
            <div class="gt-msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($thread['status'] === 'closed'): ?>

    <div class="gt-notice gt-notice--closed">
        <?= $t['support_thread_closed_notice'] ?>
        <a href="/contact/"><?= $t['support_thread_contact_link'] ?></a>
    </div>

    <?php else: ?>

    <form method="POST" action="/support-thread/reply.php" class="gt-reply">
        <?= csrf_input() ?>
        <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
        <input type="text" name="website" value="" class="gt-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <textarea name="body" rows="4" maxlength="2000" required placeholder="<?= htmlspecialchars($t['support_thread_reply_ph']) ?>"></textarea>
        <div class="gt-reply-foot">
            <span class="gt-reply-note"><?= $t['support_thread_note'] ?></span>
            <button type="submit"><?= $t['support_thread_send'] ?></button>
        </div>
    </form>

    <?php endif; ?>

    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
