<?php session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/privacy/privacy.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="legal-wrap">
<?php if ($lang === 'km'): ?>
        <h1>គោលការណ៍ភាពឯកជន</h1>
        <p class="legal-effective">កាលបរិច្ឆេទចូលជាធរមាន៖ ១ មិថុនា ២០២៦</p>

        <p>គោលការណ៍ភាពឯកជននេះពន្យល់ពីរបៀបដែល ទីផ្សារ («យើង») ប្រមូល ប្រើប្រាស់ និងរក្សាទុកព័ត៌មានផ្ទាល់ខ្លួន នៅពេលអ្នកប្រើប្រាស់វេទិការបស់យើង។ ដោយបង្កើតគណនី ឬដាក់កម្មង់ អ្នកយល់ព្រមនឹងការអនុវត្តដែលបានពិពណ៌នានៅទីនេះ។</p>

        <h2>១. អ្វីដែលយើងប្រមូល</h2>
        <p>នៅពេលអ្នកចុះឈ្មោះ ឬប្រើប្រាស់ ទីផ្សារ យើងអាចប្រមូលព័ត៌មានដូចខាងក្រោម៖</p>
        <ul>
            <li><strong>ព័ត៌មានគណនី</strong> — ឈ្មោះ អាសយដ្ឋានអ៊ីមែល និងពាក្យសម្ងាត់ (រក្សាទុកជាតម្លៃ hash មិនដែលជាអក្សរធម្មតាទេ)</li>
            <li><strong>ព័ត៌មានទំនាក់ទំនង និងការដឹកជញ្ជូន</strong> — លេខទូរស័ព្ទ អាសយដ្ឋានដឹកជញ្ជូន កំណត់ចំណាំអាសយដ្ឋាន និងចំណុចផែនទី (រយៈទទឹង/រយៈបណ្តោយ) សម្រាប់ការប៉ាន់ស្មានការដឹកជញ្ជូន</li>
            <li><strong>រូបថតប្រវត្តិរូប</strong> — រូបភាព avatar ស្រេចចិត្តដែលអ្នកផ្ទុកឡើង</li>
            <li><strong>ប្រវត្តិកម្មង់</strong> — ទំនិញដែលបានកម្មង់ តម្លៃដែលបានបង់ ស្ថានភាពកម្មង់ និងពេលវេលា</li>
            <li><strong>កំណត់ត្រាការបង់ប្រាក់</strong> — ភស្តុតាងនៃការផ្ទេរប្រាក់ធនាគារ ABA ដែលអ្នកទិញដាក់ស្នើ។ យើងមិនរក្សាទុកលេខកាត ឬព័ត៌មានសម្ងាត់ធនាគារទេ។</li>
            <li><strong>ព័ត៌មានអាជីវកម្ម</strong> — សម្រាប់អ្នកលក់៖ ឈ្មោះអាជីវកម្ម ទីតាំង កូដ QR ABA សម្រាប់ការទូទាត់ និងបញ្ជីផលិតផល</li>
        </ul>

        <h2>២. ហេតុអ្វីយើងប្រមូល</h2>
        <ul>
            <li>ដើម្បីបង្កើត និងគ្រប់គ្រងគណនីរបស់អ្នក</li>
            <li>ដើម្បីដំណើរការកម្មង់ និងសម្របសម្រួលការដឹកជញ្ជូនតាម Grab</li>
            <li>ដើម្បីគណនាថ្លៃដឹកជញ្ជូនផ្អែកលើចម្ងាយ</li>
            <li>ដើម្បីដំណើរការការទូទាត់ដល់អ្នកលក់តាមការផ្ទេរប្រាក់ធនាគារ ABA</li>
            <li>ដើម្បីឆ្លើយតបនឹងសំណើជំនួយ</li>
            <li>ដើម្បីរកឃើញ និងការពារសកម្មភាពក្លែងបន្លំ</li>
        </ul>

        <h2>៣. របៀបរក្សាទុក</h2>
        <p>ទិន្នន័យរបស់អ្នកត្រូវបានរក្សាទុកនៅលើម៉ាស៊ីនមេដែលមានសុវត្ថិភាព។ ពាក្យសម្ងាត់ត្រូវបាន hash ដោយប្រើ algorithm ស្តង់ដារឧស្សាហកម្ម ហើយមិនដែលរក្សាទុក ឬបញ្ជូនជាអក្សរធម្មតាទេ។ ឯកសារដែលបានផ្ទុកឡើងត្រូវបានរក្សាទុកនៅក្នុងថតដែលមានការរឹតបន្តឹង ដោយបិទការប្រតិបត្តិ script។</p>

        <h2>៤. ភាគីទីបី</h2>
        <p>យើងចែករំលែកទិន្នន័យជាមួយភាគីទីបីតែនៅពេលចាំបាច់ដើម្បីដំណើរការវេទិកា៖</p>
        <ul>
            <li><strong>Mapbox</strong> — ប្រើសម្រាប់បង្ហាញផែនទី និងគណនាចម្ងាយ។ ចំណុចទីតាំងរបស់អ្នកត្រូវបានផ្ញើទៅ Mapbox សម្រាប់ការប៉ាន់ស្មានថ្លៃដឹកជញ្ជូន។</li>
            <li><strong>Grab</strong> — ប្រើសម្រាប់ការដឹកជញ្ជូនកម្មង់។ អាសយដ្ឋានដឹកជញ្ជូនរបស់អ្នកត្រូវបានចែករំលែកជាមួយអ្នកបើកបរ Grab ដែលបានចាត់តាំង។ គោលការណ៍ភាពឯកជនរបស់ Grab គ្រប់គ្រងការដោះស្រាយទិន្នន័យនេះ។</li>
            <li><strong>ធនាគារ ABA</strong> — ការបង់ប្រាក់ត្រូវបានធ្វើឡើងដោយផ្ទាល់រវាងអ្នកទិញ និង ទីផ្សារ តាមការផ្ទេរប្រាក់ធនាគារ ABA។ យើងមិនចែករំលែកព័ត៌មានធនាគាររបស់អ្នកជាមួយភាគីទីបីទេ។</li>
        </ul>
        <p>យើងមិនលក់ទិន្នន័យផ្ទាល់ខ្លួនរបស់អ្នកទៅឱ្យអ្នកផ្សាយពាណិជ្ជកម្ម ឬឈ្មួញកណ្តាលទិន្នន័យទេ។</p>

        <h2>៥. ខូគី</h2>
        <p>ទីផ្សារ ប្រើខូគី session តែមួយដើម្បីរក្សាឱ្យអ្នកនៅក្នុងគណនីក្នុងអំឡុងពេលទស្សនា។ ខូគីនេះមិនមានព័ត៌មានផ្ទាល់ខ្លួនទេ ហើយត្រូវបានលុបនៅពេល session បញ្ចប់។ យើងមិនប្រើខូគីតាមដាន ផ្សាយពាណិជ្ជកម្ម ឬ analytics ទេ។</p>

        <h2>៦. ការរក្សាទុកទិន្នន័យ</h2>
        <p>ទិន្នន័យរបស់អ្នកត្រូវបានរក្សាទុករយៈពេលដែលគណនីរបស់អ្នកនៅសកម្ម។ ប្រសិនបើអ្នកលុបគណនី ព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នកត្រូវបានដកចេញពីប្រព័ន្ធរបស់យើង។ កំណត់ត្រាកម្មង់អាចត្រូវបានរក្សាទុករយៈពេលខ្លីសម្រាប់គោលបំណងគណនេយ្យ និងការដោះស្រាយវិវាទ មុននឹងត្រូវលុប។</p>

        <h2>៧. សិទ្ធិរបស់អ្នក</h2>
        <p>អ្នកមានសិទ្ធិ៖</p>
        <ul>
            <li>ចូលមើលទិន្នន័យផ្ទាល់ខ្លួនដែលយើងកាន់កាប់អំពីអ្នក</li>
            <li>ស្នើសុំកែតម្រូវទិន្នន័យមិនត្រឹមត្រូវ</li>
            <li>ស្នើសុំលុបគណនី និងទិន្នន័យពាក់ព័ន្ធរបស់អ្នក</li>
        </ul>
        <p>ដើម្បីអនុវត្តសិទ្ធិណាមួយ សូមទាក់ទងយើងតាមអាសយដ្ឋានខាងក្រោម។</p>

        <h2>៨. ការផ្លាស់ប្តូរគោលការណ៍នេះ</h2>
        <p>យើងអាចធ្វើបច្ចុប្បន្នភាពគោលការណ៍ភាពឯកជននេះពីពេលមួយទៅពេលមួយ។ ការផ្លាស់ប្តូរនឹងត្រូវបានបង្ហោះនៅលើទំព័រនេះ ជាមួយកាលបរិច្ឆេទចូលជាធរមានថ្មី។ ការបន្តប្រើប្រាស់វេទិកាបន្ទាប់ពីការផ្លាស់ប្តូរ បង្ហាញពីការទទួលយកគោលការណ៍ដែលបានធ្វើបច្ចុប្បន្នភាព។</p>

        <h2>៩. ទំនាក់ទំនង</h2>
        <p>សម្រាប់សំណួរអំពីភាពឯកជន ឬសំណើទិន្នន័យ សូមទាក់ទងយើងតាមរយៈ <a href="/help/">មជ្ឈមណ្ឌលជំនួយ</a> របស់យើង។</p>

        <p class="legal-note">គោលការណ៍នេះគួរតែត្រូវបានពិនិត្យដោយទីប្រឹក្សាច្បាប់ដែលមានលក្ខណៈសម្បត្តិ មុនពេលវេទិកាដាក់ឱ្យប្រើប្រាស់ជាសាធារណៈ។</p>
<?php else: ?>
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
<?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
