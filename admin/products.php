<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();

$search       = trim($_GET['search'] ?? '');
$activeFilter = in_array($_GET['active'] ?? '', ['all', '1', '0']) ? ($_GET['active'] ?? 'all') : 'all';
$catId        = (int)($_GET['category'] ?? 0);

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

$pwhere  = [];
$pparams = [];
if ($search !== '') {
    $like     = '%' . $search . '%';
    $pwhere[] = '(p.name LIKE ? OR b.name LIKE ? OR v.name LIKE ? OR v.email LIKE ?)';
    $pparams  = array_merge($pparams, [$like, $like, $like, $like]);
}
if (!empty($catFilterIds)) {
    $cph      = implode(',', array_fill(0, count($catFilterIds), '?'));
    $pwhere[] = "p.category_id IN ($cph)";
    $pparams  = array_merge($pparams, $catFilterIds);
}
if ($activeFilter === '1')  { $pwhere[] = 'p.active = 1'; }
elseif ($activeFilter === '0') { $pwhere[] = 'p.active = 0'; }

$psql = '
    SELECT p.id, p.name AS product_name, p.description, p.price, p.stock, p.active,
           pp.filename AS photo,
           p.category_id, c.name AS category_name,
           b.id AS business_id, b.name AS business_name,
           v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN businesses b ON b.id = p.business_id
    JOIN vendors v ON v.id = b.user_id
    LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1'
    . (!empty($pwhere) ? ' WHERE ' . implode(' AND ', $pwhere) : '')
    . ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($psql);
$stmt->execute($pparams);
$products = $stmt->fetchAll();

$pcounts = $pdo->query("SELECT COUNT(*) AS total, SUM(active) AS active, SUM(1-active) AS inactive FROM products")->fetch();
$adminSection = 'admin';
$adminTab     = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Products</title>
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
    <h1>Products</h1>

    <?php
    $pSearchQ = $search !== '' ? '&search=' . urlencode($search) : '';
    $pCatQ    = $catId ? '&category=' . $catId : '';
    ?>
    <div class="admin-filters-row">
        <form method="GET" action="/admin/products.php" class="admin-search-form">
            <?php if ($activeFilter !== 'all'): ?><input type="hidden" name="active" value="<?= htmlspecialchars($activeFilter) ?>"><?php endif; ?>
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Product, business, or vendor…" class="admin-search-input" autocomplete="off">
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
            <?php foreach (['all' => 'All', '1' => 'Active', '0' => 'Inactive'] as $key => $label):
                  $n = $key === 'all' ? ($pcounts['total'] ?? 0) : ($key === '1' ? ($pcounts['active'] ?? 0) : ($pcounts['inactive'] ?? 0)); ?>
            <a href="/admin/products.php?active=<?= $key ?><?= $pSearchQ . $pCatQ ?>" class="filter-btn <?= $activeFilter === $key ? 'active' : '' ?>">
                <?= $label ?> <span class="filter-count"><?= $n ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <p class="empty">No products found.</p>
    <?php else: ?>
    <div class="vendor-list">
        <?php foreach ($products as $p): ?>
        <?php $badgeClass = $p['active'] ? 'badge-green' : 'badge-grey';
              $badgeLabel = $p['active'] ? 'Active' : 'Inactive'; ?>
        <a href="/admin/product.php?id=<?= $p['id'] ?>" class="vendor-row" style="text-decoration:none;color:inherit;">
            <div class="vendor-row-main">
                <span class="vendor-row-name"><?= htmlspecialchars($p['product_name']) ?></span>
                <span class="vendor-row-email"><?= htmlspecialchars($p['business_name']) ?> · <?= htmlspecialchars($p['vendor_email']) ?></span>
            </div>
            <div class="vendor-row-right">
                <span class="vendor-row-biz">$<?= number_format($p['price'], 2) ?> · <?= (int)$p['stock'] ?> stock</span>
                <?php if ($p['category_name']): ?><span class="vendor-row-biz" style="color:#9ca3af"><?= htmlspecialchars($p['category_name']) ?></span><?php endif; ?>
                <span class="order-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
