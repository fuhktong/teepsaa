<?php
// Canvassing — shops pitched in the field. See database/migration-prospects.sql.

require_once __DIR__ . '/upload.php';

const PROSPECT_STATUSES = [
    'to_visit'       => 'To visit',
    'pitched'        => 'Pitched',
    'interested'     => 'Interested',
    'signed_up'      => 'Signed up',
    'not_interested' => 'Not interested',
    'closed_down'    => 'Closed down',
];

// Pin and badge colour per status, shared by the list, the detail page and the map.
const PROSPECT_COLORS = [
    'to_visit'       => '#6b7280',
    'pitched'        => '#2563eb',
    'interested'     => '#d97706',
    'signed_up'      => '#16a34a',
    'not_interested' => '#94a3b8',
    'closed_down'    => '#dc2626',
];

function prospect_status_label(string $s): string {
    return PROSPECT_STATUSES[$s] ?? $s;
}

function prospect_status_color(string $s): string {
    return PROSPECT_COLORS[$s] ?? '#6b7280';
}

function prospect_valid_status(?string $s): string {
    return isset(PROSPECT_STATUSES[$s]) ? $s : 'to_visit';
}

// ── Photos ────────────────────────────────────────────────────────────
// Shrunk to a PNG in the browser by /js/photo-shrink.js and posted as a data
// URL, so a 4 MB phone photo never crosses the mobile connection. The raw file
// input is the no-JS fallback and is capped server-side.

const PROSPECT_PHOTO_MAX_BYTES = 1500000;   // ~1.5 MB after shrinking

// Decodes a "data:image/png;base64,…" payload from the shrinker and saves it
// to /uploads/. Returns the filename, or null with $err set.
function prospect_save_data_url(string $dataUrl, int $prospectId, ?string &$err = null): ?string {
    if (!preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl, $m)) {
        $err = 'Unrecognised image data.';
        return null;
    }
    $raw = base64_decode(substr($dataUrl, strlen($m[0])), true);
    if ($raw === false || $raw === '') {
        $err = 'Image data could not be read.';
        return null;
    }
    if (strlen($raw) > PROSPECT_PHOTO_MAX_BYTES) {
        $err = 'Photo is too large even after shrinking.';
        return null;
    }

    // Same magic-byte check every other upload path uses — the data URL's own
    // declared type is just a string the browser sent us.
    $tmp = tempnam(sys_get_temp_dir(), 'psp');
    file_put_contents($tmp, $raw);
    $mime = image_type_from_magic($tmp);
    unlink($tmp);
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        $err = 'Only PNG or JPG photos are accepted.';
        return null;
    }

    $ext  = $mime === 'image/png' ? 'png' : 'jpg';
    $name = 'prospect_' . $prospectId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (file_put_contents(__DIR__ . '/../uploads/' . $name, $raw) === false) {
        $err = 'Could not save the photo.';
        return null;
    }
    return $name;
}

// No-JS fallback: an ordinary multipart upload straight off the camera.
function prospect_save_upload(array $file, int $prospectId, ?string &$err = null): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $err = 'Upload failed.';
        return null;
    }
    $mime = image_type_from_magic($file['tmp_name']);
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        $err = 'Only PNG or JPG photos are accepted.';
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        $err = 'Photo is too large (8 MB max).';
        return null;
    }
    $ext  = $mime === 'image/png' ? 'png' : 'jpg';
    $name = 'prospect_' . $prospectId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $name)) {
        $err = 'Could not save the photo.';
        return null;
    }
    return $name;
}

// Takes whichever of the two arrived and files it against the prospect.
function prospect_attach_photo(PDO $pdo, int $prospectId, ?int $visitId, ?string &$err = null): bool {
    $name = null;
    if (!empty($_POST['photo_data'])) {
        $name = prospect_save_data_url($_POST['photo_data'], $prospectId, $err);
    } elseif (!empty($_FILES['photo']['name'])) {
        $name = prospect_save_upload($_FILES['photo'], $prospectId, $err);
    } else {
        return true;                        // no photo on this submit — fine
    }
    if (!$name) return false;

    $pdo->prepare('INSERT INTO prospect_photos (prospect_id, visit_id, filename) VALUES (?, ?, ?)')
        ->execute([$prospectId, $visitId, $name]);
    return true;
}

// Removes a photo row and the file behind it. The FK only cascades the row.
function prospect_delete_photo_file(string $filename): void {
    $path = __DIR__ . '/../uploads/' . basename($filename);
    if (is_file($path)) @unlink($path);
}

// ── Geo ───────────────────────────────────────────────────────────────

// Metres between two points. Good enough at city scale for "is there already
// a vendor on this corner" and for sorting by nearest.
function prospect_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function prospect_format_distance(float $m): string {
    return $m < 1000 ? round($m) . ' m' : number_format($m / 1000, 1) . ' km';
}

// Warns about walking into a shop that is already on the books — an existing
// vendor within 80 m, or a prospect with the same name. Returns a message or ''.
function prospect_duplicate_warning(PDO $pdo, string $name, ?float $lat, ?float $lng, int $ignoreId = 0): string {
    $hits = [];

    $same = $pdo->prepare('SELECT id, business_name FROM prospects WHERE business_name = ? AND id <> ? LIMIT 1');
    $same->execute([$name, $ignoreId]);
    if ($row = $same->fetch(PDO::FETCH_ASSOC)) {
        $hits[] = 'another prospect is already called "' . $row['business_name'] . '"';
    }

    if ($lat !== null && $lng !== null) {
        // Rough bounding box first (~0.001° ≈ 111 m), then exact distance.
        $near = $pdo->prepare(
            'SELECT name, lat, lng FROM businesses
              WHERE deleted_at IS NULL AND NOT (lat = 0 AND lng = 0)
                AND lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?'
        );
        $near->execute([$lat - 0.001, $lat + 0.001, $lng - 0.001, $lng + 0.001]);
        foreach ($near->fetchAll(PDO::FETCH_ASSOC) as $b) {
            if (prospect_distance_m($lat, $lng, (float)$b['lat'], (float)$b['lng']) <= 80) {
                $hits[] = 'registered vendor "' . $b['name'] . '" is within 80 m';
                break;
            }
        }
    }

    return $hits ? 'Heads up — ' . implode(', and ', $hits) . '.' : '';
}
