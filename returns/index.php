<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/returns/returns.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="returns-wrap">
        <h1>Returns</h1>

        <div class="returns-section">
            <h2>Return eligibility</h2>
            <p>Returns are handled between the buyer and the individual vendor. Contact the vendor directly if you have an issue with your order.</p>
        </div>

        <div class="returns-section">
            <h2>Disputes</h2>
            <p>If you are unable to resolve an issue with a vendor, contact teepsaa support and we will assist in mediating the dispute.</p>
        </div>

        <div class="returns-section">
            <h2>Refunds</h2>
            <p>Refund eligibility is determined on a case-by-case basis. teepsaa does not guarantee refunds for all purchases. Please review a vendor's listing carefully before ordering.</p>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
