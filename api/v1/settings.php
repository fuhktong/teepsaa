<?php
// Everything the app's Settings screen shows about the person signed in — the
// website's settings-vendor/?tab=account, minus the parts that are not the
// account itself.
//
// What is deliberately not here:
//   - The shop. Name, categories, address and bank details moved to their own
//     page on the website and their own screen in the app; business.php serves
//     those. This file is the person, not the business.
//   - Deleting the account. Both app stores require it and it gets its own
//     endpoint, because a screen that can erase everything should not share a
//     reply with one that changes a phone number.
//
// The avatar comes back two ways on purpose. `avatar` is a photo URL when one
// was uploaded on the website; when it is null the app draws the same coloured
// silhouette the site draws, which is why `avatar_color` and the palette are
// sent whether or not a photo exists. Without the palette the app would have to
// keep its own copy of five hex values and they would drift apart on the day
// someone changes one.
//
// image_variant() arrives with this: api.php requires db.php, which requires
// upload.php. Requiring it again here would be a redeclare fatal.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$stmt = $pdo->prepare('
    SELECT name, email, phone, avatar, avatar_color, lang
      FROM vendors
     WHERE id = ?
');
$stmt->execute([$userId]);
$row = $stmt->fetch();

// api_require_vendor() just resolved this id, so the row is there. Checked
// anyway rather than letting a null index turn into a 500 with no cause named.
if (!$row) api_json(['error' => 'not_found'], 404);

// The column is NULL until the vendor picks a colour. The website falls back to
// id % 5 so everyone has a colour from the first day, and the app has to land
// on the same one or the avatar would change the moment the app draws it.
$colorIdx = $row['avatar_color'] !== null ? (int)$row['avatar_color'] : abs($userId) % 5;

api_json([
    'vendor' => [
        'id'           => $userId,
        'name'         => $row['name'],
        'email'        => $row['email'],
        'phone'        => $row['phone'] ?? '',
        // w400 is the copy the website serves for an avatar too. The original
        // can be a 2MB phone photo shown at 64 pixels.
        'avatar'       => $row['avatar'] ? 'https://teepsaa.com' . image_variant($row['avatar'], 'w400') : null,
        'avatar_color' => $colorIdx,
        'lang'         => $row['lang'] ?: 'km',
    ],

    // Same five, in the same order, as _avatar_svg() in header/header.php. The
    // pair is [ring, figure] — the app draws the circle in the first and the
    // silhouette in the second.
    'palette' => [
        ['#4a86e8', '#a4c2f4'],
        ['#e06055', '#f4b8b4'],
        ['#f6b026', '#ffd966'],
        ['#57bb8a', '#a8d5b5'],
        ['#8e63ce', '#c3a6e8'],
    ],

    // What the columns actually hold, and the shortest password the server will
    // accept. Sent so the app can stop a save that is certain to be refused
    // instead of making the vendor wait for the refusal.
    'limits' => [
        'name'         => 255,
        'phone'        => 20,
        'password_min' => 8,
    ],
]);
