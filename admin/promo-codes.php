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

admin_require('promo-codes');

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$codes = $pdo->query('SELECT * FROM promo_codes ORDER BY created_at DESC')->fetchAll();
$adminSection = 'marketing';
$adminTab     = 'promo-codes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Promo Codes</title>
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

    <h1>Promo Codes</h1>

    <?php if ($success): ?><p class="admin-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="admin-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="admin-filters-row">
        <form method="POST" action="/admin/promo-codes-action.php" class="admin-search-form" style="gap:0.5rem;flex-wrap:wrap">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <input type="text" name="code" required maxlength="50" placeholder="Code — e.g. LAUNCH2026" class="admin-search-input" style="text-transform:uppercase;width:180px">
            <input type="text" name="description" maxlength="255" placeholder="Description — e.g. June pitch event" class="admin-search-input" style="width:220px">
            <input type="number" name="uses_limit" min="1" placeholder="Max uses (blank = unlimited)" class="admin-search-input" style="width:200px">
            <button type="submit" class="btn-save" style="margin-top:0">Create code</button>
        </form>
    </div>

    <?php if (empty($codes)): ?>
    <p class="empty">No promo codes yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Description</th>
                <th>Uses</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($codes as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['code']) ?></strong></td>
            <td><?= htmlspecialchars($c['description'] ?: '—') ?></td>
            <td><?= (int)$c['uses_count'] ?><?= $c['uses_limit'] ? ' / ' . (int)$c['uses_limit'] : '' ?></td>
            <td>
                <?php if ($c['active']): ?>
                    <span class="status status-approved">Active</span>
                <?php else: ?>
                    <span class="status status-rejected">Inactive</span>
                <?php endif; ?>
            </td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td>
                <form method="POST" action="/admin/promo-codes-action.php" style="display:inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-admin-sm"><?= $c['active'] ? 'Deactivate' : 'Activate' ?></button>
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
