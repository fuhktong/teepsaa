<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/privacy/privacy.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="legal-wrap">
        <h1>Privacy Policy</h1>
        <p class="legal-effective">Effective date: 1 June 2026</p>

        <p>This Privacy Policy explains how teepsaa ("we", "us", "our") collects, uses, and stores personal information when you use our platform. By creating an account or placing an order, you agree to the practices described here.</p>

        <h2>1. What we collect</h2>
        <p>When you register or use teepsaa, we may collect the following:</p>
        <ul>
            <li><strong>Account information</strong> — name, email address, and password (stored as a hashed value, never in plain text)</li>
            <li><strong>Contact and delivery details</strong> — phone number, delivery address, address notes, and a map pin (latitude/longitude) for delivery estimates</li>
            <li><strong>Profile photo</strong> — an optional avatar image you upload</li>
            <li><strong>Order history</strong> — items ordered, prices paid, order status, and timestamps</li>
            <li><strong>Payment records</strong> — evidence of ABA bank transfers submitted by buyers. We do not store card numbers or banking credentials.</li>
            <li><strong>Business information</strong> — for vendors: business name, location, ABA QR code for payouts, and product listings</li>
        </ul>

        <h2>2. Why we collect it</h2>
        <ul>
            <li>To create and manage your account</li>
            <li>To process orders and coordinate delivery via Grab</li>
            <li>To calculate delivery fees based on distance</li>
            <li>To process vendor payouts via ABA bank transfer</li>
            <li>To respond to support requests</li>
            <li>To detect and prevent fraudulent activity</li>
        </ul>

        <h2>3. How it is stored</h2>
        <p>Your data is stored on a secured server. Passwords are hashed using industry-standard algorithms and are never stored or transmitted in plain text. Uploaded files are stored in a restricted directory with script execution disabled.</p>

        <h2>4. Third parties</h2>
        <p>We share data with third parties only where necessary to operate the platform:</p>
        <ul>
            <li><strong>Mapbox</strong> — used to render maps and calculate distances. Your location pin is sent to Mapbox for delivery fee estimation.</li>
            <li><strong>Grab</strong> — used for order delivery. Your delivery address is shared with the assigned Grab driver. Grab's own privacy policy governs their handling of this data.</li>
            <li><strong>ABA Bank</strong> — payments are made directly between buyers and teepsaa via ABA bank transfer. We do not share your banking details with third parties.</li>
        </ul>
        <p>We do not sell your personal data to advertisers or data brokers.</p>

        <h2>5. Cookies</h2>
        <p>teepsaa uses a single session cookie to keep you logged in during your visit. This cookie contains no personal information and is deleted when your session ends. We do not use tracking, advertising, or analytics cookies.</p>

        <h2>6. Data retention</h2>
        <p>Your data is retained for as long as your account is active. If you delete your account, your personal information is removed from our systems. Order records may be retained for a short period for accounting and dispute resolution purposes before being deleted.</p>

        <h2>7. Your rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access the personal data we hold about you</li>
            <li>Request correction of inaccurate data</li>
            <li>Request deletion of your account and associated data</li>
        </ul>
        <p>To exercise any of these rights, contact us at the address below.</p>

        <h2>8. Changes to this policy</h2>
        <p>We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated effective date. Continued use of the platform after changes constitutes acceptance of the updated policy.</p>

        <h2>9. Contact</h2>
        <p>For privacy questions or data requests, contact us via our <a href="/help/">Help Center</a>.</p>

        <p class="legal-note">This policy should be reviewed by a qualified legal advisor before the platform launches publicly.</p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
