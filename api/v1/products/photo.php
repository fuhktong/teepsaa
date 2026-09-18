<?php
// Product photos from the phone: add one, or remove one.
//
// This is the app's version of two website files — the save_gallery_photos()
// function inside products/save.php, and products/photo-delete.php — and every
// check in both is repeated here.
//
// Why this endpoint is shaped differently from every other v1 endpoint: a
// photo cannot travel as JSON. JSON is text, so a picture has to be base64'd
// into it, which makes it a third larger and has to be decoded into a
// temporary file before any of the checks below can look at it. So 'add'
// arrives as an ordinary multipart form upload, exactly as it does from the
// website, which means $_FILES, move_uploaded_file() and the magic-byte check
// all work unchanged.
//
// 'delete' has no file and arrives as JSON like the rest of the app. Reading
// $_POST first and falling back to api_body() is what lets one endpoint answer
// both, and it is safe because a multipart request never populates the JSON
// body and a JSON request never populates $_POST.
//
// image_variant() and image_make_derivatives() arrive with api.php, which
// requires db.php, which requires upload.php. Requiring it here is a redeclare
// and a 500 before any code runs.
require __DIR__ . '/../../../config/api.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// A file larger than PHP's post_max_size is thrown away before this script
// starts, and PHP hands us an empty $_POST *and* an empty $_FILES with no error
// of any kind. Without this check the reply would be 'missing_product_id',
// which sends whoever is debugging it to the wrong end of the problem.
//
// The content-type test is what makes it safe: a JSON request also arrives with
// an empty $_POST and an empty $_FILES, so without it every JSON call here
// would be answered 'too_large'.
$isMultipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
if ($isMultipart && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$in     = $_POST ?: api_body();
$action = (string)($in['action'] ?? 'add');
if ($action !== 'add' && $action !== 'delete') {
    api_json(['error' => 'bad_action', 'allowed' => ['add', 'delete']], 400);
}

// ── The product ──────────────────────────────────────────────────────
//
// Ownership is the same test product-form.php and product-save.php use: a
// business this vendor owns, approved and not suspended. Matching them matters
// — if a photo could be added to a product the edit form refuses to open, the
// app would offer a button that can only fail.
$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1 AND suspended = 0');
$stmt->execute([$userId]);
$bizIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
if (empty($bizIds)) {
    api_json(['error' => 'no_business'], 409);
}
$ph = implode(',', array_fill(0, count($bizIds), '?'));

$productId = (int)($in['product_id'] ?? 0);
if ($productId <= 0) {
    api_json(['error' => 'missing_product_id'], 400);
}

$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND business_id IN ($ph)");
$stmt->execute(array_merge([$productId], $bizIds));
if (!$stmt->fetch()) {
    api_json(['error' => 'not_found'], 404);
}

/**
 * Every photo on this product, in the order the shop shows them.
 *
 * Sent back after both actions rather than just the one row that changed, so
 * the screen can redraw from the reply instead of keeping its own count. The
 * count is the thing that matters — nine is the cap — and a screen that tracks
 * it separately drifts the moment two phones edit the same product.
 */
function photo_list(PDO $pdo, int $productId): array {
    $stmt = $pdo->prepare('
        SELECT id, filename, is_primary
          FROM product_photos
         WHERE product_id = ?
         ORDER BY sort_order ASC, id ASC
    ');
    $stmt->execute([$productId]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            // Absolute, not the '/uploads/…' the website uses. The app runs
            // from https://localhost, so a root-relative address points the
            // phone at itself and the picture silently never loads.
            'url'        => 'https://teepsaa.com' . image_variant($row['filename']),
            'is_primary' => (bool)$row['is_primary'],
        ];
    }
    return $out;
}

const PHOTO_MAX_PER_PRODUCT = 9;
const PHOTO_MAX_BYTES       = 2 * 1024 * 1024;

// ── Remove one ───────────────────────────────────────────────────────
if ($action === 'delete') {
    $photoId = (int)($in['photo_id'] ?? 0);
    if ($photoId <= 0) api_json(['error' => 'missing_photo_id'], 400);

    $stmt = $pdo->prepare('
        SELECT pp.filename, pp.is_primary
          FROM product_photos pp
         WHERE pp.id = ? AND pp.product_id = ?
    ');
    $stmt->execute([$photoId, $productId]);
    $photo = $stmt->fetch();

    // Already gone is not an error worth showing a vendor — two taps on the
    // same X would otherwise put a red message under a photo that did exactly
    // what was asked.
    if (!$photo) {
        api_json(['ok' => true, 'already_gone' => true, 'photos' => photo_list($pdo, $productId)]);
    }

    $path = __DIR__ . '/../../../uploads/' . $photo['filename'];
    if (is_file($path)) @unlink($path);
    image_delete_derivatives($photo['filename']);
    $pdo->prepare('DELETE FROM product_photos WHERE id = ?')->execute([$photoId]);

    // Deleting the primary leaves the shop with no picture to show on a card,
    // so the next one in line takes over. The website does the same.
    if ($photo['is_primary']) {
        $next = $pdo->prepare('
            SELECT id FROM product_photos WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1
        ');
        $next->execute([$productId]);
        $nextId = (int)$next->fetchColumn();
        if ($nextId) {
            $pdo->prepare('UPDATE product_photos SET is_primary = 1 WHERE id = ?')->execute([$nextId]);
        }
    }

    api_json(['ok' => true, 'photos' => photo_list($pdo, $productId)]);
}

// ── Add one ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT COUNT(*) FROM product_photos WHERE product_id = ?');
$stmt->execute([$productId]);
$have = (int)$stmt->fetchColumn();

if ($have >= PHOTO_MAX_PER_PRODUCT) {
    api_json([
        'error'  => 'too_many',
        'max'    => PHOTO_MAX_PER_PRODUCT,
        'photos' => photo_list($pdo, $productId),
    ], 409);
}

$file = $_FILES['photo'] ?? null;
if (!$file || !is_uploaded_file($file['tmp_name'] ?? '')) {
    api_json(['error' => 'missing_file'], 400);
}
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    // INI_SIZE and FORM_SIZE both mean the picture was too big; the rest are
    // faults on this end, and a vendor retrying is the right advice for both.
    $tooBig = in_array((int)$file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    api_json([
        'error' => $tooBig ? 'too_large' : 'upload_failed',
        'code'  => (int)$file['error'],
    ], $tooBig ? 413 : 400);
}

// The first eight bytes of the file itself, never the type the phone claimed.
// A name and a Content-Type are both just text in the request and either can
// say 'image/jpeg' about anything at all.
$mime = image_type_from_magic($file['tmp_name']);
if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
    api_json(['error' => 'bad_type', 'allowed' => ['image/jpeg', 'image/png']], 422);
}
if ((int)$file['size'] > PHOTO_MAX_BYTES) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

// Random name, and the extension decided by the magic bytes rather than by
// whatever the phone called the file. The uploads folder is served directly, so
// a name taken from the request is a name an attacker chooses.
$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$dest     = __DIR__ . '/../../../uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    api_json(['error' => 'save_failed'], 500);
}

// The small WebP copies the shop pages actually serve — see config/upload.php.
// It degrades on its own if GD cannot do WebP, and image_variant() then keeps
// returning the original, so a failure here is not worth refusing the upload.
image_make_derivatives($dest, $filename);

// A product with no photos yet gets this one as the primary — the picture on
// its card in the shop. sort_order continues from what is already there.
$isPrimary = $have === 0 ? 1 : 0;
$pdo->prepare('
    INSERT INTO product_photos (product_id, filename, sort_order, is_primary)
    VALUES (?, ?, ?, ?)
')->execute([$productId, $filename, $have, $isPrimary]);

api_json([
    'ok'     => true,
    'id'     => (int)$pdo->lastInsertId(),
    'photos' => photo_list($pdo, $productId),
    'slots'  => PHOTO_MAX_PER_PRODUCT - ($have + 1),
], 201);
