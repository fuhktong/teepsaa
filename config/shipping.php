<?php
// How far Grab goes, and what a courier costs beyond that. Read by
// price_delivery() in config/delivery-calc.php — every cart, checkout and
// order in the website and both apps is priced from this one file.
//
// The courier prices are J&T Express's Phnom Penh-to-province rates
// (2026-10-25: 1 kg 6,000 KHR, 2 kg 6,600 KHR), at 4,000 KHR = $1 as in
// config/delivery.php. J&T quoted only 1 and 2 kg; the heavier bands carry on
// at their +600 KHR a kilo.
//
// Within grab_max_km of the shop: the Grab formula in config/delivery.php.
// Beyond it: the courier band for the shop's total packed weight, plus
// courier_markup — the same idea as Grab's markup, so the vendor is covered
// when the real price comes in a little higher.
//
// A shop's part of the cart can only go by Grab when any item in it has no
// weight (weight_g = 0, made before weight was asked for) or is "more than
// 10 kg" (config/weights.php) — neither can be priced from a band.
return [
    'grab_max_km' => 25,

    // Smallest first. A shop's weight takes the first band it fits under.
    'courier_bands' => [
        ['max_g' => 1000,  'fee' => 1.50],   //  6,000 KHR
        ['max_g' => 2000,  'fee' => 1.65],   //  6,600 KHR
        ['max_g' => 3000,  'fee' => 1.80],   //  7,200 KHR
        ['max_g' => 5000,  'fee' => 2.10],   //  8,400 KHR
        ['max_g' => 10000, 'fee' => 2.85],   // 11,400 KHR
    ],
    // Past the last band: its fee plus this for each kilo or part of one over.
    'courier_per_extra_kg' => 0.15,          //    600 KHR
    'courier_markup'       => 0.20,

    // How long cron/auto-confirm.php waits after dispatch before marking a
    // courier parcel delivered for a buyer who never pressed "received". By
    // the straight-line distance from the shop, which is shorter than the road,
    // so the edges leave slack. Too short is the costly mistake: the 24-hour
    // refund window would close before the parcel arrived. Grab orders keep
    // 24 hours.
    'courier_confirm_days' => [
        ['max_km' => 50,  'days' => 2],
        ['max_km' => 200, 'days' => 4],
    ],
    // Further than the last tier, or a courier order with no recorded distance.
    'courier_confirm_days_far' => 7,
];
