<?php
// The checkout preview — checkout/index.php without the HTML. See
// buyer_checkout() in config/buyer-checkout.php for the shape.
//
//   ?coupon=CODE   checked afresh on every call; the website keeps the code in
//                  the session, the app keeps it on the phone and sends it
//   ?ship[SHOP_ID]=grab|courier
//                  the buyer's pick per shop (public id) where both are on
//                  offer — groups[].delivery.options. Left out, Grab nearby
//                  and the courier beyond, as before the choice existed.
//   ?qr_image=1    also send the QR picture itself, base64, for "Save QR". The
//                  app cannot fetch the file directly: teepsaa.com sends no
//                  CORS header for /uploads/, and a WebView page is another
//                  origin. Only asked for when the button is pressed.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';
require __DIR__ . '/../../../config/buyer-cart.php';
require __DIR__ . '/../../../config/buyer-checkout.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

$ship  = isset($_GET['ship']) && is_array($_GET['ship']) ? $_GET['ship'] : [];
$reply = buyer_checkout($pdo, (int)$buyer['id'], (string)($_GET['coupon'] ?? ''), $ship);

if (!empty($_GET['qr_image'])) {
    $file = __DIR__ . '/../../../uploads/aba-qr.png';
    $reply['qr_image'] = is_file($file) ? base64_encode((string)file_get_contents($file)) : null;
}

api_json($reply);
