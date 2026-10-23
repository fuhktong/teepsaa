<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business-vendor/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$houseNumber = trim($_POST['house_number'] ?? '');
$address     = trim($_POST['address'] ?? '');
$notes       = trim($_POST['address_notes'] ?? '');
$khan        = trim($_POST['khan'] ?? '');
$sangkat     = trim($_POST['sangkat'] ?? '');
$city        = trim($_POST['city'] ?? '');
$lat         = $_POST['lat'] ?? '';
$lng         = $_POST['lng'] ?? '';

// Only accept a province we deliver in (config/cities.php), and a district
// and commune that belong to it. The selects only offer real ones; this is
// for a stale page or a hand-made post.
require __DIR__ . '/../config/locations.php';
$offered = array_keys(locations_offered());
$city    = in_array($city, $offered, true) ? $city : ($offered[0] ?? null);

if (location_errors($city, $khan, $sangkat)) {
    $_SESSION['settings_error'] = 'That district or commune isn\'t in the province you picked. Please choose them again.';
    header('Location: /business-vendor/');
    exit;
}

// The pin has to land in Cambodia (config/delivery-area.php). The map
// already keeps it there; this is the check for a hand-posted form.
require __DIR__ . '/../config/delivery-area.php';

$latVal = $lat !== '' ? filter_var($lat, FILTER_VALIDATE_FLOAT) : null;
$lngVal = $lng !== '' ? filter_var($lng, FILTER_VALIDATE_FLOAT) : null;

// Every shop needs a pin: delivery is priced by the distance from it, and
// submit/submit.php already refuses a new shop without one. The page posts
// the saved pin back, so this only stops a pin being cleared.
$pinGiven   = $latVal !== null && $latVal !== false && $lngVal !== null && $lngVal !== false;
$pinInArea  = $pinGiven
    && point_in_delivery_area((float)$latVal, (float)$lngVal);

if ($pinGiven && !$pinInArea) {
    $_SESSION['settings_error'] = 'That map pin is outside Cambodia. Drop it on your shop\'s location and try again.';
    header('Location: /business-vendor/');
    exit;
}
if (!$pinGiven) {
    $_SESSION['settings_error'] = 'Drop a pin on the map at your shop\'s location, then save again.';
    header('Location: /business-vendor/');
    exit;
}

$stmt = $pdo->prepare('
    UPDATE businesses
    SET house_number = ?, address = ?, address_notes = ?, khan = ?, sangkat = ?, city = ?, lat = ?, lng = ?
    WHERE user_id = ? AND deleted_at IS NULL
');
$stmt->execute([
    $houseNumber ?: null,
    $address     ?: null,
    $notes       ?: null,
    $khan        ?: null,
    $sangkat     ?: null,
    $city,
    $latVal,
    $lngVal,
    $userId,
]);

$_SESSION['settings_success'] = 'Address updated.';
header('Location: /business-vendor/');
exit;
