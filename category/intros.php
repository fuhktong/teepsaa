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

    'tops-t-shirts' => [

        'en' => "Men's T-shirts and tops from Phnom Penh sellers — plain cotton, printed and locally designed. In this heat most buyers go for light cotton over polyester blends, and listings usually name the fabric.",

        'km' => "អាវយឺត និងអាវខ្លីបុរសពីអ្នកលក់ក្នុងភ្នំពេញ — កប្បាសធម្មតា បោះពុម្ព និងរចនាក្នុងស្រុក។ ក្នុងអាកាសធាតុក្តៅនេះ អ្នកទិញភាគច្រើនជ្រើសរើសកប្បាសស្រាលជាងក្រណាត់សំយោគ ហើយការផ្សាយច្រើនតែបញ្ជាក់ប្រភេទក្រណាត់។",

    ],
    'shirts-dress-shirts' => [
        'en' => "Men's shirts and dress shirts from local shops — office, formal and casual short-sleeve. Check the listed chest and sleeve measurements rather than the size letter; Cambodian, Thai and Chinese sizing all run differently.",
        'km' => "អាវសឺមី និងអាវផ្លូវការបុរសពីហាងក្នុងស្រុក — សម្រាប់ការិយាល័យ ពិធីផ្លូវការ និងអាវដៃខ្លីធម្មតា។ សូមពិនិត្យទំហំទ្រូង និងដៃអាវដែលបានបញ្ជាក់ ជាជាងអក្សរទំហំ ព្រោះទំហំខ្មែរ ថៃ និងចិន សុទ្ធតែខុសគ្នា។",
    ],
    'mens-hoodies-sweatshirts' => [
        'en' => "Hoodies and sweatshirts from Phnom Penh sellers. Mostly bought for evening motorbike rides and heavily air-conditioned offices rather than cold weather, so lighter cotton blends outsell heavy fleece here.",
        'km' => "អាវហូឌី និងអាវយឺតដៃវែងបុរសពីអ្នកលក់ក្នុងភ្នំពេញ។ ភាគច្រើនទិញសម្រាប់ជិះម៉ូតូពេលល្ងាច និងការិយាល័យត្រជាក់ ជាជាងអាកាសធាតុរងា ដូច្នេះក្រណាត់កប្បាសស្រាលលក់ដាច់ជាងអាវក្រាស់។",
    ],
    'mens-jackets-coats' => [
        'en' => "Men's jackets and coats from local shops — windbreakers, rain shells, denim and light bombers. Rainy season and long motorbike commutes are what most of these are bought for; check whether a listing is water-resistant or only showerproof.",
        'km' => "អាវក្រៅបុរសពីហាងក្នុងស្រុក — អាវការពារខ្យល់ អាវការពារភ្លៀង អាវខូវប៊យ និងអាវបុមបឺរស្រាល។ ភាគច្រើនទិញសម្រាប់រដូវវស្សា និងការជិះម៉ូតូផ្លូវឆ្ងាយ។ សូមពិនិត្យថាតើអាវនោះការពារទឹកបាន ឬត្រឹមតែទប់ភ្លៀងតិចតួច។",
    ],
    'mens-trousers-chinos' => [
        'en' => "Chinos and trousers from Phnom Penh sellers — office wear, cotton chinos and lightweight formal trousers. Waist is quoted in inches on most listings and in centimetres on some, so read which before ordering.",
        'km' => "ខោឆាណូ និងខោវែងបុរសពីអ្នកលក់ក្នុងភ្នំពេញ — ខោការិយាល័យ ខោឆាណូកប្បាស និងខោផ្លូវការស្រាល។ ទំហំចង្កេះបញ្ជាក់ជាអ៊ីញនៅការផ្សាយភាគច្រើន និងជាសង់ទីម៉ែត្រនៅខ្លះ ដូច្នេះសូមអានឱ្យច្បាស់មុននឹងកម្មង់។",
    ],
    'mens-jeans' => [
        'en' => "Men's jeans from local shops — straight, slim and relaxed cuts in light and dark denim. Lighter-weight denim is worth looking for in this climate, and the waist and inside-leg figures matter more than the size label.",
        'km' => "ខោខូវប៊យបុរសពីហាងក្នុងស្រុក — បែបត្រង់ បែបស្តើង និងបែបធូរ ទាំងពណ៌ភ្លឺ និងពណ៌ចាស់។ ក្នុងអាកាសធាតុនេះ គួររកក្រណាត់ខូវប៊យស្រាល ហើយទំហំចង្កេះ និងប្រវែងខោសំខាន់ជាងស្លាកទំហំ។",
    ],
    'mens-shorts' => [
        'en' => "Men's shorts from Phnom Penh sellers — cotton, cargo, sports and swim shorts. Worn year-round here, so this is one of the categories where local sellers restock fastest.",
        'km' => "ខោខ្លីបុរសពីអ្នកលក់ក្នុងភ្នំពេញ — កប្បាស ខោហោប៉ៅ កីឡា និងខោហែលទឹក។ ពាក់បានពេញមួយឆ្នាំនៅទីនេះ ដូច្នេះនេះជាប្រភេទមួយដែលអ្នកលក់ក្នុងស្រុកបញ្ចូលទំនិញថ្មីលឿនជាងគេ។",
    ],
    'suits-blazers' => [
        'en' => "Suits and blazers from Phnom Penh tailors and shops, for weddings, office and formal events. Ask the seller about lightweight or half-lined construction — a full-weight suit is hard work in this climate, and several sellers here will alter to fit.",
        'km' => "ឈុតបុរស និងអាវធំពីជាងកាត់ដេរ និងហាងក្នុងភ្នំពេញ សម្រាប់ពិធីមង្គលការ ការិយាល័យ និងកម្មវិធីផ្លូវការ។ សូមសួរអ្នកលក់អំពីឈុតស្រាល ឬឈុតស្រទាប់ពាក់កណ្តាល — ឈុតក្រាស់ពិបាកពាក់ក្នុងអាកាសធាតុនេះ ហើយអ្នកលក់មួយចំនួនអាចកែតម្រូវទំហំបាន។",
    ],
    'mens-activewear' => [
        'en' => "Men's activewear from local sellers — running, gym and football kit. Quick-dry synthetic fabric is what most people buy for early-morning runs along the riverside; the listing usually names the material.",
        'km' => "សម្លៀកបំពាក់កីឡាបុរសពីអ្នកលក់ក្នុងស្រុក — សម្រាប់រត់ ហាត់ប្រាណ និងបាល់ទាត់។ ក្រណាត់សំយោគស្ងួតលឿនជាទីនិយមសម្រាប់ការរត់ពេលព្រឹកតាមមាត់ទន្លេ ហើយការផ្សាយច្រើនតែបញ្ជាក់ប្រភេទក្រណាត់។",
    ],
    'underwear-socks' => [
        'en' => "Men's underwear and socks from Phnom Penh sellers. Cotton and cotton-blend for the heat, sold as singles and in multipacks — check the quantity, as prices here are quoted both ways.",
        'km' => "ខោទ្រនាប់ និងស្រោមជើងបុរសពីអ្នកលក់ក្នុងភ្នំពេញ។ កប្បាស និងកប្បាសលាយសម្រាប់អាកាសធាតុក្តៅ លក់ជាដុំៗ និងជាកញ្ចប់។ សូមពិនិត្យចំនួនក្នុងការផ្សាយ ព្រោះតម្លៃមានទាំងពីរបែប។",
    ],
    'mens-sleepwear' => [
        'en' => "Men's sleepwear from local shops — cotton shorts sets, pyjamas and lounge wear. Light cotton is the practical choice for a Phnom Penh bedroom, with or without air conditioning.",
        'km' => "ឈុតគេងបុរសពីហាងក្នុងស្រុក — ឈុតខោខ្លីកប្បាស ឈុតគេង និងសម្លៀកបំពាក់សម្រាកនៅផ្ទះ។ កប្បាសស្រាលជាជម្រើសសមរម្យសម្រាប់បន្ទប់គេងនៅភ្នំពេញ ទោះមានម៉ាស៊ីនត្រជាក់ឬអត់។",
    ],
    'tops-blouses' => [
        'en' => "Women's tops and blouses from Phnom Penh shops — office blouses, casual tops and locally designed pieces. Many are made in small runs by the seller, so sizes and colours are limited and rarely restocked.",
        'km' => "អាវនារីពីហាងក្នុងភ្នំពេញ — អាវការិយាល័យ អាវធម្មតា និងម៉ូដរចនាក្នុងស្រុក។ ភាគច្រើនផលិតជាចំនួនតិចដោយអ្នកលក់ផ្ទាល់ ដូច្នេះទំហំ និងពណ៌មានកំណត់ ហើយកម្រមានផលិតបន្ថែម។",
    ],
    'womens-hoodies-sweatshirts' => [
        'en' => "Women's hoodies and sweatshirts from local sellers. Bought here for cold offices, night markets and motorbike rides rather than winter, so the lighter fabrics are the more useful buy.",
        'km' => "អាវហូឌី និងអាវយឺតដៃវែងនារីពីអ្នកលក់ក្នុងស្រុក។ នៅទីនេះទិញសម្រាប់ការិយាល័យត្រជាក់ ផ្សារយប់ និងការជិះម៉ូតូ ជាជាងរដូវរងា ដូច្នេះក្រណាត់ស្រាលមានប្រយោជន៍ជាង។",
    ],
    'womens-jackets-coats' => [
        'en' => "Women's jackets and coats from Phnom Penh sellers — light jackets, rain shells, denim and cardigans. Rainy season and air-conditioned offices are the two reasons most of these sell.",
        'km' => "អាវក្រៅនារីពីអ្នកលក់ក្នុងភ្នំពេញ — អាវក្រៅស្រាល អាវការពារភ្លៀង អាវខូវប៊យ និងអាវយឺតរុំ។ រដូវវស្សា និងការិយាល័យត្រជាក់ជាមូលហេតុសំខាន់ពីរដែលធ្វើឱ្យអាវទាំងនេះលក់ដាច់។",
    ],
    'skirts' => [
        'en' => "Skirts from Phnom Penh shops — office pencil skirts, casual midi and long skirts, and modern cuts in traditional fabric. Check the listed length; the same description covers very different hemlines.",
        'km' => "សំពត់ពីហាងក្នុងភ្នំពេញ — សំពត់ការិយាល័យ សំពត់ធម្មតាបណ្តោយកណ្តាល សំពត់វែង និងម៉ូដទំនើបធ្វើពីក្រណាត់ប្រពៃណី។ សូមពិនិត្យប្រវែងដែលបានបញ្ជាក់ ព្រោះការពិពណ៌នាដូចគ្នាអាចមានប្រវែងខុសគ្នាឆ្ងាយ។",
    ],
    'womens-trousers-chinos' => [
        'en' => "Women's trousers and chinos from local shops — office trousers, wide-leg, cotton chinos and lightweight formal cuts. The waist and hip measurements in the listing are more reliable than S/M/L.",
        'km' => "ខោវែង និងខោឆាណូនារីពីហាងក្នុងស្រុក — ខោការិយាល័យ ខោជើងធំ ខោឆាណូកប្បាស និងខោផ្លូវការស្រាល។ ទំហំចង្កេះ និងត្រគាកក្នុងការផ្សាយគួរទុកចិត្តជាងអក្សរ S/M/L។",
    ],
    'womens-jeans' => [
        'en' => "Women's jeans from Phnom Penh sellers — skinny, straight, wide-leg and high-waisted. Denim weight varies a lot between listings, and the lighter ones are far more wearable most of the year here.",
        'km' => "ខោខូវប៊យនារីពីអ្នកលក់ក្នុងភ្នំពេញ — បែបស្តើង ត្រង់ ជើងធំ និងចង្កេះខ្ពស់។ ក្រណាត់មានកម្រាស់ខុសៗគ្នាច្រើន ហើយបែបស្រាលងាយពាក់ជាងស្ទើរពេញមួយឆ្នាំនៅទីនេះ។",
    ],
    'womens-shorts' => [
        'en' => "Women's shorts from local sellers — denim, cotton, tailored and sports. Worn year-round in Phnom Penh, so stock moves quickly and new designs appear often.",
        'km' => "ខោខ្លីនារីពីអ្នកលក់ក្នុងស្រុក — ខូវប៊យ កប្បាស ខោកាត់ដេរ និងខោកីឡា។ ពាក់បានពេញមួយឆ្នាំនៅភ្នំពេញ ដូច្នេះទំនិញលក់ដាច់លឿន ហើយម៉ូដថ្មីចេញញឹកញាប់។",
    ],
    'womens-activewear' => [
        'en' => "Women's activewear from Phnom Penh sellers — leggings, sports bras, gym and yoga wear. Quick-dry fabric matters more than anything else in this humidity, and most listings name the material.",
        'km' => "សម្លៀកបំពាក់កីឡានារីពីអ្នកលក់ក្នុងភ្នំពេញ — ខោលេគីង អាវទ្រនាប់កីឡា សម្លៀកបំពាក់ហាត់ប្រាណ និងយូហ្គា។ ក្រណាត់ស្ងួតលឿនសំខាន់ជាងអ្វីទាំងអស់ក្នុងអាកាសធាតុសើមនេះ ហើយការផ្សាយភាគច្រើនបញ្ជាក់ប្រភេទក្រណាត់។",
    ],
    'underwear-lingerie' => [
        'en' => "Women's underwear and lingerie from local sellers. Sizing varies widely between brands here, so compare the measurements in the listing rather than the cup and band letters, and message the seller if they aren't given.",
        'km' => "ខោអាវទ្រនាប់នារីពីអ្នកលក់ក្នុងស្រុក។ ទំហំខុសគ្នាច្រើនរវាងម៉ាកនីមួយៗ ដូច្នេះសូមប្រៀបធៀបទំហំជាក់ស្តែងក្នុងការផ្សាយ ជាជាងអក្សរទំហំ ហើយផ្ញើសារសួរអ្នកលក់ បើគេមិនបានបញ្ជាក់។",
    ],
    'womens-sleepwear' => [
        'en' => "Women's sleepwear from Phnom Penh shops — cotton sets, nightdresses and lounge wear. Light, breathable cotton is what sells here; satin and synthetic sets are stocked more as gifts.",
        'km' => "ឈុតគេងនារីពីហាងក្នុងភ្នំពេញ — ឈុតកប្បាស រ៉ូបគេង និងសម្លៀកបំពាក់សម្រាកនៅផ្ទះ។ កប្បាសស្រាល និងខ្យល់ចេញចូលបានល្អគឺជាទីនិយម ចំណែកឈុតសាទីន និងក្រណាត់សំយោគ ភាគច្រើនសម្រាប់ជាអំណោយ។",
    ],
    'boys-clothing' => [
        'en' => "Boys' clothing from Phnom Penh sellers — school basics, T-shirts, shorts, trousers and outfits for family occasions. Listings usually give an age range and a height in centimetres; the height is the more reliable of the two.",
        'km' => "សម្លៀកបំពាក់ក្មេងប្រុសពីអ្នកលក់ក្នុងភ្នំពេញ — សម្លៀកបំពាក់សាលា អាវយឺត ខោខ្លី ខោវែង និងឈុតសម្រាប់ពិធីគ្រួសារ។ ការផ្សាយច្រើនតែបញ្ជាក់អាយុ និងកម្ពស់ជាសង់ទីម៉ែត្រ ហើយកម្ពស់គួរទុកចិត្តជាង។",
    ],
    'girls-clothing' => [
        'en' => "Girls' clothing from local shops — everyday wear, school basics, dresses and outfits for weddings and ceremonies. Sizes are given by age and by height; measure once and shop by centimetres.",
        'km' => "សម្លៀកបំពាក់ក្មេងស្រីពីហាងក្នុងស្រុក — សម្លៀកបំពាក់ប្រចាំថ្ងៃ សម្លៀកបំពាក់សាលា រ៉ូប និងឈុតសម្រាប់ពិធីមង្គលការ និងពិធីបុណ្យ។ ទំហំបញ្ជាក់តាមអាយុ និងកម្ពស់។ វាស់ម្តង រួចទិញតាមសង់ទីម៉ែត្រ។",
    ],
    'baby-toddler' => [
        'en' => "Baby and toddler clothing from Phnom Penh sellers — bodysuits, sets, sleepwear and going-out outfits. Soft cotton is worth insisting on in this heat, and it is fine to message a seller about the fabric before ordering.",
        'km' => "សម្លៀកបំពាក់ទារក និងកុមារតូចពីអ្នកលក់ក្នុងភ្នំពេញ — ឈុតរុំខ្លួន ឈុតពេញ ឈុតគេង និងឈុតសម្រាប់ចេញក្រៅ។ គួរជ្រើសរើសកប្បាសទន់ក្នុងអាកាសធាតុក្តៅនេះ ហើយអ្នកអាចផ្ញើសារសួរអ្នកលក់អំពីក្រណាត់មុននឹងកម្មង់។",
    ],
    'hats-caps' => [
        'en' => "Hats and caps from local sellers — baseball caps, bucket hats, sun hats and straw. Sun protection on a motorbike is what most of these are bought for, so brim width is worth a look.",
        'km' => "មួកពីអ្នកលក់ក្នុងស្រុក — មួកកីឡា មួកធុង មួកការពារថ្ងៃ និងមួកចំបើង។ ភាគច្រើនទិញសម្រាប់ការពារកម្តៅថ្ងៃពេលជិះម៉ូតូ ដូច្នេះទទឹងគែមមួកជាចំណុចគួរពិនិត្យ។",
    ],
    'belts' => [
        'en' => "Belts from Phnom Penh shops — leather, faux leather and woven, for work and everyday. Measure a belt you already own from the buckle to the hole you use; that number is what to match, not your trouser size.",
        'km' => "ខ្សែក្រវាត់ពីហាងក្នុងភ្នំពេញ — ស្បែក ស្បែកសិប្បនិម្មិត និងបែបត្បាញ សម្រាប់ការងារ និងប្រចាំថ្ងៃ។ សូមវាស់ខ្សែក្រវាត់ដែលអ្នកមានស្រាប់ ពីក្បាលដល់រន្ធដែលអ្នកប្រើ។ លេខនោះទើបជាលេខត្រូវប្រៀបធៀប មិនមែនទំហំខោទេ។",
    ],
    'wallets' => [
        'en' => "Wallets and card holders from local sellers — leather, canvas and handmade. A common gift here, and several sellers on teepsaa will emboss initials on request; message them before ordering.",
        'km' => "កាបូបលុយ និងកាបូបដាក់កាតពីអ្នកលក់ក្នុងស្រុក — ស្បែក ក្រណាត់កាន់វ៉ាស់ និងផលិតដោយដៃ។ ជាអំណោយពេញនិយមនៅទីនេះ ហើយអ្នកលក់មួយចំនួននៅ teepsaa អាចឆ្លាក់អក្សរផ្តើមឈ្មោះបាន បើស្នើសុំ។ សូមផ្ញើសារមុននឹងកម្មង់។",
    ],
    'sunglasses' => [
        'en' => "Sunglasses from Phnom Penh sellers — everyday, sports and driving styles. Worth checking that a listing states UV protection rather than tint alone, especially for a daily motorbike commute.",
        'km' => "វ៉ែនតាការពារថ្ងៃពីអ្នកលក់ក្នុងភ្នំពេញ — បែបប្រចាំថ្ងៃ បែបកីឡា និងបែបសម្រាប់បើកបរ។ គួរពិនិត្យថាការផ្សាយបញ្ជាក់ការការពារកាំរស្មី UV មិនត្រឹមតែពណ៌កញ្ចក់ទេ ជាពិសេសសម្រាប់អ្នកជិះម៉ូតូរាល់ថ្ងៃ។",
    ],
    'watches' => [
        'en' => "Watches from local sellers — analogue, digital and smart watches, dress and everyday. Check whether a listing says water-resistant and to what depth; rainy season is hard on a watch that isn't.",
        'km' => "នាឡិកាដៃពីអ្នកលក់ក្នុងស្រុក — បែបទ្រនិច បែបឌីជីថល និងនាឡិកាឆ្លាតវៃ ទាំងបែបផ្លូវការ និងប្រចាំថ្ងៃ។ សូមពិនិត្យថាការផ្សាយបញ្ជាក់ការទប់ទឹក និងកម្រិតជម្រៅប៉ុន្មាន ព្រោះរដូវវស្សាពិបាកសម្រាប់នាឡិកាដែលមិនទប់ទឹក។",
    ],
    'mens-shoes' => [
        'en' => "Men's shoes from Phnom Penh shops — formal, loafers, casual and work shoes. Most listings give the inner length in centimetres alongside the EU or UK size, and that measurement is the one to trust.",
        'km' => "ស្បែកជើងបុរសពីហាងក្នុងភ្នំពេញ — បែបផ្លូវការ ស្បែកជើងស្លីបអន ស្បែកជើងធម្មតា និងស្បែកជើងការងារ។ ការផ្សាយភាគច្រើនបញ្ជាក់ប្រវែងខាងក្នុងជាសង់ទីម៉ែត្រ ជាមួយទំហំ EU ឬ UK ហើយប្រវែងនោះទើបគួរទុកចិត្តបំផុត។",
    ],
    'womens-shoes' => [
        'en' => "Women's shoes from local sellers — flats, heels, loafers and everyday styles. Sizing differs between brands, so compare the inner length in centimetres against a pair you already wear.",
        'km' => "ស្បែកជើងនារីពីអ្នកលក់ក្នុងស្រុក — បែបរាបស្មើ កែងខ្ពស់ ស្លីបអន និងបែបប្រចាំថ្ងៃ។ ទំហំខុសគ្នាតាមម៉ាក ដូច្នេះសូមប្រៀបធៀបប្រវែងខាងក្នុងជាសង់ទីម៉ែត្រ នឹងគូដែលអ្នកពាក់ស្រាប់។",
    ],
    'kids-shoes' => [
        'en' => "Children's shoes from Phnom Penh sellers — school shoes, sandals, sneakers and first walkers. Measure the child's foot in centimetres and buy to that; age labels vary far too much between sellers.",
        'km' => "ស្បែកជើងកុមារពីអ្នកលក់ក្នុងភ្នំពេញ — ស្បែកជើងសាលា ស្បែកជើងផ្ទាត់ ស្បែកជើងកីឡា និងស្បែកជើងសម្រាប់ក្មេងទើបដើរ។ សូមវាស់ជើងកូនជាសង់ទីម៉ែត្រ រួចទិញតាមលេខនោះ ព្រោះស្លាកតាមអាយុខុសគ្នាឆ្ងាយពេកតាមអ្នកលក់នីមួយៗ។",
    ],
    'boots' => [
        'en' => "Boots from local shops — ankle, work and fashion boots. A practical rainy-season buy when the streets flood; check the listing for the shaft height and whether the upper is treated.",
        'km' => "ស្បែកជើងកវែងពីហាងក្នុងស្រុក — បែបកវែងកជើង បែបការងារ និងបែបម៉ូដ។ ជាជម្រើសសមរម្យសម្រាប់រដូវវស្សាពេលផ្លូវជន់ទឹក។ សូមពិនិត្យកម្ពស់កស្បែកជើង និងថាតើផ្នែកខាងលើមានលាបការពារទឹកឬអត់។",
    ],
    'formal-ceremony' => [
        'en' => "Formal and ceremonial wear from Phnom Penh sellers — outfits for weddings, Pchum Ben, Khmer New Year and family ceremonies, in both traditional and modern cuts. Many pieces are made to order, so ask about timing before a fixed date.",
        'km' => "សម្លៀកបំពាក់ផ្លូវការ និងពិធីបុណ្យពីអ្នកលក់ក្នុងភ្នំពេញ — ឈុតសម្រាប់ពិធីមង្គលការ បុណ្យភ្ជុំបិណ្ឌ បុណ្យចូលឆ្នាំខ្មែរ និងពិធីគ្រួសារ ទាំងបែបប្រពៃណី និងទំនើប។ ទំនិញជាច្រើនកាត់ដេរតាមការកម្មង់ ដូច្នេះសូមសួរអំពីរយៈពេលមុនកាលបរិច្ឆេទកំណត់។",
    ],
];
