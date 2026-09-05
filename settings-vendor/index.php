<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId    = $_SESSION['user_id'];
$validTabs = ['account', 'password', 'danger'];
$tab       = $_GET['tab'] ?? 'account';
// The Business tab (and the old Address / Bank QR tabs folded into it) moved
// to its own page — keep old links working
if (in_array($tab, ['business', 'address', 'aba-qr'], true)) {
    header('Location: /business-vendor/');
    exit;
}
if (!in_array($tab, $validTabs)) $tab = 'account';

$stmt = $pdo->prepare('SELECT name, email, phone, avatar, avatar_color FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$vendor = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$business = $stmt->fetch();

$bizProductCount = 0;
$bizOpenOrders   = 0;
if ($tab === 'danger' && $business) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE business_id = ?');
    $stmt->execute([$business['id']]);
    $bizProductCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('completed','cancelled','refunded','refund_rejected')");
    $stmt->execute([$business['id']]);
    $bizOpenOrders = (int)$stmt->fetchColumn();
}

$success = $_SESSION['settings_success'] ?? '';
$error   = $_SESSION['settings_error']   ?? '';
unset($_SESSION['settings_success'], $_SESSION['settings_error']);
?>
<!DOCTYPE html>
<html lang="<?= ($_SESSION['lang'] ?? 'km') === 'km' ? 'km' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/settings-vendor/settings-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>
<?php require __DIR__ . '/../vendor-subnav/vendor-subnav.php'; ?>

<main>
    <h1 style="margin-bottom:1.5rem"><?= $t['settings_title'] ?></h1>

    <div class="settings-wrap">

        <nav class="settings-nav">
            <a href="?tab=account"  class="<?= $tab === 'account'  ? 'active' : '' ?>"><?= $t['settings_tab_account'] ?></a>
            <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>"><?= $t['settings_password_heading'] ?></a>
            <a href="?tab=danger"   class="danger-link <?= $tab === 'danger' ? 'active' : '' ?>"><?= $t['settings_delete_account'] ?></a>
        </nav>

        <div class="settings-content">

            <?php if ($success): ?>
            <p class="settings-msg settings-msg--success"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
            <p class="settings-msg settings-msg--error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if ($tab === 'account'): ?>
            <div class="settings-section">
                <h2><?= $t['settings_tab_account'] ?></h2>

                <?php $vColorIdx = isset($vendor['avatar_color']) ? (int)$vendor['avatar_color'] : (abs($userId) % 5); ?>
                <div class="avatar-preview-wrap">
                    <?php if ($vendor['avatar']): ?>
                        <img src="/uploads/<?= htmlspecialchars($vendor['avatar']) ?>" alt="" class="avatar-preview">
                    <?php else: ?>
                        <?= _avatar_svg($userId, $vColorIdx, 64) ?>
                    <?php endif; ?>
                    <div>
                        <form method="POST" action="/settings-vendor/avatar-action.php" enctype="multipart/form-data" style="display:inline">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="photo">
                            <label for="avatar" class="btn-upload"><?= $t['settings_choose_photo'] ?></label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png" style="display:none" onchange="this.form.submit()">
                        </form>
                        <?php if ($vendor['avatar']): ?>
                        <form method="POST" action="/settings-vendor/avatar-action.php" style="display:inline;margin-left:0.5rem">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-remove-avatar"><?= $t['settings_remove_photo'] ?></button>
                        </form>
                        <?php endif; ?>
                        <p class="field-hint" style="margin-top:0.35rem"><?= $t['settings_photo_hint'] ?></p>
                    </div>
                </div>

                <?php $avPalette = ['#4a86e8','#e06055','#f6b026','#57bb8a','#8e63ce']; ?>
                <div style="margin-top:1.1rem">
                    <label class="settings-field-label"><?= $t['settings_avatar_color'] ?> <span class="field-hint" style="font-weight:400"><?= $t['settings_avatar_hint'] ?></span></label>
                    <form method="POST" action="/settings-vendor/avatar-color-action.php">
                        <?= csrf_input() ?>
                        <div class="avatar-color-picker">
                            <?php foreach ($avPalette as $i => $bg): ?>
                            <label class="avatar-color-swatch <?= $vColorIdx === $i ? 'selected' : '' ?>" style="--ac:<?= $bg ?>">
                                <input type="radio" name="color" value="<?= $i ?>" onchange="this.form.submit()"<?= $vColorIdx === $i ? ' checked' : '' ?>>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>

                <hr class="form-divider">

                <form method="POST" action="/settings-vendor/profile-action.php">
                    <?= csrf_input() ?>
                    <div class="settings-field">
                        <label for="name"><?= $t['vendor_contact_name'] ?></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($vendor['name']) ?>" required>
                    </div>
                    <div class="settings-field">
                        <label for="email"><?= $t['vendor_contact_email'] ?></label>
                        <input type="email" id="email" value="<?= htmlspecialchars($vendor['email']) ?>" readonly>
                        <p class="field-hint"><?= $t['settings_email_hint'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="phone"><?= $t['vendor_contact_phone'] ?></label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>" placeholder="e.g. 012 345 678">
                    </div>
                    <button type="submit" class="btn-save"><?= $t['settings_save'] ?></button>
                </form>
            </div>

            <?php elseif ($tab === 'password'): ?>
            <div class="settings-section">
                <h2><?= $t['settings_password_heading'] ?></h2>
                <form method="POST" action="/settings-vendor/password-action.php">
                    <?= csrf_input() ?>
                    <input type="text" name="username" value="<?= htmlspecialchars($vendor['email']) ?>" autocomplete="username" hidden readonly>
                    <div class="settings-field">
                        <label for="current_password"><?= $t['settings_current_pw'] ?></label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="settings-field">
                        <label for="new_password"><?= $t['settings_new_pw'] ?></label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                        <p class="field-hint"><?= $t['settings_pw_hint'] ?></p>
                    </div>
                    <div class="settings-field">
                        <label for="confirm_password"><?= $t['settings_confirm_pw'] ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-save"><?= $t['settings_update_pw'] ?></button>
                </form>
            </div>

            <?php elseif ($tab === 'danger'): ?>
            <div class="settings-section">
                <?php if ($business): ?>
                <h2><?= $t['settings_delete_business'] ?></h2>
                <div class="danger-zone" style="margin-bottom:2rem">
                    <p><?= $t['settings_delete_biz_explain'] ?></p>
                    <?php if ($bizProductCount > 0 || $bizOpenOrders > 0): ?>
                        <?php if ($bizProductCount > 0): ?>
                        <p><?= sprintf($t['settings_delete_biz_products'], $bizProductCount) ?> <a href="/products/"><?= $t['settings_delete_biz_goto_products'] ?></a></p>
                        <?php endif; ?>
                        <?php if ($bizOpenOrders > 0): ?>
                        <p><?= sprintf($t['settings_delete_biz_orders'], $bizOpenOrders) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?= $t['settings_delete_biz_warning'] ?></p>
                        <form method="POST" action="/settings-vendor/business-delete-action.php">
                            <?= csrf_input() ?>
                            <div class="settings-field">
                                <label for="delete_biz_password"><?= $t['settings_confirm_pw_label'] ?></label>
                                <input type="password" id="delete_biz_password" name="password" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn-danger"><?= $t['settings_delete_biz_confirm'] ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h2><?= $t['settings_delete_account'] ?></h2>
                <div class="danger-zone">
                    <p><?= $t['vendor_delete_warning'] ?></p>
                    <form method="POST" action="/settings-vendor/delete-action.php">
                        <?= csrf_input() ?>
                        <div class="settings-field">
                            <label for="delete_password"><?= $t['settings_confirm_pw_label'] ?></label>
                            <input type="password" id="delete_password" name="password" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn-danger"><?= $t['settings_delete_confirm'] ?></button>
                    </form>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
