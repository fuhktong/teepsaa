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

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor') AND read_at IS NULL")->fetchColumn();
$stmt = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$adminEmail = $stmt->fetchColumn() ?: '';
$success = $_SESSION['settings_success'] ?? '';
$error   = $_SESSION['settings_error']   ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);
$adminSection = 'settings';
$adminTab     = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Settings</title>
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
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Settings</h1>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php
    $aStmt = $pdo->prepare('SELECT avatar, avatar_color FROM admins WHERE id = ?');
    $aStmt->execute([$_SESSION['user_id']]);
    $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
    $adminAvatar      = $aRow['avatar'] ?? '';
    $adminAvatarColor = isset($aRow['avatar_color']) ? (int)$aRow['avatar_color'] : null;
    $adminColorIdx    = $adminAvatarColor ?? (abs((int)$_SESSION['user_id']) % 5);
    $avPalette = ['#4a86e8','#e06055','#f6b026','#57bb8a','#8e63ce'];
    ?>
    <div class="settings-form">
        <h2>Avatar</h2>

        <div class="avatar-preview-wrap" style="margin-bottom:1.25rem">
            <?php if ($adminAvatar): ?>
                <img src="/uploads/<?= htmlspecialchars($adminAvatar) ?>" alt="" class="avatar-preview">
            <?php else: ?>
                <?= _avatar_svg((int)$_SESSION['user_id'], $adminAvatarColor, 64) ?>
            <?php endif; ?>
            <div>
                <form method="POST" action="/admin/avatar-action.php" enctype="multipart/form-data" style="display:inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="photo">
                    <label class="btn-upload" style="cursor:pointer">Choose photo
                        <input type="file" name="avatar" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                    </label>
                </form>
                <?php if ($adminAvatar): ?>
                <form method="POST" action="/admin/avatar-action.php" style="display:inline;margin-left:0.5rem">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn-remove-avatar">Remove</button>
                </form>
                <?php endif; ?>
                <p class="field-hint" style="margin-top:0.35rem">JPG or PNG, max 2MB.</p>
            </div>
        </div>

        <label class="settings-field-label">Avatar color <span class="field-hint" style="font-weight:400">— shown when no photo is set</span></label>
        <form method="POST" action="/admin/avatar-action.php">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="color">
            <div class="avatar-color-picker">
                <?php foreach ($avPalette as $i => $bg): ?>
                <label class="avatar-color-swatch <?= $adminColorIdx === $i ? 'selected' : '' ?>" style="--ac:<?= $bg ?>">
                    <input type="radio" name="color" value="<?= $i ?>" onchange="this.form.submit()"<?= $adminColorIdx === $i ? ' checked' : '' ?>>
                </label>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <div class="settings-form" style="margin-top:2rem">
        <h2>Change password</h2>
        <form method="POST" action="/admin/settings-password-action.php">
            <?= csrf_input() ?>
            <input type="text" name="username" value="<?= htmlspecialchars($adminEmail) ?>" autocomplete="username" hidden readonly>
            <div class="settings-field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="settings-field">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                <p class="field-hint">At least 8 characters.</p>
            </div>
            <div class="settings-field">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-save">Update password</button>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
