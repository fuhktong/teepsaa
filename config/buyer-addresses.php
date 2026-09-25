<?php
// The buyer's address book, as the mobile API sends it, and the city boundary
// a pin has to fall inside. Shared by api/v1/buyer/addresses.php (which shows
// them) and api/v1/buyer/address-action.php (which changes them and answers
// with the new list), so the two can never describe an address differently.

/** Every saved address, default first, then oldest first — the website's order. */
function buyer_address_list(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT id, label, house_number, address, address_notes, khan, sangkat, city, lat, lng, is_default
          FROM buyer_addresses
         WHERE buyer_user_id = ?
         ORDER BY is_default DESC, created_at ASC, id ASC
    ');
    $stmt->execute([$userId]);

    $out = [];
    foreach ($stmt->fetchAll() as $a) {
        $lat = $a['lat'] !== null ? (float)$a['lat'] : null;
        $lng = $a['lng'] !== null ? (float)$a['lng'] : null;
        $out[] = [
            'id'            => (int)$a['id'],
            'label'         => $a['label'],
            'house_number'  => $a['house_number'] ?? '',
            'address'       => $a['address'] ?? '',
            'address_notes' => $a['address_notes'] ?? '',
            'khan'          => $a['khan'] ?? '',
            'sangkat'       => $a['sangkat'] ?? '',
            'city'          => $a['city'] ?? '',
            'lat'           => $lat,
            'lng'           => $lng,
            'is_default'    => (bool)$a['is_default'],
            // An address saved before the completeness rule existed can still
            // be missing something. Checkout will send the buyer back for it,
            // so the list says so up front.
            'missing'       => address_missing_fields($a['address'], $a['khan'], $a['sangkat'], $lat, $lng),
        ];
    }
    return $out;
}

/**
 * The Phnom Penh outline as [[lat, lng], …], read from js/boundary.js — the file
 * the website's maps draw and test against. Read rather than copied so there
 * is one outline: move a corner there and the app and this check both follow.
 */
function city_boundary(): array
{
    static $ring = null;
    if ($ring !== null) return $ring;

    $ring = [];
    $js   = @file_get_contents(__DIR__ . '/../js/boundary.js');
    if ($js !== false && preg_match('/CITY_BOUNDARY\s*=\s*\[(.*?)\];/s', $js, $m)) {
        preg_match_all('/\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\]/', $m[1], $pairs, PREG_SET_ORDER);
        foreach ($pairs as $p) $ring[] = [(float)$p[1], (float)$p[2]];
    }
    return $ring;
}

/** pointInPolygon() from js/boundary.js, line for line. */
function point_in_city(float $lat, float $lng): bool
{
    $poly = city_boundary();
    // No outline to test against is a server fault, not the buyer's pin.
    if (count($poly) < 3) return true;

    $inside = false;
    for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
        [$latI, $lngI] = $poly[$i];
        [$latJ, $lngJ] = $poly[$j];
        $intersect = (($lngI > $lng) !== ($lngJ > $lng))
            && ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}
