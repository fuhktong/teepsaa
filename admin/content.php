<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('content');

try {
    $pages = $pdo->query("SELECT * FROM content_pages ORDER BY FIELD(slug, 'privacy', 'terms', 'shipping', 'returns')")->fetchAll();
} catch (PDOException $e) {
    $pages = [];
    $error = 'content_pages table not found — run database/migration-content-pages.sql first.';
}

$editSlug = $_GET['edit'] ?? '';

$error   = $error ?? ($_SESSION['content_error']   ?? '');
$success = $_SESSION['content_success'] ?? '';
unset($_SESSION['content_error'], $_SESSION['content_success']);
$adminSection = 'content';
$adminTab     = 'content';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pages — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .content-admin-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .content-admin-item {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .content-admin-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            list-style: none;
        }
        .content-admin-row::-webkit-details-marker { display: none; }
        .content-admin-row::before {
            content: '▸';
            flex-shrink: 0;
            color: var(--text-muted);
            transition: transform 0.15s ease;
        }
        .content-admin-item[open] .content-admin-row::before { transform: rotate(90deg); }
        .content-admin-row:hover { background: #f9fafb; }
        .content-admin-info { flex: 1; min-width: 0; }
        .content-admin-info strong { display: block; font-size: 0.95rem; }
        .content-admin-info span { font-size: 0.82rem; color: var(--text-muted); display: block; margin-top: 2px; }
        .upload-section {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
        }
        .upload-lang-heading {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
            padding-bottom: 0.35rem;
            border-bottom: 2px solid var(--border);
        }
        .upload-lang-heading:not(:first-child) { margin-top: 1.75rem; }
        .upload-field { margin-bottom: 0.75rem; }
        .upload-label { font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px; }
        .upload-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-family: inherit;
        }
        textarea.upload-input { resize: vertical; min-height: 320px; line-height: 1.5; }
        .upload-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }
        .btn {
            display: inline-block; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong); background: #fff; font-size: 0.85rem;
            cursor: pointer; font-family: inherit; white-space: nowrap;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .admin-alert { padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem; }
        .admin-alert--error   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .admin-alert--success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>Pages</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Edit the site's static content pages. Body text supports Markdown (## headings, **bold**, *italic*, [links](url), - lists).</p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="content-admin-grid">
        <?php foreach ($pages as $p): ?>
        <details class="content-admin-item" id="content-<?= htmlspecialchars($p['slug']) ?>" <?= $editSlug === $p['slug'] ? 'open' : '' ?>>
            <summary class="content-admin-row">
                <div class="content-admin-info">
                    <strong><?= htmlspecialchars($p['title_en']) ?></strong>
                    <span>Last updated <?= htmlspecialchars($p['updated_at']) ?> &middot; /<?= htmlspecialchars($p['slug']) ?>/</span>
                </div>
            </summary>
            <div class="upload-section">
                <form method="POST" action="/admin/content-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">

                    <h3 class="upload-lang-heading">English</h3>
                    <div class="upload-field">
                        <label class="upload-label">Title</label>
                        <input type="text" name="title_en" class="upload-input" maxlength="150" required
                               value="<?= htmlspecialchars($p['title_en']) ?>">
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Body (Markdown)</label>
                        <textarea name="body_en" class="upload-input" required><?= htmlspecialchars($p['body_en']) ?></textarea>
                    </div>

                    <h3 class="upload-lang-heading">ខ្មែរ</h3>
                    <div class="upload-field">
                        <label class="upload-label">Title</label>
                        <input type="text" name="title_km" class="upload-input" maxlength="150" required
                               value="<?= htmlspecialchars($p['title_km']) ?>">
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Body (Markdown)</label>
                        <textarea name="body_km" class="upload-input" required><?= htmlspecialchars($p['body_km']) ?></textarea>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                        <a href="/<?= htmlspecialchars($p['slug']) ?>/" class="btn" target="_blank" rel="noopener">View live page</a>
                    </div>
                </form>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
