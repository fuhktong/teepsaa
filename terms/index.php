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
    <title>Terms of Service — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/terms/terms.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="legal-wrap">
<?php if ($lang === 'km'): ?>
        <h1>លក្ខខណ្ឌប្រើប្រាស់</h1>
        <p class="legal-effective">កាលបរិច្ឆេទចូលជាធរមាន៖ ១ មិថុនា ២០២៦</p>

        <p>លក្ខខណ្ឌប្រើប្រាស់ទាំងនេះ («លក្ខខណ្ឌ») គ្រប់គ្រងការប្រើប្រាស់វេទិកា ទីផ្សារ របស់អ្នក។ ដោយចុះឈ្មោះគណនី ឬប្រើប្រាស់សេវាកម្មរបស់យើង អ្នកយល់ព្រមគោរពតាមលក្ខខណ្ឌទាំងនេះ។ ប្រសិនបើអ្នកមិនយល់ព្រម សូមកុំប្រើប្រាស់វេទិកា។</p>

        <h2>១. ការទទួលយក</h2>
        <p>ដោយចូលប្រើ ឬប្រើប្រាស់ ទីផ្សារ អ្នកបញ្ជាក់ថាអ្នកបានអាន យល់ និងយល់ព្រមនឹងលក្ខខណ្ឌទាំងនេះ។ លក្ខខណ្ឌទាំងនេះបង្កើតជាកិច្ចព្រមព្រៀងចងភ្ជាប់រវាងអ្នក និង ទីផ្សារ។</p>

        <h2>២. លក្ខណៈសម្បត្តិ</h2>
        <p>អ្នកត្រូវមានអាយុគ្រប់ច្បាប់ក្នុងការចុះកិច្ចសន្យាក្រោមច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា ដើម្បីប្រើប្រាស់ ទីផ្សារ។ ដោយចុះឈ្មោះ អ្នកបញ្ជាក់ថាអ្នកបំពេញតម្រូវការនេះ។</p>

        <h2>៣. កាតព្វកិច្ចអ្នកទិញ</h2>
        <p>ក្នុងនាមជាអ្នកទិញ អ្នកយល់ព្រម៖</p>
        <ul>
            <li>ផ្តល់អាសយដ្ឋានដឹកជញ្ជូនត្រឹមត្រូវ ហើយអាចទទួលកម្មង់របស់អ្នក</li>
            <li>ដាក់ស្នើការបង់ប្រាក់ពិតប្រាកដតាមការផ្ទេរប្រាក់ធនាគារ ABA ភ្លាមៗបន្ទាប់ពីដាក់កម្មង់</li>
            <li>ទទួលយកការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) ដល់អ្នកបើកបរ Grab នៅពេលដឹកជញ្ជូន</li>
            <li>មិនដាក់ស្នើកម្មង់ក្លែងក្លាយ ឬបោកបញ្ឆោត</li>
        </ul>

        <h2>៤. កាតព្វកិច្ចអ្នកលក់</h2>
        <p>ក្នុងនាមជាអ្នកលក់ អ្នកយល់ព្រម៖</p>
        <ul>
            <li>ផ្តល់បញ្ជីផលិតផលត្រឹមត្រូវ ពេញលេញ និងទាន់សម័យ</li>
            <li>បំពេញកម្មង់ដែលបានបញ្ជាក់ភ្លាមៗ និងរក្សាកម្រិតស្តុកត្រឹមត្រូវ</li>
            <li>មិនដាក់លក់ទំនិញហាមឃាត់ ខុសច្បាប់ ឬក្លែងក្លាយ</li>
            <li>រក្សាកូដ QR ABA របស់អ្នកឱ្យទាន់សម័យសម្រាប់ការទូទាត់</li>
            <li>ឆ្លើយតបនឹងវិវាទអ្នកទិញ និងសំណើសំណងដោយសុច្ចរិត</li>
        </ul>

        <h2>៥. ខ្លឹមសារហាមឃាត់</h2>
        <p>ខាងក្រោមនេះត្រូវបានហាមឃាត់នៅលើ ទីផ្សារ៖</p>
        <ul>
            <li>ទំនិញ ឬសេវាកម្មខុសច្បាប់ក្រោមច្បាប់កម្ពុជា</li>
            <li>ផលិតផលក្លែងក្លាយ ឬរំលោភពាណិជ្ជសញ្ញា</li>
            <li>អាវុធ គ្រឿងញៀន ឬសារធាតុគ្រប់គ្រង</li>
            <li>ផលិតផលដែលមានគ្រោះថ្នាក់ដោយគ្មានការបញ្ជាក់សមស្រប</li>
            <li>ខ្លឹមសារណាមួយដែលបោកបញ្ឆោត ភ័ន្តច្រឡំ ឬបំភាន់</li>
        </ul>
        <p>ទីផ្សារ រក្សាសិទ្ធិក្នុងការដកបញ្ជីណាមួយ និងផ្អាកគណនីណាមួយដែលរំលោភការរឹតបន្តឹងទាំងនេះ។</p>

        <h2>៦. ការបង់ប្រាក់</h2>
        <p>ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA តែប៉ុណ្ណោះ។ អ្នកទិញត្រូវបំពេញការផ្ទេរប្រាក់បន្ទាប់ពីដាក់កម្មង់។ កម្មង់ត្រូវបានបញ្ជូនទៅអ្នកលក់តែនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងារ ទីផ្សារ។ ទីផ្សារ មិនរក្សាទុក ឬដំណើរការការបង់ប្រាក់តាមកាតទេ។</p>
        <p>ទីផ្សារ ដំណើរការជាវេទិកាទីផ្សារ មិនមែនជាអ្នកផ្តល់សេវាបង់ប្រាក់ ឬអន្តរការីបង់ប្រាក់ទេ។ ការបញ្ជាក់ការបង់ប្រាក់ដោយក្រុមការងារ ទីផ្សារ គឺជាជំហានផ្ទៀងផ្ទាត់រដ្ឋបាល ដើម្បីការពារទាំងអ្នកទិញ និងអ្នកលក់ពីការក្លែងបន្លំ។ ទីផ្សារ មិនកាន់កាប់ ប្រមូលផ្តុំ ឬផ្ទេរមូលនិធិជំនួសភាគីទីបីទេ។ ការទូទាត់ដល់អ្នកលក់ត្រូវបានចេញផ្ទាល់ដោយ ទីផ្សារ ទៅគណនី ABA ដែលបានចុះឈ្មោះរបស់អ្នកលក់ ជាការទូទាត់នៃប្រាក់ចំណូលដែលអ្នកលក់រកបានពីការលក់ដែលបានបញ្ចប់នៅលើវេទិកា។</p>

        <h2>៧. ការដឹកជញ្ជូន</h2>
        <p>ការដឹកជញ្ជូនត្រូវបានបំពេញតាម Grab។ ការបង់ប្រាក់សម្រាប់ការដឹកជញ្ជូនត្រូវបានធ្វើឡើងដោយផ្ទាល់ដោយអ្នកទិញទៅអ្នកបើកបរ Grab ជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD)។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះការពន្យារពេល ការដឹកជញ្ជូនបរាជ័យ ឬឥរិយាបថអ្នកបើកបរ ដែលត្រូវបានគ្រប់គ្រងដោយលក្ខខណ្ឌ និងគោលការណ៍ផ្ទាល់របស់ Grab។</p>

        <h2>៨. ថ្លៃវេទិកា</h2>
        <p>ទីផ្សារ គិតថ្លៃវេទិកាលើកម្មង់ដែលបានបញ្ចប់នីមួយៗ។ ថ្លៃនេះត្រូវបានគិតជាថ្នូរនឹងការចូលប្រើទីផ្សារ ទីផ្សារ រួមមានការស្វែងរកផលិតផល ការទាក់ទាញអ្នកទិញ ហេដ្ឋារចនាសម្ព័ន្ធទំនុកចិត្ត និងសុវត្ថិភាព ការសម្រុះសម្រួលវិវាទ និងសេវាផ្ទៀងផ្ទាត់ការបង់ប្រាក់។ ថ្លៃវេទិកាមិនមែនជាថ្លៃដំណើរការការបង់ប្រាក់ ឬថ្លៃអន្តរការីទេ។</p>
        <p>អត្រាដែលអនុវត្តត្រូវបានកំណត់ដោយប្រភេទផលិតផល និងការកែតម្រូវគណនីសកម្មណាមួយ ហើយត្រូវបានបង្ហាញដល់អ្នកលក់នៅពេលដាក់លក់ផលិតផល។ ដោយដាក់លក់ផលិតផល អ្នកលក់យល់ព្រមនឹងការកាត់ថ្លៃវេទិកាដែលបានបញ្ជាក់ពីការទូទាត់របស់ពួកគេ។</p>

        <h2>៩. សំណង និងវិវាទ</h2>
        <p>អ្នកទិញអាចស្នើសុំសំណងក្នុងរយៈពេល ២៤ ម៉ោងបន្ទាប់ពីការដឹកជញ្ជូន ប្រសិនបើទំនិញដែលទទួលបានខុស ខូច ឬខុសគ្នាយ៉ាងខ្លាំងពីបញ្ជី។ ទីផ្សារ នឹងសម្រុះសម្រួលវិវាទរវាងអ្នកទិញ និងអ្នកលក់។ ការសម្រេចចិត្តរបស់ ទីផ្សារ ក្នុងវិវាទទាំងអស់គឺជាចុងក្រោយ។ សំណងដែលបានអនុម័តត្រូវបានកាត់ពីការទូទាត់អ្នកលក់នាពេលអនាគត។ ទីផ្សារ មិនធានាសំណងក្នុងគ្រប់ស្ថានភាពទេ។</p>

        <h2>១០. ការបញ្ចប់គណនី</h2>
        <p>ទីផ្សារ រក្សាសិទ្ធិក្នុងការផ្អាក ឬហាមឃាត់ជាអចិន្ត្រៃយ៍នូវគណនីណាមួយដែលរំលោភលក្ខខណ្ឌទាំងនេះ ចូលរួមក្នុងសកម្មភាពក្លែងបន្លំ ឬបង្កហានិភ័យដល់អ្នកប្រើប្រាស់ផ្សេងទៀត ឬវេទិកា។ អ្នកលក់ដែលមានកាតព្វកិច្ចមិនទាន់រួច (កម្មង់មិនទាន់រួច ការទូទាត់មិនទាន់រួច) អាចមិនអាចលុបគណនីរបស់ពួកគេបានទេ រហូតដល់កាតព្វកិច្ចទាំងនោះត្រូវបានដោះស្រាយ។</p>

        <h2>១១. ដែនកំណត់នៃការទទួលខុសត្រូវ</h2>
        <p>ទីផ្សារ គឺជាវេទិកាទីផ្សារភ្ជាប់អ្នកទិញ និងអ្នកលក់។ យើងមិនមែនជាអ្នកលក់ផលិតផលណាមួយដែលដាក់លក់នៅលើវេទិកាទេ។ យើងមិនទទួលខុសត្រូវចំពោះគុណភាព សុវត្ថិភាព ភាពស្របច្បាប់ ឬភាពសមស្របនៃផលិតផលដែលលក់ដោយអ្នកលក់ លើសពីកាតព្វកិច្ចរបស់យើងក្នុងការសម្រុះសម្រួលវិវាទ។ ការទទួលខុសត្រូវសរុបរបស់យើងចំពោះការទាមទារណាមួយដែលកើតឡើងពីការប្រើប្រាស់វេទិកា ត្រូវបានកំណត់ត្រឹមតម្លៃនៃប្រតិបត្តិការពាក់ព័ន្ធ។</p>

        <h2>១២. ការផ្លាស់ប្តូរលក្ខខណ្ឌទាំងនេះ</h2>
        <p>ទីផ្សារ អាចធ្វើបច្ចុប្បន្នភាពលក្ខខណ្ឌទាំងនេះពីពេលមួយទៅពេលមួយ។ ការផ្លាស់ប្តូរនឹងត្រូវបានបង្ហោះនៅលើទំព័រនេះ ជាមួយកាលបរិច្ឆេទចូលជាធរមានថ្មី។ ការបន្តប្រើប្រាស់វេទិកាបន្ទាប់ពីការផ្លាស់ប្តូរ បង្ហាញពីការទទួលយកលក្ខខណ្ឌដែលបានធ្វើបច្ចុប្បន្នភាព។</p>

        <h2>១៣. ច្បាប់គ្រប់គ្រង</h2>
        <p>លក្ខខណ្ឌទាំងនេះត្រូវបានគ្រប់គ្រងដោយច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា។ វិវាទណាមួយដែលកើតឡើងពីលក្ខខណ្ឌទាំងនេះ ត្រូវស្ថិតនៅក្រោមយុត្តាធិការនៃតុលាការកម្ពុជា។</p>

        <h2>១៤. ទំនាក់ទំនង</h2>
        <p>សម្រាប់សំណួរផ្នែកច្បាប់ សូមទាក់ទងយើងតាមរយៈ <a href="/help/">មជ្ឈមណ្ឌលជំនួយ</a> របស់យើង។</p>

        <p class="legal-note">លក្ខខណ្ឌទាំងនេះគួរតែត្រូវបានពិនិត្យដោយទីប្រឹក្សាច្បាប់កម្ពុជាដែលមានលក្ខណៈសម្បត្តិ មុនពេលវេទិកាដាក់ឱ្យប្រើប្រាស់ជាសាធារណៈ។</p>
<?php else: ?>
        <h1>Terms of Service</h1>
        <p class="legal-effective">Effective date: 1 June 2026</p>

        <p>These Terms of Service ("Terms") govern your use of the teepsaa platform. By registering an account or using our services, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>

        <h2>1. Acceptance</h2>
        <p>By accessing or using teepsaa, you confirm that you have read, understood, and agreed to these Terms. These Terms form a binding agreement between you and teepsaa.</p>

        <h2>2. Eligibility</h2>
        <p>You must be of legal age to enter into contracts under the laws of the Kingdom of Cambodia to use teepsaa. By registering, you confirm that you meet this requirement.</p>

        <h2>3. Buyer obligations</h2>
        <p>As a buyer, you agree to:</p>
        <ul>
            <li>Provide an accurate delivery address and be available to receive your order</li>
            <li>Submit genuine payment via ABA bank transfer promptly after placing an order</li>
            <li>Accept cash-on-delivery (COD) payment to the Grab driver at the time of delivery</li>
            <li>Not submit false or fraudulent orders</li>
        </ul>

        <h2>4. Vendor obligations</h2>
        <p>As a vendor, you agree to:</p>
        <ul>
            <li>Provide accurate, complete, and up-to-date product listings</li>
            <li>Fulfil confirmed orders promptly and maintain accurate stock levels</li>
            <li>Not list prohibited, illegal, or counterfeit items</li>
            <li>Keep your ABA QR code current for payout processing</li>
            <li>Respond to buyer disputes and refund requests in good faith</li>
        </ul>

        <h2>5. Prohibited content</h2>
        <p>The following are prohibited on teepsaa:</p>
        <ul>
            <li>Illegal goods or services under Cambodian law</li>
            <li>Counterfeit or trademark-infringing products</li>
            <li>Weapons, narcotics, or controlled substances</li>
            <li>Products that are hazardous without appropriate disclosure</li>
            <li>Any content that is fraudulent, misleading, or deceptive</li>
        </ul>
        <p>teepsaa reserves the right to remove any listing and suspend any account that violates these restrictions.</p>

        <h2>6. Payments</h2>
        <p>teepsaa accepts payment via ABA Bank transfer only. Buyers must complete the transfer after placing an order. Orders are only released to vendors once payment is confirmed by the teepsaa team. teepsaa does not store or process card payments.</p>
        <p>teepsaa operates as a marketplace platform and not as a payment service provider or payment intermediary. Payment confirmation by the teepsaa team is an administrative verification step to protect both buyers and vendors from fraud. teepsaa does not hold, pool, or transfer funds on behalf of third parties. Vendor payouts are disbursed directly by teepsaa to the vendor's registered ABA account as settlement of the vendor's earned proceeds from completed sales on the platform.</p>

        <h2>7. Delivery</h2>
        <p>Deliveries are fulfilled via Grab. Payment for delivery is made directly by the buyer to the Grab driver as cash on delivery (COD). teepsaa is not responsible for delays, failed deliveries, or driver conduct, which are governed by Grab's own terms and policies.</p>

        <h2>8. Platform fees</h2>
        <p>teepsaa charges a platform fee on each completed order. This fee is charged in exchange for access to the teepsaa marketplace, including product discovery, buyer acquisition, trust and safety infrastructure, dispute mediation, and payment verification services. The platform fee is not a payment processing charge or intermediary fee.</p>
        <p>The applicable rate is determined by the product category and any active account adjustments, and is displayed to vendors when listing a product. By listing a product, vendors agree to the stated platform fee deduction from their payout.</p>

        <h2>9. Refunds and disputes</h2>
        <p>Buyers may request a refund within 24 hours of delivery if the item received is wrong, damaged, or significantly differs from the listing. teepsaa will mediate disputes between buyers and vendors. teepsaa's decision in all disputes is final. Approved refunds are deducted from future vendor payouts. teepsaa does not guarantee refunds in all circumstances.</p>

        <h2>10. Account termination</h2>
        <p>teepsaa reserves the right to suspend or permanently ban any account that violates these Terms, engages in fraudulent activity, or poses a risk to other users or the platform. Vendors with outstanding obligations (open orders, pending payouts) may not be able to delete their accounts until those obligations are resolved.</p>

        <h2>11. Limitation of liability</h2>
        <p>teepsaa is a marketplace platform connecting buyers and vendors. We are not the seller of any product listed on the platform. We are not liable for the quality, safety, legality, or fitness of products sold by vendors, beyond our obligations to mediate disputes. Our total liability for any claim arising from use of the platform is limited to the value of the relevant transaction.</p>

        <h2>12. Changes to these Terms</h2>
        <p>teepsaa may update these Terms from time to time. Changes will be posted on this page with an updated effective date. Continued use of the platform after changes constitutes acceptance of the updated Terms.</p>

        <h2>13. Governing law</h2>
        <p>These Terms are governed by the laws of the Kingdom of Cambodia. Any disputes arising from these Terms shall be subject to the jurisdiction of the courts of Cambodia.</p>

        <h2>14. Contact</h2>
        <p>For legal enquiries, contact us via our <a href="/help/">Help Center</a>.</p>

        <p class="legal-note">These Terms should be reviewed by a qualified Cambodian legal advisor before the platform launches publicly.</p>
<?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
