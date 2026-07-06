<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('vendors');

$success = $_SESSION['admin_success'] ?? '';
unset($_SESSION['admin_success']);

$search         = trim($_GET['search'] ?? '');
$statusFilter   = in_array($_GET['status'] ?? '', ['all','pending','approved','rejected','no_business'])
                  ? ($_GET['status'] ?? 'all') : 'all';
$catId = (int)($_GET['category'] ?? 0);

$allCats    = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll();
$catBuild   = function(array $cats, $pid = null) use (&$catBuild): array {
    $b = [];
    foreach ($cats as $c) {
        if ($c['parent_id'] == $pid) { $c['children'] = $catBuild($cats, $c['id']); $b[] = $c; }
    }
    return $b;
};
$catFlatten = function(array $nodes, int $d = 0) use (&$catFlatten): array {
    $r = [];
    foreach ($nodes as $n) { $ch = $n['children']; unset($n['children']); $n['depth'] = $d; $r[] = $n; $r = array_merge($r, $catFlatten($ch, $d + 1)); }
    return $r;
};
$catFlat = $catFlatten($catBuild($allCats));
if ($catId && !in_array($catId, array_column($catFlat, 'id'))) $catId = 0;

$catFilterIds = [];
if ($catId) {
    $catFilterIds[] = $catId;
    $collecting = false; $targetDepth = null;
    foreach ($catFlat as $node) {
        if ($node['id'] === $catId) { $collecting = true; $targetDepth = $node['depth']; continue; }
        if ($collecting) { if ($node['depth'] > $targetDepth) $catFilterIds[] = $node['id']; else break; }
    }
}

$vwhere  = [];
$vparams = [];
if ($search !== '') {
    $vwhere[]  = '(v.name LIKE ? OR v.email LIKE ? OR b.name LIKE ?)';
    $vparams[] = '%' . $search . '%';
    $vparams[] = '%' . $search . '%';
    $vparams[] = '%' . $search . '%';
}
if (!empty($catFilterIds)) {
    $cph      = implode(',', array_fill(0, count($catFilterIds), '?'));
    $vwhere[] = "EXISTS (SELECT 1 FROM products pp WHERE pp.business_id = b.id AND pp.category_id IN ($cph))";
    $vparams  = array_merge($vparams, $catFilterIds);
}
if ($statusFilter === 'pending')         { $vwhere[] = 'b.approved = 0 AND b.id IS NOT NULL'; }
elseif ($statusFilter === 'approved')    { $vwhere[] = 'b.approved = 1'; }
elseif ($statusFilter === 'rejected')    { $vwhere[] = 'b.approved = -1'; }
elseif ($statusFilter === 'no_business') { $vwhere[] = 'b.id IS NULL'; }

$vsql = '
    SELECT v.id, v.name, v.email, v.created_at,
           v.banned, v.ban_reason, v.banned_at,
           b.id AS business_id, b.name AS business_name,
           b.category, b.description, b.address, b.lat, b.lng,
           b.approved, b.created_at AS submitted_at
    FROM vendors v
    LEFT JOIN businesses b ON b.user_id = v.id'
    . (!empty($vwhere) ? ' WHERE ' . implode(' AND ', $vwhere) : '')
    . ' ORDER BY CASE WHEN b.approved = 0 AND b.id IS NOT NULL THEN 0 ELSE 1 END ASC, v.created_at DESC';

$stmt = $pdo->prepare($vsql);
$stmt->execute($vparams);
$vendors = $stmt->fetchAll();

$vcounts = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN b.id IS NULL THEN 1 ELSE 0 END) AS no_business,
           SUM(CASE WHEN b.approved = 0 AND b.id IS NOT NULL THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN b.approved = 1 THEN 1 ELSE 0 END) AS approved,
           SUM(CASE WHEN b.approved = -1 THEN 1 ELSE 0 END) AS rejected
    FROM vendors v LEFT JOIN businesses b ON b.user_id = v.id
")->fetch();


$pendingVendorCount = (int)($vcounts['pending'] ?? 0);
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();

$adminSection = 'admin';
$adminTab     = 'vendors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Vendors</title>
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
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Vendors</h1>

    <?php
    $vSearchQ = $search !== '' ? '&search=' . urlencode($search) : '';
    $vCatQ    = $catId ? '&category=' . $catId : '';
    ?>
    <div class="admin-filters-row">
        <form method="GET" action="/admin/" class="admin-search-form">
            <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or email…" class="admin-search-input" autocomplete="off">
            <?php if (!empty($catFlat)): ?>
            <select name="category" class="admin-select" onchange="this.form.submit()">
                <option value="">All categories</option>
                <?php foreach ($catFlat as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catId === $cat['id'] ? 'selected' : '' ?>>
                    <?= str_repeat('— ', $cat['depth']) . htmlspecialchars($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
        <div class="order-filters">
            <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'no_business' => 'No business'] as $key => $label):
                  $n = $key === 'all' ? ($vcounts['total'] ?? 0) : ($vcounts[$key] ?? 0); ?>
            <a href="/admin/?status=<?= $key ?><?= $vSearchQ . $vCatQ ?>" class="filter-btn <?= $statusFilter === $key ? 'active' : '' ?>">
                <?= $label ?> <span class="filter-count"><?= $n ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($vendors)): ?>
        <p class="empty">No vendors registered yet.</p>
    <?php else: ?>
    <div class="vendor-list">
        <?php foreach ($vendors as $v): ?>
        <?php
        if (!$v['business_id'])           { $statusLabel = 'No business'; $statusClass = 'badge-grey'; }
        elseif ($v['approved'] === 1)     { $statusLabel = 'Approved';    $statusClass = 'badge-green'; }
        elseif ($v['approved'] === -1)    { $statusLabel = 'Rejected';    $statusClass = 'badge-red'; }
        else                              { $statusLabel = 'Pending';     $statusClass = 'badge-yellow'; }
        ?>
        <a href="/admin/vendor.php?id=<?= $v['id'] ?>" class="vendor-row" style="text-decoration:none;color:inherit;">
            <div class="vendor-row-main">
                <span class="vendor-row-name"><?= $v['business_name'] ? htmlspecialchars($v['business_name']) : '—' ?></span>
                <span class="vendor-row-email"><?= htmlspecialchars($v['email']) ?></span>
            </div>
            <div class="vendor-row-right">
                <span class="vendor-row-biz"><?= htmlspecialchars($v['name'] ?: $v['email']) ?></span>
                <?php if ($v['banned']): ?>
                <span class="order-badge badge-red">Suspended</span>
                <?php endif; ?>
                <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                <span class="vendor-row-date"><?= date('M j, Y', strtotime($v['created_at'])) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
