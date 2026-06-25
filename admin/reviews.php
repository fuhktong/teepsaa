<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$search = trim($_GET['q'] ?? '');

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();

$sql = '
    SELECT r.id, r.rating, r.comment, r.created_at,
           r.buyer_id,
           b.name AS buyer_name,
           COALESCE(p.name, oi.product_name) AS product_display,
           biz.name AS business_name,
           v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email,
           r.product_id
    FROM reviews r
    JOIN buyers b ON b.id = r.buyer_id
    JOIN order_items oi ON oi.id = r.order_item_id
    LEFT JOIN products p ON p.id = r.product_id
    JOIN businesses biz ON biz.id = r.business_id
    JOIN vendors v ON v.id = biz.user_id
    WHERE 1=1
';
$params = [];

if ($search !== '') {
    $sql .= ' AND (biz.name LIKE ? OR v.name LIKE ? OR v.email LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= ' ORDER BY r.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
$adminSection = 'admin';
$adminTab     = 'reviews';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Reviews</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>Reviews <?php if (!empty($reviews) || $search): ?><span class="admin-count-chip"><?= count($reviews) ?></span><?php endif; ?></h1>

    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="admin-filters-row">
        <form method="GET" class="admin-search-form">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Vendor or business…" class="admin-search-input">
            <button type="submit" class="btn-save">Search</button>
            <?php if ($search): ?><a href="/admin/reviews.php" class="admin-filter-clear">Clear</a><?php endif; ?>
        </form>
    </div>

    <?php if (empty($reviews)): ?>
    <p class="empty">No reviews found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Rating</th>
                <th>Product</th>
                <th>Business</th>
                <th>Buyer</th>
                <th>Comment</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reviews as $r):
            $nameParts   = explode(' ', trim($r['buyer_name']));
            $displayName = $nameParts[0] . (count($nameParts) > 1 ? ' ' . strtoupper(substr(end($nameParts), 0, 1)) . '.' : '');
        ?>
        <tr>
            <td class="review-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
            <td><?php if ($r['product_id']): ?><a href="/admin/product.php?id=<?= $r['product_id'] ?>" class="admin-row-link"><?= htmlspecialchars($r['product_display']) ?></a><?php else: ?><?= htmlspecialchars($r['product_display']) ?><?php endif; ?></td>
            <td><a href="/admin/vendor.php?id=<?= $r['vendor_id'] ?>" class="admin-row-link"><?= htmlspecialchars($r['business_name']) ?></a></td>
            <td><a href="/admin/buyer.php?id=<?= $r['buyer_id'] ?>" class="admin-row-link"><?= htmlspecialchars($displayName) ?></a></td>
            <td class="review-comment"><?= $r['comment'] ? htmlspecialchars(mb_strimwidth($r['comment'], 0, 120, '…')) : '<span class="review-no-comment">—</span>' ?></td>
            <td class="review-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td>
                <form method="POST" action="/admin/review-action.php" onsubmit="return confirm('Delete this review?')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="product_id" value="<?= $r['product_id'] ?? 0 ?>">
                    <input type="hidden" name="redirect_to" value="reviews">
                    <button type="submit" class="btn-admin-sm btn-admin-sm--danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
