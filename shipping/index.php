<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/shipping/shipping.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="shipping-wrap">
        <h1>Shipping</h1>

        <div class="shipping-section">
            <h2>Delivery</h2>
            <p>teepsaa uses Grab for deliveries within Phnom Penh. Delivery is cash on delivery (COD) — you pay the driver directly when your order arrives. The estimated delivery fee is shown in your cart and at checkout for reference.</p>
        </div>

        <div class="shipping-section">
            <h2>Delivery area</h2>
            <p>Deliveries are currently available within Phnom Penh only. Orders from vendors outside the delivery range cannot be completed.</p>
        </div>

        <div class="shipping-section">
            <h2>Payment</h2>
            <p>teepsaa accepts payment via ABA bank transfer. After placing your order, scan the QR code in your ABA app and submit your payment. Orders are processed once payment is confirmed by our team.</p>
        </div>

        <div class="shipping-section">
            <h2>Marketplace policy</h2>
            <p>teepsaa is a marketplace connecting buyers and independent vendors. Each vendor is responsible for the quality and accuracy of their listings. teepsaa is not responsible for the condition of items sold by vendors.</p>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
