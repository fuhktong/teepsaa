<?php
$_DELIVERY_CFG = require __DIR__ . '/delivery.php';
$_SHIPPING_CFG = require __DIR__ . '/shipping.php';
require_once __DIR__ . '/weights.php';

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function calculate_delivery(float $distance_km, string $vehicle_type = 'bike'): array {
    global $_DELIVERY_CFG;
    $cfg   = $_DELIVERY_CFG;
    $rates = $cfg[$vehicle_type] ?? $cfg['bike'];

    $raw = $rates['base_fare'] + $rates['per_km'] * $distance_km;
    $raw = max($rates['min_fare'], $raw);
    $fee = round($raw * (1 + $cfg['markup']), 2);

    return [
        'fee'          => $fee,
        'distance_km'  => round($distance_km, 2),
        'vehicle_type' => $vehicle_type,
    ];
}

/**
 * What delivering one shop's part of the cart costs the buyer. Shared by the
 * cart, checkout and the order itself, on the website and in the app, so the
 * fee shown is the fee charged. It prices only; the vendor chooses how the
 * parcel goes.
 *
 *   $lines   that shop's cart lines, each with 'weight_g' and 'quantity'
 *   $vehicle 'tuktuk' when any line is a tuk-tuk item, else 'bike'
 *
 * Returns one of:
 *   ['state' => 'no_pin']                     the shop or the buyer has no map pin
 *   ['state' => 'ok', 'fee', 'distance_km', 'weight_g', 'service' => 'grab'|'courier', 'vehicle_type']
 *   ['state' => 'out_of_range', 'reason' => 'weight', 'distance_km', 'max_km']
 *            past Grab's reach, with an item that has no weight or is over 10 kg.
 *            'out_of_range' is the state older app builds already handle.
 */
function price_delivery(?float $buyerLat, ?float $buyerLng, ?float $bizLat, ?float $bizLng,
                        array $lines, string $vehicle = 'bike'): array {
    global $_SHIPPING_CFG;
    $cfg = $_SHIPPING_CFG;

    if ($buyerLat === null || $buyerLng === null || $bizLat === null || $bizLng === null) {
        return ['state' => 'no_pin'];
    }

    $dist     = haversine_km($buyerLat, $buyerLng, $bizLat, $bizLng);
    $grams    = 0;
    $unpriced = false;
    foreach ($lines as $l) {
        $g = (int)$l['weight_g'];
        if ($g <= 0 || $g >= WEIGHT_OVER_MAX_G) $unpriced = true;
        $grams += $g * (int)$l['quantity'];
    }

    if ($dist <= $cfg['grab_max_km']) {
        $d = calculate_delivery($dist, $vehicle);
        return ['state' => 'ok', 'fee' => $d['fee'], 'distance_km' => $d['distance_km'],
                'weight_g' => $grams, 'service' => 'grab', 'vehicle_type' => $vehicle];
    }

    if ($unpriced) {
        return ['state' => 'out_of_range', 'reason' => 'weight',
                'distance_km' => round($dist, 1), 'max_km' => $cfg['grab_max_km']];
    }

    $bands = $cfg['courier_bands'];
    $last  = end($bands);
    $raw   = null;
    foreach ($bands as $b) {
        if ($grams <= $b['max_g']) { $raw = $b['fee']; break; }
    }
    if ($raw === null) {
        $raw = $last['fee'] + $cfg['courier_per_extra_kg'] * ceil(($grams - $last['max_g']) / 1000);
    }

    return ['state' => 'ok', 'fee' => round($raw * (1 + $cfg['courier_markup']), 2),
            'distance_km' => round($dist, 2), 'weight_g' => $grams,
            'service' => 'courier', 'vehicle_type' => $vehicle];
}
