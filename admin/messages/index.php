<?php
session_start();
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$success = $_SESSION['admin_msg_success'] ?? '';
unset($_SESSION['admin_msg_success']);

$validRoles   = ['buyer', 'vendor', 'guest'];
$roleFilter   = in_array($_GET['role'] ?? '', $validRoles) ? $_GET['role'] : 'buyer';
$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'open', 'closed']) ? $_GET['status'] : 'pending';

$threads = $pdo->prepare("
    SELECT t.id, t.sender_id, t.sender_role, t.guest_name, t.guest_email,
           t.subject, t.issue_type, t.status, t.updated_at,
           (SELECT body FROM support_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) AS last_body,
           (SELECT COUNT(*) FROM support_messages WHERE thread_id = t.id AND sender != 'admin' AND read_at IS NULL) AS unread_count
    FROM support_threads t
    WHERE t.sender_role = ? AND t.status = ?
    ORDER BY t.updated_at DESC
");
$threads->execute([$roleFilter, $statusFilter]);
$threads = $threads->fetchAll();

// Resolve names for buyer/vendor threads
$buyerIds = $vendorIds = [];
foreach ($threads as $t) {
    if ($t['sender_role'] === 'buyer')  $buyerIds[]  = $t['sender_id'];
    if ($t['sender_role'] === 'vendor') $vendorIds[] = $t['sender_id'];
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
$counts = $pdo->prepare("
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'open')    AS open,
        SUM(status = 'closed')  AS closed
    FROM support_threads
    WHERE sender_role = ?
")->execute([$roleFilter]) ? null : null;
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
        <a href="<?= msgUrl('buyer', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'buyer' ? 'active' : '' ?>">Buyers</a>
        <a href="<?= msgUrl('vendor', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'vendor' ? 'active' : '' ?>">Vendors</a>
        <a href="<?= msgUrl('guest', $statusFilter) ?>" class="amsg-role-tab <?= $roleFilter === 'guest' ? 'active' : '' ?>">Contact Form</a>
    </div>

    <div class="order-filters" style="margin-bottom:1.25rem;">
        <a href="<?= msgUrl($roleFilter, 'pending') ?>" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
            Pending <?php if ($counts['pending'] > 0): ?><span class="filter-count"><?= $counts['pending'] ?></span><?php endif; ?>
        </a>
        <a href="<?= msgUrl($roleFilter, 'open') ?>" class="filter-btn <?= $statusFilter === 'open' ? 'active' : '' ?>">
            Open <span class="filter-count"><?= (int)$counts['open'] ?></span>
        </a>
        <a href="<?= msgUrl($roleFilter, 'closed') ?>" class="filter-btn <?= $statusFilter === 'closed' ? 'active' : '' ?>">
            Closed <span class="filter-count"><?= (int)$counts['closed'] ?></span>
        </a>
    </div>

    <?php if (empty($threads)): ?>
        <p style="color:#9ca3af;font-size:0.9rem;">No messages.</p>
    <?php else: ?>
    <div class="amsg-list">
        <?php foreach ($threads as $t):
            if ($t['sender_role'] === 'guest') {
                $name = $t['guest_name'] ?: $t['guest_email'] ?: 'Guest';
            } elseif ($t['sender_role'] === 'buyer') {
                $name = $buyerNames[$t['sender_id']] ?? 'Unknown buyer';
            } else {
                $name = $vendorNames[$t['sender_id']] ?? 'Unknown vendor';
            }
            $hasUnread = $t['unread_count'] > 0;
        ?>
        <a href="/admin/messages/thread.php?id=<?= $t['id'] ?>" class="amsg-row <?= $hasUnread ? 'amsg-row--unread' : '' ?>">
            <?php if ($hasUnread): ?>
                <span class="amsg-dot"></span>
            <?php else: ?>
                <span style="width:8px;flex-shrink:0;"></span>
            <?php endif; ?>
            <span class="amsg-sender">
                <?= htmlspecialchars($name) ?>
                <span class="amsg-role-badge amsg-role-badge--<?= $t['sender_role'] ?>"><?= $t['sender_role'] === 'guest' ? 'Contact form' : ucfirst($t['sender_role']) ?></span>
            </span>
            <span class="amsg-subject">
                <?= htmlspecialchars($t['subject']) ?>
                <?php if ($t['issue_type']): ?>
                    <span class="amsg-issue-type"><?= htmlspecialchars($t['issue_type']) ?></span>
                <?php endif; ?>
            </span>
            <?php if ($t['last_body']): ?>
            <span class="amsg-preview"><?= htmlspecialchars(mb_substr($t['last_body'], 0, 60)) ?></span>
            <?php endif; ?>
            <span class="amsg-meta">
                <span class="thread-badge thread-badge--<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span>
                <span class="amsg-date"><?= date('M j', strtotime($t['updated_at'])) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

</body>
</html>
