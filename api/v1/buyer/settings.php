<?php
// Everything the buyer app's Account screen shows about the person signed in —
// the website's settings-buyer/?tab=account. The buyer version of
// api/v1/settings.php, and shaped the same so the two apps read alike.
//
// Addresses are not here; they are addresses.php, because that screen also
// needs the map and the khan list and this one does not.
//
// `missing` is buyer_missing_fields() on the buyers row — the list checkout
// refuses on. Sent so the Account tab can say "add a phone number" before the
// buyer finds out at the till.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/delivery-address.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$stmt = $pdo->prepare('
    SELECT name, email, phone, avatar, avatar_color, lang, address, khan, sangkat, lat, lng
      FROM buyers
     WHERE id = ?
');
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) api_json(['error' => 'not_found'], 404);

// NULL until a colour is picked; the website falls back to id % 5.
$colorIdx = $row['avatar_color'] !== null ? (int)$row['avatar_color'] : abs($userId) % 5;

api_json([
    'buyer' => [
        'id'           => $userId,
        'name'         => $row['name'],
        'email'        => $row['email'],
        'phone'        => $row['phone'] ?? '',
        'avatar'       => $row['avatar'] ? 'https://teepsaa.com' . image_variant($row['avatar'], 'w400') : null,
        'avatar_color' => $colorIdx,
        'lang'         => $row['lang'] ?: 'km',
    ],

    // _avatar_svg() in header/header.php, [ring, figure].
    'palette' => [
        ['#4a86e8', '#a4c2f4'],
        ['#e06055', '#f4b8b4'],
        ['#f6b026', '#ffd966'],
        ['#57bb8a', '#a8d5b5'],
        ['#8e63ce', '#c3a6e8'],
    ],

    'limits' => [
        'name'         => 255,
        'phone'        => 20,
        'password_min' => 8,
        'photo_mb'     => 2,
    ],

    'missing' => buyer_missing_fields($row),
]);
