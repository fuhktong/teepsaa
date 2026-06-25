<?php
$_DELIVERY_CFG = require __DIR__ . '/delivery.php';

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
