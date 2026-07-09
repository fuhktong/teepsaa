<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

$success = $_SESSION['email_tpl_success'] ?? '';
unset($_SESSION['email_tpl_success']);

try {
    $templates = $pdo->query('SELECT template_key, label, tokens, updated_at FROM email_templates ORDER BY sort_order, label')->fetchAll();
} catch (PDOException $e) {
    $templates = [];
    $error = 'email_templates table not found — run database/migration-email-templates.sql, then database/seed-email-templates.php.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email templates — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/messages/messages-admin.css">
    <style>
        .et-list { display:flex; flex-direction:column; gap:0.6rem; margin-bottom:2rem; }
        .et-row {
            display:flex; align-items:center; gap:1rem; text-decoration:none; color:inherit;
            background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:0.85rem 1.1rem;
            transition:border-color .12s, background .12s;
        }
        .et-row:hover { border-color:var(--primary); background:#fafbff; }
        .et-info { flex:1; min-width:0; }
        .et-info strong { display:block; font-size:0.95rem; }
        .et-info span { display:block; font-size:0.78rem; color:var(--text-muted); margin-top:3px; font-family:monospace; }
        .et-arrow { color:var(--text-muted); font-size:1.2rem; flex-shrink:0; }
        .admin-alert { padding:0.75rem 1rem; border-radius:var(--radius); font-size:0.9rem; margin-bottom:1rem; }
        .admin-alert--error   { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
        .admin-alert--success { background:#f0fdf4; color:#15803d; border:1px solid #86efac; }
    </style>
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
        <a href="/admin/messages/emails.php" class="amsg-role-tab active">Email templates</a>
    </div>

    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Edit the wording of automated emails. Every email is sent bilingually — Khmer on top, English below. Keep the <code>{tokens}</code> intact; they are replaced with real values (name, order number, etc.) when the email is sent.</p>

    <?php if (!empty($error)): ?>
    <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="et-list">
        <?php foreach ($templates as $tpl): ?>
        <a class="et-row" href="/admin/messages/email-edit.php?key=<?= urlencode($tpl['template_key']) ?>">
            <div class="et-info">
                <strong><?= htmlspecialchars($tpl['label']) ?></strong>
                <span><?= htmlspecialchars($tpl['template_key']) ?><?= $tpl['tokens'] ? ' · ' . htmlspecialchars($tpl['tokens']) : '' ?></span>
            </div>
            <span class="et-arrow">&rsaquo;</span>
        </a>
        <?php endforeach; ?>
    </div>
</main>
<?php require __DIR__ . '/../../footer/footer.php'; ?>
</body>
</html>
