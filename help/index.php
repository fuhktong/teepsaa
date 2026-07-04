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

$lang = $_SESSION['lang'] ?? 'km';

if ($lang === 'km') {
$faqs = [
    'ការកម្មង់ និងការទូទាត់' => [
        ['q' => 'តើខ្ញុំដាក់កម្មង់ដោយរបៀបណា?',
         'a' => 'រកមើលផលិតផល បញ្ចូលទំនិញទៅក្នុងរទេះ បន្ទាប់មកបន្តទៅការទូទាត់។ អ្នកនឹងបញ្ជាក់អាសយដ្ឋានដឹកជញ្ជូន ហើយដាក់ស្នើកម្មង់របស់អ្នក។ ការបង់ប្រាក់គឺតាមការផ្ទេរប្រាក់ធនាគារ ABA បន្ទាប់ពីដាក់កម្មង់។'],
        ['q' => 'តើខ្ញុំអាចប្តូរ ឬបោះបង់កម្មង់បន្ទាប់ពីដាក់ស្នើបានទេ?',
         'a' => 'កម្មង់អាចប្តូរ ឬបោះបង់បានតែមុនពេលអ្នកលក់បានបញ្ជាក់ប៉ុណ្ណោះ។ នៅពេលបញ្ជាក់រួច កម្មង់កំពុងត្រូវបានរៀបចំ ហើយមិនអាចកែប្រែបានទេ។ សូមទាក់ទងជំនួយឱ្យបានឆាប់ ប្រសិនបើអ្នកត្រូវការផ្លាស់ប្តូរ។'],
        ['q' => 'តើខ្ញុំដឹងថាកម្មង់របស់ខ្ញុំត្រូវបានទទួលដោយរបៀបណា?',
         'a' => 'បន្ទាប់ពីដាក់កម្មង់ អ្នកនឹងឃើញទំព័របញ្ជាក់ជាមួយលេខកម្មង់របស់អ្នក។ អ្នកអាចតាមដានស្ថានភាពកម្មង់ទាំងអស់ពីទំព័រកម្មង់របស់អ្នក។'],
        ['q' => 'តើខ្ញុំអាចកម្មង់ពីអ្នកលក់ច្រើននាក់ក្នុងពេលតែមួយបានទេ?',
         'a' => 'បាទ/ចាស។ រទេះរបស់អ្នកអាចផ្ទុកទំនិញពីអ្នកលក់ច្រើននាក់។ ទំនិញរបស់អ្នកលក់នីមួយៗត្រូវបានដាក់ជាក្រុមដាច់ដោយឡែកនៅពេលទូទាត់ ហើយថ្លៃដឹកជញ្ជូនត្រូវបានគណនាតាមអ្នកលក់នីមួយៗ។'],
    ],
    'ការដឹកជញ្ជូន' => [
        ['q' => 'តើការដឹកជញ្ជូនដំណើរការដោយរបៀបណា?',
         'a' => 'ទីផ្សារ ប្រើ Grab សម្រាប់ការដឹកជញ្ជូននៅក្នុងភ្នំពេញ។ នៅពេលកម្មង់របស់អ្នកត្រូវបានបញ្ជូន អ្នកបើកបរ Grab នឹងយកវាពីអ្នកលក់ ហើយដឹកជញ្ជូនទៅអាសយដ្ឋានរបស់អ្នក។ ការដឹកជញ្ជូនគឺជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) — អ្នកបង់ប្រាក់ឱ្យអ្នកបើកបរដោយផ្ទាល់នៅពេលកម្មង់មកដល់។'],
        ['q' => 'តើការដឹកជញ្ជូនចំណាយពេលប៉ុន្មាន?',
         'a' => 'ការដឹកជញ្ជូនភាគច្រើននៅក្នុងភ្នំពេញមកដល់ក្នុងរយៈពេល ១–៣ ម៉ោង នៅពេលអ្នកលក់បញ្ជូនកម្មង់។ ពេលវេលាជាក់ស្តែងអាស្រ័យលើពេលរៀបចំរបស់អ្នកលក់ និងភាពអាចរកបាននៃអ្នកបើកបរ។'],
        ['q' => 'តើថ្លៃដឹកជញ្ជូនត្រូវបានគណនាដោយរបៀបណា?',
         'a' => 'ថ្លៃដឹកជញ្ជូនត្រូវបានប៉ាន់ស្មានផ្អែកលើចម្ងាយរវាងទីតាំងអ្នកលក់ និងអាសយដ្ឋានដឹកជញ្ជូនរបស់អ្នក។ ការប៉ាន់ស្មានត្រូវបានបង្ហាញនៅក្នុងរទេះ និងពេលទូទាត់។ ថ្លៃ Grab ជាក់ស្តែងអាចប្រែប្រួលបន្តិច។'],
        ['q' => 'ចុះបើខ្ញុំមិននៅផ្ទះពេលកម្មង់មកដល់?',
         'a' => 'អ្នកបើកបរ Grab នឹងព្យាយាមដឹកជញ្ជូនទៅអាសយដ្ឋានរបស់អ្នក។ ប្រសិនបើអ្នកមិននៅ សូមទាក់ទងអ្នកបើកបរដោយផ្ទាល់តាមរយៈកម្មវិធី Grab។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះការដឹកជញ្ជូនបរាជ័យ ដោយសារអ្នកទិញមិនអាចទាក់ទងបានទេ។'],
    ],
    'ការបង់ប្រាក់' => [
        ['q' => 'តើអ្នកទទួលយកវិធីបង់ប្រាក់អ្វីខ្លះ?',
         'a' => 'ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA។ បន្ទាប់ពីដាក់កម្មង់ សូមស្កេនកូដ QR ក្នុងកម្មវិធី ABA របស់អ្នក ហើយបំពេញការផ្ទេរប្រាក់។ កម្មង់របស់អ្នកនឹងត្រូវបានដំណើរការនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងាររបស់យើង។'],
        ['q' => 'តើការបង់ប្រាក់របស់ខ្ញុំនឹងត្រូវបានបញ្ជាក់នៅពេលណា?',
         'a' => 'ការបញ្ជាក់ការបង់ប្រាក់ជាធម្មតាកើតឡើងក្នុងរយៈពេលពីរបីម៉ោងក្នុងម៉ោងធ្វើការ។ អ្នកនឹងឃើញស្ថានភាពកម្មង់របស់អ្នកធ្វើបច្ចុប្បន្នភាពនៅពេលបញ្ជាក់រួច។'],
        ['q' => 'ចុះបើខ្ញុំបានបង់ប្រាក់ ប៉ុន្តែស្ថានភាពកម្មង់មិនទាន់ធ្វើបច្ចុប្បន្នភាព?',
         'a' => 'ប្រសិនបើការបង់ប្រាក់របស់អ្នកត្រូវបានផ្ញើ ប៉ុន្តែកម្មង់នៅតែបង្ហាញថាមិនទាន់បង់ប្រាក់បន្ទាប់ពីពីរបីម៉ោង សូមទាក់ទងជំនួយជាមួយលេខកម្មង់ និងរូបថតការបញ្ជាក់ការផ្ទេរប្រាក់របស់អ្នក។'],
    ],
    'ការប្រគល់ទំនិញ និងសំណង' => [
        ['q' => 'តើគោលការណ៍ការប្រគល់ទំនិញជាអ្វី?',
         'a' => 'ការប្រគល់ទំនិញត្រូវបានដោះស្រាយជាករណីៗ។ ប្រសិនបើអ្នកទទួលបានទំនិញខុស ទំនិញខូច ឬទំនិញខុសគ្នាយ៉ាងខ្លាំងពីបញ្ជី អ្នកអាចមានលក្ខណៈសម្បត្តិសម្រាប់ការប្រគល់ទំនិញ ឬសំណង។ សូមទាក់ទងជំនួយក្នុងរយៈពេល ២៤ ម៉ោងបន្ទាប់ពីទទួលកម្មង់របស់អ្នក។'],
        ['q' => 'តើខ្ញុំស្នើសុំសំណងដោយរបៀបណា?',
         'a' => 'ចូលទៅទំព័រកម្មង់របស់អ្នក រកកម្មង់ពាក់ព័ន្ធ ហើយដាក់ស្នើសំណើសំណងជាមួយព័ត៌មានលម្អិតនៃបញ្ហា។ ក្រុមការងាររបស់យើងនឹងពិនិត្យសំណើរបស់អ្នក ហើយតាមដាន។'],
        ['q' => 'តើសំណងចំណាយពេលប៉ុន្មាន?',
         'a' => 'នៅពេលសំណងត្រូវបានអនុម័ត វាជាធម្មតាត្រូវបានដំណើរការក្នុងរយៈពេល ៣–៥ ថ្ងៃធ្វើការ ត្រឡប់ទៅវិធីបង់ប្រាក់ដើមរបស់អ្នក។'],
        ['q' => 'ចុះបើអ្នកលក់ជំទាស់នឹងសំណើសំណងរបស់ខ្ញុំ?',
         'a' => 'ក្រុមការងារជំនួយរបស់យើងនឹងពិនិត្យភាគីទាំងពីរ ហើយធ្វើការសម្រេច។ ការសម្រេចចិត្តរបស់ ទីផ្សារ គឺជាចុងក្រោយក្នុងគ្រប់ករណីវិវាទ។'],
    ],
    'គណនីរបស់ខ្ញុំ' => [
        ['q' => 'តើខ្ញុំបង្កើតគណនីដោយរបៀបណា?',
         'a' => 'ចុច ចុះឈ្មោះ នៅលើទំព័រចូលគណនី ហើយបំពេញព័ត៌មានលម្អិតរបស់អ្នក។ គណនីអ្នកទិញ និងអ្នកលក់ដាច់ដោយឡែក — បង្កើតគណនីអ្នកទិញដើម្បីទិញ ឬដាក់ពាក្យជាអ្នកលក់ដើម្បីលក់។'],
        ['q' => 'តើខ្ញុំអាចមានទាំងគណនីអ្នកទិញ និងអ្នកលក់បានទេ?',
         'a' => 'បាទ/ចាស។ គណនីអ្នកលក់សម្រាប់តែការលក់ប៉ុណ្ណោះ។ ប្រសិនបើអ្នកចង់ទិញនៅលើ ទីផ្សារ ផងដែរ អ្នកត្រូវចុះឈ្មោះគណនីអ្នកទិញដាច់ដោយឡែកជាមួយអ៊ីមែលផ្សេង។'],
        ['q' => 'តើខ្ញុំធ្វើបច្ចុប្បន្នភាពអាសយដ្ឋានដឹកជញ្ជូនដោយរបៀបណា?',
         'a' => 'ចូលទៅការកំណត់គណនីរបស់អ្នក ហើយធ្វើបច្ចុប្បន្នភាពអាសយដ្ឋានក្រោមផ្ទាំង អាសយដ្ឋាន។ សូមប្រាកដថាកំណត់ pin នៅលើផែនទី ដើម្បីឱ្យថ្លៃដឹកជញ្ជូនត្រូវបានគណនាត្រឹមត្រូវ។'],
        ['q' => 'តើខ្ញុំប្តូរពាក្យសម្ងាត់ដោយរបៀបណា?',
         'a' => 'ចូលទៅ ការកំណត់ → ពាក្យសម្ងាត់ ហើយបញ្ចូលពាក្យសម្ងាត់បច្ចុប្បន្នរបស់អ្នក បន្តដោយពាក្យសម្ងាត់ថ្មី។'],
    ],
    'ការលក់នៅ ទីផ្សារ' => [
        ['q' => 'តើខ្ញុំក្លាយជាអ្នកលក់ដោយរបៀបណា?',
         'a' => 'ចុះឈ្មោះគណនីអ្នកលក់ បន្ទាប់មកដាក់ស្នើព័ត៌មានលម្អិតអាជីវកម្មរបស់អ្នកសម្រាប់ការត្រួតពិនិត្យ។ នៅពេលបានអនុម័តដោយក្រុមការងាររបស់យើង អ្នកអាចចាប់ផ្តើមដាក់លក់ផលិតផល។'],
        ['q' => 'តើការទូទាត់ដំណើរការដោយរបៀបណា?',
         'a' => 'បន្ទាប់ពីអ្នកទិញបញ្ជាក់ការទទួល (ឬបន្ទាប់ពីរយៈពេលបញ្ជាក់ស្វ័យប្រវត្តិកន្លងផុត) ការទូទាត់របស់អ្នកត្រូវបានគណនា និងដាក់ជាជួរ។ ក្រុមការងាររបស់យើងដំណើរការការទូទាត់តាមការផ្ទេរប្រាក់ធនាគារ ABA។'],
        ['q' => 'តើ ទីផ្សារ គិតថ្លៃអ្វីខ្លះ?',
         'a' => 'ទីផ្សារ គិតកម្រៃជើងសារក្នុងមួយការលក់ ដែលប្រែប្រួលតាមប្រភេទផលិតផល។ ថ្លៃនេះត្រូវបានបង្ហាញនៅពេលអ្នកដាក់លក់ផលិតផល ហើយការទូទាត់ប៉ាន់ស្មានរបស់អ្នកត្រូវបានបង្ហាញនៅលើទម្រង់ផលិតផល។'],
        ['q' => 'មានអ្វីកើតឡើងប្រសិនបើអ្នកទិញស្នើសុំសំណងលើផលិតផលរបស់ខ្ញុំ?',
         'a' => 'អ្នកនឹងត្រូវបានជូនដំណឹងអំពីសំណើសំណងណាមួយ។ អ្នកអាចទទួលយក ឬជំទាស់សំណើ។ ក្រុមការងារជំនួយរបស់យើងសម្រុះសម្រួល ប្រសិនបើមានការមិនចុះសម្រុង។ សំណងដែលបានអនុម័តត្រូវបានកាត់ពីការទូទាត់នាពេលអនាគត។'],
    ],
];
} else {
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
}
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
        <h1><?= $t['footer_help_center'] ?></h1>
        <p><?= $lang === 'km' ? 'រកចម្លើយចំពោះសំណួរទូទៅខាងក្រោម។' : 'Find answers to common questions below.' ?></p>
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
        <h2><?= $lang === 'km' ? 'នៅតែត្រូវការជំនួយ?' : 'Still need help?' ?></h2>
        <p><?= $lang === 'km' ? 'ប្រសិនបើអ្នករកមិនឃើញអ្វីដែលអ្នកកំពុងស្វែងរក ក្រុមការងារជំនួយរបស់យើងនៅទីនេះ។' : 'If you couldn\'t find what you were looking for, our support team is here.' ?></p>
        <a href="<?= $contactUrl ?>" class="help-contact-btn"><?= $t['messages_contact'] ?></a>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
