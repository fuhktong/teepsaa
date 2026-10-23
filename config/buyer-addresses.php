<?php
require_once __DIR__ . '/delivery-area.php';

// The buyer's address book, as the mobile API sends it. Where a pin may fall
// is config/delivery-area.php, pulled in here for the files that use both. Shared by api/v1/buyer/addresses.php (which shows
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

