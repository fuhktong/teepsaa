<?php
session_start();
require __DIR__ . '/../config/db.php';

$role = $_SESSION['role'] ?? '';

if ($role === 'buyer') {
    $contactUrl = '/contact-buyer/';
} elseif ($role === 'vendor') {
    $contactUrl = '/contact-vendor/';
} else {
    $contactUrl = '/contact/';
}

$faqs = [
    'Orders & Checkout' => [
        ['q' => 'How do I place an order?',
         'a' => 'Browse products, add items to your cart, then proceed to checkout. You\'ll confirm your delivery address and submit your order. Payment is via ABA bank transfer after placing the order.'],
        ['q' => 'Can I change or cancel my order after placing it?',
         'a' => 'Orders can only be changed or cancelled before the vendor has confirmed them. Once confirmed, the order is being prepared and cannot be modified. Contact support as soon as possible if you need to make a change.'],
        ['q' => 'How do I know my order was received?',
         'a' => 'After placing your order you\'ll see a confirmation page with your order number. You can track the status of all your orders from your Orders page.'],
        ['q' => 'Can I order from multiple vendors at once?',
         'a' => 'Yes. Your cart can hold items from multiple vendors. Each vendor\'s items are grouped separately at checkout, and delivery fees are calculated per vendor.'],
    ],
    'Delivery' => [
        ['q' => 'How does delivery work?',
         'a' => 'teep\'saa uses Grab for deliveries within Phnom Penh. Once your order is dispatched, a Grab driver will pick it up from the vendor and deliver it to your address. Delivery is cash on delivery (COD) — you pay the driver directly when your order arrives.'],
        ['q' => 'How long does delivery take?',
         'a' => 'Most deliveries within Phnom Penh arrive within 1–3 hours once the vendor dispatches the order. Actual time depends on the vendor\'s preparation time and driver availability.'],
        ['q' => 'How is the delivery fee calculated?',
         'a' => 'The delivery fee is estimated based on the distance between the vendor\'s location and your delivery address. The estimate is shown in your cart and at checkout. The actual Grab fare may vary slightly.'],
        ['q' => 'What if I\'m not home when my order arrives?',
         'a' => 'The Grab driver will attempt delivery at your address. If you\'re unavailable, contact the driver directly through the Grab app. teep\'saa is not responsible for failed deliveries due to the buyer being unreachable.'],
    ],
    'Payments' => [
        ['q' => 'What payment methods do you accept?',
         'a' => 'teep\'saa accepts payment via ABA Bank transfer. After placing your order, scan the QR code in your ABA app and complete the transfer. Your order will be processed once payment is confirmed by our team.'],
        ['q' => 'When will my payment be confirmed?',
         'a' => 'Payment confirmation typically happens within a few hours during business hours. You\'ll see your order status update once confirmed.'],
        ['q' => 'What if I paid but my order status hasn\'t updated?',
         'a' => 'If your payment has been sent but your order still shows as unpaid after several hours, please contact support with your order number and a screenshot of your transfer confirmation.'],
    ],
    'Returns & Refunds' => [
        ['q' => 'What is the return policy?',
         'a' => 'Returns are handled on a case-by-case basis. If you received the wrong item, a damaged item, or an item significantly different from the listing, you may be eligible for a return or refund. Contact support within 24 hours of receiving your order.'],
        ['q' => 'How do I request a refund?',
         'a' => 'Go to your Orders page, find the relevant order, and submit a refund request with details of the issue. Our team will review your request and follow up.'],
        ['q' => 'How long does a refund take?',
         'a' => 'Once a refund is approved, it is typically processed within 3–5 business days back to your original payment method.'],
        ['q' => 'What if the vendor disputes my refund request?',
         'a' => 'Our support team will review both sides and make a determination. teep\'saa\'s decision is final in all dispute cases.'],
    ],
    'My Account' => [
        ['q' => 'How do I create an account?',
         'a' => 'Click Register on the login page and fill in your details. Buyer and vendor accounts are separate — create a buyer account to shop, or apply as a vendor to sell.'],
        ['q' => 'Can I have both a buyer and a vendor account?',
         'a' => 'Yes. Vendor accounts are for selling only. If you also want to shop on teep\'saa, you\'ll need to register a separate buyer account with a different email address.'],
        ['q' => 'How do I update my delivery address?',
         'a' => 'Go to your account Settings and update your address under the Address tab. Make sure to set a pin on the map so your delivery fee is calculated correctly.'],
        ['q' => 'How do I change my password?',
         'a' => 'Go to Settings → Password and enter your current password followed by your new one.'],
    ],
    'Selling on teep\'saa' => [
        ['q' => 'How do I become a vendor?',
         'a' => 'Register a vendor account, then submit your business details for review. Once approved by our team, you can start listing products.'],
        ['q' => 'How do payouts work?',
         'a' => 'After a buyer confirms delivery (or after the auto-confirmation window passes), your payout is calculated and queued. Our team processes payouts via ABA bank transfer.'],
        ['q' => 'What fees does teep\'saa charge?',
         'a' => 'teep\'saa charges a royalty fee per sale, which varies by product category. The fee is shown when you list a product, and your estimated payout is displayed on the product form.'],
        ['q' => 'What happens if a buyer requests a refund on my product?',
         'a' => 'You\'ll be notified of any refund requests. You can accept or dispute the request. Our support team mediates if there is a disagreement. Approved refunds are deducted from future payouts.'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/help/help.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="help-hero">
        <h1>Help Center</h1>
        <p>Find answers to common questions below.</p>
    </div>

    <div class="help-toc">
        <?php foreach (array_keys($faqs) as $section): ?>
            <a href="#<?= urlencode($section) ?>" class="help-toc-link"><?= htmlspecialchars($section) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="help-sections">
        <?php foreach ($faqs as $section => $items): ?>
        <section class="help-section" id="<?= urlencode($section) ?>">
            <h2 class="help-section-title"><?= htmlspecialchars($section) ?></h2>
            <div class="help-faqs">
                <?php foreach ($items as $item): ?>
                <details class="faq-item">
                    <summary class="faq-q"><?= htmlspecialchars($item['q']) ?></summary>
                    <p class="faq-a"><?= htmlspecialchars($item['a']) ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <div class="help-contact-cta">
        <h2>Still need help?</h2>
        <p>If you couldn't find what you were looking for, our support team is here.</p>
        <a href="<?= $contactUrl ?>" class="help-contact-btn">Contact Support</a>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
