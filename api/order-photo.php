<?php
// Serve one dispatch or return photo from teepsaa-private/order-photos/.
// Why there is no login check here is explained in config/order-photo.php.

require __DIR__ . '/../config/upload.php';
require __DIR__ . '/../config/order-photo.php';

$f = (string)($_GET['f'] ?? '');
// The exact shape order_photo_save() writes, so nothing else can be asked for.
if (!preg_match('/^[0-9a-f]{32}\.(jpg|png)$/', $f, $m) || !is_file($path = ORDER_PHOTO_DIR . '/' . $f)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . ($m[1] === 'png' ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');
readfile($path);
