<?php

// Intro copy for category pages, keyed by the slug config/category.php
// derives from the category name.
//
// Two or three sentences of real writing is most of what separates a page
// worth indexing from a bare grid of thumbnails — it is what a search engine
// reads to decide what the page is about, and what a first-time visitor reads
// to decide whether this is a real shop. Where a category has no entry here
// the page still renders; it just falls back to a generated meta description
// and shows no paragraph.
//
// It lives in a file rather than the database on purpose: it deploys with the
// code, needs no migration hand-applied on the server, and can be edited by
// anyone who can edit a PHP file.
//
// To add one, fill in the 'en' and 'km' strings. Keep them specific — say
// what is actually in the category and what a buyer should check before
// ordering. Text that would fit any category is worse than no text at all,
// because a dozen pages carrying the same paragraph read as duplicates.
//
// The 'km' string is what a Khmer visitor sees and it is the half that
// matters most — this is a Khmer-first marketplace. Have a native speaker
// read these before they go live.

return [

    // ── Top level ────────────────────────────────────────────────────

    'clothing' => [
        'en' => "Clothing from shops across Phnom Penh, listed by the people who make and sell it. Browse men's, women's and kids' wear, traditional Khmer outfits and everyday basics — and order from as many shops as you like in one checkout.",
        'km' => "សម្លៀកបំពាក់ពីហាងនានាទូទាំងភ្នំពេញ ដាក់លក់ដោយផ្ទាល់ពីអ្នកផលិត និងអ្នកលក់។ រកមើលសម្លៀកបំពាក់បុរស នារី កុមារ សម្លៀកបំពាក់ប្រពៃណីខ្មែរ និងសម្លៀកបំពាក់ប្រចាំថ្ងៃ — ហើយកម្មង់ពីហាងច្រើនក្នុងការទូទាត់តែម្តង។",
    ],

    'mens' => [
        'en' => "Men's clothing from local Phnom Penh shops — shirts, T-shirts, jeans, chinos, jackets and workwear. Everything here is listed by a seller you can message before you buy, and you can order from several shops in one basket.",
        'km' => "សម្លៀកបំពាក់បុរសពីហាងក្នុងភ្នំពេញ — អាវសឺមី អាវយឺត ខោខូវប៊យ ខោឆាណូ អាវក្រៅ និងសម្លៀកបំពាក់ការងារ។ ទំនិញទាំងអស់ដាក់លក់ដោយអ្នកលក់ដែលអ្នកអាចផ្ញើសារសួរមុននឹងទិញ ហើយអ្នកអាចកម្មង់ពីហាងច្រើនក្នុងកន្ត្រកតែមួយ។",
    ],

    'womens' => [
        'en' => "Women's clothing from shops across Phnom Penh — dresses, blouses, skirts, jeans and outerwear, alongside traditional Khmer pieces. Sizes and colours are set by each shop, so check the listing or message the seller before ordering.",
        'km' => "សម្លៀកបំពាក់នារីពីហាងទូទាំងភ្នំពេញ — រ៉ូប អាវ សំពត់ ខោខូវប៊យ និងអាវក្រៅ ព្រមទាំងសម្លៀកបំពាក់ប្រពៃណីខ្មែរ។ ទំហំ និងពណ៌កំណត់ដោយហាងនីមួយៗ ដូច្នេះសូមពិនិត្យការផ្សាយ ឬផ្ញើសារទៅអ្នកលក់មុននឹងកម្មង់។",
    ],

    'kids' => [
        'en' => "Clothes for babies, toddlers and school-age children from Phnom Penh shops. Sizing varies between sellers — most listings give measurements, and you can message the shop if you are unsure which size to order.",
        'km' => "សម្លៀកបំពាក់សម្រាប់ទារក កុមារតូច និងកុមារវ័យសិក្សា ពីហាងក្នុងភ្នំពេញ។ ទំហំខុសគ្នាតាមអ្នកលក់នីមួយៗ — ការផ្សាយភាគច្រើនមានបញ្ជាក់រង្វាស់ ហើយអ្នកអាចផ្ញើសារទៅហាង បើមិនប្រាកដពីទំហំដែលត្រូវកម្មង់។",
    ],

    'accessories' => [
        'en' => "Bags, wallets, belts, hats, scarves, sunglasses, jewellery and watches from Phnom Penh sellers. This is where the handmade and small-batch work tends to show up, so it rewards browsing rather than searching.",
        'km' => "កាបូប កាបូបលុយ ខ្សែក្រវាត់ មួក ក្រមា វ៉ែនតា គ្រឿងអលង្ការ និងនាឡិកា ពីអ្នកលក់ក្នុងភ្នំពេញ។ នេះជាកន្លែងដែលទំនិញធ្វើដោយដៃ និងផលិតជាបាច់តូចតែងតែលេចឡើង ដូច្នេះការរកមើលមួយៗមានតម្លៃជាងការស្វែងរក។",
    ],

    'footwear' => [
        'en' => "Shoes, sneakers, sandals and boots for men, women and children, sold by shops in Phnom Penh. Shoe sizing is the thing buyers most often get wrong online — check the size chart on the listing, and message the seller if it is not clear.",
        'km' => "ស្បែកជើង ស្បែកជើងកីឡា ស្បែកជើងផ្ទាត់ និងស្បែកជើងកវែង សម្រាប់បុរស នារី និងកុមារ លក់ដោយហាងក្នុងភ្នំពេញ។ ទំហំស្បែកជើងគឺជារឿងដែលអ្នកទិញតាមអនឡាញច្រឡំច្រើនជាងគេ — សូមពិនិត្យតារាងទំហំក្នុងការផ្សាយ ហើយផ្ញើសារទៅអ្នកលក់ បើមិនច្បាស់។",
    ],

    'traditional-cultural-wear' => [
        'en' => "Khmer traditional and ceremonial clothing — sampot, krama, wedding and pagoda outfits — from shops in Phnom Penh. Many of these are made to order or in small numbers, so message the shop early if you need something for a particular date.",
        'km' => "សម្លៀកបំពាក់ប្រពៃណី និងពិធីខ្មែរ — សំពត់ ក្រមា សម្លៀកបំពាក់អាពាហ៍ពិពាហ៍ និងចូលវត្ត — ពីហាងក្នុងភ្នំពេញ។ ភាគច្រើនធ្វើតាមការកម្មង់ ឬផលិតជាចំនួនតិច ដូច្នេះសូមផ្ញើសារទៅហាងជាមុន បើអ្នកត្រូវការសម្រាប់កាលបរិច្ឆេទជាក់លាក់។",
    ],

    // ── Leaves worth writing first ───────────────────────────────────
    // The ones with the clearest search intent behind them.

    'khmer-traditional' => [
        'en' => "Sampot, krama, silk shirts and other Khmer traditional dress from Phnom Penh sellers, including hand-woven pieces from weaving families. Ask the shop about the weave and the fabric — most are glad to explain what you are buying.",
        'km' => "សំពត់ ក្រមា អាវសូត្រ និងសម្លៀកបំពាក់ប្រពៃណីខ្មែរផ្សេងទៀត ពីអ្នកលក់ក្នុងភ្នំពេញ រួមទាំងទំនិញត្បាញដៃពីគ្រួសារអ្នកតម្បាញ។ សូមសួរហាងអំពីវិធីត្បាញ និងក្រណាត់ — ភាគច្រើនរីករាយក្នុងការពន្យល់ពីអ្វីដែលអ្នកកំពុងទិញ។",
    ],

    'dresses' => [
        'en' => "Dresses from Phnom Penh shops — everyday, office, party and traditional. Each listing comes from one seller, so if a shop's style suits you it is worth opening their storefront to see the rest.",
        'km' => "រ៉ូបពីហាងក្នុងភ្នំពេញ — សម្រាប់ប្រចាំថ្ងៃ ការិយាល័យ ពិធីជប់លៀង និងប្រពៃណី។ ការផ្សាយនីមួយៗមកពីអ្នកលក់តែមួយ ដូច្នេះបើអ្នកចូលចិត្តរចនាបថរបស់ហាងណាមួយ វាមានតម្លៃក្នុងការបើកមើលហាងនោះទាំងមូល។",
    ],

    'bags-purses' => [
        'en' => "Bags and purses from Phnom Penh shops — everyday totes, shoulder bags, backpacks, and handmade pieces in silk, leather and woven fibre. The photographs are the seller's own, so what you see is the bag that arrives.",
        'km' => "កាបូបគ្រប់ប្រភេទពីហាងក្នុងភ្នំពេញ — កាបូបប្រចាំថ្ងៃ កាបូបស្ពាយ កាបូបខ្នង និងកាបូបធ្វើដោយដៃពីសូត្រ ស្បែក និងសរសៃត្បាញ។ រូបថតជារបស់អ្នកលក់ផ្ទាល់ ដូច្នេះអ្វីដែលអ្នកឃើញគឺជាកាបូបដែលអ្នកនឹងទទួលបាន។",
    ],

    'scarves-wraps' => [
        'en' => "Krama, silk scarves and wraps from Phnom Penh shops, including hand-woven cotton and silk from Cambodian weaving families. A krama is the most useful thing anyone will ever hand you, and it travels well as a gift.",
        'km' => "ក្រមា កន្សែងសូត្រ និងក្រណាត់រុំ ពីហាងក្នុងភ្នំពេញ រួមទាំងកប្បាស និងសូត្រត្បាញដៃពីគ្រួសារអ្នកតម្បាញកម្ពុជា។ ក្រមាគឺជារបស់ដែលមានប្រយោជន៍បំផុត ហើយវាជាអំណោយដ៏ល្អសម្រាប់នាំទៅឆ្ងាយ។",
    ],

    'jewellery' => [
        'en' => "Jewellery from Phnom Penh sellers — silver, gold-plated, beaded and handmade. Check the listing for the metal and the stone the maker actually used, and message the shop where it is not stated.",
        'km' => "គ្រឿងអលង្ការពីអ្នកលក់ក្នុងភ្នំពេញ — ប្រាក់ ស្រោបមាស អង្កាំ និងធ្វើដោយដៃ។ សូមពិនិត្យការផ្សាយអំពីលោហៈ និងត្បូងដែលអ្នកផលិតបានប្រើពិតប្រាកដ ហើយផ្ញើសារទៅហាង បើមិនបានបញ្ជាក់។",
    ],

    'sneakers' => [
        'en' => "Sneakers and trainers from Phnom Penh sellers. Sizes run differently between brands — most listings give the inner length in centimetres, which is the reliable number to compare against a shoe you already own.",
        'km' => "ស្បែកជើងកីឡាពីអ្នកលក់ក្នុងភ្នំពេញ។ ទំហំខុសគ្នាតាមម៉ាកនីមួយៗ — ការផ្សាយភាគច្រើនបញ្ជាក់ប្រវែងខាងក្នុងជាសង់ទីម៉ែត្រ ដែលជាលេខគួរទុកចិត្តសម្រាប់ប្រៀបធៀបនឹងស្បែកជើងដែលអ្នកមានស្រាប់។",
    ],

    'sandals-flip-flops' => [
        'en' => "Sandals and flip flops from local shops — the footwear Phnom Penh actually wears most of the year. Leather, rubber and woven styles for men, women and children.",
        'km' => "ស្បែកជើងផ្ទាត់ពីហាងក្នុងស្រុក — ស្បែកជើងដែលភ្នំពេញពាក់ស្ទើរពេញមួយឆ្នាំ។ មានបែបស្បែក កៅស៊ូ និងត្បាញ សម្រាប់បុរស នារី និងកុមារ។",
    ],

    't-shirts' => [
        'en' => "Women's T-shirts from local sellers — plain, printed and locally designed. Printed tees are often made in small runs, so a design you like may not be restocked once it sells out.",
        'km' => "អាវយឺតនារីពីអ្នកលក់ក្នុងស្រុក — ធម្មតា បោះពុម្ព និងរចនាក្នុងស្រុក។ អាវយឺតបោះពុម្ពច្រើនតែផលិតជាចំនួនតិច ដូច្នេះម៉ូដដែលអ្នកចូលចិត្តអាចនឹងមិនមានផលិតបន្ថែមទេ បន្ទាប់ពីលក់អស់។",
    ],

    // ── Still to write ───────────────────────────────────────────────
    // Every remaining category, with its slug already correct. Fill in the
    // pair and the page picks it up on the next request — nothing else to
    // change. Empty entries are ignored, so leaving one blank is safe.

    'tops-t-shirts'              => ['en' => '', 'km' => ''],
    'shirts-dress-shirts'        => ['en' => '', 'km' => ''],
    'mens-hoodies-sweatshirts'   => ['en' => '', 'km' => ''],
    'mens-jackets-coats'         => ['en' => '', 'km' => ''],
    'mens-trousers-chinos'       => ['en' => '', 'km' => ''],
    'mens-jeans'                 => ['en' => '', 'km' => ''],
    'mens-shorts'                => ['en' => '', 'km' => ''],
    'suits-blazers'              => ['en' => '', 'km' => ''],
    'mens-activewear'            => ['en' => '', 'km' => ''],
    'underwear-socks'            => ['en' => '', 'km' => ''],
    'mens-sleepwear'             => ['en' => '', 'km' => ''],

    'tops-blouses'               => ['en' => '', 'km' => ''],
    'womens-hoodies-sweatshirts' => ['en' => '', 'km' => ''],
    'womens-jackets-coats'       => ['en' => '', 'km' => ''],
    'skirts'                     => ['en' => '', 'km' => ''],
    'womens-trousers-chinos'     => ['en' => '', 'km' => ''],
    'womens-jeans'               => ['en' => '', 'km' => ''],
    'womens-shorts'              => ['en' => '', 'km' => ''],
    'womens-activewear'          => ['en' => '', 'km' => ''],
    'underwear-lingerie'         => ['en' => '', 'km' => ''],
    'womens-sleepwear'           => ['en' => '', 'km' => ''],

    'boys-clothing'              => ['en' => '', 'km' => ''],
    'girls-clothing'             => ['en' => '', 'km' => ''],
    'baby-toddler'               => ['en' => '', 'km' => ''],

    'hats-caps'                  => ['en' => '', 'km' => ''],
    'belts'                      => ['en' => '', 'km' => ''],
    'wallets'                    => ['en' => '', 'km' => ''],
    'sunglasses'                 => ['en' => '', 'km' => ''],
    'watches'                    => ['en' => '', 'km' => ''],

    'mens-shoes'                 => ['en' => '', 'km' => ''],
    'womens-shoes'               => ['en' => '', 'km' => ''],
    'kids-shoes'                 => ['en' => '', 'km' => ''],
    'boots'                      => ['en' => '', 'km' => ''],

    'formal-ceremony'            => ['en' => '', 'km' => ''],
];
