<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId   = $_SESSION['user_id'];
$threadId = (int)($_GET['id'] ?? 0);

if (!$threadId) {
    header('Location: /messages-buyer/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM support_threads WHERE id = ? AND sender_id = ? AND sender_role = 'buyer'");
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(403);
    exit('Thread not found.');
}

$pdo->prepare("UPDATE support_messages SET read_at = NOW() WHERE thread_id = ? AND sender = 'admin' AND read_at IS NULL")
    ->execute([$threadId]);

$msgs = $pdo->prepare('SELECT id, sender, body, created_at FROM support_messages WHERE thread_id = ? ORDER BY id ASC');
$msgs->execute([$threadId]);
$messages = $msgs->fetchAll();
$lastId   = !empty($messages) ? (int)end($messages)['id'] : 0;
$isPending = $thread['status'] === 'pending';
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($thread['subject']) ?> — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/messages-buyer/messages-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>

    <?php if ($isPending): ?>

    <a href="/messages-buyer/" class="ticket-back">← <?= $t['messages_title'] ?></a>

    <div class="ticket-header">
        <div class="ticket-header-top">
            <h1 class="ticket-subject"><?= htmlspecialchars($thread['subject']) ?></h1>
            <span class="thread-badge thread-badge--pending"><?= $t['messages_status_pending'] ?></span>
        </div>
        <div class="ticket-meta">
            <?php if ($thread['issue_type']): ?>
                <span class="ticket-issue-type"><?= htmlspecialchars($thread['issue_type']) ?></span>
            <?php endif; ?>
            <span class="ticket-date"><?= $t['messages_submitted'] ?> <?= fmt_date('M j, Y', strtotime($thread['created_at'])) ?></span>
        </div>
    </div>

    <div class="ticket-thread">
        <?php foreach ($messages as $m): ?>
        <div class="ticket-msg ticket-msg--<?= $m['sender'] === 'admin' ? 'admin' : 'user' ?>" data-msg-id="<?= $m['id'] ?>">
            <div class="ticket-msg-header">
                <span class="ticket-msg-from"><?= $m['sender'] === 'admin' ? $t['messages_support_name'] : $t['messages_you'] ?></span>
                <span class="ticket-msg-time"><?= fmt_date('M j, Y · g:ia', strtotime($m['created_at'])) ?></span>
            </div>
            <div class="ticket-msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="ticket-notice ticket-notice--pending">
        <?= $t['messages_pending_notice'] ?>
    </div>

    <?php else: ?>

    <div class="chat-topbar">
        <a href="/messages-buyer/" class="chat-back">←</a>
        <div class="chat-topbar-info">
            <div class="chat-topbar-title"><?= htmlspecialchars($thread['subject']) ?></div>
            <div class="chat-topbar-sub">
                <?= $t['messages_support_name'] ?>
                <?php if ($thread['issue_type']): ?>
                    · <span class="ticket-issue-type"><?= htmlspecialchars($thread['issue_type']) ?></span>
                <?php endif; ?>
                · <span class="thread-badge thread-badge--<?= $thread['status'] ?>"><?= $t['messages_status_' . $thread['status']] ?? ucfirst($thread['status']) ?></span>
            </div>
        </div>
    </div>

    <div class="chat-messages" id="msg-list">
        <?php foreach ($messages as $m): ?>
        <?php $isMe = $m['sender'] !== 'admin'; ?>
        <div class="msg-bubble-wrap <?= $isMe ? 'msg-bubble-wrap--sent' : 'msg-bubble-wrap--recv' ?>" data-msg-id="<?= $m['id'] ?>">
            <div class="msg-bubble-label"><?= $isMe ? $t['messages_you'] : $t['messages_support_name'] ?></div>
            <div class="msg-bubble <?= $isMe ? 'msg-bubble--sent' : 'msg-bubble--recv' ?>"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
            <div class="msg-bubble-time"><?= fmt_date('M j, g:ia', strtotime($m['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($thread['status'] === 'open'): ?>
    <div class="chat-input-wrap">
        <form id="reply-form" class="chat-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">
            <textarea id="reply-body" name="body" class="chat-textarea" rows="1"
                      placeholder="<?= htmlspecialchars($t['messages_reply_placeholder']) ?>" maxlength="2000"></textarea>
            <button type="submit" class="chat-send-btn" aria-label="Send">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
        <div id="chat-error" class="chat-error" style="display:none;"></div>
    </div>
    <?php else: ?>
    <div class="chat-closed-bar"><?= sprintf($t['messages_closed_notice'], '<a href="/contact-buyer/">' . $t['messages_contact_lower'] . '</a>') ?></div>
    <?php endif; ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
const THREAD_ID    = <?= $thread['id'] ?>;
const CSRF_TOKEN   = <?= json_encode(csrf_token()) ?>;
const YOU_LABEL    = <?= json_encode($t['messages_you']) ?>;
const ADMIN_LABEL  = <?= json_encode($t['messages_support_name']) ?>;
let   lastId       = <?= $lastId ?>;
let   isPending    = <?= $isPending ? 'true' : 'false' ?>;

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'<br>');
}

function fmtTime(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'2-digit'});
}

// Below 640px the message list isn't its own scroller (the composer goes
// sticky and the page scrolls instead), so scrolling the list is a no-op
// there and the window has to be moved instead.
function listScrolls() {
    const list = document.getElementById('msg-list');
    return list && list.scrollHeight > list.clientHeight + 1;
}

// How far the newest message sits below the fold. Negative means it's
// already on screen — the sticky composer's height counts as "covered".
function overshoot() {
    const list = document.getElementById('msg-list');
    const bar  = document.querySelector('.chat-input-wrap, .chat-closed-bar');
    return list.getBoundingClientRect().bottom
         + (bar ? bar.offsetHeight : 0)
         - window.innerHeight;
}

function scrollBottom(smooth) {
    const list     = document.getElementById('msg-list');
    if (!list) return;
    const behavior = smooth ? 'smooth' : 'auto';
    if (listScrolls()) {
        list.scrollTo({top: list.scrollHeight, behavior});
        return;
    }
    // Nudge the page just far enough, rather than to document bottom — that
    // would park a short thread down in the footer.
    const delta = overshoot();
    if (delta > 0) window.scrollBy({top: delta, behavior});
}

function isNearBottom() {
    const list = document.getElementById('msg-list');
    if (!list) return true;
    if (listScrolls()) {
        return list.scrollHeight - list.scrollTop - list.clientHeight < 80;
    }
    return overshoot() < 120;
}

function buildBubble(msg) {
    const isMe = msg.sender !== 'admin';
    const wrap = document.createElement('div');
    wrap.className = 'msg-bubble-wrap ' + (isMe ? 'msg-bubble-wrap--sent' : 'msg-bubble-wrap--recv');
    wrap.dataset.msgId = msg.id;
    wrap.innerHTML =
        '<div class="msg-bubble-label">' + escHtml(isMe ? YOU_LABEL : ADMIN_LABEL) + '</div>' +
        '<div class="msg-bubble ' + (isMe ? 'msg-bubble--sent' : 'msg-bubble--recv') + '">' + escHtml(msg.body) + '</div>' +
        '<div class="msg-bubble-time">' + fmtTime(msg.created_at) + '</div>';
    return wrap;
}

async function poll() {
    try {
        const res = await fetch('/api/messages/poll.php?thread_id=' + THREAD_ID + '&after=' + lastId);
        if (!res.ok) return;
        const data = await res.json();
        if (data.messages && data.messages.length) {
            const hasAdmin = data.messages.some(m => m.sender === 'admin');
            if (hasAdmin && isPending) { location.reload(); return; }
            const list = document.getElementById('msg-list');
            if (list) {
                const stick = isNearBottom();
                data.messages.forEach(msg => {
                    if (msg.sender === 'admin') {
                        list.appendChild(buildBubble(msg));
                        if (msg.id > lastId) lastId = msg.id;
                    }
                });
                if (stick) scrollBottom(true);
            }
        }
    } catch (_) {}
}

setInterval(poll, 10000);

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
