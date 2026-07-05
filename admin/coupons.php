<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$coupons = $pdo->query('
    SELECT c.*, b.name AS business_name
    FROM coupons c
    LEFT JOIN businesses b ON b.id = c.business_id
    ORDER BY c.created_at DESC
')->fetchAll();
$adminSection = 'marketing';
$adminTab     = 'coupons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Coupons</title>
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

    <h1>Coupons</h1>
    <p class="cat-page-desc">Coupons created here (Shop: —) are sitewide — the platform absorbs the discount and vendor payouts are unaffected. Coupons with a Shop listed were created by that vendor and the discount comes out of their own payout.</p>

    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="admin-filters-row">
        <form method="POST" action="/admin/coupon-action.php" class="admin-search-form" style="gap:0.5rem;flex-wrap:wrap">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <input type="text" name="code" required maxlength="32" placeholder="Code — e.g. SAVE10" class="admin-search-input" style="text-transform:uppercase;width:140px">
            <select name="type" required class="admin-search-input" style="width:110px">
                <option value="percent">% off</option>
                <option value="fixed">$ off</option>
            </select>
            <input type="number" name="value" required min="0.01" step="0.01" placeholder="Value" class="admin-search-input" style="width:100px">
            <input type="number" name="min_order" min="0" step="0.01" placeholder="Min order" class="admin-search-input" style="width:110px">
            <input type="number" name="max_uses" min="1" placeholder="Max uses" class="admin-search-input" style="width:100px">
            <input type="date" name="starts_at" class="admin-search-input" title="Starts (blank = immediately)" style="width:150px">
            <input type="date" name="expires_at" class="admin-search-input" title="Expires (blank = never)" style="width:150px">
            <button type="submit" class="btn-save" style="margin-top:0">Create coupon</button>
        </form>
    </div>

    <?php if (empty($coupons)): ?>
    <p class="empty">No coupons yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Shop</th>
                <th>Discount</th>
                <th>Min Order</th>
                <th>Max Uses</th>
                <th>Uses</th>
                <th>Starts</th>
                <th>Expires</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($coupons as $c):
            $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
            $discountLabel = $c['type'] === 'percent'
                ? rtrim(rtrim(number_format($c['value'], 2), '0'), '.') . '%'
                : '$' . number_format($c['value'], 2);
        ?>
        <?php if ($isExpired): ?>
        <tr>
                <td><strong><?= htmlspecialchars($c['code']) ?></strong></td>
                <td><?= $c['business_name'] ? htmlspecialchars($c['business_name']) : '<span style="color:#9ca3af">—</span>' ?></td>
                <td><?= $discountLabel ?></td>
                <td>$<?= number_format($c['min_order'], 2) ?></td>
                <td><?= $c['max_uses'] !== null ? (int)$c['max_uses'] : '—' ?></td>
                <td><?= (int)$c['used_count'] ?></td>
                <td><?= $c['starts_at'] ? date('d M Y', strtotime($c['starts_at'])) : '—' ?></td>
                <td><?= date('d M Y', strtotime($c['expires_at'])) ?></td>
                <td><span class="status status-rejected">Expired</span></td>
                <td>
                    <form method="POST" action="/admin/coupon-action.php" style="display:inline" onsubmit="return confirm('Delete this coupon?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn-admin-sm">Delete</button>
                    </form>
                </td>
        </tr>
        <?php else: ?>
        <tr>
            <form method="POST" action="/admin/coupon-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <td><strong><?= htmlspecialchars($c['code']) ?></strong> <span style="color:#9ca3af;font-size:0.8em"><?= $c['type'] === 'percent' ? '% off' : '$ off' ?></span></td>
                    <td><?= $c['business_name'] ? htmlspecialchars($c['business_name']) : '<span style="color:#9ca3af">—</span>' ?></td>
                    <td><input type="number" name="value" min="0.01" step="0.01" value="<?= number_format($c['value'], 2) ?>" required style="width:80px"></td>
                    <td><input type="number" name="min_order" min="0" step="0.01" value="<?= number_format($c['min_order'], 2) ?>" style="width:90px"></td>
                    <td><input type="number" name="max_uses" min="1" value="<?= htmlspecialchars($c['max_uses'] ?? '') ?>" placeholder="∞" style="width:80px"></td>
                    <td><?= (int)$c['used_count'] ?><?= $c['max_uses'] ? ' / ' . (int)$c['max_uses'] : '' ?></td>
                    <td><input type="date" name="starts_at" value="<?= $c['starts_at'] ? date('Y-m-d', strtotime($c['starts_at'])) : '' ?>" style="width:135px"></td>
                    <td><input type="date" name="expires_at" value="<?= $c['expires_at'] ? date('Y-m-d', strtotime($c['expires_at'])) : '' ?>" style="width:135px"></td>
                    <td>
                        <?php if ($c['active']): ?>
                            <span class="status status-approved">Active</span>
                        <?php else: ?>
                            <span class="status status-rejected">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <button type="submit" class="btn-admin-sm">Save</button>
                    </td>
            </form>
        </tr>
        <?php endif; ?>
        <?php if (!$isExpired): ?>
        <tr>
            <td colspan="10" style="padding-top:0;border-top:none">
                <form method="POST" action="/admin/coupon-action.php" style="display:inline-block;margin-right:0.5rem">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-admin-sm"><?= $c['active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <?php if ((int)$c['used_count'] === 0): ?>
                <form method="POST" action="/admin/coupon-action.php" style="display:inline-block" onsubmit="return confirm('Delete this coupon?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-admin-sm">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
