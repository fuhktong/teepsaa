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

admin_require('vendors');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/'); exit; }

$stmt = $pdo->prepare('
    SELECT v.id, v.name, v.email, v.created_at, v.banned, v.ban_reason, v.banned_at,
           v.admin_note,
           b.id AS business_id, b.name AS business_name, b.category, b.description,
           b.address, b.house_number, b.khan, b.sangkat,
           b.approved, b.created_at AS submitted_at,
           b.approved_at, b.spot_checked_at,
           b.royalty_add_on AS company_royalty_add_on, b.royalty_waived
    FROM vendors v
    LEFT JOIN businesses b ON b.user_id = v.id AND b.deleted_at IS NULL
    WHERE v.id = ?
');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { header('Location: /admin/'); exit; }

$photos = [];
if ($v['business_id']) {
    $stmt = $pdo->prepare('SELECT filename FROM photos WHERE business_id = ? ORDER BY id ASC');
    $stmt->execute([$v['business_id']]);
    $photos = array_column($stmt->fetchAll(), 'filename');
}

$penalties = [];
if ($v['business_id']) {
    $stmt = $pdo->prepare('SELECT id, business_id, rate_increase, admin_note, start_date, end_date FROM vendor_penalties WHERE business_id = ? AND cleared_at IS NULL ORDER BY start_date DESC');
    $stmt->execute([$v['business_id']]);
    $penalties = $stmt->fetchAll();
}

$orders = [];
if ($v['business_id']) {
    $stmt = $pdo->prepare('
        SELECT o.id, o.subtotal, o.royalty_rate, o.royalty_amount, o.vendor_payout,
               o.status, o.created_at,
               bu.name AS buyer_name, bu.email AS buyer_email
        FROM orders o
        JOIN buyers bu ON bu.id = o.buyer_user_id
        WHERE o.business_id = ?
        ORDER BY o.created_at DESC
        LIMIT 30
    ');
    $stmt->execute([$v['business_id']]);
    $orders = $stmt->fetchAll();
}

$products = [];
if ($v['business_id']) {
    $s = $pdo->prepare('
        SELECT p.id, p.name, p.price, p.stock, p.active, p.delivery_method,
               c.name AS category_name,
               pp.filename AS photo
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
        WHERE p.business_id = ?
        ORDER BY p.archived ASC, p.name ASC
    ');
    $s->execute([$v['business_id']]);
    $products = $s->fetchAll();
}
$productCount = count($products);

$openOrderCount = 0;
if ($v['business_id']) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('completed','cancelled','refunded','refund_rejected')");
    $stmt->execute([$v['business_id']]);
    $openOrderCount = (int)$stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE user_id = ? AND deleted_at IS NOT NULL');
$stmt->execute([$v['id']]);
$deletedBizCount = (int)$stmt->fetchColumn();

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();

if (!$v['business_id'])        { $statusLabel = $deletedBizCount > 0 ? 'Business deleted' : 'No business'; $statusClass = 'badge-grey'; }
elseif ($v['approved'] === 1)  { $statusLabel = 'Approved';    $statusClass = 'badge-green'; }
elseif ($v['approved'] === -1) { $statusLabel = 'Rejected';    $statusClass = 'badge-red'; }
else                           { $statusLabel = 'Pending';     $statusClass = 'badge-yellow'; }
$adminSection = 'admin';
$adminTab     = 'vendors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($v['business_name'] ?: $v['email']) ?></title>
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


    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="detail-header">
        <h1><?= htmlspecialchars($v['business_name'] ?: '—') ?></h1>
        <?php if ($v['banned']): ?><span class="order-badge badge-red">Suspended</span><?php endif; ?>
        <span class="order-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <div class="detail-columns">
        <div>
            <!-- Vendor account -->
            <div class="detail-card">
                <div class="detail-card-title">Vendor account</div>
                <div class="detail-row"><span class="detail-row-label">Name</span><span class="detail-row-value"><?= htmlspecialchars($v['name'] ?: '—') ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Email</span><span class="detail-row-value"><?= htmlspecialchars($v['email']) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Joined</span><span class="detail-row-value"><?= date('M j, Y', strtotime($v['created_at'])) ?></span></div>
            </div>

            <?php if ($v['business_id']): ?>
            <!-- Business info -->
            <div class="detail-card">
                <div class="detail-card-title">Business</div>
                <div class="detail-row"><span class="detail-row-label">Category</span><span class="detail-row-value"><?= htmlspecialchars($v['category'] ?? '—') ?></span></div>
                <?php if ($v['address'] || $v['khan']): ?>
                <div class="detail-row">
                    <span class="detail-row-label">Address</span>
                    <span class="detail-row-value"><?= htmlspecialchars(implode(', ', array_filter([
                        trim(($v['house_number'] ?? '') . ' ' . ($v['address'] ?? '')),
                        $v['sangkat'] ?? '',
                        $v['khan'] ?? '',
                    ]))) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($v['description']): ?>
                <div class="detail-row"><span class="detail-row-label">Description</span><span class="detail-row-value"><?= htmlspecialchars($v['description']) ?></span></div>
                <?php endif; ?>
                <div class="detail-row"><span class="detail-row-label">Submitted</span><span class="detail-row-value"><?= date('M j, Y', strtotime($v['submitted_at'])) ?></span></div>
                <div class="detail-row"><span class="detail-row-label">Products</span><span class="detail-row-value"><?= $productCount ?></span></div>
            </div>

            <!-- Photos -->
            <?php if (!empty($photos)): ?>
            <div class="detail-card">
                <div class="detail-card-title">Business photos</div>
                <div class="detail-photos">
                    <?php foreach ($photos as $fn): ?>
                        <img src="/uploads/<?= htmlspecialchars($fn) ?>" alt="">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <div>
            <?php if ($v['business_id'] && $v['approved'] === 1): ?>
            <!-- Royalty waiver -->
            <div class="detail-card">
                <div class="detail-card-title">Royalty waiver</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem">When on, this vendor pays 0% royalty on every order — overrides the category rate, company add-on, and any penalties.</p>
                <form method="POST" action="/admin/vendor-action.php" style="display:flex;align-items:center;gap:0.6rem">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="set_royalty_waived">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="business_id" value="<?= $v['business_id'] ?>">
                    <label class="switch">
                        <input type="checkbox" name="royalty_waived" value="1" <?= $v['royalty_waived'] ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="switch-slider"></span>
                    </label>
                    <span><?= $v['royalty_waived'] ? 'Waived — vendor pays no royalty' : 'Not waived' ?></span>
                </form>
            </div>

            <!-- Company royalty add-on -->
            <div class="detail-card">
                <div class="detail-card-title">Company royalty add-on</div>
                <p style="font-size:0.875rem;color:#6b7280;margin:0 0 0.75rem">Added on top of each product's category rate and any active penalties.</p>
                <form method="POST" action="/admin/vendor-action.php" class="add-on-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="set_company_royalty_add_on">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="business_id" value="<?= $v['business_id'] ?>">
                    <input type="number" name="royalty_add_on" min="0" max="100" step="1"
                           value="<?= number_format((float)$v['company_royalty_add_on'] * 100, 1) ?>">
                    <span class="add-on-pct">%</span>
                    <button type="submit" class="btn-approve">Save</button>
                </form>
            </div>

            <!-- Penalties -->
            <div class="detail-card">
                <div class="detail-card-title">Penalties</div>
                <?php if (!empty($penalties)): ?>
                <div class="penalty-list">
                    <?php foreach ($penalties as $p):
                        $today = date('Y-m-d');
                        if ($p['start_date'] > $today)                                      { $pLabel = 'Scheduled'; $pClass = 'badge-yellow'; }
                        elseif ($p['end_date'] !== null && $p['end_date'] < $today)         { $pLabel = 'Expired';   $pClass = 'badge-grey'; }
                        else                                                                 { $pLabel = 'Active';    $pClass = 'badge-red'; }
                    ?>
                    <div class="penalty-item">
                        <div class="penalty-item-info">
                            <div class="penalty-item-top">
                                <span class="order-badge <?= $pClass ?>"><?= $pLabel ?></span>
                                <span class="penalty-item-rate">+<?= number_format($p['rate_increase'] * 100, 1) ?>%</span>
                                <span class="penalty-item-dates">
                                    <?= date('M j, Y', strtotime($p['start_date'])) ?> →
                                    <?= $p['end_date'] ? date('M j, Y', strtotime($p['end_date'])) : 'Indefinite' ?>
                                </span>
                            </div>
                            <?php if ($p['admin_note']): ?>
                            <div class="penalty-item-note"><?= htmlspecialchars($p['admin_note']) ?></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="/admin/penalty-action.php">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="penalty_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn-penalty-remove">Remove</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="penalty-none">No active penalties.</p>
                <?php endif; ?>

                <div class="penalty-add-form" style="margin-top:1rem">
                    <p class="penalty-add-label">Apply penalty</p>
                    <form method="POST" action="/admin/penalty-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="business_id" value="<?= $v['business_id'] ?>">
                        <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                        <div class="penalty-form-row">
                            <div class="penalty-form-field">
                                <label>Rate increase</label>
                                <div class="cat-rate-wrap">
                                    <input type="number" name="rate_increase" min="1" max="100" step="1" value="5" required>
                                    <span>%</span>
                                </div>
                            </div>
                            <div class="penalty-form-field">
                                <label>Start date</label>
                                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="penalty-form-field">
                                <label>End date <span class="penalty-label-hint">(blank = indefinite)</span></label>
                                <input type="date" name="end_date">
                            </div>
                        </div>
                        <div class="penalty-form-field" style="margin-top:0.5rem;">
                            <label>Internal note <span class="penalty-label-hint">(not shown to vendor)</span></label>
                            <textarea name="admin_note" rows="2" placeholder="Reason for penalty…" class="penalty-textarea"></textarea>
                        </div>
                        <button type="submit" class="btn-reject" style="margin-top:0.65rem;width:100%;">Apply penalty</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($v['business_id'] && $v['approved'] === 0): ?>
            <!-- Approve / Reject -->
            <div class="detail-card">
                <div class="detail-card-title">Review</div>
                <div class="popup-actions">
                    <form method="POST" action="/admin/action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= $v['business_id'] ?>">
                        <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                        <button type="submit" name="action" value="approve" class="btn-approve">Approve business</button>
                    </form>
                    <form method="POST" action="/admin/action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= $v['business_id'] ?>">
                        <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($v['business_id'] && $v['approved'] === 1 && !$v['spot_checked_at']):
                  $spotDue = !$v['approved_at'] || strtotime($v['approved_at']) <= strtotime('-7 days'); ?>
            <!-- One-week spot check -->
            <div class="detail-card">
                <div class="detail-card-title">Spot check</div>
                <?php if ($spotDue): ?>
                <p style="margin:0 0 0.75rem;font-size:0.9rem;color:#666;">
                    <?= $v['approved_at'] ? 'Approved ' . date('M j, Y', strtotime($v['approved_at'])) . ' — the' : 'The' ?>
                    one-week spot check is due. Review the products and photos above, then mark it done.
                </p>
                <form method="POST" action="/admin/vendor-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="business_id" value="<?= $v['business_id'] ?>">
                    <button type="submit" name="action" value="spot_check_done" class="btn-approve">Mark spot check done</button>
                </form>
                <?php else: ?>
                <p style="margin:0;font-size:0.9rem;color:#666;">
                    Scheduled for <?= date('M j, Y', strtotime($v['approved_at'] . ' +7 days')) ?>.
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Admin note -->
            <div class="detail-card">
                <div class="detail-card-title">Internal note</div>
                <form method="POST" action="/admin/vendor-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <textarea name="admin_note" rows="3" class="penalty-textarea" placeholder="Internal note (not visible to vendor)…"><?= htmlspecialchars($v['admin_note'] ?? '') ?></textarea>
                    <button type="submit" class="btn-approve" style="margin-top:0.5rem;width:100%;">Save note</button>
                </form>
            </div>

            <!-- Suspend / Unsuspend -->
            <div class="detail-card">
                <?php if ($v['banned']): ?>
                <div class="detail-card-title">Suspended</div>
                <?php if ($v['ban_reason']): ?>
                <div class="detail-row"><span class="detail-row-label">Reason</span><span class="detail-row-value"><?= htmlspecialchars($v['ban_reason']) ?></span></div>
                <?php endif; ?>
                <?php if ($v['banned_at']): ?>
                <div class="detail-row"><span class="detail-row-label">Since</span><span class="detail-row-value"><?= date('M j, Y', strtotime($v['banned_at'])) ?></span></div>
                <?php endif; ?>
                <form method="POST" action="/admin/vendor-action.php" style="margin-top:0.75rem;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="unsuspend">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <button type="submit" class="btn-approve" style="width:100%;">Lift suspension</button>
                </form>
                <?php else: ?>
                <div class="detail-card-title">Suspend account</div>
                <form method="POST" action="/admin/vendor-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <textarea name="ban_reason" rows="2" class="penalty-textarea" placeholder="Reason for suspension (internal only)…" required></textarea>
                    <button type="submit" class="btn-reject" style="margin-top:0.5rem;width:100%;">Suspend account</button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($v['business_id']): ?>
            <!-- Delete business -->
            <div class="detail-card">
                <div class="detail-card-title">Delete business</div>
                <?php if ($openOrderCount > 0): ?>
                <p style="font-size:0.85rem;color:#6b7280;">Cannot delete — <?= $openOrderCount ?> open order<?= $openOrderCount === 1 ? '' : 's' ?>. All orders must be completed, cancelled, or refunded first.</p>
                <?php else: ?>
                <p style="font-size:0.85rem;color:#6b7280;">Permanently removes the store page and gallery photos<?= $productCount > 0 ? ", plus its $productCount product" . ($productCount === 1 ? '' : 's') : '' ?>. Order history is kept for accounting. The vendor account stays active. This cannot be undone.</p>
                <form method="POST" action="/admin/vendor-action.php" onsubmit="return confirm('Delete this business permanently? This cannot be undone.');" style="margin-top:0.5rem;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete_business">
                    <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="business_id" value="<?= $v['business_id'] ?>">
                    <button type="submit" class="btn-reject" style="width:100%;">Delete business</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($products)): ?>
    <div class="detail-card" style="margin-top:1.25rem;">
        <div class="detail-card-title">Products (<?= $productCount ?>)</div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Delivery</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $prod): ?>
            <tr style="cursor:pointer;" onclick="location.href='/admin/product.php?id=<?= $prod['id'] ?>'">
                <td style="width:44px;">
                    <?php if ($prod['photo']): ?>
                        <img src="/uploads/<?= htmlspecialchars($prod['photo']) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #e5e7eb;display:block;">
                    <?php else: ?>
                        <div style="width:36px;height:36px;background:#f3f4f6;border-radius:4px;border:1px solid #e5e7eb;"></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($prod['name']) ?></td>
                <td style="color:#9ca3af"><?= htmlspecialchars($prod['category_name'] ?? '—') ?></td>
                <td>$<?= number_format($prod['price'], 2) ?></td>
                <td><?= (int)$prod['stock'] ?></td>
                <td><?= $prod['delivery_method'] === 'tuktuk' ? 'Tuk-Tuk' : 'Bike' ?></td>
                <td><span class="order-badge <?= $prod['active'] ? 'badge-green' : 'badge-grey' ?>"><?= $prod['active'] ? 'Active' : 'Inactive' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($orders)): ?>
    <div class="detail-card">
        <div class="detail-card-title">Order history</div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Buyer</th>
                    <th>Subtotal</th>
                    <th>Royalty</th>
                    <th>Payout</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                $oref = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);
            ?>
            <tr>
                <td><a href="/admin/order.php?id=<?= $o['id'] ?>"><?= $oref ?></a></td>
                <td><?= htmlspecialchars($o['buyer_name'] ?: $o['buyer_email']) ?></td>
                <td>$<?= number_format($o['subtotal'], 2) ?></td>
                <td><?php if ($o['royalty_amount'] !== null): ?>$<?= number_format($o['royalty_amount'], 2) ?> (<?= round($o['royalty_rate'] * 100, 1) ?>%)<?php else: ?>—<?php endif; ?></td>
                <td><?= $o['vendor_payout'] !== null ? '$' . number_format($o['vendor_payout'], 2) : '—' ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
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
