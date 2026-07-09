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

$threadId = (int)($_GET['id'] ?? 0);
if (!$threadId) {
    header('Location: /admin/messages/');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM support_threads WHERE id = ?');
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    exit('Thread not found.');
}

// Fetch sender name
if ($thread['sender_role'] === 'guest') {
    $senderName  = $thread['guest_name']  ?: ($thread['guest_email'] ?: 'Guest');
    $guestEmail  = $thread['guest_email'] ?? '';
} elseif ($thread['sender_role'] === 'buyer') {
    $s = $pdo->prepare('SELECT name, email FROM buyers WHERE id = ?');
    $s->execute([$thread['sender_id']]);
    $sender = $s->fetch();
    $senderName = $sender ? ($sender['name'] ?: $sender['email']) : 'Unknown';
    $guestEmail = '';
} else {
    $s = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
    $s->execute([$thread['sender_id']]);
    $sender = $s->fetch();
    $senderName = $sender ? ($sender['name'] ?: $sender['email']) : 'Unknown';
    $guestEmail = '';
}

// Mark buyer/vendor messages as read
$pdo->prepare("UPDATE support_messages SET read_at = NOW() WHERE thread_id = ? AND sender IN ('buyer','vendor') AND read_at IS NULL")
    ->execute([$threadId]);

$msgs = $pdo->prepare('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? ORDER BY id ASC');
$msgs->execute([$threadId]);
$messages = $msgs->fetchAll();
$lastId   = !empty($messages) ? (int)end($messages)['id'] : 0;

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();
$adminSection = 'messages';
$adminTab     = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($thread['subject']) ?> — Admin</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/messages/messages-admin.css">
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <div class="amsg-role-tabs">
        <a href="/admin/messages/?role=buyer" class="amsg-role-tab <?= $thread['sender_role'] === 'buyer' ? 'active' : '' ?>">Buyers</a>
        <a href="/admin/messages/?role=vendor" class="amsg-role-tab <?= $thread['sender_role'] === 'vendor' ? 'active' : '' ?>">Vendors</a>
        <a href="/admin/messages/?role=guest" class="amsg-role-tab <?= $thread['sender_role'] === 'guest' ? 'active' : '' ?>">Contact Form</a>
    </div>

    <div class="chat-topbar chat-topbar--admin">
        <a href="/admin/messages/" class="chat-back">←</a>
        <div class="chat-topbar-info">
            <div class="chat-topbar-title"><?= htmlspecialchars($thread['subject']) ?></div>
            <div class="chat-topbar-sub">
                <?= htmlspecialchars($senderName) ?>
                <?php if ($guestEmail): ?>
                    <span class="amsg-guest-email"><?= htmlspecialchars($guestEmail) ?></span>
                <?php endif; ?>
                <span class="amsg-role-badge amsg-role-badge--<?= $thread['sender_role'] ?>"><?= $thread['sender_role'] === 'guest' ? 'Contact form' : ucfirst($thread['sender_role']) ?></span>
                · <span class="thread-badge thread-badge--<?= $thread['status'] ?>"><?= ucfirst($thread['status']) ?></span>
            </div>
        </div>
        <?php if ($thread['status'] === 'open'): ?>
        <form method="POST" action="/admin/messages/status.php" style="margin:0;flex-shrink:0;">
            <?= csrf_input() ?>
            <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
            <input type="hidden" name="action" value="close">
            <button type="submit" class="chat-close-btn">Close</button>
        </form>
        <?php else: ?>
        <form method="POST" action="/admin/messages/status.php" style="margin:0;flex-shrink:0;">
            <?= csrf_input() ?>
            <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
            <input type="hidden" name="action" value="reopen">
            <button type="submit" class="chat-close-btn">Reopen</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="chat-messages chat-messages--admin" id="msg-list">
        <?php foreach ($messages as $m): ?>
        <?php $isMe = $m['sender'] === 'admin'; ?>
        <div class="msg-bubble-wrap <?= $isMe ? 'msg-bubble-wrap--admin-sent' : 'msg-bubble-wrap--user-recv' ?>" data-msg-id="<?= $m['id'] ?>">
            <div class="msg-bubble-label"><?= $isMe ? 'You' : htmlspecialchars($senderName) ?></div>
            <div class="msg-bubble <?= $isMe ? 'msg-bubble--admin-sent' : 'msg-bubble--user-recv' ?>"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
            <div class="msg-bubble-time"><?= date('M j, g:ia', strtotime($m['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <p class="chat-empty">No messages yet.</p>
        <?php endif; ?>
    </div>

    <?php if ($thread['status'] === 'pending'): ?>
    <div class="chat-pending-notice">This request is pending review. Replying will open the thread.</div>
    <?php endif; ?>

    <?php if (in_array($thread['status'], ['pending', 'open'])): ?>
    <div class="chat-input-wrap chat-input-wrap--admin">
        <form id="reply-form" class="chat-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
            <textarea id="reply-body" name="body" class="chat-textarea" rows="1"
                      placeholder="Reply to <?= htmlspecialchars($senderName) ?>…" maxlength="2000"></textarea>
            <button type="submit" class="chat-send-btn" aria-label="Send">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
        <div id="chat-error" class="chat-error" style="display:none;"></div>
    </div>
    <?php elseif ($thread['status'] === 'closed'): ?>
    <div class="chat-closed-bar">Thread closed.</div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

<script>
const THREAD_ID   = <?= $thread['id'] ?>;
const CSRF_TOKEN  = <?= json_encode(csrf_token()) ?>;
const SENDER_NAME = <?= json_encode($senderName) ?>;
const IS_GUEST    = <?= $thread['sender_role'] === 'guest' ? 'true' : 'false' ?>;
let   lastId      = <?= $lastId ?>;
let   isPending   = <?= $thread['status'] === 'pending' ? 'true' : 'false' ?>;

function markOpen() {
    if (!isPending) return;
    isPending = false;
    const notice = document.querySelector('.chat-pending-notice');
    if (notice) notice.remove();
    const badge = document.querySelector('.chat-topbar-sub .thread-badge');
    if (badge) {
        badge.textContent = 'Open';
        badge.className = 'thread-badge thread-badge--open';
    }
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'<br>');
}

function fmtTime(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'2-digit'});
}

function buildBubble(msg) {
    const isMe  = msg.sender === 'admin';
    const label = isMe ? 'You' : escHtml(SENDER_NAME);
    const wrap  = document.createElement('div');
    wrap.className = 'msg-bubble-wrap ' + (isMe ? 'msg-bubble-wrap--admin-sent' : 'msg-bubble-wrap--user-recv');
    wrap.dataset.msgId = msg.id;
    wrap.innerHTML =
        '<div class="msg-bubble-label">' + label + '</div>' +
        '<div class="msg-bubble ' + (isMe ? 'msg-bubble--admin-sent' : 'msg-bubble--user-recv') + '">' + escHtml(msg.body) + '</div>' +
        '<div class="msg-bubble-time">' + fmtTime(msg.created_at) + '</div>';
    return wrap;
}

function scrollBottom(smooth) {
    const list = document.getElementById('msg-list');
    list.scrollTo({top: list.scrollHeight, behavior: smooth ? 'smooth' : 'auto'});
}

async function poll() {
    try {
        const res = await fetch('/api/messages/poll.php?thread_id=' + THREAD_ID + '&after=' + lastId);
        if (!res.ok) return;
        const data = await res.json();
        if (data.messages && data.messages.length) {
            const list = document.getElementById('msg-list');
            const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            data.messages.forEach(msg => {
                list.appendChild(buildBubble(msg));
                if (msg.id > lastId) lastId = msg.id;
            });
            if (nearBottom) scrollBottom(true);
        }
    } catch (_) {}
}

setInterval(poll, 3000);

const form     = document.getElementById('reply-form');
const textarea = document.getElementById('reply-body');
const errBox   = document.getElementById('chat-error');

if (form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const body = textarea.value.trim();
        if (!body) return;

        const btn = form.querySelector('.chat-send-btn');
        btn.disabled = true;
        errBox.style.display = 'none';

        try {
            const res = await fetch('/api/messages/reply.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({csrf_token: CSRF_TOKEN, thread_id: THREAD_ID, body})
            });
            const data = await res.json();
            if (!res.ok) {
                errBox.textContent = data.error || 'Failed to send.';
                errBox.style.display = 'block';
            } else {
                const list = document.getElementById('msg-list');
                list.appendChild(buildBubble(data));
                if (data.id > lastId) lastId = data.id;
                textarea.value = '';
                textarea.style.height = '';
                scrollBottom(true);
                markOpen();
            }
        } catch (_) {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        }

        btn.disabled = false;
        textarea.focus();
    });

    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', {bubbles: true}));
        }
    });

    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
}

scrollBottom(false);
</script>
</body>
</html>
