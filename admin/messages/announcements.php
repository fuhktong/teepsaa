<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/announcements.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

$success = $_SESSION['ann_success'] ?? '';
$error   = $_SESSION['ann_error']   ?? '';
unset($_SESSION['ann_success'], $_SESSION['ann_error']);

$missingTable = false;
try {
    $rows = $pdo->query(
        'SELECT id, audience, kind, subject_en, subject_km, status,
                total_recipients, sent_count, failed_count, created_at, queued_at
           FROM announcements
          ORDER BY id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
    $missingTable = true;
    $error = 'announcements table not found — run database/migration-announcements.sql.';
}

// Anything still draining drives the auto-refresh below.
$sending = false;
foreach ($rows as $r) {
    if ($r['status'] === 'sending') { $sending = true; break; }
}

$audienceLabel = ['buyers' => 'Buyers', 'vendors' => 'Vendors', 'both' => 'Buyers + Vendors'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($sending): ?><meta http-equiv="refresh" content="20"><?php endif; ?>
    <title>Announcements — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
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

    <div class="amsg-role-tabs">
        <a href="/admin/messages/?role=buyer&status=pending" class="amsg-role-tab">Buyers</a>
        <a href="/admin/messages/?role=vendor&status=pending" class="amsg-role-tab">Vendors</a>
        <a href="/admin/messages/?role=guest&status=pending" class="amsg-role-tab">Contact Form</a>
        <a href="/admin/messages/announcements.php" class="amsg-role-tab active">Announcements</a>
        <a href="/admin/messages/emails.php" class="amsg-role-tab">Email templates</a>
    </div>

    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">One-off emails to every buyer and/or vendor — a new feature, a policy change, a sale. Automated per-order emails live under <a href="/admin/messages/emails.php">Email templates</a>. Promotional announcements carry an unsubscribe link and skip anyone who has opted out; service announcements go to everyone.</p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$missingTable): ?>
    <p style="margin-bottom:1.25rem;">
        <a href="/admin/messages/announcement-edit.php" class="btn btn-primary">New announcement</a>
    </p>
    <?php endif; ?>

    <?php if (!$rows && !$missingTable): ?>
    <p style="color:#6b7280;font-size:0.9rem;">No announcements yet.</p>
    <?php endif; ?>

    <div class="ann-list">
        <?php foreach ($rows as $r):
            $total = (int)$r['total_recipients'];
            $done  = (int)$r['sent_count'] + (int)$r['failed_count'];
            $pct   = $total > 0 ? min(100, (int)round($done / $total * 100)) : 0;
        ?>
        <a class="ann-row" href="/admin/messages/announcement-edit.php?id=<?= (int)$r['id'] ?>">
            <div class="ann-row-main">
                <strong><?= htmlspecialchars($r['subject_en'] ?: $r['subject_km']) ?></strong>
                <span class="ann-row-sub">
                    <?= htmlspecialchars($audienceLabel[$r['audience']] ?? $r['audience']) ?>
                    · <?= $r['kind'] === 'service' ? 'Service' : 'Promotional' ?>
                    · created <?= date('j M Y', strtotime($r['created_at'])) ?>
                </span>
            </div>
            <div class="ann-row-status">
                <span class="ann-badge ann-badge--<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span>
                <?php if ($r['status'] === 'sending' || $r['status'] === 'sent'): ?>
                <span class="ann-progress-text"><?= (int)$r['sent_count'] ?> / <?= $total ?> sent<?= (int)$r['failed_count'] ? ', ' . (int)$r['failed_count'] . ' failed' : '' ?></span>
                <span class="ann-progress"><span class="ann-progress-bar" style="width:<?= $pct ?>%"></span></span>
                <?php endif; ?>
            </div>
            <span class="ann-arrow">&rsaquo;</span>
        </a>
        <?php endforeach; ?>
    </div>
</main>
<?php require __DIR__ . '/../../footer/footer.php'; ?>
</body>
</html>
