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

admin_require('admins');

$admins = $pdo->query('SELECT id, name, email, admin_role, is_active, is_owner, created_at FROM admins ORDER BY created_at ASC')->fetchAll();

$permStmt = $pdo->query('SELECT admin_id, section FROM admin_permissions');
$permsByAdmin = [];
foreach ($permStmt->fetchAll() as $row) {
    $permsByAdmin[$row['admin_id']][] = $row['section'];
}

$error   = $_SESSION['admins_error']   ?? '';
$success = $_SESSION['admins_success'] ?? '';
unset($_SESSION['admins_error'], $_SESSION['admins_success']);
$adminSection = 'admins';
$adminTab     = '';

function admin_form_fields(?array $admin, array $perms): void {
    $isSuper = !$admin || $admin['admin_role'] === 'super';
    ?>
    <div class="upload-form-grid">
        <div>
            <label class="upload-label">Name <span style="color:#9ca3af">(optional)</span></label>
            <input type="text" name="name" class="upload-input" maxlength="255"
                   value="<?= $admin ? htmlspecialchars($admin['name']) : '' ?>"
                   placeholder="e.g. Sophea Chan">
        </div>
        <div>
            <label class="upload-label">Email <span style="color:#ef4444">*</span></label>
            <input type="email" name="email" class="upload-input" maxlength="255" required
                   value="<?= $admin ? htmlspecialchars($admin['email']) : '' ?>"
                   placeholder="name@teepsaa.com">
        </div>
        <div class="full">
            <label class="upload-label">Access level</label>
            <div class="role-choice">
                <label><input type="radio" name="admin_role" value="super" <?= $isSuper ? 'checked' : '' ?>> Full access — includes managing admins</label>
                <label><input type="radio" name="admin_role" value="custom" <?= !$isSuper ? 'checked' : '' ?>> Custom — pick sections below</label>
            </div>
        </div>
        <div class="full permission-panel">
            <div class="perm-header">
                <label class="upload-label">Admin Sections</label>
                <button type="button" class="preset-btn" data-preset="">Clear all</button>
            </div>
            <div class="perm-groups">
                <?php foreach (ADMIN_SECTION_GROUPS as $groupLabel => $sections): ?>
                <div class="perm-group">
                    <div class="perm-group-header">
                        <span class="perm-group-title"><?= htmlspecialchars($groupLabel) ?></span>
                        <label class="switch">
                            <input type="checkbox" class="group-toggle-checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <?php foreach ($sections as $key => $label): ?>
                    <label class="perm-check">
                        <span><?= htmlspecialchars($label) ?></span>
                        <span class="switch">
                            <input type="checkbox" class="perm-checkbox" name="sections[]" value="<?= $key ?>" <?= in_array($key, $perms, true) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label class="upload-label"><?= $admin ? 'Reset password' : 'Password' ?> <span style="color:<?= $admin ? '#9ca3af' : '#ef4444' ?>"><?= $admin ? '(leave blank to keep current)' : '*' ?></span></label>
            <input type="password" name="password" class="upload-input" minlength="8" autocomplete="new-password" <?= $admin ? '' : 'required' ?>>
            <p class="field-hint">At least 8 characters.</p>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .admin-admin-grid { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem; }
        .admin-admin-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .admin-admin-row {
            display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem;
        }
        .admin-inactive { opacity: 0.5; }
        .row-toggle {
            flex-shrink: 0; width: 22px; height: 22px; border-radius: var(--radius-sm); border: 1px solid var(--border-strong);
            background: #fff; cursor: pointer; font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center;
            font-family: inherit; transition: transform 0.15s;
        }
        .row-toggle:hover { background: #f3f4f6; }
        .row-toggle[aria-expanded="true"] { transform: rotate(90deg); }
        .admin-admin-info { flex: 1; min-width: 0; cursor: pointer; }
        .admin-admin-info strong { display: block; font-size: 0.95rem; }
        .admin-admin-info span { font-size: 0.82rem; color: var(--text-muted); display: block; margin-top: 2px; }
        .admin-admin-controls { display: flex; gap: 0.4rem; align-items: center; flex-shrink: 0; }
        .admin-edit-panel { border-top: 1px solid var(--border); background: #fafafa; padding: 1.25rem 1.25rem 1.25rem 2.75rem; }
        .role-badge {
            font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: var(--radius-lg);
            color: #3730a3; background: #e0e7ff; white-space: nowrap; flex-shrink: 0;
        }
        .badge-inactive { font-size: 0.75rem; font-weight: 600; color: #92400e; background: #fef3c7; padding: 2px 8px; border-radius: var(--radius-lg); }
        .badge-owner { font-size: 0.75rem; font-weight: 600; color: #065f46; background: #d1fae5; padding: 2px 8px; border-radius: var(--radius-lg); white-space: nowrap; flex-shrink: 0; }
        .upload-section { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem; }
        .upload-section h2 { font-size: 1rem; margin-bottom: 1rem; }
        .upload-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .upload-form-grid .full { grid-column: 1 / -1; }
        .upload-label { font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px; }
        .upload-input, .upload-select {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm); font-size: 0.9rem; font-family: inherit;
        }
        .field-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; }
        .role-choice { display: flex; gap: 1.25rem; padding-top: 0.4rem; }
        .role-choice label { display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; font-weight: 400; }
        .perm-header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem; }
        .perm-header .upload-label { margin-bottom: 0; }
        .preset-btn {
            font-size: 0.8rem; padding: 0.3rem 0.65rem; border-radius: var(--radius-sm);
            border: 1px solid var(--border-strong); background: #fff; cursor: pointer; font-family: inherit;
        }
        .preset-btn:hover { background: #f3f4f6; }
        .perm-groups { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .perm-group { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.6rem 0.75rem; }
        .perm-group-header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.4rem; }
        .perm-group-title { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
        .perm-check { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; font-size: 0.88rem; padding: 4px 0; cursor: pointer; }
        .switch { position: relative; display: inline-block; width: 36px; height: 20px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .switch .slider { position: absolute; inset: 0; background: #d1d5db; border-radius: 20px; cursor: pointer; transition: background 0.15s; }
        .switch .slider::before { content: ""; position: absolute; height: 14px; width: 14px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.25); }
        .switch input:checked + .slider { background: var(--primary); }
        .switch input:checked + .slider::before { transform: translateX(16px); }
        .switch input:focus-visible + .slider { outline: 2px solid var(--primary); outline-offset: 2px; }
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
            .perm-groups { grid-template-columns: 1fr; }
            .admin-admin-row { flex-wrap: wrap; }
            .admin-edit-panel { padding-left: 1.25rem; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>

    <h1>Manage Admins</h1>
    <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.25rem;">Create admin accounts and toggle exactly which sections each one can access. Only super admins can see this page.</p>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="admin-alert admin-alert--success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="admin-admin-grid">
        <?php foreach ($admins as $a): ?>
        <?php $ap = $permsByAdmin[$a['id']] ?? []; ?>
        <div class="admin-admin-card">
            <div class="admin-admin-row<?= !$a['is_active'] ? ' admin-inactive' : '' ?>">
                <button type="button" class="row-toggle" aria-expanded="false" aria-label="Edit admin">&#9656;</button>
                <div class="admin-admin-info">
                    <strong><?= htmlspecialchars($a['name'] ?: $a['email']) ?></strong>
                    <span><?= htmlspecialchars($a['email']) ?></span>
                </div>
                <span class="role-badge"><?= $a['admin_role'] === 'super' ? 'Full access' : 'Custom' ?></span>
                <?php if ($a['is_owner']): ?>
                <span class="badge-owner">Owner</span>
                <?php endif; ?>
                <?php if (!$a['is_active']): ?>
                <span class="badge-inactive">Deactivated</span>
                <?php endif; ?>
                <div class="admin-admin-controls">
                    <?php if ((int) $a['id'] !== (int) $_SESSION['user_id'] && !$a['is_owner']): ?>
                    <?php if (!$a['is_active']): ?>
                    <form method="POST" action="/admin/admins-action.php">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn">Reactivate</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" action="/admin/admins-action.php" onsubmit="return confirm('Delete this admin account?')">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-edit-panel" hidden>
                <form method="POST" action="/admin/admins-action.php" class="admin-role-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <?php admin_form_fields($a, $ap); ?>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="upload-section">
        <h2>Add new admin</h2>
        <form method="POST" action="/admin/admins-action.php" class="admin-role-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save">
            <?php admin_form_fields(null, []); ?>
            <div style="margin-top:1rem;">
                <button type="submit" class="btn btn-primary">Create admin</button>
            </div>
        </form>
    </div>
</main>

<script>
    document.querySelectorAll('.admin-role-form').forEach(form => {
        const permPanel = form.querySelector('.permission-panel');

        function updateRoleUI() {
            const custom = form.querySelector('input[name="admin_role"][value="custom"]').checked;
            permPanel.style.display = custom ? 'block' : 'none';
        }
        form.querySelectorAll('input[name="admin_role"]').forEach(r => r.addEventListener('change', updateRoleUI));
        updateRoleUI();

        form.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                form.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
                form.querySelectorAll('.group-toggle-checkbox').forEach(gt => { gt.checked = false; });
            });
        });

        form.querySelectorAll('.perm-group').forEach(group => {
            const groupToggle = group.querySelector('.group-toggle-checkbox');
            const boxes       = group.querySelectorAll('.perm-checkbox');

            groupToggle.checked = boxes.length > 0 && Array.from(boxes).every(cb => cb.checked);

            groupToggle.addEventListener('change', () => {
                boxes.forEach(cb => { cb.checked = groupToggle.checked; });
            });

            boxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    groupToggle.checked = Array.from(boxes).every(box => box.checked);
                });
            });
        });
    });

    document.querySelectorAll('.admin-admin-card').forEach(card => {
        const toggleBtn = card.querySelector('.row-toggle');
        const info      = card.querySelector('.admin-admin-info');
        const panel     = card.querySelector('.admin-edit-panel');

        function togglePanel() {
            const willShow = panel.hidden;
            panel.hidden = !willShow;
            toggleBtn.setAttribute('aria-expanded', String(willShow));
        }

        toggleBtn.addEventListener('click', togglePanel);
        info.addEventListener('click', togglePanel);
    });
</script>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
