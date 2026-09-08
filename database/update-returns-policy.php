<?php
// One-shot: bring the live /returns/ and /shipping/ copy, and the four
// Returns & Refunds FAQ answers, into line with what the platform actually
// does. The seeded text predated the in-app refund flow and still told
// buyers that returns were "handled between the buyer and the individual
// vendor" on a "case-by-case basis" — neither of which is true: a buyer
// requests a refund on the order, within 24 hours of delivery, and teepsaa
// decides.
//
// A mismatch between this copy and the hasMerchantReturnPolicy block in
// config/schema.php is exactly what Merchant Center issues manual actions
// for, which is why the two were written together.
//
// The prose below is a copy of database/seed-content.php. That file only
// runs against an empty table, so the two never both apply; this one is
// safe to run twice and can be deleted once it has.

require __DIR__ . '/../config/db.php';

$pages = [
    'shipping' => [
        'en' => <<<'MD'
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
MD,
        'km' => <<<'MD'
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
MD,
    ],
    'returns' => [
        'en' => <<<'MD'
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
MD,
        'km' => <<<'MD'
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
MD,
    ],
];

$faq = [
    ['What is the return policy?',
     'You have 24 hours from the moment your order is delivered to request a refund, and you do it on the order itself — there is no need to contact the vendor privately. Wrong item, damaged item, missing parts, or something significantly different from the listing are all valid reasons. Once the 24 hours pass the vendor is paid and the window closes.',
     'អ្នកមានរយៈពេល ២៤ ម៉ោង គិតចាប់ពីពេលកម្មង់របស់អ្នកត្រូវបានដឹកជញ្ជូន ដើម្បីស្នើសុំសំណង ហើយអ្នកធ្វើវានៅលើកម្មង់នោះផ្ទាល់ — មិនចាំបាច់ទាក់ទងអ្នកលក់ដោយផ្ទាល់ទេ។ ទំនិញខុស ទំនិញខូច ខ្វះគ្រឿងបន្លាស់ ឬទំនិញខុសគ្នាយ៉ាងខ្លាំងពីបញ្ជី សុទ្ធតែជាមូលហេតុត្រឹមត្រូវ។ នៅពេល ២៤ ម៉ោងកន្លងផុតទៅ អ្នកលក់ត្រូវបានបង់ប្រាក់ ហើយរយៈពេលនេះបញ្ចប់។'],
    ['How do I request a refund?',
     'Open the order in Orders, choose a reason from the list, and submit. teepsaa reviews it and tells you the decision. If it is approved you send the item back to the vendor via Grab at your own cost, paste the tracking link into the order, and once the vendor confirms it arrived we send your refund by ABA transfer.',
     'បើកកម្មង់ក្នុងទំព័រកម្មង់ ជ្រើសរើសមូលហេតុពីបញ្ជី ហើយដាក់ស្នើ។ ទីផ្សារ ពិនិត្យវា ហើយប្រាប់អ្នកអំពីការសម្រេច។ ប្រសិនបើត្រូវបានអនុម័ត អ្នកផ្ញើទំនិញត្រឡប់ទៅអ្នកលក់វិញតាម Grab ដោយចំណាយផ្ទាល់ខ្លួន បញ្ចូលតំណតាមដានទៅក្នុងកម្មង់ ហើយនៅពេលអ្នកលក់បញ្ជាក់ថាបានទទួល យើងផ្ញើសំណងរបស់អ្នកតាមការផ្ទេរប្រាក់ ABA។'],
    ['How long does a refund take?',
     'Approval is only the first step — the money moves after the item is back with the vendor. Send it via Grab as soon as you are approved, and once the vendor confirms it arrived teepsaa transfers your refund, normally within a few business days.',
     'ការអនុម័តគ្រាន់តែជាជំហានដំបូងប៉ុណ្ណោះ — ប្រាក់ត្រូវបានផ្ទេរបន្ទាប់ពីទំនិញត្រឡប់ទៅអ្នកលក់វិញ។ សូមផ្ញើវាតាម Grab ឱ្យបានឆាប់បន្ទាប់ពីត្រូវបានអនុម័ត ហើយនៅពេលអ្នកលក់បញ្ជាក់ថាបានទទួល ទីផ្សារ ផ្ទេរសំណងរបស់អ្នក ជាធម្មតាក្នុងរយៈពេលពីរបីថ្ងៃធ្វើការ។'],
    ['What if the vendor disputes my refund request?',
     'The vendor does not decide — teepsaa reviews every refund request and makes the call. If your request is declined the outcome appears on the order, and you can contact support to have it looked at again. teepsaa\'s decision is final in all dispute cases.',
     'អ្នកលក់មិនមែនជាអ្នកសម្រេចទេ — ទីផ្សារ ពិនិត្យរាល់សំណើសំណង ហើយធ្វើការសម្រេច។ ប្រសិនបើសំណើរបស់អ្នកត្រូវបានបដិសេធ លទ្ធផលនឹងបង្ហាញនៅលើកម្មង់ ហើយអ្នកអាចទាក់ទងជំនួយដើម្បីឱ្យពិនិត្យម្តងទៀត។ ការសម្រេចចិត្តរបស់ ទីផ្សារ គឺជាចុងក្រោយក្នុងគ្រប់ករណីវិវាទ។'],
];

$pageStmt = $pdo->prepare(
    'UPDATE content_pages SET body_en = ?, body_km = ? WHERE slug = ?'
);
foreach ($pages as $slug => $body) {
    $pageStmt->execute([$body['en'], $body['km'], $slug]);
    printf("content_pages/%s: %d row(s)\n", $slug, $pageStmt->rowCount());
}

// Matched on the English question, which is what identifies the row —
// the answers are what changed.
$faqStmt = $pdo->prepare(
    'UPDATE faq_items SET answer_en = ?, answer_km = ? WHERE question_en = ?'
);
foreach ($faq as [$question, $answerEn, $answerKm]) {
    $faqStmt->execute([$answerEn, $answerKm, $question]);
    printf("faq_items/%.40s: %d row(s)\n", $question, $faqStmt->rowCount());
}

echo "Done.\n";
