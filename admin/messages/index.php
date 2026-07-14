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

$success = $_SESSION['admin_msg_success'] ?? '';
unset($_SESSION['admin_msg_success']);

$validRoles   = ['buyer', 'vendor', 'guest'];
$roleFilter   = in_array($_GET['role'] ?? '', $validRoles) ? $_GET['role'] : 'buyer';
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'pending', 'open', 'closed']) ? $_GET['status'] : 'all';

$threads = $pdo->prepare("
    SELECT t.id, t.sender_id, t.sender_role, t.guest_name, t.guest_email,
           t.subject, t.issue_type, t.status, t.updated_at,
           (SELECT body FROM support_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT COUNT(*) FROM support_messages WHERE thread_id = t.id AND sender != 'admin' AND read_at IS NULL) AS unread_count
    FROM support_threads t
    WHERE t.sender_role = ?" . ($statusFilter !== 'all' ? " AND t.status = ?" : "") . "
    ORDER BY t.updated_at DESC
");
$threads->execute($statusFilter !== 'all' ? [$roleFilter, $statusFilter] : [$roleFilter]);
$threads = $threads->fetchAll();

// Resolve names for buyer/vendor threads
// (loop var must NOT be $t — that's the translations array the header/footer use)
$buyerIds = $vendorIds = [];
foreach ($threads as $th) {
    if ($th['sender_role'] === 'buyer')  $buyerIds[]  = $th['sender_id'];
    if ($th['sender_role'] === 'vendor') $vendorIds[] = $th['sender_id'];
}
$buyerNames = $vendorNames = [];
if (!empty($buyerIds)) {
    $ph = implode(',', array_fill(0, count($buyerIds), '?'));
    $s  = $pdo->prepare("SELECT id, name, email FROM buyers WHERE id IN ($ph)");
    $s->execute(array_values($buyerIds));
    foreach ($s->fetchAll() as $b) $buyerNames[$b['id']] = $b['name'] ?: $b['email'];
}
if (!empty($vendorIds)) {
    $ph = implode(',', array_fill(0, count($vendorIds), '?'));
    $s  = $pdo->prepare("SELECT id, name, email FROM vendors WHERE id IN ($ph)");
    $s->execute(array_values($vendorIds));
    foreach ($s->fetchAll() as $v) $vendorNames[$v['id']] = $v['name'] ?: $v['email'];
}

// Counts scoped to current role filter
$countStmt = $pdo->prepare("
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'open')    AS open,
        SUM(status = 'closed')  AS closed
    FROM support_threads
    WHERE sender_role = ?
");
$countStmt->execute([$roleFilter]);
$counts = $countStmt->fetch();

// Per-role badge counts for the role tabs (threads still needing attention)
$roleCounts = ['buyer' => 0, 'vendor' => 0, 'guest' => 0];
foreach ($pdo->query("SELECT sender_role, COUNT(*) AS c FROM support_threads WHERE status IN ('pending','open') GROUP BY sender_role") as $rc) {
    $roleCounts[$rc['sender_role']] = (int)$rc['c'];
}

function msgUrl(string $role, string $status): string {
    return '/admin/messages/?role=' . urlencode($role) . '&status=' . urlencode($status);
}
$adminSection = 'messages';
$adminTab     = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Messages</title>
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
    <div class="amsg-page-head">
        <h1>Messages</h1>
    </div>

    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <div class="amsg-role-tabs">
        <a href="<?= msgUrl('buyer', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'buyer' ? 'active' : '' ?>">Buyers<?php if ($roleCounts['buyer'] > 0): ?> <span class="admin-tab-badge"><?= $roleCounts['buyer'] ?></span><?php endif; ?></a>
        <a href="<?= msgUrl('vendor', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'vendor' ? 'active' : '' ?>">Vendors<?php if ($roleCounts['vendor'] > 0): ?> <span class="admin-tab-badge"><?= $roleCounts['vendor'] ?></span><?php endif; ?></a>
        <a href="<?= msgUrl('guest', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'guest' ? 'active' : '' ?>">Contact Form<?php if ($roleCounts['guest'] > 0): ?> <span class="admin-tab-badge"><?= $roleCounts['guest'] ?></span><?php endif; ?></a>
        <a href="/admin/messages/emails.php" class="amsg-role-tab">Email templates</a>
    </div>

    <div class="order-filters" style="margin-bottom:1.25rem;">
        <a href="<?= msgUrl($roleFilter, 'all') ?>" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
        <a href="<?= msgUrl($roleFilter, 'pending') ?>" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
            Pending<?php if ($counts['pending'] > 0): ?> <span class="filter-count"><?= (int)$counts['pending'] ?></span><?php endif; ?>
        </a>
        <a href="<?= msgUrl($roleFilter, 'open') ?>" class="filter-btn <?= $statusFilter === 'open' ? 'active' : '' ?>">
            Open<?php if ($counts['open'] > 0): ?> <span class="filter-count"><?= (int)$counts['open'] ?></span><?php endif; ?>
        </a>
        <a href="<?= msgUrl($roleFilter, 'closed') ?>" class="filter-btn <?= $statusFilter === 'closed' ? 'active' : '' ?>">Closed</a>
    </div>

    <?php if (empty($threads)): ?>
        <p style="color:#9ca3af;font-size:0.9rem;">No messages.</p>
    <?php else: ?>
    <div class="amsg-list">
        <?php foreach ($threads as $th):
            if ($th['sender_role'] === 'guest') {
                $name = $th['guest_name'] ?: $th['guest_email'] ?: 'Guest';
            } elseif ($th['sender_role'] === 'buyer') {
                $name = $buyerNames[$th['sender_id']] ?? 'Unknown buyer';
            } else {
                $name = $vendorNames[$th['sender_id']] ?? 'Unknown vendor';
            }
            $hasUnread = $th['unread_count'] > 0;
        ?>
        <a href="/admin/messages/thread.php?id=<?= $th['id'] ?>" class="amsg-row <?= $hasUnread ? 'amsg-row--unread' : '' ?>">
            <?php if ($hasUnread): ?>
                <span class="amsg-dot"></span>
            <?php else: ?>
                <span style="width:8px;flex-shrink:0;"></span>
            <?php endif; ?>
            <span class="amsg-sender">
                <?= htmlspecialchars($name) ?>
                <span class="amsg-role-badge amsg-role-badge--<?= $th['sender_role'] ?>"><?= $th['sender_role'] === 'guest' ? 'Contact form' : ucfirst($th['sender_role']) ?></span>
            </span>
            <span class="amsg-subject">
                <?= htmlspecialchars($th['subject']) ?>
                <?php if ($th['issue_type']): ?>
                    <span class="amsg-issue-type"><?= htmlspecialchars($th['issue_type']) ?></span>
                <?php endif; ?>
            </span>
            <?php if ($th['last_body']): ?>
            <span class="amsg-preview"><?= htmlspecialchars(mb_substr($th['last_body'], 0, 60)) ?></span>
            <?php endif; ?>
            <span class="amsg-meta">
                <span class="thread-badge thread-badge--<?= $th['status'] ?>"><?= ucfirst($th['status']) ?></span>
                <span class="amsg-date"><?= date('M j', strtotime($th['updated_at'])) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

</body>
</html>
