<?php
// The buyer app's address book screen — settings-buyer/?tab=address. The saved
// addresses, and everything the add/edit form needs so the app holds no copy
// of its own: the khan → sangkat lists, the cities delivered to, and the map.
//
// The map token is the same public pk. token the website puts in its pages.
// It is sent rather than built into the app so it can be rotated with a
// website deploy instead of a store upload.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/delivery-address.php';
require __DIR__ . '/../../../config/buyer-addresses.php';
require __DIR__ . '/../../../config/mapbox.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$stmt = $pdo->prepare('SELECT phone, address, khan, sangkat, lat, lng FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch() ?: [];

api_json([
    'addresses' => buyer_address_list($pdo, $userId),

    // What checkout would refuse on right now — the default address (mirrored
    // on the buyers row) plus the phone.
    'missing'   => buyer_missing_fields($row),

    'cities'    => require __DIR__ . '/../../../config/cities.php',

    // { "Chamkar Mon": ["Boeng Trabaek", …], … } — keys keep the file's order.
    'locations' => require __DIR__ . '/../../../config/phnom-penh-locations.php',

    'limits' => [
        'label'         => 100,
        'house_number'  => 50,
        'address'       => 255,
        'address_notes' => 255,
    ],

    // settings-buyer/index.php's map, value for value.
    'map' => [
        'token'    => MAPBOX_TOKEN,
        'style'    => 'mapbox://styles/mapbox/streets-v12',
        'center'   => [104.9160, 11.5564],
        'zoom'     => 13,
        'bounds'   => [[104.654628, 11.324807], [105.055619, 11.737473]],
        // [[lat, lng], …] from js/boundary.js, the order pointInPolygon() takes.
        'boundary' => city_boundary(),
    ],
]);
