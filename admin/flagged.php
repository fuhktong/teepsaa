<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

// Every account that matched a suspended account's email or phone and has not
// been looked at yet — where the daily digest's "Sign-ups matching a suspended
// account" row lands. Each one is reviewed on its own buyer/vendor page.
// Matching is explained in config/fingerprint.php.
$canBuyers  = admin_can('buyers');
$canVendors = admin_can('vendors');
if (!$canBuyers && !$canVendors) {
    header('Location: ' . admin_home_url() . '?denied=1');
    exit;
}

try {
    $rows = $pdo->query("
        SELECT m.role, m.account_id, m.created_at, f.kind, f.role AS old_role, f.account_id AS old_id, f.reason,
               COALESCE(b.name, v.name) AS name, COALESCE(b.email, v.email) AS email
          FROM account_matches m
          JOIN account_fingerprints f ON f.id = m.fingerprint_id
          LEFT JOIN buyers  b ON m.role = 'buyer'  AND b.id = m.account_id
          LEFT JOIN vendors v ON m.role = 'vendor' AND v.id = m.account_id
         WHERE m.reviewed_at IS NULL
         ORDER BY m.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $rows = [];
}
$rows = array_values(array_filter($rows, fn($r) => $r['role'] === 'buyer' ? $canBuyers : $canVendors));

// Badge counts for the shared nav
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND (refund_closed_at IS NOT NULL OR delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND))")->fetchColumn();
$adminSection = 'buyers';
$adminTab     = 'flagged';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Flagged sign-ups</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>
<?php require __DIR__ . '/../admin-subnav/admin-subnav.php'; ?>

<main>
    <h1>Flagged sign-ups</h1>
    <p class="account-matches-hint">Accounts with the same email or phone as a suspended account. Open each one, decide, and mark it reviewed there. Nothing has been blocked.</p>

    <?php if (!$rows): ?>
    <p>Nothing waiting.</p>
    <?php else: ?>
    <table class="orders-table">
        <thead>
            <tr><th>Account</th><th>Matched</th><th>Suspended account</th><th>Flagged</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="/admin/<?= $r['role'] ?>.php?id=<?= (int)$r['account_id'] ?>"><?= htmlspecialchars($r['name'] ?: $r['email'] ?: ($r['role'] . ' #' . $r['account_id'])) ?></a> <span style="color:#6b7280;">(<?= $r['role'] ?>)</span></td>
                <td><?= $r['kind'] === 'email' ? 'Email' : 'Phone' ?></td>
                <td><?= htmlspecialchars($r['old_role']) ?> #<?= (int)$r['old_id'] ?><?= $r['reason'] ? ' — ' . htmlspecialchars($r['reason']) : '' ?></td>
                <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
