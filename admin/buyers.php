<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('buyers');

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor','guest') AND read_at IS NULL")->fetchColumn();

$search       = trim($_GET['search'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'active', 'banned']) ? ($_GET['status'] ?? 'all') : 'all';

$bwhere  = [];
$bparams = [];
if ($search !== '') {
    $bwhere[]  = '(b.name LIKE ? OR b.email LIKE ?)';
    $bparams[] = '%' . $search . '%';
    $bparams[] = '%' . $search . '%';
}
if ($statusFilter === 'active')  { $bwhere[] = 'b.banned = 0'; }
elseif ($statusFilter === 'banned') { $bwhere[] = 'b.banned = 1'; }

$bsql = '
    SELECT b.id, b.name, b.email, b.phone, b.house_number, b.address, b.khan, b.sangkat,
           b.created_at, b.banned, b.ban_reason, b.banned_at, b.admin_note,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status NOT IN (\'cancelled\') THEN o.subtotal - o.discount_amount END), 0) AS total_spent,
           SUM(CASE WHEN o.status = \'refund_requested\' OR o.status LIKE \'refund%\' OR o.status LIKE \'return%\' THEN 1 ELSE 0 END) AS refund_count
    FROM buyers b
    LEFT JOIN orders o ON o.buyer_user_id = b.id'
    . (!empty($bwhere) ? ' WHERE ' . implode(' AND ', $bwhere) : '')
    . ' GROUP BY b.id ORDER BY b.created_at DESC';

$stmt = $pdo->prepare($bsql);
$stmt->execute($bparams);
$buyers = $stmt->fetchAll();

$bcounts = $pdo->query("SELECT COUNT(*) AS total, SUM(banned) AS banned, SUM(1-banned) AS active FROM buyers")->fetch();

$adminSection = 'admin';
$adminTab     = 'buyers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Buyers</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Buyers</h1>

    <?php $bSearchQ = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
    <div class="admin-filters-row">
        <form method="GET" action="/admin/buyers.php" class="admin-search-form">
            <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or email…" class="admin-search-input" autocomplete="off">
        </form>
        <div class="order-filters">
            <?php foreach (['all' => 'All', 'active' => 'Active', 'banned' => 'Banned'] as $key => $label):
                  $n = $key === 'all' ? ($bcounts['total'] ?? 0) : ($bcounts[$key] ?? 0); ?>
            <a href="/admin/buyers.php?status=<?= $key ?><?= $bSearchQ ?>" class="filter-btn <?= $statusFilter === $key ? 'active' : '' ?>">
                <?= $label ?> <span class="filter-count"><?= $n ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (empty($buyers)): ?>
        <p class="empty">No buyers registered yet.</p>
    <?php else: ?>
    <div class="vendor-list">
        <?php foreach ($buyers as $b): ?>
        <?php $statusClass = $b['banned'] ? 'badge-red' : 'badge-green';
              $statusLabel = $b['banned'] ? 'Banned' : 'Active'; ?>
        <a href="/admin/buyer.php?id=<?= $b['id'] ?>" class="vendor-row" style="text-decoration:none;color:inherit;">
            <div class="vendor-row-main">
                <span class="vendor-row-name"><?= htmlspecialchars($b['name']) ?></span>
                <span class="vendor-row-email"><?= htmlspecialchars($b['email']) ?></span>
            </div>
            <div class="vendor-row-right">
                <span class="vendor-row-biz"><?= (int)$b['order_count'] ?> orders · $<?= number_format($b['total_spent'], 2) ?></span>
                <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                <span class="vendor-row-date"><?= date('M j, Y', strtotime($b['created_at'])) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
