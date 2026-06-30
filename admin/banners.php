<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

$pendingVendorCount = (int) $pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$pendingPayoutCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$refundCount        = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();

try {
    $banners = $pdo->query('SELECT * FROM banners ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (PDOException $e) {
    $banners = [];
    $error = 'Banners table not found — run database/migration-banners.sql first.';
}

$error   = $_SESSION['banner_error']   ?? '';
$success = $_SESSION['banner_success'] ?? '';
unset($_SESSION['banner_error'], $_SESSION['banner_success']);
$adminSection = 'marketing';
$adminTab     = 'banners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banners — Admin — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .banner-admin-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .banner-admin-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
        }
        .banner-admin-thumb {
            width: 140px;
            height: 72px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            flex-shrink: 0;
            background: #f3f4f6;
        }
        .banner-admin-thumb--empty {
            width: 140px;
            height: 72px;
            border-radius: var(--radius-sm);
            background: #f3f4f6;
            flex-shrink: 0;
        }
        .banner-admin-info { flex: 1; min-width: 0; }
        .banner-admin-info strong { display: block; font-size: 0.95rem; }
        .banner-admin-info span  { font-size: 0.82rem; color: var(--text-muted); display: block; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .banner-admin-controls { display: flex; gap: 0.4rem; align-items: center; flex-shrink: 0; }
        .banner-inactive { opacity: 0.45; }
        .banner-badge-active   { font-size: 0.75rem; font-weight: 600; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: var(--radius-lg); }
        .banner-badge-inactive { font-size: 0.75rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 2px 8px; border-radius: var(--radius-lg); }
        .banner-sort-btns { display: flex; flex-direction: column; gap: 2px; }
        .banner-sort-btn {
            background: none; border: 1px solid var(--border-strong); border-radius: var(--radius-sm);
            width: 26px; height: 22px; cursor: pointer; font-size: 0.75rem; line-height: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .banner-sort-btn:hover { background: #f3f4f6; }
        .upload-section {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }
        .upload-section h2 { font-size: 1rem; margin-bottom: 1rem; }
        .upload-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .upload-form-grid .full { grid-column: 1 / -1; }
        .upload-label { font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px; }
        .upload-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }
        .upload-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }
        .btn {
            display: inline-block; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong); background: #fff; font-size: 0.85rem;
            cursor: pointer; font-family: inherit; white-space: nowrap;
        }
        .btn:hover { background: #f3f4f6; }
        .btn-primary {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-danger { color: #dc2626; border-color: #fca5a5; }
        .btn-danger:hover { background: #fef2f2; }
        .admin-alert {
            padding: 0.75rem 1rem; border-radius: var(--radius); font-size: 0.9rem;
        }
        .admin-alert--error   { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .admin-alert--success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
        @media (max-width: 600px) {
            .upload-form-grid { grid-template-columns: 1fr; }
            .banner-admin-row { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <?php require __DIR__ . '/../includes/banner-carousel.php'; ?>

    <h1>Banners</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Manage homepage banner slides. Drag order using the arrows. Banners show on the buyer, vendor, and admin home pages.</p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($banners)): ?>
    <p style="color:#6b7280;margin-bottom:1.5rem;">No banners yet. Upload one below.</p>
    <?php else: ?>
    <div class="banner-admin-grid">
        <?php foreach ($banners as $i => $b): ?>
        <div class="banner-admin-row<?= !$b['active'] ? ' banner-inactive' : '' ?>">
            <img src="/uploads/<?= htmlspecialchars($b['image_filename']) ?>"
                 alt="" class="banner-admin-thumb"
                 onerror="this.style.display='none'">
            <div class="banner-admin-info">
                <strong><?= $b['title'] ? htmlspecialchars($b['title']) : '<em style="color:#9ca3af;font-weight:400">No title</em>' ?></strong>
                <span><?= $b['subtitle'] ? htmlspecialchars($b['subtitle']) : '' ?></span>
                <?php if ($b['link_url']): ?>
                <span><?= htmlspecialchars($b['link_url']) ?></span>
                <?php endif; ?>
            </div>
            <span class="<?= $b['active'] ? 'banner-badge-active' : 'banner-badge-inactive' ?>">
                <?= $b['active'] ? 'Active' : 'Hidden' ?>
            </span>
            <div class="banner-sort-btns">
                <?php if ($i > 0): ?>
                <form method="POST" action="/admin/banner-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="move_up">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button type="submit" class="banner-sort-btn" title="Move up">▲</button>
                </form>
                <?php else: ?>
                <div style="width:26px;height:22px;"></div>
                <?php endif; ?>
                <?php if ($i < count($banners) - 1): ?>
                <form method="POST" action="/admin/banner-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="move_down">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button type="submit" class="banner-sort-btn" title="Move down">▼</button>
                </form>
                <?php else: ?>
                <div style="width:26px;height:22px;"></div>
                <?php endif; ?>
            </div>
            <div class="banner-admin-controls">
                <form method="POST" action="/admin/banner-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-sm">
                        <?= $b['active'] ? 'Hide' : 'Show' ?>
                    </button>
                </form>
                <form method="POST" action="/admin/banner-action.php"
                      onsubmit="return confirm('Delete this banner?')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="upload-section">
        <h2>Upload new banner</h2>
        <form method="POST" action="/admin/banner-action.php" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="upload">
            <div class="upload-form-grid">
                <div class="full">
                    <label class="upload-label">Image <span style="color:#ef4444">*</span></label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           class="upload-input" required>
                    <div class="upload-hint">JPEG, PNG or WebP. Recommended size: 1200 × 380 px.</div>
                </div>
                <div>
                    <label class="upload-label">Title <span style="color:#9ca3af">(optional)</span></label>
                    <input type="text" name="title" class="upload-input" maxlength="150"
                           placeholder="e.g. New season arrivals">
                </div>
                <div>
                    <label class="upload-label">Subtitle <span style="color:#9ca3af">(optional)</span></label>
                    <input type="text" name="subtitle" class="upload-input" maxlength="255"
                           placeholder="e.g. Shop the latest from local makers">
                </div>
                <div class="full">
                    <label class="upload-label">Link URL <span style="color:#9ca3af">(optional)</span></label>
                    <input type="text" name="link_url" class="upload-input" maxlength="500"
                           placeholder="e.g. /search/?q=shoes">
                    <div class="upload-hint">Leave blank for a non-clickable banner.</div>
                </div>
            </div>
            <div style="margin-top:1rem;">
                <button type="submit" class="btn btn-primary">Upload banner</button>
            </div>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
