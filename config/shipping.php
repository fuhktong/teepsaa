<?php
// How far Grab goes, and what a courier costs beyond that. Read by
// price_delivery() in config/delivery-calc.php — every cart, checkout and
// order in the website and both apps is priced from this one file.
//
// THE COURIER PRICES BELOW ARE MADE UP (2026-10-24), as placeholders until the
// real courier price lists are in (decisions D and E). Replace them before
// cities.php lists a second province.
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
        ['max_g' => 1000,  'fee' => 1.50],
        ['max_g' => 3000,  'fee' => 2.50],
        ['max_g' => 5000,  'fee' => 3.50],
        ['max_g' => 10000, 'fee' => 5.00],
    ],
    // Past the last band: its fee plus this for each kilo or part of one over.
    'courier_per_extra_kg' => 0.50,
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
