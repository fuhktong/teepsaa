<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';

$buyerBlocked = false;
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'buyer';
    if ($role === 'vendor') { header('Location: /dashboard-vendor/'); exit; }
    if ($role === 'admin')  { header('Location: /admin/'); exit; }
    // A logged-in buyer can't sell — vendor accounts are separate accounts.
    // Show an explainer instead of silently bouncing to the buyer dashboard.
    $buyerBlocked = true;
}

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as a Vendor — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/register-vendor/register-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <?php if ($buyerBlocked): ?>
        <h1><?= $t['sell_cta_title'] ?></h1>
        <p class="auth-info"><?= $t['sell_cta_body1'] ?></p>
        <p class="auth-info"><?= $t['sell_cta_body2'] ?></p>
        <a class="auth-cta" href="/logout/logout.php?next=/register-vendor/"><?= $t['sell_cta_button'] ?></a>
        <p class="auth-switch"><?= $t['sell_cta_back'] ?> <a href="/dashboard-buyer/"><?= $t['sell_cta_back_link'] ?></a></p>
        <?php else: ?>
        <h1><?= $t['register_as_vendor'] ?></h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/register-vendor/register-vendor.php">
            <?= csrf_input() ?>
            <label for="name"><?= $t['register_name'] ?></label>
            <input type="text" id="name" name="name" required autofocus autocomplete="name">
            <label for="email"><?= $t['register_email'] ?></label>
            <input type="email" id="email" name="email" required autocomplete="email">
            <label for="password"><?= $t['register_password'] ?></label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <label for="password_confirm"><?= $t['register_confirm'] ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            <label for="promo_code"><?= $t['register_promo'] ?> <span style="font-weight:400;color:#6b7280"><?= $t['form_optional'] ?></span></label>
            <input type="text" id="promo_code" name="promo_code" maxlength="50" placeholder="Enter code if you have one" style="text-transform:uppercase">
            <button type="submit"><?= $t['register_submit'] ?></button>
        </form>
        <p class="auth-tos"><?= sprintf($t['auth_agree'], '<a href="/terms/">' . $t['footer_terms'] . '</a>', '<a href="/privacy/">' . $t['footer_privacy'] . '</a>') ?></p>
        <p class="auth-switch"><?= $t['register_have_account'] ?> <a href="/login-vendor/"><?= $t['login_vendor_title'] ?></a></p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
