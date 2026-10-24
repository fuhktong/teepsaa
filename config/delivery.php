<?php
// Grab Express Cambodia published rates (KHR converted at 4,000 KHR = $1 USD)
return [
    'bike' => [
        'base_fare' => 0.50,   // 2,000 KHR
        'per_km'    => 0.225,  // 900 KHR/km
        'min_fare'  => 0.63,   // 2,500 KHR
    ],
    'tuktuk' => [
        'base_fare' => 0.50,   // 2,000 KHR
        'per_km'    => 0.30,   // 1,200 KHR/km
        'min_fare'  => 1.00,   // 4,000 KHR
    ],
    // 20% on top of Grab's published fare. The buyer pays this at checkout
    // and the vendor gets it with the payout, so rush hour or rain costs the
    // vendor nothing up to that much. How far Grab goes is config/shipping.php.
    'markup'       => 0.20,
];
