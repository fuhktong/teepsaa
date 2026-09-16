<?php
// Who does this token belong to? The app calls this on every launch: a valid
// reply means go straight to the dashboard, a 401 means the token is dead —
// revoked, or the account suspended or deleted since — so wipe it and show
// login. api_require_vendor() re-runs the suspended / verified / deleted checks
// on every call, which is what makes that 401 trustworthy.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);

api_json([
    'vendor' => [
        'id'   => (int)$vendor['id'],
        'name' => $vendor['name'],
        'lang' => $vendor['lang'] ?: 'km',
    ],
]);
