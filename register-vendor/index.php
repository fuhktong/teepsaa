<?php
session_start();
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
        <h1>Want to sell on teepsaa?</h1>
        <p class="auth-info">You're signed in with a <strong>buyer</strong> account. Vendor accounts are separate, so to start selling you'll need to register a vendor account with a different email address.</p>
        <p class="auth-info">Log out first, then create your vendor account.</p>
        <a class="auth-cta" href="/logout/logout.php?next=/register-vendor/">Log out &amp; register as a vendor</a>
        <p class="auth-switch">Changed your mind? <a href="/dashboard-buyer/">Back to your account</a></p>
        <?php else: ?>
        <h1>Register as a vendor</h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/register-vendor/register-vendor.php">
            <?= csrf_input() ?>
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" required autofocus>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            <label for="promo_code">Promo code <span style="font-weight:400;color:#6b7280">(optional)</span></label>
            <input type="text" id="promo_code" name="promo_code" maxlength="50" placeholder="Enter code if you have one" style="text-transform:uppercase">
            <button type="submit">Register</button>
        </form>
        <p class="auth-tos">By registering you agree to our <a href="/terms/">Terms of Service</a> and <a href="/privacy/">Privacy Policy</a>.</p>
        <p class="auth-switch">Already have an account? <a href="/login-vendor/">Vendor log in</a></p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
