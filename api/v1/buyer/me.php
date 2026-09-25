<?php
// Who does this token belong to? The buyer app calls this on launch: a valid
// reply means signed in, a 401 means the token is dead —
// revoked, or the account suspended or deleted since — so wipe it and carry
// on signed out. api_require_buyer() re-runs the suspended / verified / deleted checks
// on every call, which is what makes that 401 trustworthy.

require __DIR__ . '/../../../config/api.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

api_json([
    'buyer' => [
        'id'   => (int)$buyer['id'],
        'name' => $buyer['name'],
        'lang' => $buyer['lang'] ?: 'km',
    ],
]);
