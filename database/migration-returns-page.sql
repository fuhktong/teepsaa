-- The Returns page (content_pages, slug 'returns') rewritten for the refund
-- rules built in Part 4: photos with a dispute, the vendor's 48-hour reply,
-- change of mind approved at once, who pays to send the item back (the buyer
-- after a change of mind, the shop by cash on delivery otherwise), held
-- refunds after a return that is used, empty or different, and courier
-- delivery. The page can also be edited at /admin/content.php; this replaces
-- whatever is there. The same text is in database/seed-content.php.

UPDATE content_pages
   SET body_en = '## How refunds work
Refunds are handled through teepsaa, not privately between you and the vendor. Everything happens on the order itself.

When an order is marked delivered, a refund request appears on that order in [your orders](/orders-buyer/). The window is **24 hours from delivery**. Once it closes the vendor has been paid, and a refund can no longer be requested through the platform.

## Requesting a refund
1. Open the order and choose a reason — changed my mind, item not as described, wrong item received, item arrived damaged, missing parts or accessories, quality not as expected, or describe the problem in your own words.
2. **For every reason except a change of mind, add 1 to 3 photos** of the problem. The vendor has 48 hours to reply with their side, then teepsaa decides. You are notified of the decision.
3. **A change of mind is approved straight away** — there is nothing to decide.

## Sending the item back
Once a return is approved, pack the item and send it to the shop''s address shown on the order, with any delivery service. Paste the tracking link into the order once it is on its way.

- **Changed your mind:** you pay the postage.
- **Any other reason:** send it **cash on delivery**. The shop pays the postage when the parcel arrives.

## When the item arrives
The vendor checks what came back. If all is as it should be, teepsaa sends your refund by ABA transfer.

If the vendor reports, with photos, that the item is used, empty or not what was sent, the refund is held and teepsaa decides: a full refund, part of it, or none. If no refund is given, the item is still yours — the shop posts it back to you, and you pay the postage.

## What is refunded
You are refunded the price of the items, less any discount that was applied. **Delivery is not refunded** — it was paid to the driver or courier who brought the order.

## If your request is declined
The order returns to its delivered state and the outcome is shown on it. If you disagree, contact teepsaa support and we will look at it again. teepsaa''s decision is final in a dispute.

## Check your order when it arrives
Twenty-four hours is short on purpose — it is what lets vendors be paid quickly. Open the parcel and check the item as soon as it arrives, and take photos of anything wrong.',
       body_km = '## របៀបដែលសំណងដំណើរការ
សំណងត្រូវបានដោះស្រាយតាមរយៈ ទីផ្សារ មិនមែនរវាងអ្នក និងអ្នកលក់ដោយផ្ទាល់ទេ។ អ្វីៗទាំងអស់កើតឡើងនៅលើកម្មង់នោះផ្ទាល់។

នៅពេលកម្មង់ត្រូវបានសម្គាល់ថាបានដឹកជញ្ជូនរួច ការស្នើសុំសំណងនឹងបង្ហាញនៅលើកម្មង់នោះក្នុង [កម្មង់របស់អ្នក](/orders-buyer/)។ រយៈពេលគឺ **២៤ ម៉ោង គិតចាប់ពីពេលដឹកជញ្ជូន**។ នៅពេលរយៈពេលនេះបញ្ចប់ អ្នកលក់ត្រូវបានបង់ប្រាក់រួច ហើយអ្នកលែងអាចស្នើសុំសំណងតាមវេទិកាបានទៀតទេ។

## របៀបស្នើសុំសំណង
១. បើកកម្មង់ ហើយជ្រើសរើសមូលហេតុ — ប្តូរចិត្ត ទំនិញមិនដូចការពិពណ៌នា ទទួលបានទំនិញខុស ទំនិញមកដល់ក្នុងសភាពខូច ខ្វះគ្រឿងបន្លាស់ ឬគ្រឿងបន្ថែម គុណភាពមិនដូចការរំពឹងទុក ឬពិពណ៌នាបញ្ហាតាមពាក្យរបស់អ្នកផ្ទាល់។
២. **សម្រាប់គ្រប់មូលហេតុ លើកលែងតែការប្តូរចិត្ត សូមភ្ជាប់រូបថត ១ ដល់ ៣** នៃបញ្ហា។ អ្នកលក់មានពេល ៤៨ ម៉ោង ដើម្បីឆ្លើយតបពីភាគីរបស់ពួកគេ បន្ទាប់មក ទីផ្សារ សម្រេច។ អ្នកនឹងទទួលបានការជូនដំណឹងអំពីការសម្រេច។
៣. **ការប្តូរចិត្តត្រូវបានអនុម័តភ្លាមៗ** — គ្មានអ្វីត្រូវសម្រេចទេ។

## ការផ្ញើទំនិញត្រឡប់វិញ
នៅពេលការប្រគល់ត្រឡប់ត្រូវបានអនុម័ត សូមខ្ចប់ទំនិញ ហើយផ្ញើទៅអាសយដ្ឋានហាងដែលបង្ហាញនៅលើកម្មង់ តាមសេវាដឹកជញ្ជូនណាមួយ។ សូមបញ្ចូលតំណតាមដានទៅក្នុងកម្មង់ បន្ទាប់ពីផ្ញើរួច។

- **ប្តូរចិត្ត៖** អ្នកបង់ថ្លៃដឹក។
- **មូលហេតុផ្សេងទៀត៖** សូមផ្ញើដោយ**បង់ប្រាក់ពេលទទួល (COD)**។ ហាងបង់ថ្លៃដឹកនៅពេលកញ្ចប់មកដល់។

## នៅពេលទំនិញមកដល់
អ្នកលក់ពិនិត្យទំនិញដែលបានត្រឡប់មកវិញ។ ប្រសិនបើអ្វីៗត្រឹមត្រូវ ទីផ្សារ ផ្ញើសំណងរបស់អ្នកតាមការផ្ទេរប្រាក់ ABA។

ប្រសិនបើអ្នកលក់រាយការណ៍ ដោយមានរូបថត ថាទំនិញត្រូវបានប្រើរួច ទទេ ឬមិនមែនជាទំនិញដែលបានផ្ញើ សំណងនឹងត្រូវបានរក្សាទុកសិន ហើយ ទីផ្សារ សម្រេច៖ សងវិញទាំងស្រុង មួយផ្នែក ឬមិនសង។ ប្រសិនបើមិនមានសំណងទេ ទំនិញនៅតែជារបស់អ្នក — ហាងផ្ញើវាត្រឡប់មកអ្នកវិញ ហើយអ្នកបង់ថ្លៃដឹក។

## អ្វីដែលត្រូវបានសងវិញ
អ្នកនឹងទទួលបានសងតម្លៃទំនិញ ដកបញ្ចុះតម្លៃណាមួយដែលបានប្រើ។ **ថ្លៃដឹកជញ្ជូនមិនត្រូវបានសងវិញទេ** — វាត្រូវបានបង់ទៅអ្នកបើកបរ ឬក្រុមហ៊ុនដឹកជញ្ជូនដែលបាននាំកម្មង់មកឱ្យអ្នករួចហើយ។

## ប្រសិនបើសំណើរបស់អ្នកត្រូវបានបដិសេធ
កម្មង់នឹងត្រឡប់ទៅស្ថានភាពដឹកជញ្ជូនរួចវិញ ហើយលទ្ធផលនឹងបង្ហាញនៅលើកម្មង់នោះ។ ប្រសិនបើអ្នកមិនយល់ស្រប សូមទាក់ទងជំនួយ ទីផ្សារ ហើយយើងនឹងពិនិត្យម្តងទៀត។ ការសម្រេចចិត្តរបស់ ទីផ្សារ គឺជាចុងក្រោយក្នុងករណីវិវាទ។

## សូមពិនិត្យកម្មង់នៅពេលវាមកដល់
រយៈពេល ២៤ ម៉ោង គឺខ្លីដោយចេតនា — វាជាអ្វីដែលធ្វើឱ្យអ្នកលក់ទទួលបានប្រាក់រហ័ស។ សូមបើកកញ្ចប់ ហើយពិនិត្យទំនិញភ្លាមៗនៅពេលវាមកដល់ ហើយថតរូបអ្វីដែលខុស។',
       updated_at = NOW()
 WHERE slug = 'returns';
