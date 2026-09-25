<?php
// Changing the address book from the app — settings-buyer/address-book-action.php
// with JSON in and out. Every rule there is repeated:
//
//   - street, khan, sangkat and a map pin are required (address_missing_fields,
//     the same rule the cart and checkout enforce);
//   - lengths are cut to the columns; an empty label falls back to the khan;
//   - the city must be one teepsaa delivers in, else the first;
//   - the first save imports the legacy address on the buyers row;
//   - the first address becomes the default on its own;
//   - the default is mirrored onto the buyers row, which is what delivery
//     pricing and checkout read;
//   - deleting the default promotes the oldest one left, or clears the row;
//   - every query is scoped to this buyer.
//
// Two additions. The pin must fall inside the city outline — the website's map
// refuses a tap outside it, but nothing on the server did, and here there is no
// map in front of the request. And a khan or sangkat must be one from the list,
// which the website's dropdowns guarantee and a JSON body does not.
//
// Every reply carries the whole list again, so the screen redraws from it.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/delivery-address.php';
require __DIR__ . '/../../../config/buyer-addresses.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body   = api_body();
$action = (string)($body['action'] ?? '');

$allowed = ['add', 'edit', 'set_default', 'delete'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

function mirror_to_buyer(PDO $pdo, int $userId, ?array $a): void
{
    if ($a) {
        $pdo->prepare('UPDATE buyers SET house_number=?, address=?, address_notes=?, khan=?, sangkat=?, city=?, lat=?, lng=? WHERE id=?')
            ->execute([$a['house_number'], $a['address'], $a['address_notes'], $a['khan'], $a['sangkat'], $a['city'], $a['lat'], $a['lng'], $userId]);
    } else {
        $pdo->prepare('UPDATE buyers SET house_number=NULL, address=NULL, address_notes=NULL, khan=NULL, sangkat=NULL, city=NULL, lat=NULL, lng=NULL WHERE id=?')
            ->execute([$userId]);
    }
}

function reply(PDO $pdo, int $userId, array $extra = []): void
{
    api_json(['ok' => true] + $extra + ['addresses' => buyer_address_list($pdo, $userId)]);
}

function own_address(PDO $pdo, int $userId, $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE id = ? AND buyer_user_id = ?');
    $stmt->execute([(int)$id, $userId]);
    return $stmt->fetch() ?: null;
}

// ── Add or edit: read and check the form ─────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $existing = null;
    if ($action === 'edit') {
        $existing = own_address($pdo, $userId, $body['address_id'] ?? 0);
        if (!$existing) api_json(['error' => 'not_found'], 404);
    }

    $str = fn($k, $max) => mb_substr(trim((string)($body[$k] ?? '')), 0, $max);

    $cities   = require __DIR__ . '/../../../config/cities.php';
    $postCity = trim((string)($body['city'] ?? ''));
    $city     = in_array($postCity, $cities, true) ? $postCity : ($cities[0] ?? null);

    $label        = $str('label', 100);
    $houseNumber  = $str('house_number', 50) ?: null;
    $address      = $str('address', 255) ?: null;
    $addressNotes = $str('address_notes', 255) ?: null;
    $khan         = $str('khan', 100) ?: null;
    $sangkat      = $str('sangkat', 100) ?: null;
    $lat          = is_numeric($body['lat'] ?? null) ? (float)$body['lat'] : null;
    $lng          = is_numeric($body['lng'] ?? null) ? (float)$body['lng'] : null;

    $missing = address_missing_fields($address, $khan, $sangkat, $lat, $lng);
    if ($missing) api_json(['error' => 'incomplete', 'missing' => $missing], 422);

    $locations = require __DIR__ . '/../../../config/phnom-penh-locations.php';
    $fields = [];
    if (!isset($locations[$khan]))                                $fields['khan']    = 'Pick a khan from the list.';
    elseif (!in_array($sangkat, $locations[$khan], true))         $fields['sangkat'] = 'Pick a sangkat in that khan.';
    if (!point_in_city($lat, $lng))                               $fields['pin']     = 'Please select a location inside Phnom Penh.';
    if ($fields) api_json(['error' => 'invalid', 'fields' => $fields], 422);

    // Never blank — the checkout picker shows it.
    $label = $label ?: $khan;
}

// ── Add ──────────────────────────────────────────────────────────────
if ($action === 'add') {
    // First use of the address book: bring the old single address across so
    // it is not stranded on the buyers row.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buyer_addresses WHERE buyer_user_id = ?');
    $stmt->execute([$userId]);
    if ((int)$stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare('SELECT house_number, address, address_notes, khan, sangkat, city, lat, lng FROM buyers WHERE id = ?');
        $stmt->execute([$userId]);
        $b = $stmt->fetch();
        if ($b && ($b['address'] !== null || $b['khan'] !== null || $b['house_number'] !== null)) {
            $pdo->prepare('INSERT INTO buyer_addresses (buyer_user_id, label, house_number, address, address_notes, khan, sangkat, city, lat, lng, is_default) VALUES (?,?,?,?,?,?,?,?,?,?,1)')
                ->execute([$userId, $b['khan'] ?: 'Address', $b['house_number'], $b['address'], $b['address_notes'], $b['khan'], $b['sangkat'], $b['city'], $b['lat'], $b['lng']]);
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buyer_addresses WHERE buyer_user_id = ? AND is_default = 1');
    $stmt->execute([$userId]);
    $makeDefault = (int)$stmt->fetchColumn() === 0 ? 1 : 0;

    $pdo->prepare('INSERT INTO buyer_addresses (buyer_user_id, label, house_number, address, address_notes, khan, sangkat, city, lat, lng, is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$userId, $label, $houseNumber, $address, $addressNotes, $khan, $sangkat, $city, $lat, $lng, $makeDefault]);
    $newId = (int)$pdo->lastInsertId();

    if ($makeDefault) {
        mirror_to_buyer($pdo, $userId, [
            'house_number' => $houseNumber, 'address' => $address, 'address_notes' => $addressNotes,
            'khan' => $khan, 'sangkat' => $sangkat, 'city' => $city, 'lat' => $lat, 'lng' => $lng,
        ]);
    }

    reply($pdo, $userId, ['id' => $newId, 'made_default' => (bool)$makeDefault]);
}

// ── Edit ─────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $pdo->prepare('UPDATE buyer_addresses SET label=?, house_number=?, address=?, address_notes=?, khan=?, sangkat=?, city=?, lat=?, lng=? WHERE id=? AND buyer_user_id=?')
        ->execute([$label, $houseNumber, $address, $addressNotes, $khan, $sangkat, $city, $lat, $lng, (int)$existing['id'], $userId]);

    if ($existing['is_default']) {
        mirror_to_buyer($pdo, $userId, [
            'house_number' => $houseNumber, 'address' => $address, 'address_notes' => $addressNotes,
            'khan' => $khan, 'sangkat' => $sangkat, 'city' => $city, 'lat' => $lat, 'lng' => $lng,
        ]);
    }

    reply($pdo, $userId);
}

// ── Make one the default ─────────────────────────────────────────────
if ($action === 'set_default') {
    $addr = own_address($pdo, $userId, $body['address_id'] ?? 0);
    if (!$addr) api_json(['error' => 'not_found'], 404);

    $pdo->prepare('UPDATE buyer_addresses SET is_default = 0 WHERE buyer_user_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE buyer_addresses SET is_default = 1 WHERE id = ? AND buyer_user_id = ?')->execute([(int)$addr['id'], $userId]);
    mirror_to_buyer($pdo, $userId, $addr);

    reply($pdo, $userId);
}

// ── Delete ───────────────────────────────────────────────────────────
if ($action === 'delete') {
    $addr = own_address($pdo, $userId, $body['address_id'] ?? 0);
    // Already gone: two taps on the same button did what was asked.
    if (!$addr) reply($pdo, $userId, ['already_gone' => true]);

    $pdo->prepare('DELETE FROM buyer_addresses WHERE id = ? AND buyer_user_id = ?')->execute([(int)$addr['id'], $userId]);

    if ($addr['is_default']) {
        $stmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE buyer_user_id = ? ORDER BY created_at ASC, id ASC LIMIT 1');
        $stmt->execute([$userId]);
        $next = $stmt->fetch() ?: null;
        if ($next) {
            $pdo->prepare('UPDATE buyer_addresses SET is_default = 1 WHERE id = ?')->execute([(int)$next['id']]);
        }
        mirror_to_buyer($pdo, $userId, $next);
    }

    reply($pdo, $userId);
}
