<?php
// A new avatar photo from the phone — settings-buyer/avatar-action.php's
// upload branch. Multipart, like api/v1/products/photo.php, because a picture
// does not travel well as JSON; every check the website makes is repeated:
// the file itself says JPG or PNG (never the name or the type the phone
// claims), under 2MB, then the small WebP copies, then the old photo removed.
//
// The filename is the website's scheme, avatar_b_<id>_<time>, so a photo put
// up from the app and one put up from the website are the same kind of file.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

// Over post_max_size PHP drops the whole body and says nothing; without this
// the reply would be 'missing_file' for a photo that was simply too big.
$isMultipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
if ($isMultipart && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$file = $_FILES['avatar'] ?? null;
if (!$file || !is_uploaded_file($file['tmp_name'] ?? '')) {
    api_json(['error' => 'missing_file'], 400);
}
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    $tooBig = in_array((int)$file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    api_json([
        'error' => $tooBig ? 'too_large' : 'upload_failed',
        'code'  => (int)$file['error'],
    ], $tooBig ? 413 : 400);
}

$mime = image_type_from_magic($file['tmp_name']);
if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
    api_json(['error' => 'bad_type', 'allowed' => ['image/jpeg', 'image/png']], 422);
}
if ((int)$file['size'] > AVATAR_MAX_BYTES) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'avatar_b_' . $userId . '_' . time() . '.' . $ext;
$dest     = upload_dir() . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    api_json(['error' => 'save_failed'], 500);
}

image_make_derivatives($dest, $filename);

$stmt = $pdo->prepare('SELECT avatar FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$old = $stmt->fetchColumn();

$pdo->prepare('UPDATE buyers SET avatar = ? WHERE id = ?')->execute([$filename, $userId]);

// Same second, same name: two uploads inside one second would otherwise delete
// the file that was just saved.
if ($old && $old !== $filename) {
    $oldPath = upload_dir() . '/' . basename((string)$old);
    if (is_file($oldPath)) @unlink($oldPath);
    image_delete_derivatives($old);
}

api_json([
    'ok'     => true,
    'avatar' => 'https://teepsaa.com' . image_variant($filename, 'w400'),
], 201);
