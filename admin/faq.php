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

admin_require('faq');

try {
    $items = $pdo->query('SELECT * FROM faq_items ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (PDOException $e) {
    $items = [];
    $error = 'faq_items table not found — run database/migration-content-pages.sql first.';
}

$sections = [];
foreach ($items as $it) {
    $sections[$it['section_en']] ??= $it['section_km'];
}

$editId   = (int) ($_GET['edit'] ?? 0);
$isAdding = isset($_GET['add']);

$error   = $error ?? ($_SESSION['faq_error']   ?? '');
$success = $_SESSION['faq_success'] ?? '';
unset($_SESSION['faq_error'], $_SESSION['faq_success']);
$adminSection = 'content';
$adminTab     = 'faq';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .faq-admin-section-header {
            font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--text-muted); margin: 1.5rem 0 0.5rem;
        }
        .faq-admin-section-header:first-child { margin-top: 0; }
        .faq-admin-grid { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; }
        .content-admin-item {
            background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
            overflow: hidden;
        }
        .faq-admin-row {
            display: flex; align-items: center; gap: 1rem;
            padding: 0.6rem 1rem; cursor: pointer; list-style: none;
        }
        .faq-admin-row::-webkit-details-marker { display: none; }
        .faq-admin-row::before {
            content: '▸'; flex-shrink: 0; color: var(--text-muted); transition: transform 0.15s ease;
        }
        .content-admin-item[open] .faq-admin-row::before { transform: rotate(90deg); }
        .faq-admin-row:hover { background: #f9fafb; }
        .faq-admin-row.faq-inactive { opacity: 0.45; }
        .faq-admin-info { flex: 1; min-width: 0; }
        .faq-admin-info strong { display: block; font-size: 0.9rem; }
        .faq-admin-info span { font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 2px; }
        .faq-sort-btns { display: flex; flex-direction: column; gap: 2px; }
        .faq-sort-btn {
            background: none; border: 1px solid var(--border-strong); border-radius: var(--radius-sm);
            width: 26px; height: 22px; cursor: pointer; font-size: 0.75rem; line-height: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .faq-sort-btn:hover { background: #f3f4f6; }
        .faq-admin-controls { display: flex; gap: 0.4rem; align-items: center; flex-shrink: 0; }
        .badge-active   { font-size: 0.75rem; font-weight: 600; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: var(--radius-lg); white-space: nowrap; }
        .badge-inactive { font-size: 0.75rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 2px 8px; border-radius: var(--radius-lg); white-space: nowrap; }
        .upload-section {
            padding: 1.5rem; border-top: 1px solid var(--border);
        }
        .upload-lang-heading {
            font-size: 1.1rem; font-weight: 700; margin: 0 0 0.75rem;
            padding-bottom: 0.35rem; border-bottom: 2px solid var(--border);
        }
        .upload-lang-heading:not(:first-child) { margin-top: 1.75rem; }
        .upload-field { margin-bottom: 0.75rem; }
        .upload-label { font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px; }
        .upload-input {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm); font-size: 0.9rem; font-family: inherit;
        }
        textarea.upload-input { resize: vertical; min-height: 100px; line-height: 1.5; }
        .upload-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }
        .btn {
            display: inline-block; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong); background: #fff; font-size: 0.85rem;
            cursor: pointer; font-family: inherit; white-space: nowrap;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-danger { color: #dc2626; border-color: #fca5a5; }
        .btn-danger:hover { background: #fef2f2; }
        .faq-add-item {
            background: none; border: none; border-radius: 0; padding-left: 0.5rem;
        }
        .faq-add-item > summary {
            list-style: none; cursor: pointer; display: inline-block;
        }
        .faq-add-item > summary::-webkit-details-marker { display: none; }
        .admin-alert { padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem; }
        .admin-alert--error   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .admin-alert--success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
        @media (max-width: 700px) {
            .faq-admin-row { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>FAQ</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Manage the Help Center's frequently asked questions, grouped by section.</p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <p style="color:#6b7280;margin-bottom:1.5rem;">No FAQ items yet. Add one below.</p>
    <?php else: ?>
    <div class="faq-admin-grid">
        <?php $lastSection = null; foreach ($items as $i => $it): ?>
        <?php if ($it['section_en'] !== $lastSection): $lastSection = $it['section_en']; ?>
        <div class="faq-admin-section-header"><?= htmlspecialchars($it['section_en']) ?> &middot; <?= htmlspecialchars($it['section_km']) ?></div>
        <?php endif; ?>
        <details class="content-admin-item" id="faq-<?= $it['id'] ?>" <?= $editId === (int) $it['id'] ? 'open' : '' ?>>
            <summary class="faq-admin-row<?= !$it['active'] ? ' faq-inactive' : '' ?>">
                <div class="faq-admin-info">
                    <strong><?= htmlspecialchars($it['question_en']) ?></strong>
                    <span><?= htmlspecialchars($it['question_km']) ?></span>
                </div>
                <span class="<?= $it['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $it['active'] ? 'Active' : 'Hidden' ?></span>
                <div class="faq-sort-btns">
                    <?php if ($i > 0): ?>
                    <form method="POST" action="/admin/faq-action.php" onclick="event.stopPropagation()">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="move_up">
                        <input type="hidden" name="id" value="<?= $it['id'] ?>">
                        <button type="submit" class="faq-sort-btn" title="Move up">▲</button>
                    </form>
                    <?php else: ?>
                    <div style="width:26px;height:22px;"></div>
                    <?php endif; ?>
                    <?php if ($i < count($items) - 1): ?>
                    <form method="POST" action="/admin/faq-action.php" onclick="event.stopPropagation()">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="move_down">
                        <input type="hidden" name="id" value="<?= $it['id'] ?>">
                        <button type="submit" class="faq-sort-btn" title="Move down">▼</button>
                    </form>
                    <?php else: ?>
                    <div style="width:26px;height:22px;"></div>
                    <?php endif; ?>
                </div>
                <div class="faq-admin-controls">
                    <form method="POST" action="/admin/faq-action.php" onclick="event.stopPropagation()">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $it['id'] ?>">
                        <button type="submit" class="btn btn-sm"><?= $it['active'] ? 'Hide' : 'Show' ?></button>
                    </form>
                    <form method="POST" action="/admin/faq-action.php" onclick="event.stopPropagation()" onsubmit="return confirm('Delete this FAQ item?')">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $it['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </summary>
            <div class="upload-section">
                <form method="POST" action="/admin/faq-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $it['id'] ?>">

                    <h3 class="upload-lang-heading">English</h3>
                    <div class="upload-field">
                        <label class="upload-label">Section</label>
                        <input type="text" name="section_en" class="upload-input" list="faq-sections-en" maxlength="100" required
                               value="<?= htmlspecialchars($it['section_en']) ?>">
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Question</label>
                        <input type="text" name="question_en" class="upload-input" maxlength="255" required
                               value="<?= htmlspecialchars($it['question_en']) ?>">
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Answer</label>
                        <textarea name="answer_en" class="upload-input" required><?= htmlspecialchars($it['answer_en']) ?></textarea>
                    </div>

                    <h3 class="upload-lang-heading">ខ្មែរ</h3>
                    <div class="upload-field">
                        <label class="upload-label">Section</label>
                        <input type="text" name="section_km" class="upload-input" maxlength="100" required
                               value="<?= htmlspecialchars($it['section_km']) ?>">
                        <div class="upload-hint">Use the same section name as existing items to group under one heading.</div>
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Question</label>
                        <input type="text" name="question_km" class="upload-input" maxlength="255" required
                               value="<?= htmlspecialchars($it['question_km']) ?>">
                    </div>
                    <div class="upload-field">
                        <label class="upload-label">Answer</label>
                        <textarea name="answer_km" class="upload-input" required><?= htmlspecialchars($it['answer_km']) ?></textarea>
                    </div>

                    <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <datalist id="faq-sections-en">
        <?php foreach ($sections as $en => $km): ?>
        <option value="<?= htmlspecialchars($en) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <details class="content-admin-item faq-add-item" id="faq-add" <?= $isAdding ? 'open' : '' ?>>
        <summary class="btn btn-primary">Add FAQ item</summary>
        <div class="upload-section">
            <form method="POST" action="/admin/faq-action.php">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add">

                <h3 class="upload-lang-heading">English</h3>
                <div class="upload-field">
                    <label class="upload-label">Section</label>
                    <input type="text" name="section_en" class="upload-input" list="faq-sections-en" maxlength="100" required>
                </div>
                <div class="upload-field">
                    <label class="upload-label">Question</label>
                    <input type="text" name="question_en" class="upload-input" maxlength="255" required>
                </div>
                <div class="upload-field">
                    <label class="upload-label">Answer</label>
                    <textarea name="answer_en" class="upload-input" required></textarea>
                </div>

                <h3 class="upload-lang-heading">ខ្មែរ</h3>
                <div class="upload-field">
                    <label class="upload-label">Section</label>
                    <input type="text" name="section_km" class="upload-input" maxlength="100" required>
                    <div class="upload-hint">Use the same section name as existing items to group under one heading.</div>
                </div>
                <div class="upload-field">
                    <label class="upload-label">Question</label>
                    <input type="text" name="question_km" class="upload-input" maxlength="255" required>
                </div>
                <div class="upload-field">
                    <label class="upload-label">Answer</label>
                    <textarea name="answer_km" class="upload-input" required></textarea>
                </div>

                <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                    <button type="submit" class="btn btn-primary">Add item</button>
                </div>
            </form>
        </div>
    </details>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
