<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'buyer')  { header('Location: /dashboard-buyer/');  exit; }
    if ($role === 'vendor') { header('Location: /dashboard-vendor/settings/'); exit; }
    if ($role === 'admin')  { header('Location: /admin/');             exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/account/account.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="account-wrap">
        <h1>Your Account</h1>
        <p>Please log in to view your account.</p>
        <div class="account-links">
            <a href="/login-buyer/" class="account-btn">Buyer login</a>
            <a href="/login-vendor/" class="account-btn account-btn--secondary">Vendor login</a>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
