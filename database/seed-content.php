<?php
// One-time seed for content_pages + faq_items — run once after migration-content-pages.sql.
// Uses PDO prepared statements (not raw SQL) so none of the prose below needs manual escaping.
require __DIR__ . '/../config/db.php';

$existing = (int) $pdo->query('SELECT COUNT(*) FROM content_pages')->fetchColumn();
if ($existing > 0) {
    echo "content_pages already has rows — skipping seed.\n";
    exit;
}

$privacyEn = <<<'MD'
*Effective date: 1 June 2026*

This Privacy Policy explains how teepsaa ("we", "us", "our") collects, uses, and stores personal information when you use our platform. By creating an account or placing an order, you agree to the practices described here.

## 1. What we collect
When you register or use teepsaa, we may collect the following:

- **Account information** — name, email address, and password (stored as a hashed value, never in plain text)
- **Contact and delivery details** — phone number, delivery address, address notes, and a map pin (latitude/longitude) for delivery estimates
- **Profile photo** — an optional avatar image you upload
- **Order history** — items ordered, prices paid, order status, and timestamps
- **Payment records** — evidence of ABA bank transfers submitted by buyers. We do not store card numbers or banking credentials.
- **Business information** — for vendors: business name, location, ABA QR code for payouts, and product listings

## 2. Why we collect it
- To create and manage your account
- To process orders and coordinate delivery via Grab
- To calculate delivery fees based on distance
- To process vendor payouts via ABA bank transfer
- To respond to support requests
- To detect and prevent fraudulent activity

## 3. How it is stored
Your data is stored on a secured server. Passwords are hashed using industry-standard algorithms and are never stored or transmitted in plain text. Uploaded files are stored in a restricted directory with script execution disabled.

## 4. Third parties
We share data with third parties only where necessary to operate the platform:

- **Mapbox** — used to render maps and calculate distances. Your location pin is sent to Mapbox for delivery fee estimation.
- **Grab** — used for order delivery. Your delivery address is shared with the assigned Grab driver. Grab's own privacy policy governs their handling of this data.
- **ABA Bank** — payments are made directly between buyers and teepsaa via ABA bank transfer. We do not share your banking details with third parties.

We do not sell your personal data to advertisers or data brokers.

## 5. Cookies
teepsaa uses a single session cookie to keep you logged in during your visit. This cookie contains no personal information and is deleted when your session ends. We do not use tracking, advertising, or analytics cookies.

## 6. Data retention
Your data is retained for as long as your account is active. If you delete your account, your personal information is removed from our systems. Order records may be retained for a short period for accounting and dispute resolution purposes before being deleted.

## 7. Your rights
You have the right to:

- Access the personal data we hold about you
- Request correction of inaccurate data
- Request deletion of your account and associated data

To exercise any of these rights, contact us at the address below.

## 8. Changes to this policy
We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated effective date. Continued use of the platform after changes constitutes acceptance of the updated policy.

## 9. Contact
For privacy questions or data requests, contact us via our [Help Center](/help/).

*This policy should be reviewed by a qualified legal advisor before the platform launches publicly.*
MD;

$privacyKm = <<<'MD'
*កាលបរិច្ឆេទចូលជាធរមាន៖ ១ មិថុនា ២០២៦*

គោលការណ៍ភាពឯកជននេះពន្យល់ពីរបៀបដែល ទីផ្សារ («យើង») ប្រមូល ប្រើប្រាស់ និងរក្សាទុកព័ត៌មានផ្ទាល់ខ្លួន នៅពេលអ្នកប្រើប្រាស់វេទិការបស់យើង។ ដោយបង្កើតគណនី ឬដាក់កម្មង់ អ្នកយល់ព្រមនឹងការអនុវត្តដែលបានពិពណ៌នានៅទីនេះ។

## ១. អ្វីដែលយើងប្រមូល
នៅពេលអ្នកចុះឈ្មោះ ឬប្រើប្រាស់ ទីផ្សារ យើងអាចប្រមូលព័ត៌មានដូចខាងក្រោម៖

- **ព័ត៌មានគណនី** — ឈ្មោះ អាសយដ្ឋានអ៊ីមែល និងពាក្យសម្ងាត់ (រក្សាទុកជាតម្លៃ hash មិនដែលជាអក្សរធម្មតាទេ)
- **ព័ត៌មានទំនាក់ទំនង និងការដឹកជញ្ជូន** — លេខទូរស័ព្ទ អាសយដ្ឋានដឹកជញ្ជូន កំណត់ចំណាំអាសយដ្ឋាន និងចំណុចផែនទី (រយៈទទឹង/រយៈបណ្តោយ) សម្រាប់ការប៉ាន់ស្មានការដឹកជញ្ជូន
- **រូបថតប្រវត្តិរូប** — រូបភាព avatar ស្រេចចិត្តដែលអ្នកផ្ទុកឡើង
- **ប្រវត្តិកម្មង់** — ទំនិញដែលបានកម្មង់ តម្លៃដែលបានបង់ ស្ថានភាពកម្មង់ និងពេលវេលា
- **កំណត់ត្រាការបង់ប្រាក់** — ភស្តុតាងនៃការផ្ទេរប្រាក់ធនាគារ ABA ដែលអ្នកទិញដាក់ស្នើ។ យើងមិនរក្សាទុកលេខកាត ឬព័ត៌មានសម្ងាត់ធនាគារទេ។
- **ព័ត៌មានអាជីវកម្ម** — សម្រាប់អ្នកលក់៖ ឈ្មោះអាជីវកម្ម ទីតាំង កូដ QR ABA សម្រាប់ការទូទាត់ និងបញ្ជីផលិតផល

## ២. ហេតុអ្វីយើងប្រមូល
- ដើម្បីបង្កើត និងគ្រប់គ្រងគណនីរបស់អ្នក
- ដើម្បីដំណើរការកម្មង់ និងសម្របសម្រួលការដឹកជញ្ជូនតាម Grab
- ដើម្បីគណនាថ្លៃដឹកជញ្ជូនផ្អែកលើចម្ងាយ
- ដើម្បីដំណើរការការទូទាត់ដល់អ្នកលក់តាមការផ្ទេរប្រាក់ធនាគារ ABA
- ដើម្បីឆ្លើយតបនឹងសំណើជំនួយ
- ដើម្បីរកឃើញ និងការពារសកម្មភាពក្លែងបន្លំ

## ៣. របៀបរក្សាទុក
ទិន្នន័យរបស់អ្នកត្រូវបានរក្សាទុកនៅលើម៉ាស៊ីនមេដែលមានសុវត្ថិភាព។ ពាក្យសម្ងាត់ត្រូវបាន hash ដោយប្រើ algorithm ស្តង់ដារឧស្សាហកម្ម ហើយមិនដែលរក្សាទុក ឬបញ្ជូនជាអក្សរធម្មតាទេ។ ឯកសារដែលបានផ្ទុកឡើងត្រូវបានរក្សាទុកនៅក្នុងថតដែលមានការរឹតបន្តឹង ដោយបិទការប្រតិបត្តិ script។

## ៤. ភាគីទីបី
យើងចែករំលែកទិន្នន័យជាមួយភាគីទីបីតែនៅពេលចាំបាច់ដើម្បីដំណើរការវេទិកា៖

- **Mapbox** — ប្រើសម្រាប់បង្ហាញផែនទី និងគណនាចម្ងាយ។ ចំណុចទីតាំងរបស់អ្នកត្រូវបានផ្ញើទៅ Mapbox សម្រាប់ការប៉ាន់ស្មានថ្លៃដឹកជញ្ជូន។
- **Grab** — ប្រើសម្រាប់ការដឹកជញ្ជូនកម្មង់។ អាសយដ្ឋានដឹកជញ្ជូនរបស់អ្នកត្រូវបានចែករំលែកជាមួយអ្នកបើកបរ Grab ដែលបានចាត់តាំង។ គោលការណ៍ភាពឯកជនរបស់ Grab គ្រប់គ្រងការដោះស្រាយទិន្នន័យនេះ។
- **ធនាគារ ABA** — ការបង់ប្រាក់ត្រូវបានធ្វើឡើងដោយផ្ទាល់រវាងអ្នកទិញ និង ទីផ្សារ តាមការផ្ទេរប្រាក់ធនាគារ ABA។ យើងមិនចែករំលែកព័ត៌មានធនាគាររបស់អ្នកជាមួយភាគីទីបីទេ។

យើងមិនលក់ទិន្នន័យផ្ទាល់ខ្លួនរបស់អ្នកទៅឱ្យអ្នកផ្សាយពាណិជ្ជកម្ម ឬឈ្មួញកណ្តាលទិន្នន័យទេ។

## ៥. ខូគី
ទីផ្សារ ប្រើខូគី session តែមួយដើម្បីរក្សាឱ្យអ្នកនៅក្នុងគណនីក្នុងអំឡុងពេលទស្សនា។ ខូគីនេះមិនមានព័ត៌មានផ្ទាល់ខ្លួនទេ ហើយត្រូវបានលុបនៅពេល session បញ្ចប់។ យើងមិនប្រើខូគីតាមដាន ផ្សាយពាណិជ្ជកម្ម ឬ analytics ទេ។

## ៦. ការរក្សាទុកទិន្នន័យ
ទិន្នន័យរបស់អ្នកត្រូវបានរក្សាទុករយៈពេលដែលគណនីរបស់អ្នកនៅសកម្ម។ ប្រសិនបើអ្នកលុបគណនី ព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នកត្រូវបានដកចេញពីប្រព័ន្ធរបស់យើង។ កំណត់ត្រាកម្មង់អាចត្រូវបានរក្សាទុករយៈពេលខ្លីសម្រាប់គោលបំណងគណនេយ្យ និងការដោះស្រាយវិវាទ មុននឹងត្រូវលុប។

## ៧. សិទ្ធិរបស់អ្នក
អ្នកមានសិទ្ធិ៖

- ចូលមើលទិន្នន័យផ្ទាល់ខ្លួនដែលយើងកាន់កាប់អំពីអ្នក
- ស្នើសុំកែតម្រូវទិន្នន័យមិនត្រឹមត្រូវ
- ស្នើសុំលុបគណនី និងទិន្នន័យពាក់ព័ន្ធរបស់អ្នក

ដើម្បីអនុវត្តសិទ្ធិណាមួយ សូមទាក់ទងយើងតាមអាសយដ្ឋានខាងក្រោម។

## ៨. ការផ្លាស់ប្តូរគោលការណ៍នេះ
យើងអាចធ្វើបច្ចុប្បន្នភាពគោលការណ៍ភាពឯកជននេះពីពេលមួយទៅពេលមួយ។ ការផ្លាស់ប្តូរនឹងត្រូវបានបង្ហោះនៅលើទំព័រនេះ ជាមួយកាលបរិច្ឆេទចូលជាធរមានថ្មី។ ការបន្តប្រើប្រាស់វេទិកាបន្ទាប់ពីការផ្លាស់ប្តូរ បង្ហាញពីការទទួលយកគោលការណ៍ដែលបានធ្វើបច្ចុប្បន្នភាព។

## ៩. ទំនាក់ទំនង
សម្រាប់សំណួរអំពីភាពឯកជន ឬសំណើទិន្នន័យ សូមទាក់ទងយើងតាមរយៈ [មជ្ឈមណ្ឌលជំនួយ](/help/) របស់យើង។

*គោលការណ៍នេះគួរតែត្រូវបានពិនិត្យដោយទីប្រឹក្សាច្បាប់ដែលមានលក្ខណៈសម្បត្តិ មុនពេលវេទិកាដាក់ឱ្យប្រើប្រាស់ជាសាធារណៈ។*
MD;

$termsEn = <<<'MD'
*Effective date: 1 June 2026*

These Terms of Service ("Terms") govern your use of the teepsaa platform. By registering an account or using our services, you agree to be bound by these Terms. If you do not agree, do not use the platform.

## 1. Acceptance
By accessing or using teepsaa, you confirm that you have read, understood, and agreed to these Terms. These Terms form a binding agreement between you and teepsaa.

## 2. Eligibility
You must be of legal age to enter into contracts under the laws of the Kingdom of Cambodia to use teepsaa. By registering, you confirm that you meet this requirement.

## 3. Buyer obligations
As a buyer, you agree to:

- Provide an accurate delivery address and be available to receive your order
- Submit genuine payment via ABA bank transfer promptly after placing an order
- Accept cash-on-delivery (COD) payment to the Grab driver at the time of delivery
- Not submit false or fraudulent orders

## 4. Vendor obligations
As a vendor, you agree to:

- Provide accurate, complete, and up-to-date product listings
- Fulfil confirmed orders promptly and maintain accurate stock levels
- Not list prohibited, illegal, or counterfeit items
- Keep your ABA QR code current for payout processing
- Respond to buyer disputes and refund requests in good faith

## 5. Prohibited content
The following are prohibited on teepsaa:

- Illegal goods or services under Cambodian law
- Counterfeit or trademark-infringing products
- Weapons, narcotics, or controlled substances
- Products that are hazardous without appropriate disclosure
- Any content that is fraudulent, misleading, or deceptive

teepsaa reserves the right to remove any listing and suspend any account that violates these restrictions.

## 6. Payments
teepsaa accepts payment via ABA Bank transfer only. Buyers must complete the transfer after placing an order. Orders are only released to vendors once payment is confirmed by the teepsaa team. teepsaa does not store or process card payments.

teepsaa operates as a marketplace platform and not as a payment service provider or payment intermediary. Payment confirmation by the teepsaa team is an administrative verification step to protect both buyers and vendors from fraud. teepsaa does not hold, pool, or transfer funds on behalf of third parties. Vendor payouts are disbursed directly by teepsaa to the vendor's registered ABA account as settlement of the vendor's earned proceeds from completed sales on the platform.

## 7. Delivery
Deliveries are fulfilled via Grab. Payment for delivery is made directly by the buyer to the Grab driver as cash on delivery (COD). teepsaa is not responsible for delays, failed deliveries, or driver conduct, which are governed by Grab's own terms and policies.

## 8. Platform fees
teepsaa charges a platform fee on each completed order. This fee is charged in exchange for access to the teepsaa marketplace, including product discovery, buyer acquisition, trust and safety infrastructure, dispute mediation, and payment verification services. The platform fee is not a payment processing charge or intermediary fee.

The applicable rate is determined by the product category and any active account adjustments, and is displayed to vendors when listing a product. By listing a product, vendors agree to the stated platform fee deduction from their payout.

## 9. Refunds and disputes
Buyers may request a refund within 24 hours of delivery if the item received is wrong, damaged, or significantly differs from the listing. teepsaa will mediate disputes between buyers and vendors. teepsaa's decision in all disputes is final. Approved refunds are deducted from future vendor payouts. teepsaa does not guarantee refunds in all circumstances.

## 10. Account termination
teepsaa reserves the right to suspend or permanently ban any account that violates these Terms, engages in fraudulent activity, or poses a risk to other users or the platform. Vendors with outstanding obligations (open orders, pending payouts) may not be able to delete their accounts until those obligations are resolved.

## 11. Limitation of liability
teepsaa is a marketplace platform connecting buyers and vendors. We are not the seller of any product listed on the platform. We are not liable for the quality, safety, legality, or fitness of products sold by vendors, beyond our obligations to mediate disputes. Our total liability for any claim arising from use of the platform is limited to the value of the relevant transaction.

## 12. Changes to these Terms
teepsaa may update these Terms from time to time. Changes will be posted on this page with an updated effective date. Continued use of the platform after changes constitutes acceptance of the updated Terms.

## 13. Governing law
These Terms are governed by the laws of the Kingdom of Cambodia. Any disputes arising from these Terms shall be subject to the jurisdiction of the courts of Cambodia.

## 14. Contact
For legal enquiries, contact us via our [Help Center](/help/).

*These Terms should be reviewed by a qualified Cambodian legal advisor before the platform launches publicly.*
MD;

$termsKm = <<<'MD'
*កាលបរិច្ឆេទចូលជាធរមាន៖ ១ មិថុនា ២០២៦*

លក្ខខណ្ឌប្រើប្រាស់ទាំងនេះ («លក្ខខណ្ឌ») គ្រប់គ្រងការប្រើប្រាស់វេទិកា ទីផ្សារ របស់អ្នក។ ដោយចុះឈ្មោះគណនី ឬប្រើប្រាស់សេវាកម្មរបស់យើង អ្នកយល់ព្រមគោរពតាមលក្ខខណ្ឌទាំងនេះ។ ប្រសិនបើអ្នកមិនយល់ព្រម សូមកុំប្រើប្រាស់វេទិកា។

## ១. ការទទួលយក
ដោយចូលប្រើ ឬប្រើប្រាស់ ទីផ្សារ អ្នកបញ្ជាក់ថាអ្នកបានអាន យល់ និងយល់ព្រមនឹងលក្ខខណ្ឌទាំងនេះ។ លក្ខខណ្ឌទាំងនេះបង្កើតជាកិច្ចព្រមព្រៀងចងភ្ជាប់រវាងអ្នក និង ទីផ្សារ។

## ២. លក្ខណៈសម្បត្តិ
អ្នកត្រូវមានអាយុគ្រប់ច្បាប់ក្នុងការចុះកិច្ចសន្យាក្រោមច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា ដើម្បីប្រើប្រាស់ ទីផ្សារ។ ដោយចុះឈ្មោះ អ្នកបញ្ជាក់ថាអ្នកបំពេញតម្រូវការនេះ។

## ៣. កាតព្វកិច្ចអ្នកទិញ
ក្នុងនាមជាអ្នកទិញ អ្នកយល់ព្រម៖

- ផ្តល់អាសយដ្ឋានដឹកជញ្ជូនត្រឹមត្រូវ ហើយអាចទទួលកម្មង់របស់អ្នក
- ដាក់ស្នើការបង់ប្រាក់ពិតប្រាកដតាមការផ្ទេរប្រាក់ធនាគារ ABA ភ្លាមៗបន្ទាប់ពីដាក់កម្មង់
- ទទួលយកការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) ដល់អ្នកបើកបរ Grab នៅពេលដឹកជញ្ជូន
- មិនដាក់ស្នើកម្មង់ក្លែងក្លាយ ឬបោកបញ្ឆោត

## ៤. កាតព្វកិច្ចអ្នកលក់
ក្នុងនាមជាអ្នកលក់ អ្នកយល់ព្រម៖

- ផ្តល់បញ្ជីផលិតផលត្រឹមត្រូវ ពេញលេញ និងទាន់សម័យ
- បំពេញកម្មង់ដែលបានបញ្ជាក់ភ្លាមៗ និងរក្សាកម្រិតស្តុកត្រឹមត្រូវ
- មិនដាក់លក់ទំនិញហាមឃាត់ ខុសច្បាប់ ឬក្លែងក្លាយ
- រក្សាកូដ QR ABA របស់អ្នកឱ្យទាន់សម័យសម្រាប់ការទូទាត់
- ឆ្លើយតបនឹងវិវាទអ្នកទិញ និងសំណើសំណងដោយសុច្ចរិត

## ៥. ខ្លឹមសារហាមឃាត់
ខាងក្រោមនេះត្រូវបានហាមឃាត់នៅលើ ទីផ្សារ៖

- ទំនិញ ឬសេវាកម្មខុសច្បាប់ក្រោមច្បាប់កម្ពុជា
- ផលិតផលក្លែងក្លាយ ឬរំលោភពាណិជ្ជសញ្ញា
- អាវុធ គ្រឿងញៀន ឬសារធាតុគ្រប់គ្រង
- ផលិតផលដែលមានគ្រោះថ្នាក់ដោយគ្មានការបញ្ជាក់សមស្រប
- ខ្លឹមសារណាមួយដែលបោកបញ្ឆោត ភ័ន្តច្រឡំ ឬបំភាន់

ទីផ្សារ រក្សាសិទ្ធិក្នុងការដកបញ្ជីណាមួយ និងផ្អាកគណនីណាមួយដែលរំលោភការរឹតបន្តឹងទាំងនេះ។

## ៦. ការបង់ប្រាក់
ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA តែប៉ុណ្ណោះ។ អ្នកទិញត្រូវបំពេញការផ្ទេរប្រាក់បន្ទាប់ពីដាក់កម្មង់។ កម្មង់ត្រូវបានបញ្ជូនទៅអ្នកលក់តែនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងារ ទីផ្សារ។ ទីផ្សារ មិនរក្សាទុក ឬដំណើរការការបង់ប្រាក់តាមកាតទេ។

ទីផ្សារ ដំណើរការជាវេទិកាទីផ្សារ មិនមែនជាអ្នកផ្តល់សេវាបង់ប្រាក់ ឬអន្តរការីបង់ប្រាក់ទេ។ ការបញ្ជាក់ការបង់ប្រាក់ដោយក្រុមការងារ ទីផ្សារ គឺជាជំហានផ្ទៀងផ្ទាត់រដ្ឋបាល ដើម្បីការពារទាំងអ្នកទិញ និងអ្នកលក់ពីការក្លែងបន្លំ។ ទីផ្សារ មិនកាន់កាប់ ប្រមូលផ្តុំ ឬផ្ទេរមូលនិធិជំនួសភាគីទីបីទេ។ ការទូទាត់ដល់អ្នកលក់ត្រូវបានចេញផ្ទាល់ដោយ ទីផ្សារ ទៅគណនី ABA ដែលបានចុះឈ្មោះរបស់អ្នកលក់ ជាការទូទាត់នៃប្រាក់ចំណូលដែលអ្នកលក់រកបានពីការលក់ដែលបានបញ្ចប់នៅលើវេទិកា។

## ៧. ការដឹកជញ្ជូន
ការដឹកជញ្ជូនត្រូវបានបំពេញតាម Grab។ ការបង់ប្រាក់សម្រាប់ការដឹកជញ្ជូនត្រូវបានធ្វើឡើងដោយផ្ទាល់ដោយអ្នកទិញទៅអ្នកបើកបរ Grab ជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD)។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះការពន្យារពេល ការដឹកជញ្ជូនបរាជ័យ ឬឥរិយាបថអ្នកបើកបរ ដែលត្រូវបានគ្រប់គ្រងដោយលក្ខខណ្ឌ និងគោលការណ៍ផ្ទាល់របស់ Grab។

## ៨. ថ្លៃវេទិកា
ទីផ្សារ គិតថ្លៃវេទិកាលើកម្មង់ដែលបានបញ្ចប់នីមួយៗ។ ថ្លៃនេះត្រូវបានគិតជាថ្នូរនឹងការចូលប្រើទីផ្សារ ទីផ្សារ រួមមានការស្វែងរកផលិតផល ការទាក់ទាញអ្នកទិញ ហេដ្ឋារចនាសម្ព័ន្ធទំនុកចិត្ត និងសុវត្ថិភាព ការសម្រុះសម្រួលវិវាទ និងសេវាផ្ទៀងផ្ទាត់ការបង់ប្រាក់។ ថ្លៃវេទិកាមិនមែនជាថ្លៃដំណើរការការបង់ប្រាក់ ឬថ្លៃអន្តរការីទេ។

អត្រាដែលអនុវត្តត្រូវបានកំណត់ដោយប្រភេទផលិតផល និងការកែតម្រូវគណនីសកម្មណាមួយ ហើយត្រូវបានបង្ហាញដល់អ្នកលក់នៅពេលដាក់លក់ផលិតផល។ ដោយដាក់លក់ផលិតផល អ្នកលក់យល់ព្រមនឹងការកាត់ថ្លៃវេទិកាដែលបានបញ្ជាក់ពីការទូទាត់របស់ពួកគេ។

## ៩. សំណង និងវិវាទ
អ្នកទិញអាចស្នើសុំសំណងក្នុងរយៈពេល ២៤ ម៉ោងបន្ទាប់ពីការដឹកជញ្ជូន ប្រសិនបើទំនិញដែលទទួលបានខុស ខូច ឬខុសគ្នាយ៉ាងខ្លាំងពីបញ្ជី។ ទីផ្សារ នឹងសម្រុះសម្រួលវិវាទរវាងអ្នកទិញ និងអ្នកលក់។ ការសម្រេចចិត្តរបស់ ទីផ្សារ ក្នុងវិវាទទាំងអស់គឺជាចុងក្រោយ។ សំណងដែលបានអនុម័តត្រូវបានកាត់ពីការទូទាត់អ្នកលក់នាពេលអនាគត។ ទីផ្សារ មិនធានាសំណងក្នុងគ្រប់ស្ថានភាពទេ។

## ១០. ការបញ្ចប់គណនី
ទីផ្សារ រក្សាសិទ្ធិក្នុងការផ្អាក ឬហាមឃាត់ជាអចិន្ត្រៃយ៍នូវគណនីណាមួយដែលរំលោភលក្ខខណ្ឌទាំងនេះ ចូលរួមក្នុងសកម្មភាពក្លែងបន្លំ ឬបង្កហានិភ័យដល់អ្នកប្រើប្រាស់ផ្សេងទៀត ឬវេទិកា។ អ្នកលក់ដែលមានកាតព្វកិច្ចមិនទាន់រួច (កម្មង់មិនទាន់រួច ការទូទាត់មិនទាន់រួច) អាចមិនអាចលុបគណនីរបស់ពួកគេបានទេ រហូតដល់កាតព្វកិច្ចទាំងនោះត្រូវបានដោះស្រាយ។

## ១១. ដែនកំណត់នៃការទទួលខុសត្រូវ
ទីផ្សារ គឺជាវេទិកាទីផ្សារភ្ជាប់អ្នកទិញ និងអ្នកលក់។ យើងមិនមែនជាអ្នកលក់ផលិតផលណាមួយដែលដាក់លក់នៅលើវេទិកាទេ។ យើងមិនទទួលខុសត្រូវចំពោះគុណភាព សុវត្ថិភាព ភាពស្របច្បាប់ ឬភាពសមស្របនៃផលិតផលដែលលក់ដោយអ្នកលក់ លើសពីកាតព្វកិច្ចរបស់យើងក្នុងការសម្រុះសម្រួលវិវាទ។ ការទទួលខុសត្រូវសរុបរបស់យើងចំពោះការទាមទារណាមួយដែលកើតឡើងពីការប្រើប្រាស់វេទិកា ត្រូវបានកំណត់ត្រឹមតម្លៃនៃប្រតិបត្តិការពាក់ព័ន្ធ។

## ១២. ការផ្លាស់ប្តូរលក្ខខណ្ឌទាំងនេះ
ទីផ្សារ អាចធ្វើបច្ចុប្បន្នភាពលក្ខខណ្ឌទាំងនេះពីពេលមួយទៅពេលមួយ។ ការផ្លាស់ប្តូរនឹងត្រូវបានបង្ហោះនៅលើទំព័រនេះ ជាមួយកាលបរិច្ឆេទចូលជាធរមានថ្មី។ ការបន្តប្រើប្រាស់វេទិកាបន្ទាប់ពីការផ្លាស់ប្តូរ បង្ហាញពីការទទួលយកលក្ខខណ្ឌដែលបានធ្វើបច្ចុប្បន្នភាព។

## ១៣. ច្បាប់គ្រប់គ្រង
លក្ខខណ្ឌទាំងនេះត្រូវបានគ្រប់គ្រងដោយច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា។ វិវាទណាមួយដែលកើតឡើងពីលក្ខខណ្ឌទាំងនេះ ត្រូវស្ថិតនៅក្រោមយុត្តាធិការនៃតុលាការកម្ពុជា។

## ១៤. ទំនាក់ទំនង
សម្រាប់សំណួរផ្នែកច្បាប់ សូមទាក់ទងយើងតាមរយៈ [មជ្ឈមណ្ឌលជំនួយ](/help/) របស់យើង។

*លក្ខខណ្ឌទាំងនេះគួរតែត្រូវបានពិនិត្យដោយទីប្រឹក្សាច្បាប់កម្ពុជាដែលមានលក្ខណៈសម្បត្តិ មុនពេលវេទិកាដាក់ឱ្យប្រើប្រាស់ជាសាធារណៈ។*
MD;

$shippingEn = <<<'MD'
## Delivery
teepsaa uses Grab for deliveries within Phnom Penh. Delivery is cash on delivery (COD) — you pay the driver directly when your order arrives. The estimated delivery fee is shown in your cart and at checkout for reference.

## What the fee is
The fee is Grab's, not ours — it is worked out from the distance between the business and your delivery pin, so it changes with every order. In practice it lands somewhere between about $0.65 and $8.50. The figure in your cart is an estimate for reference; the driver charges the real fare.

## Delivery area
Deliveries are currently available within Phnom Penh only, and within 25 km of the business. Orders from vendors outside the delivery range cannot be completed.

## Payment
teepsaa accepts payment via ABA bank transfer. After placing your order, scan the QR code in your ABA app and submit your payment. Orders are processed once payment is confirmed by our team.

## Marketplace policy
teepsaa is a marketplace connecting buyers and independent vendors. Each vendor is responsible for the quality and accuracy of their listings. teepsaa is not responsible for the condition of items sold by vendors.
MD;

$shippingKm = <<<'MD'
## ការដឹកជញ្ជូន
ទីផ្សារ ប្រើ Grab សម្រាប់ការដឹកជញ្ជូននៅក្នុងភ្នំពេញ។ ការដឹកជញ្ជូនគឺជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) — អ្នកបង់ប្រាក់ឱ្យអ្នកបើកបរដោយផ្ទាល់នៅពេលកម្មង់របស់អ្នកមកដល់។ ថ្លៃដឹកជញ្ជូនប៉ាន់ស្មានត្រូវបានបង្ហាញនៅក្នុងរទេះ និងពេលបង់ប្រាក់សម្រាប់ជាឯកសារយោង។

## ថ្លៃដឹកជញ្ជូនគឺជាអ្វី
ថ្លៃដឹកជញ្ជូនគឺជារបស់ Grab មិនមែនរបស់យើងទេ — វាត្រូវបានគណនាតាមចម្ងាយរវាងអាជីវកម្ម និងទីតាំងដឹកជញ្ជូនរបស់អ្នក ដូច្នេះវាប្រែប្រួលតាមកម្មង់នីមួយៗ។ ជាក់ស្តែង វាធ្លាក់ចន្លោះប្រហែល $0.65 ដល់ $8.50។ តួលេខក្នុងរទេះរបស់អ្នកគឺជាការប៉ាន់ស្មានសម្រាប់ជាឯកសារយោង។ អ្នកបើកបរគិតថ្លៃពិតប្រាកដ។

## តំបន់ដឹកជញ្ជូន
ការដឹកជញ្ជូនបច្ចុប្បន្នមានតែនៅក្នុងភ្នំពេញប៉ុណ្ណោះ និងក្នុងចម្ងាយ ២៥ គីឡូម៉ែត្រពីអាជីវកម្ម។ កម្មង់ពីអ្នកលក់នៅក្រៅតំបន់ដឹកជញ្ជូនមិនអាចបញ្ចប់បានទេ។

## ការបង់ប្រាក់
ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA។ បន្ទាប់ពីដាក់កម្មង់ សូមស្កេនកូដ QR ក្នុងកម្មវិធី ABA របស់អ្នក ហើយដាក់ស្នើការបង់ប្រាក់។ កម្មង់ត្រូវបានដំណើរការនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងាររបស់យើង។

## គោលការណ៍ទីផ្សារ
ទីផ្សារ គឺជាទីផ្សារភ្ជាប់អ្នកទិញ និងអ្នកលក់ឯករាជ្យ។ អ្នកលក់នីមួយៗទទួលខុសត្រូវចំពោះគុណភាព និងភាពត្រឹមត្រូវនៃបញ្ជីរបស់ពួកគេ។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះស្ថានភាពនៃទំនិញដែលលក់ដោយអ្នកលក់ទេ។
MD;

$returnsEn = <<<'MD'
## How refunds work
Refunds are handled through teepsaa, not privately between you and the vendor. Everything happens on the order itself.

When an order is marked delivered, a refund request appears on that order in [your orders](/orders-buyer/). The window is **24 hours from delivery**. Once it closes the vendor has been paid, and a refund can no longer be requested through the platform.

## Requesting a refund
1. Open the order and choose a reason — item not as described, wrong item received, item arrived damaged, missing parts or accessories, quality not as expected, or describe the problem in your own words.
2. teepsaa reviews the request. You are notified of the decision.
3. If it is approved, pack the item and send it back to the vendor via Grab. **Return delivery is at your own cost.** Paste the Grab tracking link into the order once it is on its way.
4. The vendor confirms the item has arrived.
5. teepsaa sends your refund by ABA transfer.

## What is refunded
You are refunded the price of the items, less any discount that was applied. **The delivery fee is not refunded** — it has already been paid to the Grab driver who brought the order.

## If your request is declined
The order returns to its delivered state and the outcome is shown on it. If you disagree, contact teepsaa support and we will look at it again. teepsaa's decision is final in a dispute.

## Check your order when it arrives
Twenty-four hours is short on purpose — it is what lets vendors be paid quickly. Open the parcel and check the item while the driver is still there if you can.
MD;

$returnsKm = <<<'MD'
## របៀបដែលសំណងដំណើរការ
សំណងត្រូវបានដោះស្រាយតាមរយៈ ទីផ្សារ មិនមែនរវាងអ្នក និងអ្នកលក់ដោយផ្ទាល់ទេ។ អ្វីៗទាំងអស់កើតឡើងនៅលើកម្មង់នោះផ្ទាល់។

នៅពេលកម្មង់ត្រូវបានសម្គាល់ថាបានដឹកជញ្ជូនរួច ការស្នើសុំសំណងនឹងបង្ហាញនៅលើកម្មង់នោះក្នុង [កម្មង់របស់អ្នក](/orders-buyer/)។ រយៈពេលគឺ **២៤ ម៉ោង គិតចាប់ពីពេលដឹកជញ្ជូន**។ នៅពេលរយៈពេលនេះបញ្ចប់ អ្នកលក់ត្រូវបានបង់ប្រាក់រួច ហើយអ្នកលែងអាចស្នើសុំសំណងតាមវេទិកាបានទៀតទេ។

## របៀបស្នើសុំសំណង
១. បើកកម្មង់ ហើយជ្រើសរើសមូលហេតុ — ទំនិញមិនដូចការពិពណ៌នា ទទួលបានទំនិញខុស ទំនិញមកដល់ក្នុងសភាពខូច ខ្វះគ្រឿងបន្លាស់ ឬគ្រឿងបន្ថែម គុណភាពមិនដូចការរំពឹងទុក ឬពិពណ៌នាបញ្ហាតាមពាក្យរបស់អ្នកផ្ទាល់។
២. ទីផ្សារ ពិនិត្យសំណើ។ អ្នកនឹងទទួលបានការជូនដំណឹងអំពីការសម្រេច។
៣. ប្រសិនបើត្រូវបានអនុម័ត សូមខ្ចប់ទំនិញ ហើយផ្ញើត្រឡប់ទៅអ្នកលក់វិញតាម Grab។ **ថ្លៃដឹកជញ្ជូនត្រឡប់វិញគឺជាបន្ទុករបស់អ្នក។** សូមបញ្ចូលតំណតាមដាន Grab ទៅក្នុងកម្មង់ បន្ទាប់ពីផ្ញើរួច។
៤. អ្នកលក់បញ្ជាក់ថាទំនិញបានមកដល់។
៥. ទីផ្សារ ផ្ញើសំណងរបស់អ្នកតាមការផ្ទេរប្រាក់ ABA។

## អ្វីដែលត្រូវបានសងវិញ
អ្នកនឹងទទួលបានសងតម្លៃទំនិញ ដកបញ្ចុះតម្លៃណាមួយដែលបានប្រើ។ **ថ្លៃដឹកជញ្ជូនមិនត្រូវបានសងវិញទេ** — វាត្រូវបានបង់ទៅអ្នកបើកបរ Grab ដែលបាននាំកម្មង់មកឱ្យអ្នករួចហើយ។

## ប្រសិនបើសំណើរបស់អ្នកត្រូវបានបដិសេធ
កម្មង់នឹងត្រឡប់ទៅស្ថានភាពដឹកជញ្ជូនរួចវិញ ហើយលទ្ធផលនឹងបង្ហាញនៅលើកម្មង់នោះ។ ប្រសិនបើអ្នកមិនយល់ស្រប សូមទាក់ទងជំនួយ ទីផ្សារ ហើយយើងនឹងពិនិត្យម្តងទៀត។ ការសម្រេចចិត្តរបស់ ទីផ្សារ គឺជាចុងក្រោយក្នុងករណីវិវាទ។

## សូមពិនិត្យកម្មង់នៅពេលវាមកដល់
រយៈពេល ២៤ ម៉ោង គឺខ្លីដោយចេតនា — វាជាអ្វីដែលធ្វើឱ្យអ្នកលក់ទទួលបានប្រាក់រហ័ស។ សូមបើកកញ្ចប់ ហើយពិនិត្យទំនិញនៅពេលអ្នកបើកបរនៅទីនោះ ប្រសិនបើអាចធ្វើបាន។
MD;

$pages = [
    ['slug' => 'privacy', 'title_en' => 'Privacy Policy',    'title_km' => 'គោលការណ៍ភាពឯកជន',  'body_en' => $privacyEn,  'body_km' => $privacyKm],
    ['slug' => 'terms',   'title_en' => 'Terms of Service',  'title_km' => 'លក្ខខណ្ឌប្រើប្រាស់', 'body_en' => $termsEn,    'body_km' => $termsKm],
    ['slug' => 'shipping','title_en' => 'Shipping',          'title_km' => 'ការដឹកជញ្ជូន',        'body_en' => $shippingEn, 'body_km' => $shippingKm],
    ['slug' => 'returns', 'title_en' => 'Returns',           'title_km' => 'ការប្រគល់ទំនិញ',      'body_en' => $returnsEn,  'body_km' => $returnsKm],
];

$pageStmt = $pdo->prepare('INSERT INTO content_pages (slug, title_en, title_km, body_en, body_km) VALUES (?, ?, ?, ?, ?)');
foreach ($pages as $p) {
    $pageStmt->execute([$p['slug'], $p['title_en'], $p['title_km'], $p['body_en'], $p['body_km']]);
}
echo "Seeded " . count($pages) . " content_pages rows.\n";

// section_en, section_km, [ [q_en, q_km, a_en, a_km], ... ]
$faqSections = [
    [
        'Orders & Checkout', 'ការកម្មង់ និងការទូទាត់',
        [
            ['How do I place an order?', 'តើខ្ញុំដាក់កម្មង់ដោយរបៀបណា?',
             'Browse products, add items to your cart, then proceed to checkout. You\'ll confirm your delivery address and submit your order. Payment is via ABA bank transfer after placing the order.',
             'រកមើលផលិតផល បញ្ចូលទំនិញទៅក្នុងរទេះ បន្ទាប់មកបន្តទៅការទូទាត់។ អ្នកនឹងបញ្ជាក់អាសយដ្ឋានដឹកជញ្ជូន ហើយដាក់ស្នើកម្មង់របស់អ្នក។ ការបង់ប្រាក់គឺតាមការផ្ទេរប្រាក់ធនាគារ ABA បន្ទាប់ពីដាក់កម្មង់។'],
            ['Can I change or cancel my order after placing it?', 'តើខ្ញុំអាចប្តូរ ឬបោះបង់កម្មង់បន្ទាប់ពីដាក់ស្នើបានទេ?',
             'Orders can only be changed or cancelled before the vendor has confirmed them. Once confirmed, the order is being prepared and cannot be modified. Contact support as soon as possible if you need to make a change.',
             'កម្មង់អាចប្តូរ ឬបោះបង់បានតែមុនពេលអ្នកលក់បានបញ្ជាក់ប៉ុណ្ណោះ។ នៅពេលបញ្ជាក់រួច កម្មង់កំពុងត្រូវបានរៀបចំ ហើយមិនអាចកែប្រែបានទេ។ សូមទាក់ទងជំនួយឱ្យបានឆាប់ ប្រសិនបើអ្នកត្រូវការផ្លាស់ប្តូរ។'],
            ['How do I know my order was received?', 'តើខ្ញុំដឹងថាកម្មង់របស់ខ្ញុំត្រូវបានទទួលដោយរបៀបណា?',
             'After placing your order you\'ll see a confirmation page with your order number. You can track the status of all your orders from your Orders page.',
             'បន្ទាប់ពីដាក់កម្មង់ អ្នកនឹងឃើញទំព័របញ្ជាក់ជាមួយលេខកម្មង់របស់អ្នក។ អ្នកអាចតាមដានស្ថានភាពកម្មង់ទាំងអស់ពីទំព័រកម្មង់របស់អ្នក។'],
            ['Can I order from multiple vendors at once?', 'តើខ្ញុំអាចកម្មង់ពីអ្នកលក់ច្រើននាក់ក្នុងពេលតែមួយបានទេ?',
             'Yes. Your cart can hold items from multiple vendors. Each vendor\'s items are grouped separately at checkout, and delivery fees are calculated per vendor.',
             'បាទ/ចាស។ រទេះរបស់អ្នកអាចផ្ទុកទំនិញពីអ្នកលក់ច្រើននាក់។ ទំនិញរបស់អ្នកលក់នីមួយៗត្រូវបានដាក់ជាក្រុមដាច់ដោយឡែកនៅពេលទូទាត់ ហើយថ្លៃដឹកជញ្ជូនត្រូវបានគណនាតាមអ្នកលក់នីមួយៗ។'],
        ],
    ],
    [
        'Delivery', 'ការដឹកជញ្ជូន',
        [
            ['How does delivery work?', 'តើការដឹកជញ្ជូនដំណើរការដោយរបៀបណា?',
             'teepsaa uses Grab for deliveries within Phnom Penh. Once your order is dispatched, a Grab driver will pick it up from the vendor and deliver it to your address. Delivery is cash on delivery (COD) — you pay the driver directly when your order arrives.',
             'ទីផ្សារ ប្រើ Grab សម្រាប់ការដឹកជញ្ជូននៅក្នុងភ្នំពេញ។ នៅពេលកម្មង់របស់អ្នកត្រូវបានបញ្ជូន អ្នកបើកបរ Grab នឹងយកវាពីអ្នកលក់ ហើយដឹកជញ្ជូនទៅអាសយដ្ឋានរបស់អ្នក។ ការដឹកជញ្ជូនគឺជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) — អ្នកបង់ប្រាក់ឱ្យអ្នកបើកបរដោយផ្ទាល់នៅពេលកម្មង់មកដល់។'],
            ['How long does delivery take?', 'តើការដឹកជញ្ជូនចំណាយពេលប៉ុន្មាន?',
             'Most deliveries within Phnom Penh arrive within 1–3 hours once the vendor dispatches the order. Actual time depends on the vendor\'s preparation time and driver availability.',
             'ការដឹកជញ្ជូនភាគច្រើននៅក្នុងភ្នំពេញមកដល់ក្នុងរយៈពេល ១–៣ ម៉ោង នៅពេលអ្នកលក់បញ្ជូនកម្មង់។ ពេលវេលាជាក់ស្តែងអាស្រ័យលើពេលរៀបចំរបស់អ្នកលក់ និងភាពអាចរកបាននៃអ្នកបើកបរ។'],
            ['How is the delivery fee calculated?', 'តើថ្លៃដឹកជញ្ជូនត្រូវបានគណនាដោយរបៀបណា?',
             'The delivery fee is estimated based on the distance between the vendor\'s location and your delivery address. The estimate is shown in your cart and at checkout. The actual Grab fare may vary slightly.',
             'ថ្លៃដឹកជញ្ជូនត្រូវបានប៉ាន់ស្មានផ្អែកលើចម្ងាយរវាងទីតាំងអ្នកលក់ និងអាសយដ្ឋានដឹកជញ្ជូនរបស់អ្នក។ ការប៉ាន់ស្មានត្រូវបានបង្ហាញនៅក្នុងរទេះ និងពេលទូទាត់។ ថ្លៃ Grab ជាក់ស្តែងអាចប្រែប្រួលបន្តិច។'],
            ['What if I\'m not home when my order arrives?', 'ចុះបើខ្ញុំមិននៅផ្ទះពេលកម្មង់មកដល់?',
             'The Grab driver will attempt delivery at your address. If you\'re unavailable, contact the driver directly through the Grab app. teepsaa is not responsible for failed deliveries due to the buyer being unreachable.',
             'អ្នកបើកបរ Grab នឹងព្យាយាមដឹកជញ្ជូនទៅអាសយដ្ឋានរបស់អ្នក។ ប្រសិនបើអ្នកមិននៅ សូមទាក់ទងអ្នកបើកបរដោយផ្ទាល់តាមរយៈកម្មវិធី Grab។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះការដឹកជញ្ជូនបរាជ័យ ដោយសារអ្នកទិញមិនអាចទាក់ទងបានទេ។'],
        ],
    ],
    [
        'Payments', 'ការបង់ប្រាក់',
        [
            ['What payment methods do you accept?', 'តើអ្នកទទួលយកវិធីបង់ប្រាក់អ្វីខ្លះ?',
             'teepsaa accepts payment via ABA Bank transfer. After placing your order, scan the QR code in your ABA app and complete the transfer. Your order will be processed once payment is confirmed by our team.',
             'ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA។ បន្ទាប់ពីដាក់កម្មង់ សូមស្កេនកូដ QR ក្នុងកម្មវិធី ABA របស់អ្នក ហើយបំពេញការផ្ទេរប្រាក់។ កម្មង់របស់អ្នកនឹងត្រូវបានដំណើរការនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងាររបស់យើង។'],
            ['When will my payment be confirmed?', 'តើការបង់ប្រាក់របស់ខ្ញុំនឹងត្រូវបានបញ្ជាក់នៅពេលណា?',
             'Payment confirmation typically happens within a few hours during business hours. You\'ll see your order status update once confirmed.',
             'ការបញ្ជាក់ការបង់ប្រាក់ជាធម្មតាកើតឡើងក្នុងរយៈពេលពីរបីម៉ោងក្នុងម៉ោងធ្វើការ។ អ្នកនឹងឃើញស្ថានភាពកម្មង់របស់អ្នកធ្វើបច្ចុប្បន្នភាពនៅពេលបញ្ជាក់រួច។'],
            ['What if I paid but my order status hasn\'t updated?', 'ចុះបើខ្ញុំបានបង់ប្រាក់ ប៉ុន្តែស្ថានភាពកម្មង់មិនទាន់ធ្វើបច្ចុប្បន្នភាព?',
             'If your payment has been sent but your order still shows as unpaid after several hours, please contact support with your order number and a screenshot of your transfer confirmation.',
             'ប្រសិនបើការបង់ប្រាក់របស់អ្នកត្រូវបានផ្ញើ ប៉ុន្តែកម្មង់នៅតែបង្ហាញថាមិនទាន់បង់ប្រាក់បន្ទាប់ពីពីរបីម៉ោង សូមទាក់ទងជំនួយជាមួយលេខកម្មង់ និងរូបថតការបញ្ជាក់ការផ្ទេរប្រាក់របស់អ្នក។'],
        ],
    ],
    [
        'Returns & Refunds', 'ការប្រគល់ទំនិញ និងសំណង',
        [
            ['What is the return policy?', 'តើគោលការណ៍ការប្រគល់ទំនិញជាអ្វី?',
             'You have 24 hours from the moment your order is delivered to request a refund, and you do it on the order itself — there is no need to contact the vendor privately. Wrong item, damaged item, missing parts, or something significantly different from the listing are all valid reasons. Once the 24 hours pass the vendor is paid and the window closes.',
             'អ្នកមានរយៈពេល ២៤ ម៉ោង គិតចាប់ពីពេលកម្មង់របស់អ្នកត្រូវបានដឹកជញ្ជូន ដើម្បីស្នើសុំសំណង ហើយអ្នកធ្វើវានៅលើកម្មង់នោះផ្ទាល់ — មិនចាំបាច់ទាក់ទងអ្នកលក់ដោយផ្ទាល់ទេ។ ទំនិញខុស ទំនិញខូច ខ្វះគ្រឿងបន្លាស់ ឬទំនិញខុសគ្នាយ៉ាងខ្លាំងពីបញ្ជី សុទ្ធតែជាមូលហេតុត្រឹមត្រូវ។ នៅពេល ២៤ ម៉ោងកន្លងផុតទៅ អ្នកលក់ត្រូវបានបង់ប្រាក់ ហើយរយៈពេលនេះបញ្ចប់។'],
            ['How do I request a refund?', 'តើខ្ញុំស្នើសុំសំណងដោយរបៀបណា?',
             'Open the order in Orders, choose a reason from the list, and submit. teepsaa reviews it and tells you the decision. If it is approved you send the item back to the vendor via Grab at your own cost, paste the tracking link into the order, and once the vendor confirms it arrived we send your refund by ABA transfer.',
             'បើកកម្មង់ក្នុងទំព័រកម្មង់ ជ្រើសរើសមូលហេតុពីបញ្ជី ហើយដាក់ស្នើ។ ទីផ្សារ ពិនិត្យវា ហើយប្រាប់អ្នកអំពីការសម្រេច។ ប្រសិនបើត្រូវបានអនុម័ត អ្នកផ្ញើទំនិញត្រឡប់ទៅអ្នកលក់វិញតាម Grab ដោយចំណាយផ្ទាល់ខ្លួន បញ្ចូលតំណតាមដានទៅក្នុងកម្មង់ ហើយនៅពេលអ្នកលក់បញ្ជាក់ថាបានទទួល យើងផ្ញើសំណងរបស់អ្នកតាមការផ្ទេរប្រាក់ ABA។'],
            ['How long does a refund take?', 'តើសំណងចំណាយពេលប៉ុន្មាន?',
             'Approval is only the first step — the money moves after the item is back with the vendor. Send it via Grab as soon as you are approved, and once the vendor confirms it arrived teepsaa transfers your refund, normally within a few business days.',
             'ការអនុម័តគ្រាន់តែជាជំហានដំបូងប៉ុណ្ណោះ — ប្រាក់ត្រូវបានផ្ទេរបន្ទាប់ពីទំនិញត្រឡប់ទៅអ្នកលក់វិញ។ សូមផ្ញើវាតាម Grab ឱ្យបានឆាប់បន្ទាប់ពីត្រូវបានអនុម័ត ហើយនៅពេលអ្នកលក់បញ្ជាក់ថាបានទទួល ទីផ្សារ ផ្ទេរសំណងរបស់អ្នក ជាធម្មតាក្នុងរយៈពេលពីរបីថ្ងៃធ្វើការ។'],
            ['What if the vendor disputes my refund request?', 'ចុះបើអ្នកលក់ជំទាស់នឹងសំណើសំណងរបស់ខ្ញុំ?',
             'The vendor does not decide — teepsaa reviews every refund request and makes the call. If your request is declined the outcome appears on the order, and you can contact support to have it looked at again. teepsaa\'s decision is final in all dispute cases.',
             'អ្នកលក់មិនមែនជាអ្នកសម្រេចទេ — ទីផ្សារ ពិនិត្យរាល់សំណើសំណង ហើយធ្វើការសម្រេច។ ប្រសិនបើសំណើរបស់អ្នកត្រូវបានបដិសេធ លទ្ធផលនឹងបង្ហាញនៅលើកម្មង់ ហើយអ្នកអាចទាក់ទងជំនួយដើម្បីឱ្យពិនិត្យម្តងទៀត។ ការសម្រេចចិត្តរបស់ ទីផ្សារ គឺជាចុងក្រោយក្នុងគ្រប់ករណីវិវាទ។'],
        ],
    ],
    [
        'My Account', 'គណនីរបស់ខ្ញុំ',
        [
            ['How do I create an account?', 'តើខ្ញុំបង្កើតគណនីដោយរបៀបណា?',
             'Click Register on the login page and fill in your details. Buyer and vendor accounts are separate — create a buyer account to shop, or apply as a vendor to sell.',
             'ចុច ចុះឈ្មោះ នៅលើទំព័រចូលគណនី ហើយបំពេញព័ត៌មានលម្អិតរបស់អ្នក។ គណនីអ្នកទិញ និងអ្នកលក់ដាច់ដោយឡែក — បង្កើតគណនីអ្នកទិញដើម្បីទិញ ឬដាក់ពាក្យជាអ្នកលក់ដើម្បីលក់។'],
            ['Can I have both a buyer and a vendor account?', 'តើខ្ញុំអាចមានទាំងគណនីអ្នកទិញ និងអ្នកលក់បានទេ?',
             'Yes. Vendor accounts are for selling only. If you also want to shop on teepsaa, you\'ll need to register a separate buyer account with a different email address.',
             'បាទ/ចាស។ គណនីអ្នកលក់សម្រាប់តែការលក់ប៉ុណ្ណោះ។ ប្រសិនបើអ្នកចង់ទិញនៅលើ ទីផ្សារ ផងដែរ អ្នកត្រូវចុះឈ្មោះគណនីអ្នកទិញដាច់ដោយឡែកជាមួយអ៊ីមែលផ្សេង។'],
            ['How do I update my delivery address?', 'តើខ្ញុំធ្វើបច្ចុប្បន្នភាពអាសយដ្ឋានដឹកជញ្ជូនដោយរបៀបណា?',
             'Go to your account Settings and update your address under the Address tab. Make sure to set a pin on the map so your delivery fee is calculated correctly.',
             'ចូលទៅការកំណត់គណនីរបស់អ្នក ហើយធ្វើបច្ចុប្បន្នភាពអាសយដ្ឋានក្រោមផ្ទាំង អាសយដ្ឋាន។ សូមប្រាកដថាកំណត់ pin នៅលើផែនទី ដើម្បីឱ្យថ្លៃដឹកជញ្ជូនត្រូវបានគណនាត្រឹមត្រូវ។'],
            ['How do I change my password?', 'តើខ្ញុំប្តូរពាក្យសម្ងាត់ដោយរបៀបណា?',
             'Go to Settings → Password and enter your current password followed by your new one.',
             'ចូលទៅ ការកំណត់ → ពាក្យសម្ងាត់ ហើយបញ្ចូលពាក្យសម្ងាត់បច្ចុប្បន្នរបស់អ្នក បន្តដោយពាក្យសម្ងាត់ថ្មី។'],
        ],
    ],
    [
        'Selling on teepsaa', 'ការលក់នៅ ទីផ្សារ',
        [
            ['How do I become a vendor?', 'តើខ្ញុំក្លាយជាអ្នកលក់ដោយរបៀបណា?',
             'Register a vendor account, then submit your business details for review. Once approved by our team, you can start listing products.',
             'ចុះឈ្មោះគណនីអ្នកលក់ បន្ទាប់មកដាក់ស្នើព័ត៌មានលម្អិតអាជីវកម្មរបស់អ្នកសម្រាប់ការត្រួតពិនិត្យ។ នៅពេលបានអនុម័តដោយក្រុមការងាររបស់យើង អ្នកអាចចាប់ផ្តើមដាក់លក់ផលិតផល។'],
            ['How do payouts work?', 'តើការទូទាត់ដំណើរការដោយរបៀបណា?',
             'After a buyer confirms delivery (or after the auto-confirmation window passes), your payout is calculated and queued. Our team processes payouts via ABA bank transfer.',
             'បន្ទាប់ពីអ្នកទិញបញ្ជាក់ការទទួល (ឬបន្ទាប់ពីរយៈពេលបញ្ជាក់ស្វ័យប្រវត្តិកន្លងផុត) ការទូទាត់របស់អ្នកត្រូវបានគណនា និងដាក់ជាជួរ។ ក្រុមការងាររបស់យើងដំណើរការការទូទាត់តាមការផ្ទេរប្រាក់ធនាគារ ABA។'],
            ['What fees does teepsaa charge?', 'តើ ទីផ្សារ គិតថ្លៃអ្វីខ្លះ?',
             'teepsaa charges a royalty fee per sale, which varies by product category. The fee is shown when you list a product, and your estimated payout is displayed on the product form.',
             'ទីផ្សារ គិតកម្រៃជើងសារក្នុងមួយការលក់ ដែលប្រែប្រួលតាមប្រភេទផលិតផល។ ថ្លៃនេះត្រូវបានបង្ហាញនៅពេលអ្នកដាក់លក់ផលិតផល ហើយការទូទាត់ប៉ាន់ស្មានរបស់អ្នកត្រូវបានបង្ហាញនៅលើទម្រង់ផលិតផល។'],
            ['What happens if a buyer requests a refund on my product?', 'មានអ្វីកើតឡើងប្រសិនបើអ្នកទិញស្នើសុំសំណងលើផលិតផលរបស់ខ្ញុំ?',
             'You\'ll be notified of any refund requests. You can accept or dispute the request. Our support team mediates if there is a disagreement. Approved refunds are deducted from future payouts.',
             'អ្នកនឹងត្រូវបានជូនដំណឹងអំពីសំណើសំណងណាមួយ។ អ្នកអាចទទួលយក ឬជំទាស់សំណើ។ ក្រុមការងារជំនួយរបស់យើងសម្រុះសម្រួល ប្រសិនបើមានការមិនចុះសម្រុង។ សំណងដែលបានអនុម័តត្រូវបានកាត់ពីការទូទាត់នាពេលអនាគត។'],
        ],
    ],
];

$faqStmt = $pdo->prepare('INSERT INTO faq_items (section_en, section_km, question_en, question_km, answer_en, answer_km, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
$sort = 0;
foreach ($faqSections as [$sectionEn, $sectionKm, $items]) {
    foreach ($items as [$qEn, $qKm, $aEn, $aKm]) {
        $faqStmt->execute([$sectionEn, $sectionKm, $qEn, $qKm, $aEn, $aKm, $sort++]);
    }
}
echo "Seeded $sort faq_items rows.\n";
