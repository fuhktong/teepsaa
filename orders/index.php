<?php
session_start();

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer') {
    header('Location: /dashboard-buyer/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/orders/orders.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="orders-wrap">
        <h1>Your Orders</h1>
        <p>Log in as a buyer to view your orders.</p>
        <a href="/login-buyer/" class="orders-btn">Buyer login</a>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
