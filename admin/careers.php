<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

try {
    $jobs = $pdo->query('SELECT j.*, (SELECT COUNT(*) FROM job_applications a WHERE a.job_id = j.id) AS app_count
                         FROM job_postings j ORDER BY j.is_open DESC, j.created_at DESC')->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
    $error = 'job_postings table not found — run database/migration-careers.sql first.';
}

// Prefill the form when editing an existing posting.
$editJob = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM job_postings WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editJob = $stmt->fetch() ?: null;
}

$types = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Freelance'];

$error   = $error ?? ($_SESSION['career_error'] ?? '');
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
    <title>Careers — Admin — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .job-admin-grid { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem; }
        .job-admin-row {
            display: flex; align-items: center; gap: 1rem;
            background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.75rem 1rem;
        }
        .job-closed { opacity: 0.5; }
        .job-admin-info { flex: 1; min-width: 0; }
        .job-admin-info strong { display: block; font-size: 0.95rem; }
        .job-admin-info span { font-size: 0.82rem; color: var(--text-muted); display: block; margin-top: 2px; }
        .job-admin-controls { display: flex; gap: 0.4rem; align-items: center; flex-shrink: 0; }
        .job-badge-open   { font-size: 0.75rem; font-weight: 600; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: var(--radius-lg); }
        .job-badge-closed { font-size: 0.75rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 2px 8px; border-radius: var(--radius-lg); }
        .upload-section { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; }
        .upload-section h2 { font-size: 1rem; margin-bottom: 1rem; }
        .upload-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .upload-form-grid .full { grid-column: 1 / -1; }
        .upload-label { font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px; }
        .upload-input, .upload-textarea, .upload-select {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm); font-size: 0.9rem; font-family: inherit;
        }
        .upload-textarea { min-height: 140px; resize: vertical; }
        .upload-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
        .btn { display: inline-block; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-strong); background: #fff; font-size: 0.85rem; cursor: pointer; font-family: inherit; white-space: nowrap; text-decoration: none; color: inherit; }
        .btn:hover { background: #f3f4f6; }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-danger { color: #dc2626; border-color: #fca5a5; }
        .btn-danger:hover { background: #fef2f2; }
        .admin-alert { padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem; }
        .admin-alert--error   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .admin-alert--success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
        @media (max-width: 600px) {
            .upload-form-grid { grid-template-columns: 1fr; }
            .job-admin-row { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>Careers</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Manage job postings. Open postings appear on the public <a href="/careers/" target="_blank">careers page</a>. &middot; <a href="/admin/careers-applications.php">View all applications</a></p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($jobs)): ?>
    <p style="color:#6b7280;margin-bottom:1.5rem;">No job postings yet. Add one below.</p>
    <?php else: ?>
    <div class="job-admin-grid">
        <?php foreach ($jobs as $j): ?>
        <div class="job-admin-row<?= !$j['is_open'] ? ' job-closed' : '' ?>">
            <div class="job-admin-info">
                <strong><?= htmlspecialchars($j['title']) ?></strong>
                <span>
                    <?= $j['location'] ? htmlspecialchars($j['location']) : 'No location' ?>
                    <?= $j['employment_type'] ? ' · ' . htmlspecialchars($j['employment_type']) : '' ?>
                </span>
            </div>
            <span class="<?= $j['is_open'] ? 'job-badge-open' : 'job-badge-closed' ?>">
                <?= $j['is_open'] ? 'Open' : 'Closed' ?>
            </span>
            <div class="job-admin-controls">
                <a href="/admin/careers-applications.php?job=<?= $j['id'] ?>" class="btn">Applications<?= (int)$j['app_count'] > 0 ? ' (' . (int)$j['app_count'] . ')' : '' ?></a>
                <a href="/admin/careers.php?edit=<?= $j['id'] ?>" class="btn">Edit</a>
                <form method="POST" action="/admin/careers-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <button type="submit" class="btn"><?= $j['is_open'] ? 'Close' : 'Reopen' ?></button>
                </form>
                <form method="POST" action="/admin/careers-action.php" onsubmit="return confirm('Delete this posting?')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="upload-section">
        <h2><?= $editJob ? 'Edit posting' : 'Add new posting' ?></h2>
        <form method="POST" action="/admin/careers-action.php">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($editJob): ?>
            <input type="hidden" name="id" value="<?= $editJob['id'] ?>">
            <?php endif; ?>
            <div class="upload-form-grid">
                <div>
                    <label class="upload-label">Title <span style="color:#ef4444">*</span></label>
                    <input type="text" name="title" class="upload-input" maxlength="150" required
                           value="<?= $editJob ? htmlspecialchars($editJob['title']) : '' ?>"
                           placeholder="e.g. Customer Support Associate">
                </div>
                <div>
                    <label class="upload-label">Title <span style="color:#9ca3af">(Khmer — optional)</span></label>
                    <input type="text" name="title_km" class="upload-input" maxlength="150"
                           value="<?= $editJob ? htmlspecialchars($editJob['title_km'] ?? '') : '' ?>"
                           placeholder="ចំណងជើងការងារជាភាសាខ្មែរ">
                </div>
                <div>
                    <label class="upload-label">Location <span style="color:#9ca3af">(optional)</span></label>
                    <input type="text" name="location" class="upload-input" maxlength="120"
                           value="<?= $editJob ? htmlspecialchars($editJob['location'] ?? '') : '' ?>"
                           placeholder="e.g. Phnom Penh / Remote">
                </div>
                <div>
                    <label class="upload-label">Location <span style="color:#9ca3af">(Khmer — optional)</span></label>
                    <input type="text" name="location_km" class="upload-input" maxlength="120"
                           value="<?= $editJob ? htmlspecialchars($editJob['location_km'] ?? '') : '' ?>"
                           placeholder="ទីតាំងជាភាសាខ្មែរ">
                </div>
                <div>
                    <label class="upload-label">Employment type</label>
                    <select name="employment_type" class="upload-select">
                        <option value="">—</option>
                        <?php foreach ($types as $type): ?>
                        <option value="<?= $type ?>" <?= ($editJob && $editJob['employment_type'] === $type) ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="upload-label">Status</label>
                    <label class="upload-check" style="padding-top:0.5rem;">
                        <input type="checkbox" name="is_open" value="1" <?= (!$editJob || $editJob['is_open']) ? 'checked' : '' ?>>
                        Open (visible on the careers page)
                    </label>
                </div>
                <div class="full">
                    <label class="upload-label">Description <span style="color:#9ca3af">(optional)</span></label>
                    <textarea name="description" class="upload-textarea" maxlength="5000"
                              placeholder="Role summary, responsibilities, requirements, how to apply…"><?= $editJob ? htmlspecialchars($editJob['description'] ?? '') : '' ?></textarea>
                </div>
                <div class="full">
                    <label class="upload-label">Description <span style="color:#9ca3af">(Khmer — optional)</span></label>
                    <textarea name="description_km" class="upload-textarea" maxlength="5000"
                              placeholder="ការពិពណ៌នាការងារជាភាសាខ្មែរ…"><?= $editJob ? htmlspecialchars($editJob['description_km'] ?? '') : '' ?></textarea>
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                <button type="submit" class="btn btn-primary"><?= $editJob ? 'Save changes' : 'Add posting' ?></button>
                <?php if ($editJob): ?>
                <a href="/admin/careers.php" class="btn">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
