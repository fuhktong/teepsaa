<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('coupons');

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

    <?php
    // Row forms live outside the table on purpose. A <form> as a direct child
    // of <tr> is invalid — the parser hoists it out of the table — and keeping
    // them here lets one row's Save / Deactivate / Delete share a single cell
    // instead of spilling onto a second row. The cells bind to them by id.
    ?>
    <?php foreach ($coupons as $c):
        $cid       = (int)$c['id'];
        $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
    ?>
        <?php if (!$isExpired): ?>
        <form id="coupon-edit-<?= $cid ?>" method="POST" action="/admin/coupon-action.php" class="admin-row-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $cid ?>">
        </form>
        <form id="coupon-toggle-<?= $cid ?>" method="POST" action="/admin/coupon-action.php" class="admin-row-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $cid ?>">
        </form>
        <?php endif; ?>
        <?php if ($isExpired || (int)$c['used_count'] === 0): ?>
        <form id="coupon-delete-<?= $cid ?>" method="POST" action="/admin/coupon-action.php" class="admin-row-form" onsubmit="return confirm('Delete this coupon?');">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $cid ?>">
        </form>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="admin-table-wrap">
    <table class="admin-table admin-table--coupons">
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
            $cid       = (int)$c['id'];
            $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
            $unit      = $c['type'] === 'percent' ? '% off' : '$ off';
            $discountLabel = $c['type'] === 'percent'
                ? rtrim(rtrim(number_format($c['value'], 2), '0'), '.') . '%'
                : '$' . number_format($c['value'], 2);
        ?>
        <tr>
            <td class="admin-cell-code"><strong><?= htmlspecialchars($c['code']) ?></strong></td>
            <td><?= $c['business_name'] ? htmlspecialchars($c['business_name']) : '<span class="admin-dash">&mdash;</span>' ?></td>

            <?php if ($isExpired): ?>
            <td><?= $discountLabel ?></td>
            <td>$<?= number_format($c['min_order'], 2) ?></td>
            <td><?= $c['max_uses'] !== null ? (int)$c['max_uses'] : '<span class="admin-dash">&mdash;</span>' ?></td>
            <td><?= (int)$c['used_count'] ?></td>
            <td><?= $c['starts_at'] ? date('d M Y', strtotime($c['starts_at'])) : '<span class="admin-dash">&mdash;</span>' ?></td>
            <td><?= date('d M Y', strtotime($c['expires_at'])) ?></td>
            <td><span class="status status-rejected">Expired</span></td>
            <td class="admin-row-actions">
                <button type="submit" form="coupon-delete-<?= $cid ?>" class="btn-admin-sm">Delete</button>
            </td>

            <?php else: ?>
            <td>
                <div class="admin-field">
                    <input type="number" form="coupon-edit-<?= $cid ?>" name="value" min="0.01" step="0.01" value="<?= number_format($c['value'], 2) ?>" required class="admin-cell-num">
                    <span class="admin-field-unit"><?= $unit ?></span>
                </div>
            </td>
            <td>
                <div class="admin-field">
                    <span class="admin-field-unit">$</span>
                    <input type="number" form="coupon-edit-<?= $cid ?>" name="min_order" min="0" step="0.01" value="<?= number_format($c['min_order'], 2) ?>" class="admin-cell-num">
                </div>
            </td>
            <td><input type="number" form="coupon-edit-<?= $cid ?>" name="max_uses" min="1" value="<?= htmlspecialchars($c['max_uses'] ?? '') ?>" placeholder="&#8734;" class="admin-cell-num"></td>
            <td><?= (int)$c['used_count'] ?><?= $c['max_uses'] ? ' / ' . (int)$c['max_uses'] : '' ?></td>
            <td><input type="date" form="coupon-edit-<?= $cid ?>" name="starts_at" value="<?= $c['starts_at'] ? date('Y-m-d', strtotime($c['starts_at'])) : '' ?>" class="admin-cell-date"></td>
            <td><input type="date" form="coupon-edit-<?= $cid ?>" name="expires_at" value="<?= $c['expires_at'] ? date('Y-m-d', strtotime($c['expires_at'])) : '' ?>" class="admin-cell-date"></td>
            <td>
                <?php if ($c['active']): ?>
                    <span class="status status-approved">Active</span>
                <?php else: ?>
                    <span class="status status-rejected">Inactive</span>
                <?php endif; ?>
            </td>
            <td class="admin-row-actions">
                <button type="submit" form="coupon-edit-<?= $cid ?>" class="btn-admin-sm">Save</button>
                <button type="submit" form="coupon-toggle-<?= $cid ?>" class="btn-admin-sm"><?= $c['active'] ? 'Deactivate' : 'Activate' ?></button>
                <?php if ((int)$c['used_count'] === 0): ?>
                <button type="submit" form="coupon-delete-<?= $cid ?>" class="btn-admin-sm">Delete</button>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
