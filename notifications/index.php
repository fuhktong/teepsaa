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
require __DIR__ . '/../config/seo.php';
require_once __DIR__ . '/../config/notify.php';

// Buyers and vendors both land here — the notifications table is keyed on
// (role, user_id), so one page serves each of them their own rows.
$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['buyer', 'vendor'], true)) {
    // The page answers on both hosts (it's a neutral path in config/subdomain.php),
    // so send a signed-out visitor to the sign-in door for the host they're on.
    header('Location: ' . (defined('IS_VENDOR_SUBDOMAIN') && IS_VENDOR_SUBDOMAIN ? '/login-vendor/' : '/login-buyer/'));
    exit;
}

$userId = (int)$_SESSION['user_id'];

// "Mark all read" is a plain form post rather than a fetch: the header's unread
// count is rendered server-side, so the redirect back is what clears the badge.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE role = ? AND user_id = ? AND read_at IS NULL')
        ->execute([$role, $userId]);
    header('Location: /notifications/');
    exit;
}

$perPage = 30;

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ?');
$totalStmt->execute([$role, $userId]);
$total = (int)$totalStmt->fetchColumn();

$totalPages = max(1, (int)ceil($total / $perPage));
$page       = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages) $page = $totalPages;

$stmt = $pdo->prepare(
    'SELECT id, type, message, data, link, read_at, created_at
     FROM notifications
     WHERE role = ? AND user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
);
$stmt->execute([$role, $userId]);
$rows = $stmt->fetchAll();

$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE role = ? AND user_id = ? AND read_at IS NULL');
$unreadStmt->execute([$role, $userId]);
$unread = (int)$unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
$headTitle  = 'Notifications — teepsaa';
$headRobots = 'noindex, nofollow';
$headSeo    = false;
$headCss    = ['/pagination/pagination.css', '/notifications/notifications.css'];
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main class="notif-main">

    <div class="notif-head">
        <h1 class="notif-title"><?= $t['nav_notifications'] ?></h1>
        <?php if ($unread > 0): ?>
        <form method="post" action="/notifications/">
            <?= csrf_input() ?>
            <button type="submit" class="notif-markall"><?= $t['nav_mark_all_read'] ?></button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!$rows): ?>
        <p class="notif-empty"><?= $t['notifications_empty'] ?></p>
    <?php else: ?>

    <ul class="notif-list">
        <?php foreach ($rows as $r): ?>
            <?php
            $cls = 'notif-item' . ($r['read_at'] === null ? ' notif-item--unread' : '');
            $msg = htmlspecialchars(notification_text($r, $t));
            $ago = htmlspecialchars(notification_ago($r['created_at'], $t));
            ?>
            <li>
                <?php if (!empty($r['link'])): ?>
                    <a class="<?= $cls ?>" href="<?= htmlspecialchars($r['link']) ?>" data-id="<?= (int)$r['id'] ?>">
                        <span class="notif-item-msg"><?= $msg ?></span>
                        <span class="notif-item-time"><?= $ago ?></span>
                    </a>
                <?php else: ?>
                    <?php // Nothing to open — an unlinked row only clears via "Mark all read". ?>
                    <div class="<?= $cls ?>">
                        <span class="notif-item-msg"><?= $msg ?></span>
                        <span class="notif-item-time"><?= $ago ?></span>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php
    $pgCurrent = $page;
    $pgTotal   = $totalPages;
    $pgUrl     = fn(int $p): string => '/notifications/?page=' . $p;
    require __DIR__ . '/../pagination/pagination.php';
    ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
// Same contract as the bell dropdown: opening a notification marks that one
// row read. keepalive lets the request survive the navigation it races.
document.querySelectorAll('.notif-item[data-id]').forEach(function (el) {
    el.addEventListener('click', function () {
        fetch('/api/notifications/mark-read.php', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            body: new URLSearchParams({ id: this.dataset.id, csrf_token: window.CSRF || '' })
        });
    });
});
</script>

</body>
</html>
