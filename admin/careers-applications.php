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

admin_require('careers');

$jobFilter = (int) ($_GET['job'] ?? 0);

$postings = $pdo->query('SELECT id, title FROM job_postings ORDER BY created_at DESC')->fetchAll();

if ($jobFilter) {
    $stmt = $pdo->prepare('SELECT a.*, j.title AS job_title FROM job_applications a JOIN job_postings j ON j.id = a.job_id WHERE a.job_id = ? ORDER BY a.created_at DESC');
    $stmt->execute([$jobFilter]);
    $apps = $stmt->fetchAll();
} else {
    $apps = $pdo->query('SELECT a.*, j.title AS job_title FROM job_applications a JOIN job_postings j ON j.id = a.job_id ORDER BY a.created_at DESC')->fetchAll();
}

$statuses = ['new', 'reviewed', 'shortlisted', 'rejected'];

$error   = $_SESSION['career_error']   ?? '';
$success = $_SESSION['career_success'] ?? '';
unset($_SESSION['career_error'], $_SESSION['career_success']);
$adminSection = 'marketing';
$adminTab     = 'careers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .app-toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .app-toolbar select { padding: 0.4rem 0.6rem; border: 1px solid var(--border-strong); border-radius: var(--radius-sm); font-size: 0.9rem; font-family: inherit; }
        .app-grid { display: flex; flex-direction: column; gap: 0.85rem; }
        .app-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem 1.25rem; }
        .app-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .app-name { font-size: 1rem; font-weight: 700; }
        .app-role { font-size: 0.82rem; color: var(--text-muted); }
        .app-when { font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; }
        .app-contact { font-size: 0.88rem; color: #444; margin: 0.5rem 0; }
        .app-contact a { color: var(--primary); text-decoration: none; }
        .app-message { font-size: 0.9rem; color: #555; line-height: 1.6; background: var(--bg-subtle); border-radius: var(--radius-sm); padding: 0.6rem 0.8rem; margin: 0.5rem 0; white-space: pre-wrap; }
        .app-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.6rem; }
        .app-actions select { padding: 0.35rem 0.5rem; border: 1px solid var(--border-strong); border-radius: var(--radius-sm); font-size: 0.85rem; font-family: inherit; }
        .status-badge { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; padding: 2px 8px; border-radius: var(--radius-lg); }
        .status-new         { color: #1d4ed8; background: #dbeafe; }
        .status-reviewed    { color: #6b7280; background: #f3f4f6; }
        .status-shortlisted { color: #15803d; background: #dcfce7; }
        .status-rejected    { color: #92400e; background: #fef3c7; }
        .btn { display: inline-block; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-strong); background: #fff; font-size: 0.85rem; cursor: pointer; font-family: inherit; text-decoration: none; color: inherit; }
        .btn:hover { background: #f3f4f6; }
        .btn-danger { color: #dc2626; border-color: #fca5a5; }
        .btn-danger:hover { background: #fef2f2; }
        .admin-alert { padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem; margin-bottom: 1rem; }
        .admin-alert--error   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .admin-alert--success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>Job Applications</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;"><a href="/admin/careers.php">&larr; Back to postings</a></p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="app-toolbar">
        <label for="jobsel" style="font-size:0.9rem;font-weight:600;">Filter:</label>
        <select id="jobsel" onchange="location.href='/admin/careers-applications.php' + (this.value ? '?job=' + this.value : '')">
            <option value="">All postings</option>
            <?php foreach ($postings as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $jobFilter === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($apps)): ?>
    <p style="color:#6b7280;">No applications<?= $jobFilter ? ' for this posting' : '' ?> yet.</p>
    <?php else: ?>
    <div class="app-grid">
        <?php foreach ($apps as $a): ?>
        <div class="app-card">
            <div class="app-card-head">
                <div>
                    <span class="app-name"><?= htmlspecialchars($a['name']) ?></span>
                    <span class="status-badge status-<?= $a['status'] ?>"><?= $a['status'] ?></span>
                    <div class="app-role">Applied for: <?= htmlspecialchars($a['job_title']) ?></div>
                </div>
                <span class="app-when"><?= date('M j, Y g:i a', strtotime($a['created_at'])) ?></span>
            </div>

            <p class="app-contact">
                <a href="mailto:<?= htmlspecialchars($a['email']) ?>"><?= htmlspecialchars($a['email']) ?></a>
                <?= $a['phone'] ? ' · ' . htmlspecialchars($a['phone']) : '' ?>
            </p>

            <?php if ($a['message']): ?>
            <div class="app-message"><?= htmlspecialchars($a['message']) ?></div>
            <?php endif; ?>

            <div class="app-actions">
                <?php if ($a['resume_file']): ?>
                <a class="btn" href="/admin/resume.php?id=<?= $a['id'] ?>" target="_blank" rel="noopener">Résumé &darr;</a>
                <?php else: ?>
                <span style="font-size:0.82rem;color:#9ca3af;">No résumé</span>
                <?php endif; ?>

                <form method="POST" action="/admin/careers-action.php" style="display:flex;gap:0.4rem;align-items:center;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="app_status">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="job_filter" value="<?= $jobFilter ?>">
                    <select name="status" onchange="this.form.submit()">
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form method="POST" action="/admin/careers-action.php" onsubmit="return confirm('Delete this application?')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="app_delete">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="job_filter" value="<?= $jobFilter ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
